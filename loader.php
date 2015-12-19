<?php

/*
Plugin Name: BuddyPress Registration Groups
Plugin URI: https://wordpress.org/plugins/buddypress-registration-groups-1/
Description: Allows a new BuddyPress user to select groups to join during the registration process.
Version: 1.1.2
Tags: wordpress, multisite, buddypress, groups, registration, autojoin
Requires at least: WordPress 3.7.1 / BuddyPress 1.8.1
Tested up to: WordPress 4.4 / BuddyPress 2.4.3
License: GNU/GPL 2
Author: Eric Johnson
Author URI: http://hardlyneutral.com/
Text Domain: buddypress-registration-groups-1
*/

// Define a constant that can be checked to see if the component is installed or not.
define( 'BP_REGISTRATION_GROUPS_IS_INSTALLED', 1 );

// Define a constant that will hold the current version number of the component
define( 'BP_REGISTRATION_GROUPS_VERSION', '1.1.2' );

// Define a constant that we can use to construct file paths throughout the component
define( 'BP_REGISTRATION_GROUPS_PLUGIN_DIR', dirname( __FILE__ ) );

// Define a constant that will hold the database version number that can be used for upgrading the DB
define( 'BP_REGISTRATION_GROUPS_DB_VERSION', '1' );

// Only load the component if BuddyPress is loaded and initialized.
function bp_registration_groups_init() {
	// Because our loader file uses BP_Component, it requires BP 1.5 or greater.
	if ( version_compare( BP_VERSION, '1.3', '>' ) )
		require( dirname( __FILE__ ) . '/includes/bp-registration-groups.php' );
}
add_action( 'bp_include', 'bp_registration_groups_init' );
