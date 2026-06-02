<?php
namespace BricksMCP\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tool_Validator extends Tool_Base {

	public function define(): array {
		return [
			[
				'name'        => 'bricks_validate_payload',
				'description' => 'Validate a Bricks elements array BEFORE writing it to a page or template. Catches ID uniqueness errors, broken parent-child references, wrong color formats, missing required fields, and unknown element names. Returns a list of errors, warnings, and an auto-fixed payload when fixable. Call this before bricks_create_page, bricks_update_page, bricks_create_template, or bricks_update_template to prevent corrupting the page.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'elements' => [
							'type'        => 'array',
							'description' => 'The flat elements array you intend to write.',
							'items'       => [ 'type' => 'object' ],
						],
						'auto_fix' => [
							'type'        => 'boolean',
							'description' => 'When true, return an auto-corrected payload alongside the errors. Default: true.',
						],
					],
					'required' => [ 'elements' ],
				],
			],
		];
	}

	public function execute( string $name, array $args ): array|\WP_Error {
		if ( $name !== 'bricks_validate_payload' ) {
			return $this->err( 'Unknown tool: ' . $name );
		}

		$elements = $this->arr_arg( $args, 'elements' );
		$auto_fix = $this->bool_arg( $args, 'auto_fix', true );

		if ( empty( $elements ) ) {
			return $this->err( 'elements array is empty.' );
		}

		$errors   = [];
		$warnings = [];
		$fixed    = $elements;

		// --- Build index maps ---
		$id_map    = [];   // id => index
		$id_counts = [];   // id => count (for uniqueness)

		foreach ( $elements as $i => $el ) {
			$id = $el['id'] ?? null;
			if ( $id !== null ) {
				$id_map[ (string) $id ] = $i;
				$id_counts[ (string) $id ] = ( $id_counts[ (string) $id ] ?? 0 ) + 1;
			}
		}

		// --- Pass 1: per-element validation ---
		$generated_ids = []; // track newly generated IDs to avoid re-collision

		foreach ( $fixed as $i => &$el ) {
			$ctx = "Element[{$i}]" . ( isset( $el['id'] ) ? " id={$el['id']}" : '' )
			               . ( isset( $el['name'] ) ? " name={$el['name']}" : '' );

			// Required fields
			foreach ( [ 'id', 'name', 'parent', 'children', 'settings' ] as $field ) {
				if ( ! array_key_exists( $field, $el ) ) {
					if ( $field === 'settings' ) {
						$warnings[] = "{$ctx}: missing 'settings' key — added empty object.";
						if ( $auto_fix ) {
							$el['settings'] = [];
						}
					} elseif ( $field === 'children' ) {
						$warnings[] = "{$ctx}: missing 'children' key — added empty array.";
						if ( $auto_fix ) {
							$el['children'] = [];
						}
					} else {
						$errors[] = "{$ctx}: required field '{$field}' is missing.";
					}
				}
			}

			// ID format: must be 6-char lowercase alphanumeric
			$id = (string) ( $el['id'] ?? '' );
			if ( $id !== '' && ! preg_match( '/^[a-z0-9]{6}$/', $id ) ) {
				$warnings[] = "{$ctx}: id '{$id}' should be 6 lowercase alphanumeric characters.";
			}

			// Duplicate IDs
			if ( $id !== '' && ( $id_counts[ $id ] ?? 0 ) > 1 ) {
				$errors[] = "{$ctx}: duplicate id '{$id}' — IDs must be unique across the entire array.";
				if ( $auto_fix ) {
					$new_id = $this->generate_unique_id( $id_map, $generated_ids );
					$generated_ids[] = $new_id;
					$old_id = $id;
					$el['id'] = $new_id;
					$id_map[ $new_id ] = $i;
					unset( $id_map[ $old_id ] );
					$id_counts[ $old_id ]--;
					$id_counts[ $new_id ] = 1;
					// Fix parent references in other elements
					foreach ( $fixed as &$other ) {
						if ( isset( $other['parent'] ) && (string) $other['parent'] === $old_id ) {
							$other['parent'] = $new_id;
						}
						if ( isset( $other['children'] ) && is_array( $other['children'] ) ) {
							$other['children'] = array_map(
								fn( $c ) => (string) $c === $old_id ? $new_id : $c,
								$other['children']
							);
						}
					}
					unset( $other );
				}
			}

			// parent must be 0 (integer) or a valid string ID
			if ( isset( $el['parent'] ) ) {
				$parent = $el['parent'];
				if ( $parent !== 0 && $parent !== '0' ) {
					if ( ! isset( $id_map[ (string) $parent ] ) ) {
						$errors[] = "{$ctx}: parent '{$parent}' does not exist in the elements array.";
					}
				}
				// Warn if parent is string "0" instead of integer 0
				if ( $parent === '0' ) {
					$warnings[] = "{$ctx}: parent should be integer 0 (not string \"0\") for root elements.";
					if ( $auto_fix ) {
						$el['parent'] = 0;
					}
				}
			}

			// children must all exist
			if ( isset( $el['children'] ) && is_array( $el['children'] ) ) {
				foreach ( $el['children'] as $child_id ) {
					if ( ! isset( $id_map[ (string) $child_id ] ) ) {
						$errors[] = "{$ctx}: children references id '{$child_id}' which does not exist in the array.";
						if ( $auto_fix ) {
							$el['children'] = array_values( array_filter(
								$el['children'],
								fn( $c ) => isset( $id_map[ (string) $c ] )
							) );
						}
					}
				}
			}

			// children must be array not string
			if ( isset( $el['children'] ) && ! is_array( $el['children'] ) ) {
				$errors[] = "{$ctx}: 'children' must be an array, got " . gettype( $el['children'] );
				if ( $auto_fix ) {
					$el['children'] = [];
				}
			}

			// Validate settings keys if present
			if ( ! empty( $el['settings'] ) && is_array( $el['settings'] ) ) {
				$s_errors   = [];
				$s_warnings = [];
				$this->validate_settings( $el['settings'], $ctx, $s_errors, $s_warnings, $auto_fix, $el['settings'] );
				$errors   = array_merge( $errors, $s_errors );
				$warnings = array_merge( $warnings, $s_warnings );
			}

			// Leaf elements shouldn't have children
			$leaf_elements = [ 'heading', 'text-basic', 'button', 'image', 'icon', 'text-link', 'divider', 'spacer', 'video', 'audio', 'map', 'code', 'shortcode', 'counter', 'countdown', 'rating', 'back-to-top' ];
			if ( isset( $el['name'] ) && in_array( $el['name'], $leaf_elements, true ) && ! empty( $el['children'] ) ) {
				$warnings[] = "{$ctx}: leaf element '{$el['name']}' has non-empty children array — these children will be ignored by Bricks.";
			}

			// Code signature checks — Bricks 1.9.7+ requires wp_hash() signatures for code execution.
			// Note: this plugin auto-signs on write, so these warnings only fire if the AI manually
			// set signature to empty/wrong value or forgot to include executeCode.
			$el_name = $el['name'] ?? '';
			$el_settings = $el['settings'] ?? [];

			if ( $el_name === 'code' && ! empty( $el_settings['executeCode'] ) && ! empty( $el_settings['code'] ) ) {
				if ( empty( $el_settings['signature'] ) ) {
					$warnings[] = "{$ctx}: code element has executeCode=true but no signature — auto-signed on write, but verify code execution is enabled in Bricks → Settings → Custom code.";
				}
			}

			if ( $el_name === 'svg' && ( $el_settings['source'] ?? '' ) === 'code' && ! empty( $el_settings['code'] ) ) {
				if ( empty( $el_settings['signature'] ) ) {
					$warnings[] = "{$ctx}: svg element uses inline code but has no signature — auto-signed on write.";
				}
			}

			if ( ! empty( $el_settings['query']['useQueryEditor'] ) && ! empty( $el_settings['query']['queryEditor'] ) ) {
				if ( empty( $el_settings['query']['signature'] ) ) {
					$warnings[] = "{$ctx}: query loop uses queryEditor (custom PHP) but has no signature — auto-signed on write, but verify code execution is enabled in Bricks → Settings → Custom code.";
				}
			}

			// Warn about deprecated elements
			$deprecated_elements = [ 'html' ];
			if ( isset( $el['name'] ) && in_array( $el['name'], $deprecated_elements, true ) ) {
				$warnings[] = "{$ctx}: element '{$el['name']}' is deprecated and will not appear in the Bricks panel.";
			}

			// Warn on unknown element names
			$known_elements = $this->get_known_elements();
			if ( isset( $el['name'] ) && ! in_array( $el['name'], $known_elements, true ) ) {
				$warnings[] = "{$ctx}: element name '{$el['name']}' is not a known Bricks element — verify the spelling.";
			}
		}
		unset( $el );

		// --- Pass 2: hierarchy check — content elements directly in section/container ---
		$content_elements = [ 'heading', 'text-basic', 'button', 'image', 'icon', 'text-link', 'list' ];
		$wrapper_elements = [ 'section', 'container' ];
		$id_to_name = [];
		foreach ( $fixed as $el ) {
			if ( isset( $el['id'], $el['name'] ) ) {
				$id_to_name[ (string) $el['id'] ] = $el['name'];
			}
		}

		foreach ( $fixed as $i => $el ) {
			$name   = $el['name'] ?? '';
			$parent = (string) ( $el['parent'] ?? '' );

			if ( in_array( $name, $content_elements, true ) && $parent !== '0' && $parent !== '' ) {
				$parent_name = $id_to_name[ $parent ] ?? '';
				if ( in_array( $parent_name, $wrapper_elements, true ) ) {
					$warnings[] = "Element[{$i}] id={$el['id']} name={$name}: placed directly inside '{$parent_name}' — content elements should be inside a 'block' or 'div', not directly in '{$parent_name}'.";
				}
			}
		}

		// --- Summary ---
		$valid = empty( $errors );

		$result = [
			'valid'         => $valid,
			'error_count'   => count( $errors ),
			'warning_count' => count( $warnings ),
			'errors'        => $errors,
			'warnings'      => $warnings,
		];

		if ( $auto_fix ) {
			$result['fixed_payload'] = array_values( $fixed );
			$result['fix_note']      = $valid
				? 'Payload is valid — no fixes needed.'
				: 'fixed_payload contains auto-corrections. Review and use it instead of your original array.';
		}

		return $result;
	}

	// -------------------------------------------------------------------------
	// Color validation helper
	// -------------------------------------------------------------------------

	private function validate_settings( array $settings, string $ctx, array &$errors, array &$warnings, bool $auto_fix, array &$mutable ): void {
		$color_keys = [ '_background', '_typography', '_border', 'iconColor', 'iconBgColor' ];

		foreach ( $settings as $key => $value ) {
			// Detect flat string colors (incorrect format)
			if ( $this->is_color_key( $key ) ) {
				if ( is_string( $value ) && strpos( $value, '#' ) === 0 ) {
					$errors[] = "{$ctx}: setting '{$key}' is a plain hex string '{$value}' — colors must be objects: {\"hex\": \"{$value}\"}.";
					if ( $auto_fix ) {
						$mutable[ $key ] = [ 'color' => [ 'hex' => $value ] ];
					}
				}
			}

			// _background.color must be object
			if ( $key === '_background' && is_array( $value ) ) {
				if ( isset( $value['color'] ) ) {
					if ( is_string( $value['color'] ) ) {
						$errors[] = "{$ctx}: _background.color is a plain string '{$value['color']}' — must be an object with 'hex' or 'raw' key.";
					}
					if ( is_array( $value['color'] ) && isset( $value['color']['hex'] ) && strpos( (string) $value['color']['hex'], 'var(' ) === 0 ) {
						$errors[] = "{$ctx}: _background.color.hex contains a CSS variable — put the variable in 'raw', not 'hex'. Use {\"raw\": \"{$value['color']['hex']}\"}";
					}
				}
			}

			// _typography.color must be object
			if ( $key === '_typography' && is_array( $value ) ) {
				if ( isset( $value['color'] ) ) {
					if ( is_string( $value['color'] ) ) {
						$errors[] = "{$ctx}: _typography.color is a plain string — must be an object with 'hex' or 'raw'.";
					}
					if ( is_array( $value['color'] ) && isset( $value['color']['hex'] ) && strpos( (string) $value['color']['hex'], 'var(' ) === 0 ) {
						$errors[] = "{$ctx}: _typography.color.hex contains a CSS variable — use 'raw' instead: {\"raw\": \"{$value['color']['hex']}\"}";
					}
				}
			}

			// _cssGlobalClasses must be array
			if ( $key === '_cssGlobalClasses' && ! is_array( $value ) ) {
				$errors[] = "{$ctx}: _cssGlobalClasses must be an array of class ID strings, got " . gettype( $value ) . '.';
				if ( $auto_fix ) {
					$mutable[ $key ] = is_string( $value ) ? [ $value ] : [];
				}
			}

			// _border.radius must be inside _border, not a separate key
			if ( $key === '_borderRadius' ) {
				$errors[] = "{$ctx}: '_borderRadius' is not valid — border radius goes inside '_border': {\"_border\": {\"radius\": {...}}}";
				if ( $auto_fix ) {
					if ( ! isset( $mutable['_border'] ) ) {
						$mutable['_border'] = [];
					}
					if ( is_array( $value ) ) {
						$mutable['_border']['radius'] = $value;
					}
					unset( $mutable['_borderRadius'] );
				}
			}

			// _maxWidth should be _widthMax
			if ( $key === '_maxWidth' ) {
				$warnings[] = "{$ctx}: '_maxWidth' is not valid — use '_widthMax' instead.";
				if ( $auto_fix ) {
					$mutable['_widthMax'] = $value;
					unset( $mutable['_maxWidth'] );
				}
			}

			// _boxShadow offset values should not have units
			if ( $key === '_boxShadow' && is_array( $value ) && isset( $value['values'] ) ) {
				foreach ( [ 'offsetX', 'offsetY', 'blur', 'spread' ] as $sv ) {
					if ( isset( $value['values'][ $sv ] ) ) {
						$sv_val = (string) $value['values'][ $sv ];
						if ( preg_match( '/\d+(px|rem|em)/', $sv_val ) ) {
							$errors[] = "{$ctx}: _boxShadow.values.{$sv} = '{$sv_val}' must NOT have units — use numeric string like \"4\" not \"4px\".";
							if ( $auto_fix ) {
								$mutable['_boxShadow']['values'][ $sv ] = preg_replace( '/[^0-9.\-]/', '', $sv_val );
							}
						}
					}
				}
			}

			// _width/_height must be plain CSS strings, not {size, unit} objects (old Bricks v1 format)
			$size_keys = [ '_width', '_height', '_widthMin', '_widthMax', '_heightMin', '_heightMax' ];
			if ( in_array( $key, $size_keys, true ) && is_array( $value ) && isset( $value['size'] ) ) {
				$fixed_val = ( $value['size'] ?? '0' ) . ( $value['unit'] ?? 'px' );
				$errors[] = "{$ctx}: '{$key}' must be a plain CSS string like \"100%\" or \"35rem\", not a {size, unit} object — use \"{$fixed_val}\".";
				if ( $auto_fix ) {
					$mutable[ $key ] = $fixed_val;
				}
			}

			// _padding/_margin must be an object {top, right, bottom, left} with CSS string values
			$box_model_keys = [ '_padding', '_margin' ];
			if ( in_array( $key, $box_model_keys, true ) ) {
				if ( is_string( $value ) && $value !== '' ) {
					$errors[] = "{$ctx}: '{$key}' must be an object {top, right, bottom, left} with CSS string values, not a flat string \"{$value}\".";
					if ( $auto_fix ) {
						$mutable[ $key ] = [ 'top' => $value, 'right' => $value, 'bottom' => $value, 'left' => $value ];
					}
				} elseif ( is_array( $value ) ) {
					foreach ( [ 'top', 'right', 'bottom', 'left' ] as $side ) {
						if ( isset( $value[ $side ] ) && is_numeric( $value[ $side ] ) && $value[ $side ] !== 0 ) {
							$warnings[] = "{$ctx}: '{$key}.{$side}' is a bare number — should be a CSS string like \"2rem\" or \"20px\".";
						}
					}
				}
			}

			// _cssCustom breakpoint suffix must use colon, not underscore
			// e.g. _cssCustom:mobile_landscape NOT _cssCustom_mobile_landscape
			if ( preg_match( '/^(_\w+?)_(mobile|tablet)/', (string) $key, $m ) ) {
				$warnings[] = "{$ctx}: setting key '{$key}' uses underscore before breakpoint — use colon: '{$m[1]}:{$m[2]}' (e.g. '_cssCustom:mobile_landscape').";
			}
		}
	}

	private function is_color_key( string $key ): bool {
		return in_array( $key, [ 'iconColor', 'iconBgColor', 'textColor', 'borderColor' ], true );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function generate_unique_id( array $id_map, array $generated_ids ): string {
		$chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
		do {
			$id = '';
			for ( $i = 0; $i < 6; $i++ ) {
				$id .= $chars[ random_int( 0, strlen( $chars ) - 1 ) ];
			}
		} while ( isset( $id_map[ $id ] ) || in_array( $id, $generated_ids, true ) );
		return $id;
	}

	/**
	 * Complete list of known Bricks element names.
	 * Source: Bricks Builder 2.3.6 includes/elements/ + includes/woocommerce/elements/
	 */
	private function get_known_elements(): array {
		return [
			// Layout (nestable)
			'section', 'container', 'block', 'div',

			// Basic
			'heading', 'text', 'text-basic', 'text-link', 'button', 'image', 'icon', 'video',

			// General interactive
			'accordion', 'accordion-nested',
			'tabs', 'tabs-nested',
			'toggle', 'toggle-mode',
			'slider', 'slider-nested',
			'offcanvas', 'dropdown',
			'form', 'countdown', 'counter', 'animated-typing',
			'back-to-top', 'alert', 'progress-bar', 'rating',
			'divider', 'pie-chart', 'social-icons',

			// Content & media
			'audio', 'carousel', 'image-gallery', 'svg', 'lottie',
			'code', 'shortcode',

			// Navigation
			'nav-nested', 'nav-menu', 'logo', 'search',
			'breadcrumbs', 'pagination', 'sidebar',

			// Maps
			'map', 'map-leaflet', 'map-connector',

			// Dynamic / query
			'posts', 'template', 'related-posts', 'query-results-summary',

			// Social embeds
			'instagram-feed', 'facebook-page',

			// WordPress widget
			'wordpress',

			// Single post
			'post-title', 'post-content', 'post-excerpt',
			'post-author', 'post-meta', 'post-taxonomy',
			'post-navigation', 'post-comments', 'post-toc',
			'post-reading-time', 'post-reading-progress-bar', 'post-sharing',

			// Legacy / non-preferred (still valid, warn handled separately)
			'icon-box', 'list', 'team-members', 'testimonials', 'pricing-tables',

			// Query filters
			'filter-search', 'filter-checkbox', 'filter-radio', 'filter-select',
			'filter-datepicker', 'filter-range', 'filter-submit', 'filter-active-filters',

			// Components
			'slot',

			// WooCommerce product
			'product-title', 'product-price', 'product-gallery',
			'product-short-description', 'product-content',
			'product-add-to-cart', 'product-rating', 'product-stock',
			'product-meta', 'product-tabs', 'product-additional-information',
			'product-reviews', 'product-upsells', 'product-related',

			// WooCommerce cart
			'woocommerce-cart-items', 'woocommerce-cart-coupon', 'woocommerce-cart-collaterals',

			// WooCommerce checkout
			'woocommerce-checkout-customer-details', 'woocommerce-checkout-order-review',
			'woocommerce-checkout-order-payment', 'woocommerce-checkout-coupon',
			'woocommerce-checkout-login', 'woocommerce-checkout-order-table',
			'woocommerce-checkout-thankyou',

			// WooCommerce account
			'woocommerce-account-page', 'woocommerce-account-orders',
			'woocommerce-account-form-login', 'woocommerce-account-form-register',
			'woocommerce-account-form-edit-account', 'woocommerce-account-addresses',
			'woocommerce-account-form-edit-address', 'woocommerce-account-downloads',
			'woocommerce-account-payment-methods', 'woocommerce-account-view-order',
			'woocommerce-account-add-payment-method', 'woocommerce-account-form-lost-password',
			'woocommerce-account-form-reset-password',

			// WooCommerce misc
			'woocommerce-mini-cart', 'woocommerce-notice', 'woocommerce-breadcrumbs',
		];
	}
}
