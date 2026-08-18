<?php

/*
Plugin Name: BuddyPress Registration Groups
Plugin URI: https://wordpress.org/plugins/buddypress-registration-groups-1/
Description: Allows a new BuddyPress user to select groups to join during the registration process.
Version: 1.5.0
Requires at least: 6.1
Requires PHP: 7.4
Requires Plugins: buddypress
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Author: Eric Johnson
Author URI: http://hardlyneutral.com/
Text Domain: buddypress-registration-groups-1
Domain Path: /languages
*/

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define a constant that can be checked to see if the component is installed or not.
define( 'BP_REGISTRATION_GROUPS_IS_INSTALLED', 1 );

// Define a constant that will hold the current version number of the component
define( 'BP_REGISTRATION_GROUPS_VERSION', '1.5.0' );

// Define a constant that we can use to construct file paths throughout the component
define( 'BP_REGISTRATION_GROUPS_PLUGIN_DIR', dirname( __FILE__ ) );

// Define a constant that will hold the database version number that can be used for upgrading the DB
define( 'BP_REGISTRATION_GROUPS_DB_VERSION', '1' );

// Only load the component if BuddyPress is loaded and initialized. The
// 'bp_include' action only ever fires when BuddyPress is active, so no
// further "is BuddyPress installed?" checks are needed here.
function bp_registration_groups_init() {
	// The plugin is built entirely around the Groups component; without it
	// there is nothing to display or join.
	if ( ! bp_is_active( 'groups' ) ) {
		add_action( 'admin_notices', 'bp_registration_groups_groups_component_notice' );
		return;
	}

	require( dirname( __FILE__ ) . '/includes/bp-registration-groups.php' );
}
add_action( 'bp_include', 'bp_registration_groups_init' );

/**
 * Let admins know why the plugin is idle when the Groups component is disabled.
 */
function bp_registration_groups_groups_component_notice() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	printf(
		'<div class="notice notice-warning"><p>%s</p></div>',
		esc_html__( 'BuddyPress Registration Groups requires the BuddyPress Groups component. Please enable it on the BuddyPress settings page.', 'buddypress-registration-groups-1' )
	);
}

/**
 * Load the translation files bundled with the plugin.
 *
 * Translations hosted on wordpress.org load automatically; this only matters
 * for custom .mo files dropped into the plugin's languages directory. Hooked
 * to 'init' per the WordPress 6.7+ textdomain loading convention.
 */
function bp_registration_groups_load_textdomain() {
	load_plugin_textdomain( 'buddypress-registration-groups-1', false, basename( dirname( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'bp_registration_groups_load_textdomain' );
