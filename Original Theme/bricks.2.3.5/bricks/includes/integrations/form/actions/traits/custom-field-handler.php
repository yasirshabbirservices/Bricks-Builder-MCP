<?php
namespace Bricks\Integrations\Form\Actions\Traits;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Shared trait for handling custom fields in form create/update post actions
 *
 * @since 2.2
 */
trait Custom_Field_Handler {
	/**
	 * Sanitize meta value based on the specified method
	 *
	 * @param mixed  $value The value to sanitize.
	 * @param string $method The sanitization method.
	 * @return mixed The sanitized value.
	 */
	private function sanitize_meta_value( $value, $method ) {
		// If value is an array, sanitize each element
		if ( is_array( $value ) ) {
			return array_map(
				function( $single_value ) use ( $method ) {
					return $this->sanitize_meta_value( $single_value, $method );
				},
				$value
			);
		}

		switch ( $method ) {
			case 'intval':
				return intval( $value );
			case 'floatval':
				return floatval( $value );
			case 'sanitize_email':
				return sanitize_email( $value );
			case 'esc_url':
				return esc_url( $value );
			case 'wp_kses_post':
				return wp_kses_post( $value );
			default:
				return sanitize_text_field( $value );
		}
	}

	/**
	 * Get ACF parent hierarchy for a nested field
	 *
	 * Handles unlimited nesting levels (group → group → field, etc.)
	 *
	 * @param string $meta_key Meta key (e.g., 'user_details_payment_card_number')
	 * @param int    $post_id  Post ID
	 *
	 * @return array|false Array with 'root_key' (top-level group field key) and 'path' (array of nested field names), or false if not nested
	 *
	 * @since 2.2
	 */
	private function get_acf_parent_hierarchy( $meta_key, $post_id ) {
		if ( ! function_exists( 'acf_get_field' ) ) {
			return false;
		}

		$parts        = explode( '_', $meta_key );
		$hierarchy    = [];
		$current_path = '';

		// Build hierarchy from left to right
		for ( $i = 0; $i < count( $parts ); $i++ ) {
			$current_path  = $current_path ? $current_path . '_' . $parts[ $i ] : $parts[ $i ];
			$acf_field_key = \Bricks\Integrations\Form\Init::get_acf_field_key_from_meta_key( $current_path, $post_id );

			if ( $acf_field_key ) {
				$field = acf_get_field( $acf_field_key );

				if ( $field && $field['type'] === 'group' ) {
					$hierarchy[] = [
						'name' => $field['name'],
						'key'  => $acf_field_key,
						'path' => $current_path,
					];
				}
			}
		}

		// If we found at least one group in the hierarchy, this is a nested field
		if ( ! empty( $hierarchy ) ) {
			// The remaining parts after the last group are the field name parts
			$last_group      = end( $hierarchy );
			$last_group_path = $last_group['path'];
			$field_name_part = substr( $meta_key, strlen( $last_group_path ) + 1 );

			// Build the path array: ['payment', 'card_number'] for nested groups
			$path = [];
			foreach ( $hierarchy as $index => $group ) {
				// Skip the root group in the path
				if ( $index > 0 ) {
					$path[] = $group['name'];
				}
			}
			$path[] = $field_name_part;

			return [
				'root_key' => $hierarchy[0]['key'], // Top-level group field key
				'path'     => $path,                 // Array of nested field names
			];
		}

		return false;
	}

	/**
	 * Set a value in a nested array structure using a path
	 *
	 * @param array $array Reference to the array to modify
	 * @param array $path  Array of keys representing the path (e.g., ['payment', 'card_number'])
	 * @param mixed $value The value to set
	 *
	 * @since 2.2
	 */
	private function set_nested_value( &$array, $path, $value ) {
		$current = &$array;

		foreach ( $path as $key ) {
			if ( ! isset( $current[ $key ] ) ) {
				$current[ $key ] = [];
			}
			$current = &$current[ $key ];
		}

		$current = $value;
	}

	/**
	 * Sanitize ACF field value based on field type
	 *
	 * @param mixed $value       Field value
	 * @param array $field_config ACF field configuration
	 *
	 * @return mixed Sanitized value
	 *
	 * @since 2.2
	 */
	private function sanitize_acf_field_value( $value, $field_config ) {
		switch ( $field_config['type'] ) {
			case 'image':
				// For single image fields, convert array to integer ID
				if ( is_array( $value ) && ! empty( $value ) ) {
					return intval( $value[0] );
				} elseif ( is_string( $value ) ) {
					// Split by spaces and take the first valid ID
					$ids = array_filter( array_map( 'intval', explode( ' ', $value ) ) );
					return ! empty( $ids ) ? $ids[0] : 0;
				}
				break;

			case 'gallery':
				// For gallery fields, ensure it's an array of integers
				if ( is_array( $value ) ) {
					return array_map( 'intval', $value );
				} elseif ( is_string( $value ) && strpos( $value, ',' ) !== false ) {
					return array_map( 'intval', explode( ',', $value ) );
				}
				break;

			case 'file':
				// For single file fields, convert array to integer ID
				if ( is_array( $value ) && ! empty( $value ) ) {
					return intval( $value[0] );
				} elseif ( is_string( $value ) ) {
					return intval( $value );
				}
				break;
		}

		return $value;
	}

	/**
	 * Sanitize Meta Box field value based on field type
	 *
	 * @param mixed $value        Field value
	 * @param array $field_config Meta Box field configuration
	 *
	 * @return mixed Sanitized value
	 *
	 * @since 2.2
	 */
	private function sanitize_meta_box_field_value( $value, $field_config ) {
		$field_type = $field_config['type'] ?? '';

		switch ( $field_type ) {
			case 'image_advanced':
			case 'file_advanced':
				// For gallery/file fields, ensure it's an array of integers
				if ( is_array( $value ) ) {
					return array_map( 'intval', $value );
				} elseif ( is_string( $value ) && strpos( $value, ',' ) !== false ) {
					return array_map( 'intval', explode( ',', $value ) );
				}
				break;

			case 'image':
			case 'file':
				// For single image/file fields, convert array to integer ID
				if ( is_array( $value ) && ! empty( $value ) ) {
					return intval( $value[0] );
				} elseif ( is_string( $value ) ) {
					return intval( $value );
				}
				break;
		}

		return $value;
	}

	/**
	 * Process post meta with ACF nested group support
	 *
	 * Handles both ACF and Meta Box fields, grouping nested ACF subfields
	 * for batch updates to maintain proper meta key structure.
	 *
	 * @param array $post_meta Array of meta key => value pairs
	 * @param int   $post_id   Post ID
	 *
	 * @since 2.2
	 */
	protected function process_acf_meta_fields( $post_meta, $post_id ) {
		if ( ! is_array( $post_meta ) ) {
			return;
		}

		// Group ACF subfields by their parent group
		$acf_group_values = [];
		$processed_keys   = [];

		foreach ( $post_meta as $meta_key => $meta_value ) {
			// Check if this is an ACF field
			$acf_field_key = \Bricks\Integrations\Form\Init::get_acf_field_key_from_meta_key( $meta_key, $post_id );

			if ( $acf_field_key && function_exists( 'update_field' ) ) {
				// Get ACF field configuration
				$acf_field_config = function_exists( 'acf_get_field' ) ? acf_get_field( $acf_field_key ) : false;

				if ( $acf_field_config ) {
					// Handle field type-specific sanitization
					$meta_value = $this->sanitize_acf_field_value( $meta_value, $acf_field_config );

					// Check if this is a subfield (contains underscore and is nested)
					if ( strpos( $meta_key, '_' ) !== false ) {
						$parent_hierarchy = $this->get_acf_parent_hierarchy( $meta_key, $post_id );

						if ( $parent_hierarchy ) {
							// This is a nested field - store it for batch update
							$root_group_key = $parent_hierarchy['root_key'];
							$field_path     = $parent_hierarchy['path'];

							if ( ! isset( $acf_group_values[ $root_group_key ] ) ) {
								$acf_group_values[ $root_group_key ] = [];
							}

							// Build nested array structure
							$this->set_nested_value( $acf_group_values[ $root_group_key ], $field_path, $meta_value );
							$processed_keys[] = $meta_key;
							continue;
						}
					}

					// Top-level ACF field - update directly
					update_field( $acf_field_key, $meta_value, $post_id );
					$processed_keys[] = $meta_key;
					continue;
				}
			}

			// Update Meta Box field using rwmb_set_meta
			$meta_box_field_key = \Bricks\Integrations\Form\Init::get_meta_box_field_key_from_meta_key( $meta_key, $post_id );
			if ( $meta_box_field_key && function_exists( 'rwmb_set_meta' ) ) {
				// Get Meta Box field configuration to handle different field types properly
				$mb_field_config = false;
				if ( function_exists( 'rwmb_get_object_fields' ) ) {
					$mb_fields       = rwmb_get_object_fields( $post_id );
					$mb_field_config = $mb_fields[ $meta_box_field_key ] ?? false;
				}

				if ( $mb_field_config ) {
					$meta_value = $this->sanitize_meta_box_field_value( $meta_value, $mb_field_config );
				}

				rwmb_set_meta( $post_id, $meta_box_field_key, $meta_value );
				$processed_keys[] = $meta_key;
				continue;
			}

			// Fallback to update_post_meta if not processed
			if ( ! in_array( $meta_key, $processed_keys, true ) ) {
				update_post_meta( $post_id, $meta_key, $meta_value );
			}
		}

		// Update ACF group fields with all subfield values at once
		foreach ( $acf_group_values as $group_key => $group_values ) {
			update_field( $group_key, $group_values, $post_id );
		}
	}
}
