<?php
namespace BricksMCP\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Single combined call for AI session startup.
 * Returns site info + design tokens + framework detection + memories in one round-trip
 * instead of 5+ separate calls.
 */
class Tool_Context extends Tool_Base {

	public function define(): array {
		return [
			[
				'name'        => 'bricks_get_session_context',
				'description' => 'Get all context needed at the start of an AI session in a single call: site info, color palette, global classes, CSS variables, registered fonts, active design framework (CoreFramework / OxyProps / YStudio / Advanced Themer / BricksTemplate), semantic CSS variable map, and high-priority memories. Use this INSTEAD of calling bricks_get_site_info + bricks_get_color_palette + bricks_get_global_classes + bricks_memory_list separately — it reduces startup from 5 calls to 1.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'include_memories' => [
							'type'        => 'boolean',
							'description' => 'Include high-importance memories. Default: true.',
						],
					],
				],
			],
		];
	}

	public function execute( string $name, array $args ): array|\WP_Error {
		if ( $name !== 'bricks_get_session_context' ) {
			return $this->err( 'Unknown tool: ' . $name );
		}

		$include_memories = $this->bool_arg( $args, 'include_memories', true );

		$color_palette  = $this->get_color_palette();
		$global_classes = $this->get_global_classes();
		$css_variables  = $this->get_css_variables();

		$result = [
			'site_info'      => $this->get_site_info(),
			'color_palette'  => $color_palette,
			'global_classes' => $global_classes,
			'css_variables'  => $css_variables,
			'global_fonts'   => $this->get_global_fonts(),
			'framework'      => $this->detect_framework(),
		];

		if ( $include_memories ) {
			$result['high_priority_memories'] = $this->get_high_priority_memories();
		}

		// Business profile — include if configured so AI can replace placeholder content in templates
		$business_profile = get_option( BMCP_BUSINESS_PROFILE_OPTION, [] );
		if ( ! empty( $business_profile ) && is_array( $business_profile ) ) {
			$result['business_profile']      = $business_profile;
			$result['business_profile_note'] = 'Use this to replace ALL placeholder content in templates: logo_url → logoipsum/placeholder images, email → dummy emails, phone → +111 numbers, about_text → Lorem ipsum, services → placeholder service names, nav_items → placeholder navigation links.';
		}

		// Template categories — let AI know the library exists without loading the CSV on every session
		$result['template_categories'] = [
			'note'       => 'Call bricks_search_templates to get full JSON for any category. Call bricks_get_template_library with the exact name to fetch elements.',
			'categories' => [
				'Back To Top', 'Banner', 'Bio Links', 'Brands', 'Button',
				'Call To Action', 'Cart', 'Coming Soon', 'Contact US', 'Counter',
				'Email Opt-In', 'Error Page', 'FAQs', 'Features', 'Footer',
				'Header', 'Hero', 'Pagination', 'Popup', 'Post Grid',
				'Post Loop', 'Post Section', 'Pricing', 'Product Categories',
				'Product Tabs', 'Products', 'Pros and Cons', 'Single Post',
				'Single Product', 'Slider', 'Table of Contents', 'Team', 'Testimonials',
			],
		];

		// Design system detection — embed mandatory onboarding directive in the response
		// so the AI treats it as a hard requirement (tool data), not a soft guideline (system prompt).
		$has_design_system = count( $global_classes ) >= 2
			|| count( $css_variables ) >= 5
			|| count( $color_palette ) >= 3;

		if ( $has_design_system ) {
			$result['design_system_status'] = 'configured';
			$result['design_system_note']   = 'Site has a design system. Follow the global styles hierarchy: global classes → CSS variables → theme styles → palette colors → inline settings. Use _cssCustom only for pseudo-elements or complex selectors.';
		} else {
			$result['design_system_status'] = 'not_configured';
			$result['action_required']      = 'STOP. Do not proceed with any build or design task. You MUST ask the user this question first (copy it exactly):' . "\n\n"
				. '"This site does not have global theme styles set up yet. How would you like to proceed?' . "\n\n"
				. '1. Already done — styles exist somewhere that wasn\'t detected' . "\n"
				. '2. Set it up for me — I\'ll collect your brand details and create everything' . "\n"
				. '3. Skip for now — use neutral placeholders and proceed"' . "\n\n"
				. 'Wait for the user\'s answer before doing anything else.' . "\n\n"

				. '--- IF USER PICKS 1 (Already done) ---' . "\n"
				. 'Call bricks_get_global_classes, bricks_get_css_variables, and bricks_get_theme_styles to re-check. Report exactly what was found. Proceed using whatever exists.' . "\n\n"

				. '--- IF USER PICKS 2 (Set it up for me) ---' . "\n"
				. 'Ask these questions in a single message — the user can answer all at once or skip optional ones:' . "\n\n"
				. 'BRANDING DETAILS (please provide as much as you can):' . "\n"
				. '• Primary color — hex, RGB, or description (e.g. "deep navy blue")' . "\n"
				. '• Secondary / accent color' . "\n"
				. '• Text color — default: #1a1a1a' . "\n"
				. '• Background color — default: #ffffff' . "\n"
				. '• Success / positive color — default: #22c55e' . "\n"
				. '• Error / warning color — default: #ef4444' . "\n"
				. '• Heading font — Google Font name or "system" (e.g. "Playfair Display", "Montserrat")' . "\n"
				. '• Body font — Google Font name or "system" (e.g. "Inter", "Open Sans")' . "\n"
				. '• Custom font files — if you have .woff2/.woff files hosted at a URL, share the URL(s)' . "\n"
				. '• Logo URL — direct URL to your logo image (optional)' . "\n"
				. '• Favicon URL — direct URL to your favicon (optional)' . "\n"
				. '• Site tagline or short description (1–2 sentences about what the site does)' . "\n"
				. '• Target audience (e.g. "B2B SaaS", "local restaurant", "personal portfolio")' . "\n"
				. '• Style mood — minimal / modern / bold / elegant / playful / corporate (optional)' . "\n"
				. '• Social media URLs — Twitter/X, LinkedIn, Instagram, Facebook, GitHub, YouTube, etc. (optional)' . "\n\n"
				. 'After user responds, execute in this order:' . "\n"
				. '1. bricks_update_color_palette — create brand palette with primary, secondary, accent, text, bg, success, error, white, black (use sensible defaults for anything not provided)' . "\n"
				. '2. bricks_update_global_settings with googleFonts — register heading + body fonts (read existing first via bricks_list_global_fonts, then pass the complete updated array). Include common weights: 300, 400, 500, 600, 700' . "\n"
				. '3. If custom font URL(s) provided: call bricks_upload_media_from_url to import each file to the media library, then call bricks_update_global_settings with customFonts using the returned media URL' . "\n"
				. '4. If logo URL provided: call bricks_upload_media_from_url to import logo, then call bricks_update_global_settings with siteLogoId set to the returned attachment ID' . "\n"
				. '5. If favicon URL provided: call bricks_upload_media_from_url to import favicon, then update via bricks_update_global_settings' . "\n"
				. '6. bricks_create_global_class × 6 — create these foundational classes with styles based on the brand colors and fonts collected:' . "\n"
				. '   - heading-1: h1 typography (font-family, size 5rem, weight 700, line-height 1.1, brand text color)' . "\n"
				. '   - heading-2: h2 typography (font-family, size 3.5rem, weight 600, line-height 1.2)' . "\n"
				. '   - body-text: body copy (font-family, size 1.6rem, weight 400, line-height 1.7)' . "\n"
				. '   - btn-primary: primary button (background primary color, text white/dark for contrast, padding 1.2rem 2.8rem, border-radius 0.5rem)' . "\n"
				. '   - section-padding: standard section spacing (padding top/bottom 6rem, left/right 2rem)' . "\n"
				. '   - container: max-width wrapper (max-width 1200px, margin 0 auto, padding 0 2rem)' . "\n"
				. '7. bricks_memory_add — save ALL collected branding details as high-importance site memory:' . "\n"
				. '   category: "site", title: "Brand Identity", importance: "high"' . "\n"
				. '   content: include site name, primary/secondary/accent/text/bg colors (hex values), heading font, body font, target audience, style mood, site tagline, social URLs, logo attachment ID (if uploaded)' . "\n"
				. '8. bricks_memory_add — save global class IDs as high-importance design memory:' . "\n"
				. '   category: "design", title: "Global Class IDs", importance: "high"' . "\n"
				. '   content: list every class created with its Bricks-assigned ID (e.g. "heading-1: abc123, heading-2: def456") — these IDs are needed to apply classes to elements' . "\n"
				. '9. bricks_memory_add — save typography as high-importance design memory:' . "\n"
				. '   category: "design", title: "Site Typography", importance: "high"' . "\n"
				. '   content: heading font name + weights registered, body font name + weights registered, any custom font URLs' . "\n"
				. 'Confirm setup complete, summarize what was created, then proceed with the original request.' . "\n\n"

				. '--- IF USER PICKS 3 (Skip for now) ---' . "\n"
				. 'Proceed using neutral fallbacks only: #1a1a1a text, #ffffff background, #0066cc primary action. Standard spacing: 16px/1rem base. Do NOT reference semantic_map variable names — they are placeholders not defined on this unconfigured site. Tell the user the build will look generic until the design system is configured.';
		}

		return $result;
	}

	// -------------------------------------------------------------------------
	// Site info (minimal — key fields only)
	// -------------------------------------------------------------------------

	private function get_site_info(): array {
		$post_types     = get_post_types( [ 'public' => true ], 'objects' );
		$active_plugins = [];

		if ( function_exists( 'get_plugins' ) ) {
			foreach ( get_option( 'active_plugins', [] ) as $plugin_file ) {
				$active_plugins[] = dirname( $plugin_file ) ?: $plugin_file;
			}
		}

		$front_page_id = (int) get_option( 'page_on_front' );

		return [
			'site_name'          => get_bloginfo( 'name' ),
			'url'                => get_site_url(),
			'wp_version'         => get_bloginfo( 'version' ),
			'bricks_version'     => defined( 'BRICKS_VERSION' ) ? BRICKS_VERSION : 'unknown',
			'bricks_mcp_version' => BMCP_VERSION,
			'active_theme'       => get_template(),
			'woocommerce_active' => class_exists( 'WooCommerce' ),
			'locale'             => get_locale(),
			'timezone'           => wp_timezone_string(),
			'front_page_id'      => $front_page_id,
			'front_page_url'     => $front_page_id ? get_permalink( $front_page_id ) : get_home_url(),
			'active_plugins'     => $active_plugins,
			'custom_post_types'  => array_map( fn( $t ) => [
				'name'  => $t->name,
				'label' => $t->label,
			], array_values( array_filter( $post_types, fn( $t ) => ! in_array( $t->name, [ 'post', 'page', 'attachment', 'bricks_template' ], true ) ) ) ),
		];
	}

	// -------------------------------------------------------------------------
	// Color palette
	// -------------------------------------------------------------------------

	private function get_color_palette(): array {
		$key = defined( 'BRICKS_DB_COLOR_PALETTE' ) ? BRICKS_DB_COLOR_PALETTE : 'bricks_color_palette';
		$raw = get_option( $key, [] );
		return is_array( $raw ) ? $raw : [];
	}

	// -------------------------------------------------------------------------
	// Global classes
	// -------------------------------------------------------------------------

	private function get_global_classes(): array {
		$key = defined( 'BRICKS_DB_GLOBAL_CLASSES' ) ? BRICKS_DB_GLOBAL_CLASSES : 'bricks_global_classes';
		$raw = get_option( $key, [] );
		if ( ! is_array( $raw ) ) {
			return [];
		}
		return array_values( array_map( function( $c ) {
			$name = $c['name'] ?? '';
			return [
				'id'   => $c['id'] ?? '',
				'name' => $name,
				'hint' => $this->get_class_hint( $name ),
			];
		}, $raw ) );
	}

	private function get_class_hint( string $name ): string {
		$lower = strtolower( $name );

		if ( preg_match( '/btn|button/', $lower ) ) {
			return 'Button element';
		}
		if ( preg_match( '/heading|^h[1-6]$|heading-[1-6]/', $lower ) ) {
			return 'Heading typography';
		}
		if ( preg_match( '/body.?text|paragraph|body.?copy/', $lower ) ) {
			return 'Body text';
		}
		if ( preg_match( '/section.?padding|padding|spacing/', $lower ) ) {
			return 'Section spacing';
		}
		if ( preg_match( '/container|wrapper|wrap$/', $lower ) ) {
			return 'Content width wrapper';
		}

		return '';
	}

	// -------------------------------------------------------------------------
	// CSS Variables extracted from customCss
	// -------------------------------------------------------------------------

	private function get_css_variables(): array {
		$gs_key   = defined( 'BRICKS_DB_GLOBAL_SETTINGS' ) ? BRICKS_DB_GLOBAL_SETTINGS : 'bricks_global_settings';
		$settings = get_option( $gs_key, [] );
		$custom_css = ( is_array( $settings ) && isset( $settings['customCss'] ) ) ? (string) $settings['customCss'] : '';

		$variables = [];
		if ( preg_match_all( '/(-{2}[a-zA-Z][a-zA-Z0-9_-]*):\s*([^;}\n]+)/', $custom_css, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $m ) {
				$variables[ trim( $m[1] ) ] = trim( $m[2] );
			}
		}

		return $variables;
	}

	// -------------------------------------------------------------------------
	// Global fonts
	// -------------------------------------------------------------------------

	private function get_global_fonts(): array {
		$gs_key   = defined( 'BRICKS_DB_GLOBAL_SETTINGS' ) ? BRICKS_DB_GLOBAL_SETTINGS : 'bricks_global_settings';
		$settings = get_option( $gs_key, [] );
		if ( ! is_array( $settings ) ) {
			return [];
		}

		$fonts = [];
		foreach ( [ 'googleFonts', 'customFonts', 'themeFont' ] as $key ) {
			if ( ! empty( $settings[ $key ] ) && is_array( $settings[ $key ] ) ) {
				foreach ( $settings[ $key ] as $f ) {
					if ( ! empty( $f['family'] ) ) {
						$fonts[] = [ 'family' => $f['family'], 'source' => $key ];
					}
				}
			}
		}

		return $fonts;
	}

	// -------------------------------------------------------------------------
	// Framework detection
	// -------------------------------------------------------------------------

	private function detect_framework(): array {
		$active_plugins = get_option( 'active_plugins', [] );
		$slugs          = array_map( 'dirname', $active_plugins );

		$framework = 'none';
		$prefix    = '';

		if ( in_array( 'bricks-core-framework', $slugs, true ) || in_array( 'core-framework', $slugs, true ) ) {
			$framework = 'CoreFramework';
			$prefix    = '';
		} elseif ( in_array( 'oxyprops', $slugs, true ) ) {
			$framework = 'OxyProps';
			$prefix    = '--op-';
		} elseif ( in_array( 'ystudio-variable-builder', $slugs, true ) || in_array( 'ystudio', $slugs, true ) ) {
			$framework = 'YStudio';
			$prefix    = '--ys-';
		} elseif ( in_array( 'advanced-themer', $slugs, true ) ) {
			$framework = 'AdvancedThemer';
			$prefix    = '--at-';
		} elseif ( in_array( 'bricks-template', $slugs, true ) || in_array( 'brickstemplate', $slugs, true ) ) {
			$framework = 'BricksTemplate';
			$prefix    = '';
		}

		// Fallback: sniff CSS variable prefixes from customCss
		if ( $framework === 'none' ) {
			$css_vars = $this->get_css_variables();
			// CoreFramework (legacy --cf- prefix)
			foreach ( array_keys( $css_vars ) as $var ) {
				if ( str_starts_with( $var, '--cf-' ) ) { $framework = 'CoreFramework'; $prefix = '--cf-'; break; }
				if ( str_starts_with( $var, '--op-' ) ) { $framework = 'OxyProps';       $prefix = '--op-'; break; }
				if ( str_starts_with( $var, '--ys-' ) ) { $framework = 'YStudio';        $prefix = '--ys-'; break; }
				if ( str_starts_with( $var, '--at-' ) ) { $framework = 'AdvancedThemer'; $prefix = '--at-'; break; }
			}
			// CoreFramework modern (unprefixed: --primary, --bg-body, --space-m, --text-body, --radius-m all present)
			if ( $framework === 'none' && isset( $css_vars['--primary'], $css_vars['--bg-body'], $css_vars['--space-m'], $css_vars['--text-body'] ) ) {
				$framework = 'CoreFramework';
				$prefix    = '';
			}
			// BricksTemplate (unprefixed: --color-primary + --section-padding-l + --container-width)
			if ( $framework === 'none' && isset( $css_vars['--color-primary'], $css_vars['--section-padding-l'] ) ) {
				$framework = 'BricksTemplate';
				$prefix    = '';
			}
		}

		$result = [
			'framework'    => $framework,
			'prefix'       => $prefix,
			'semantic_map' => $this->build_semantic_map( $framework, $prefix ),
		];

		if ( $framework === 'BricksTemplate' ) {
			$result['class_reference'] = $this->get_brickstemplate_class_reference();
		}

		if ( $framework === 'CoreFramework' ) {
			$result['utility_classes'] = [
				'note'    => 'Apply via _cssClasses (space-separated string) or _cssGlobalClasses (array of IDs).',
				'classes' => [
					'.btn'        => 'Primary button — flex, padding var(--space-xs) var(--space-s), bg var(--primary), white text, border-radius var(--radius-m), shadow var(--shadow-m). Variants: .btn.small .btn.large .btn.secondary .btn.ghost .btn.outline .btn.no-bg',
					'.card'       => 'Card container — grid, gap var(--space-xs), padding var(--space-m), bg var(--bg-surface), border-radius var(--radius-m), shadow var(--shadow-m). Variants: .card.primary .card.secondary',
					'.badge'      => 'Inline badge — padding var(--space-2xs) var(--space-s), bg var(--dark-10), color var(--primary), font-size var(--text-s), border-radius var(--radius-full)',
					'.link'       => 'Styled link — color var(--primary), font-weight 600, box-shadow 0 2px 0 var(--primary-20)',
					'.icon'       => 'Icon wrapper — color var(--primary), width/font-size var(--space-2xl). Variants: .icon.large .icon.small .icon.secondary .icon.outline .icon.filled',
					'.avatar'     => 'Round image — width/height var(--space-2xl), border-radius 100%, object-fit cover, shadow var(--shadow-m)',
					'.divider'    => 'Horizontal rule — height 1px, bg var(--border-primary), margin var(--space-m) 0. Variant: .divider.vertical',
					'.bg-{color}' => 'Background utility — e.g. .bg-primary .bg-primary-10 .bg-surface .bg-dark-10 .bg-body',
					'.text-{color}' => 'Text color utility — e.g. .text-primary .text-body .text-title .text-secondary',
					'.shadow-{size}' => 'Box shadow — e.g. .shadow-xs .shadow-s .shadow-m .shadow-l .shadow-xl',
					'.radius-{size}' => 'Border radius — e.g. .radius-s .radius-m .radius-l .radius-full',
					'.padding-{size}' => 'Padding utility — e.g. .padding-m .padding-top-l .padding-inline-s',
					'.gap-{size}' => 'Gap utility — e.g. .gap-s .gap-m .gap-l',
					'.theme-dark' => 'Force dark mode on this element and descendants',
					'.theme-light' => 'Force light mode on this element and descendants',
					'.theme-always-dark' => 'Always dark regardless of site mode (for hero sections on light sites)',
					'.anim-fade-in-up' => 'Scroll-triggered animation. Variants: anim-fade-in-down anim-fade-in-left anim-fade-in-right anim-fade-in anim-zoom-in',
				],
			];
		}

		return $result;
	}

	private function build_semantic_map( string $framework, string $prefix ): array {
		// Generic fallback map (vanilla Bricks without a design system)
		$generic = [
			'color_primary'   => 'var(--color-primary)',
			'color_secondary' => 'var(--color-secondary)',
			'color_text'      => 'var(--color-text)',
			'color_heading'   => 'var(--color-heading)',
			'color_bg'        => 'var(--color-bg)',
			'color_border'    => 'var(--color-border)',
			'color_white'     => 'var(--color-white)',
			'space_xs'        => 'var(--space-xs)',
			'space_s'         => 'var(--space-s)',
			'space_m'         => 'var(--space-m)',
			'space_l'         => 'var(--space-l)',
			'space_xl'        => 'var(--space-xl)',
			'radius_s'        => 'var(--radius-s)',
			'radius_m'        => 'var(--radius-m)',
			'radius_l'        => 'var(--radius-l)',
			'container_width' => 'var(--container-width)',
			'font_base'       => 'var(--font-family-base)',
			'font_heading'    => 'var(--font-family-heading)',
		];

		// If no framework detected, try to derive from actual CSS variables
		if ( $framework === 'none' ) {
			$css_vars = $this->get_css_variables();
			if ( ! empty( $css_vars ) ) {
				$map = [];
				foreach ( $generic as $role => $default_var ) {
					// Strip var( ) to get the key
					preg_match( '/var\((--[a-zA-Z0-9_-]+)\)/', $default_var, $m );
					$key = $m[1] ?? '';
					if ( $key && isset( $css_vars[ $key ] ) ) {
						$map[ $role ] = $default_var;
					}
				}
				return empty( $map ) ? $generic : $map;
			}
			return $generic;
		}

		// Framework-specific maps
		// CoreFramework modern (unprefixed) — build map first so it can be used below
		$cf_modern = [
			'color_primary'   => 'var(--primary)',
			'color_secondary' => 'var(--secondary)',
			'color_tertiary'  => 'var(--tertiary)',
			'color_text'      => 'var(--text-body)',
			'color_heading'   => 'var(--text-title)',
			'color_bg'        => 'var(--bg-body)',
			'color_surface'   => 'var(--bg-surface)',
			'color_border'    => 'var(--border-primary)',
			'color_shadow'    => 'var(--shadow-primary)',
			'color_white'     => 'var(--light)',
			'space_xs'        => 'var(--space-xs)',
			'space_s'         => 'var(--space-s)',
			'space_m'         => 'var(--space-m)',
			'space_l'         => 'var(--space-l)',
			'space_xl'        => 'var(--space-xl)',
			'space_2xl'       => 'var(--space-2xl)',
			'space_3xl'       => 'var(--space-3xl)',
			'radius_s'        => 'var(--radius-s)',
			'radius_m'        => 'var(--radius-m)',
			'radius_l'        => 'var(--radius-l)',
			'radius_full'     => 'var(--radius-full)',
			'container_width' => 'var(--max-screen-width)',
			'text_xs'         => 'var(--text-xs)',
			'text_s'          => 'var(--text-s)',
			'text_m'          => 'var(--text-m)',
			'text_l'          => 'var(--text-l)',
			'text_xl'         => 'var(--text-xl)',
			'text_2xl'        => 'var(--text-2xl)',
			'text_3xl'        => 'var(--text-3xl)',
			'text_4xl'        => 'var(--text-4xl)',
		];
		// CoreFramework legacy uses --cf- prefix with different token names
		$cf_legacy = [
			'color_primary'   => "var({$prefix}color-primary)",
			'color_secondary' => "var({$prefix}color-secondary)",
			'color_text'      => "var({$prefix}color-text)",
			'color_heading'   => "var({$prefix}color-heading)",
			'color_bg'        => "var({$prefix}color-bg)",
			'color_border'    => "var({$prefix}color-border)",
			'color_white'     => "var({$prefix}color-white)",
			'space_xs'        => "var({$prefix}space-xs)",
			'space_s'         => "var({$prefix}space-s)",
			'space_m'         => "var({$prefix}space-m)",
			'space_l'         => "var({$prefix}space-l)",
			'space_xl'        => "var({$prefix}space-xl)",
			'radius_s'        => "var({$prefix}radius-s)",
			'radius_m'        => "var({$prefix}radius-m)",
			'radius_l'        => "var({$prefix}radius-l)",
			'container_width' => "var({$prefix}container-width)",
		];

		$maps = [
			'CoreFramework' => $prefix === '--cf-' ? $cf_legacy : $cf_modern,
			'OxyProps' => [
				'color_primary'   => "var({$prefix}brand)",
				'color_secondary' => "var({$prefix}brand-2)",
				'color_text'      => "var({$prefix}text-1)",
				'color_heading'   => "var({$prefix}text-1)",
				'color_bg'        => "var({$prefix}surface-1)",
				'color_border'    => "var({$prefix}surface-3)",
				'color_white'     => "var({$prefix}gray-0)",
				'space_xs'        => "var({$prefix}size-1)",
				'space_s'         => "var({$prefix}size-2)",
				'space_m'         => "var({$prefix}size-3)",
				'space_l'         => "var({$prefix}size-5)",
				'space_xl'        => "var({$prefix}size-7)",
				'radius_s'        => "var({$prefix}radius-2)",
				'radius_m'        => "var({$prefix}radius-3)",
				'radius_l'        => "var({$prefix}radius-4)",
				'container_width' => "var({$prefix}size-content-3)",
				'font_base'       => "var({$prefix}font-sans)",
				'font_heading'    => "var({$prefix}font-heading)",
			],
			'YStudio' => [
				'color_primary'   => "var({$prefix}color-primary)",
				'color_secondary' => "var({$prefix}color-secondary)",
				'color_text'      => "var({$prefix}color-body)",
				'color_heading'   => "var({$prefix}color-heading)",
				'color_bg'        => "var({$prefix}color-background)",
				'color_border'    => "var({$prefix}color-border)",
				'color_white'     => "var({$prefix}color-white)",
				'space_xs'        => "var({$prefix}spacing-xs)",
				'space_s'         => "var({$prefix}spacing-sm)",
				'space_m'         => "var({$prefix}spacing-md)",
				'space_l'         => "var({$prefix}spacing-lg)",
				'space_xl'        => "var({$prefix}spacing-xl)",
				'radius_s'        => "var({$prefix}radius-sm)",
				'radius_m'        => "var({$prefix}radius-md)",
				'radius_l'        => "var({$prefix}radius-lg)",
				'container_width' => "var({$prefix}container-width)",
				'font_base'       => "var({$prefix}font-body)",
				'font_heading'    => "var({$prefix}font-heading)",
			],
			'AdvancedThemer' => [
				'color_primary'   => "var({$prefix}primary)",
				'color_secondary' => "var({$prefix}secondary)",
				'color_text'      => "var({$prefix}body-color)",
				'color_heading'   => "var({$prefix}heading-color)",
				'color_bg'        => "var({$prefix}body-bg)",
				'color_border'    => "var({$prefix}border-color)",
				'color_white'     => "#ffffff",
				'space_xs'        => "var({$prefix}space-1)",
				'space_s'         => "var({$prefix}space-2)",
				'space_m'         => "var({$prefix}space-3)",
				'space_l'         => "var({$prefix}space-4)",
				'space_xl'        => "var({$prefix}space-5)",
				'radius_s'        => "var({$prefix}radius-sm)",
				'radius_m'        => "var({$prefix}radius-md)",
				'radius_l'        => "var({$prefix}radius-lg)",
				'container_width' => "var({$prefix}container-xl)",
				'font_base'       => "var({$prefix}font-family-base)",
				'font_heading'    => "var({$prefix}font-family-headings)",
			],
			'BricksTemplate' => [
				'color_primary'      => 'var(--color-primary)',
				'color_secondary'    => 'var(--color-secondary)',
				'color_text'         => 'var(--color-text)',
				'color_heading'      => 'var(--color-heading)',
				'color_bg'           => 'var(--color-bg)',
				'color_border'       => 'var(--color-border)',
				'color_white'        => 'var(--color-white)',
				'space_xs'           => 'var(--space-xs)',
				'space_s'            => 'var(--space-s)',
				'space_m'            => 'var(--space-m)',
				'space_l'            => 'var(--space-l)',
				'space_xl'           => 'var(--space-xl)',
				'radius_s'           => 'var(--radius-s)',
				'radius_m'           => 'var(--radius-m)',
				'radius_l'           => 'var(--radius-l)',
				'radius_full'        => 'var(--radius-full)',
				'section_padding_l'  => 'var(--section-padding-l)',
				'section_padding_m'  => 'var(--section-padding-m)',
				'section_padding_s'  => 'var(--section-padding-s)',
				'section_padding_xs' => 'var(--section-padding-xs)',
				'container_width'    => 'var(--container-width)',
				'font_base'          => 'var(--font-base)',
				'font_heading'       => 'var(--font-heading)',
				'body_text_s'        => 'var(--body-text-s)',
				'body_text_m'        => 'var(--body-text-m)',
				'body_text_l'        => 'var(--body-text-l)',
			],
		];

		return $maps[ $framework ] ?? $generic;
	}

	// -------------------------------------------------------------------------
	// BricksTemplate class ID reference
	// -------------------------------------------------------------------------

	private function get_brickstemplate_class_reference(): array {
		return [
			'note'         => 'BricksTemplate design system class IDs. Apply via _cssGlobalClasses: ["id"]. IDs are opaque — always use these exact values, never guess.',
			'typography'   => [
				'h1'           => 'mrlpju',
				'h2'           => 'rblwep',
				'h3'           => 'xdlghw',
				'h4'           => 'ewinig',
				'h5'           => 'jvxxkf',
				'h6'           => 'vunewz',
				'body-text-s'  => 'zcdcay',
				'body-text-m'  => 'xnbiuz',
				'body-text-l'  => 'xsebeu',
				'text-xxl'     => 'qgkzrm',
				'text-xl'      => 'ksgwrx',
				'text-l'       => 'qyzhvi',
				'text-m'       => 'vkhjpn',
				'text-s'       => 'pizkge',
				'text-xs'      => 'urbdzt',
			],
			'font_weights' => [
				'font-200' => 'zpzdlr',
				'font-300' => 'zqoeza',
				'font-400' => 'efjjje',
				'font-500' => 'vzhlkp',
				'font-600' => 'helzar',
				'font-700' => 'znqixu',
				'font-800' => 'dkyvht',
				'font-900' => 'toishz',
			],
			'buttons'      => [
				'btn'           => 'icnnin',
				'btn-secondary' => 'vnwkta',
				'btn--outline'  => 'gmbdcm',
				'btn--round'    => 'rhizcr',
				'btn__white'    => 'tccljv',
				'btn__black'    => 'jgucoo',
				'btn--xs'       => 'thpbrm',
				'btn--s'        => 'hyqjzl',
				'btn--m'        => 'aswtwb',
				'btn--l'        => 'ucbglo',
				'btn--xl'       => 'jmpexw',
			],
			'icons'        => [
				'icon'          => 'liafdz',
				'icon--outline' => 'ptlosy',
				'icon--filled'  => 'iptope',
			],
			'spacing'      => [
				'section-padding-l'  => 'jvlvec',
				'section-padding-m'  => 'xqjblc',
				'section-padding-s'  => 'kmknar',
				'section-padding-xs' => 'pkvazj',
			],
		];
	}

	// -------------------------------------------------------------------------
	// High-priority memories
	// -------------------------------------------------------------------------

	private function get_high_priority_memories(): array {
		if ( ! class_exists( '\BricksMCP\Memory_Manager' ) ) {
			return [];
		}
		return \BricksMCP\Memory_Manager::get_high_importance();
	}
}
