<?php

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
Plugin Name: BuddyPress Registration Groups
Plugin URI: https://wordpress.org/plugins/buddypress-registration-groups-1/
Description: Allows a new BuddyPress user to select groups to join during the registration process.
Version: 1.3.0
Requires at least: 6.1
Tested up to: 6.8
Requires PHP: 7.4
Tags: wordpress, multisite, buddypress, groups, registration, autojoin
License: GNU/GPL 2
Author: Eric Johnson
Author URI: http://hardlyneutral.com/
Text Domain: buddypress-registration-groups-1
Network: true
*/

// Define a constant that can be checked to see if the component is installed or not.
define( 'BP_REGISTRATION_GROUPS_IS_INSTALLED', 1 );

// Define a constant that will hold the current version number of the component
define( 'BP_REGISTRATION_GROUPS_VERSION', '1.3.0' );

// Define a constant that we can use to construct file paths throughout the component
define( 'BP_REGISTRATION_GROUPS_PLUGIN_DIR', dirname( __FILE__ ) );

// Define a constant that will hold the database version number that can be used for upgrading the DB
define( 'BP_REGISTRATION_GROUPS_DB_VERSION', '1' );

// Only load the component if BuddyPress is loaded and initialized.
function bp_registration_groups_init() {
	// Require BuddyPress 10.0.0 or greater for modern compatibility
	if ( version_compare( BP_VERSION, '10.0.0', '>=' ) ) {
		require( dirname( __FILE__ ) . '/includes/bp-registration-groups.php' );

		// Load text domain
		$plugin_rel_path = basename( dirname( __FILE__ ) ) . '/languages';
		load_plugin_textdomain( 'buddypress-registration-groups-1', false, $plugin_rel_path );
	}
}
add_action( 'bp_include', 'bp_registration_groups_init' );
