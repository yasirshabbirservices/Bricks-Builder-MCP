<?php
/**
 * Runs when the plugin is deleted from WordPress.
 * Only erases data when "Erase all plugin data on uninstall" is enabled in Advanced settings.
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$advanced = get_option( 'bmcp_advanced_settings', [] );
if ( empty( $advanced['erase_on_uninstall'] ) ) {
	return;
}

// Delete all plugin options
$options = [
	'bmcp_api_key',
	'bmcp_admin_user_id',
	'bmcp_custom_instructions',
	'bmcp_enabled_tools',
	'bmcp_tool_states',
	'bmcp_advanced_settings',
	'bmcp_activity_log',
	'bmcp_db_version',
];
foreach ( $options as $option ) {
	delete_option( $option );
}

// Drop history table
global $wpdb;
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'bmcp_history' ); // phpcs:ignore
