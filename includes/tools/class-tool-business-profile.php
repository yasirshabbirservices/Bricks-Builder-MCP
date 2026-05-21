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
				'description' => 'Get the business profile stored in the plugin settings: brand identity (name, tagline, type, audience, tone, about text), contact details (email, phone, address), social media URLs, navigation items, CTA button text and URL, copyright text, logo URLs, and services list. Use this data to replace ALL placeholder content when inserting library templates — substitute logo images, dummy emails, phone numbers, addresses, Lorem ipsum text, and placeholder navigation links with the real values found here.',
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
			'configured' => true,
			'brand'      => [
				'business_name'   => $profile['business_name']   ?? '',
				'tagline'         => $profile['tagline']          ?? '',
				'business_type'   => $profile['business_type']   ?? '',
				'target_audience' => $profile['target_audience'] ?? '',
				'tone_of_voice'   => $profile['tone_of_voice']   ?? '',
				'about_text'      => $profile['about_text']      ?? '',
			],
			'contact'    => [
				'email'        => $profile['email']        ?? '',
				'phone'        => $profile['phone']        ?? '',
				'address'      => $profile['address']      ?? '',
				'city_country' => $profile['city_country'] ?? '',
			],
			'social'     => [
				'facebook_url'  => $profile['facebook_url']  ?? '',
				'instagram_url' => $profile['instagram_url'] ?? '',
				'linkedin_url'  => $profile['linkedin_url']  ?? '',
				'twitter_url'   => $profile['twitter_url']   ?? '',
				'youtube_url'   => $profile['youtube_url']   ?? '',
			],
			'navigation' => [
				'nav_items'      => $profile['nav_items']      ?? '',
				'cta_text'       => $profile['cta_text']       ?? '',
				'cta_url'        => $profile['cta_url']        ?? '',
				'copyright_text' => $profile['copyright_text'] ?? '',
			],
			'assets'     => [
				'logo_url'      => $profile['logo_url']      ?? '',
				'logo_dark_url' => $profile['logo_dark_url'] ?? '',
			],
			'services'   => array_values( array_filter(
				array_map( 'trim', explode( "\n", $profile['services'] ?? '' ) )
			) ),
		];
	}
}
