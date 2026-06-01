<?php
namespace BricksMCP\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides the site's business profile data — brand, contact, social,
 * navigation, assets, and services — stored via the admin settings page.
 * The AI uses this to replace placeholder content when applying library templates.
 */
class Tool_Business_Profile extends Tool_Base {

	public function define(): array {
		return [
			[
				'name'        => 'bricks_export_business_profile',
				'description' => 'Export the full business profile as a portable JSON object. Use this to back up the profile, share it with another site, or inspect all configured fields at once. The returned JSON can be imported into any Bricks MCP site via bricks_import_business_profile.',
				'inputSchema' => [ 'type' => 'object', 'properties' => [] ],
			],
			[
				'name'        => 'bricks_import_business_profile',
				'description' => 'Import a business profile JSON object exported from bricks_export_business_profile. Validates and sanitizes all fields (colors, URLs, emails, selects). Merges into the current profile — existing fields not present in the import are preserved. Returns the full profile after import.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'profile' => [
							'type'        => 'object',
							'description' => 'Business profile data object (from bricks_export_business_profile). All fields are optional — only provided fields are updated.',
						],
					],
					'required' => [ 'profile' ],
				],
			],
			[
				'name'        => 'bricks_get_business_profile',
				'description' => 'Get the full business profile stored in plugin settings. Returns: brand identity (name, tagline, type, audience, tone, about); brand colors (primary, secondary, accent, text, heading, background, surface, border, success, error hex values); typography (heading font, body font, base size); design style (style preset, border radius, spacing scale, button style); contact details (email, phone, address, plus custom extra entries); social media links (repeater of platform+url pairs); navigation items, CTA, copyright; logo URLs; and services list. Use these values to replace ALL placeholder content when building or editing any section — substitute logos, dummy emails, phone numbers, addresses, Lorem ipsum, colors, fonts, and placeholder links with the real values here.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [],
				],
			],
		];
	}

	public function execute( string $name, array $args ): array|\WP_Error {
		switch ( $name ) {
			case 'bricks_export_business_profile':
				return $this->export_profile();
			case 'bricks_import_business_profile':
				return $this->import_profile( $args['profile'] ?? [] );
			case 'bricks_get_business_profile':
				break;
			default:
				return $this->err( 'Unknown tool: ' . $name );
		}

		$profile = get_option( BMCP_BUSINESS_PROFILE_OPTION, [] );

		if ( empty( $profile ) || ! is_array( $profile ) ) {
			return [
				'configured' => false,
				'note'       => 'No business profile configured. Go to Settings → Bricks MCP → Business Profile to add your brand details.',
			];
		}

		return [
			'configured'   => true,
			'brand'        => [
				'business_name'   => $profile['business_name']   ?? '',
				'tagline'         => $profile['tagline']          ?? '',
				'business_type'   => $profile['business_type']   ?? '',
				'target_audience' => $profile['target_audience'] ?? '',
				'tone_of_voice'   => $profile['tone_of_voice']   ?? '',
				'about_text'      => $profile['about_text']      ?? '',
			],
			'colors'       => [
				'primary'    => $profile['color_primary']    ?? '',
				'secondary'  => $profile['color_secondary']  ?? '',
				'accent'     => $profile['color_accent']     ?? '',
				'text'       => $profile['color_text']       ?? '',
				'heading'    => $profile['color_heading']    ?? '',
				'background' => $profile['color_background'] ?? '',
				'surface'    => $profile['color_surface']    ?? '',
				'border'     => $profile['color_border']     ?? '',
				'success'    => $profile['color_success']    ?? '',
				'error'      => $profile['color_error']      ?? '',
			],
			'typography'   => [
				'font_heading'   => $profile['font_heading']   ?? '',
				'font_body'      => $profile['font_body']      ?? '',
				'font_size_base' => $profile['font_size_base'] ?? '',
			],
			'design_style' => [
				'style'         => $profile['design_style']  ?? '',
				'border_radius' => $profile['border_radius'] ?? '',
				'spacing_scale' => $profile['spacing_scale'] ?? '',
				'button_style'  => $profile['button_style']  ?? '',
			],
			'contact'      => [
				'email'        => $profile['email']        ?? '',
				'phone'        => $profile['phone']        ?? '',
				'address'      => $profile['address']      ?? '',
				'city_country' => $profile['city_country'] ?? '',
				'extra'        => $profile['contact_extra'] ?? [],
			],
			'social'       => $profile['social_links'] ?? [],
			'navigation'   => [
				'nav_items'      => $profile['nav_items']      ?? '',
				'cta_text'       => $profile['cta_text']       ?? '',
				'cta_url'        => $profile['cta_url']        ?? '',
				'copyright_text' => $profile['copyright_text'] ?? '',
			],
			'assets'       => [
				'logo_url'      => $profile['logo_url']      ?? '',
				'logo_dark_url' => $profile['logo_dark_url'] ?? '',
			],
			'services'     => array_values( array_filter(
				array_map( 'trim', explode( "\n", $profile['services'] ?? '' ) )
			) ),
		];
	}

	// -------------------------------------------------------------------------

	private function export_profile(): array {
		$profile = get_option( BMCP_BUSINESS_PROFILE_OPTION, [] );

		if ( empty( $profile ) || ! is_array( $profile ) ) {
			return [
				'configured' => false,
				'note'       => 'No business profile configured yet. Nothing to export.',
			];
		}

		return [
			'configured'   => true,
			'exported_at'  => wp_date( 'Y-m-d H:i:s' ),
			'site_url'     => get_site_url(),
			'bmcp_version' => BMCP_VERSION,
			'profile'      => $profile,
			'note'         => 'Pass the "profile" value to bricks_import_business_profile on any Bricks MCP site to apply these settings.',
		];
	}

	private function import_profile( $incoming ): array|\WP_Error {
		$err = $this->require_cap( 'manage_options' );
		if ( $err ) return $err;

		if ( ! is_array( $incoming ) || empty( $incoming ) ) {
			return $this->err( 'Invalid profile data. Pass the "profile" object from bricks_export_business_profile.' );
		}

		// Merge with existing profile so unset fields are preserved
		$existing = get_option( BMCP_BUSINESS_PROFILE_OPTION, [] );
		if ( ! is_array( $existing ) ) {
			$existing = [];
		}

		$merged = array_merge( $existing, $incoming );

		// Run through the same sanitizer used by the admin form
		$admin = new \BricksMCP\Admin();
		$clean = $admin->sanitize_business_profile( $merged );

		update_option( BMCP_BUSINESS_PROFILE_OPTION, $clean, false );

		return [
			'success'     => true,
			'imported_at' => wp_date( 'Y-m-d H:i:s' ),
			'message'     => 'Business profile imported and saved successfully.',
			'note'        => 'Call bricks_get_business_profile to verify the imported values.',
		];
	}
}
