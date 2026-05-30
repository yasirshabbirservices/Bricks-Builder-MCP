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
		if ( $name !== 'bricks_get_business_profile' ) {
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
}
