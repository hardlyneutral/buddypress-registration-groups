<?php
/**
 * Regression test: on a fresh page load (no signup submission), the admin
 * "checked by default" list still pre-checks groups and no error is shown.
 */

require __DIR__ . '/bootstrap.php';

bprg_test_add_group( 1, 'Public Group', 'public' );
bprg_test_add_group( 6, 'Second Public Group', 'public' );

bprg_test_set_plugin_options( array(
	'bp_registration_groups_require_selection' => 1,
	'bp_registration_groups_checked_groups'    => array( 1 ),
) );

$_POST = array();

ob_start();
do_action( 'bp_before_registration_submit_buttons' );
$output = ob_get_clean();

bprg_assert_true(
	1 === preg_match( '/value="1"\s+checked=\'checked\'/', $output ),
	'the admin default group is pre-checked on a fresh page load'
);
bprg_assert_true(
	0 === preg_match( '/value="6"\s+checked=\'checked\'/', $output ),
	'other groups are not pre-checked'
);
bprg_assert_true(
	false === strpos( $output, 'role="alert"' ),
	'no error is rendered before any signup submission'
);

bprg_test_done();
