<?php
/**
 * Regression test: when signup validation fails (for any reason), the
 * registration form re-render preserves the registrant's own selections
 * instead of resetting to the admin "checked by default" list.
 */

require __DIR__ . '/bootstrap.php';

bprg_test_add_group( 1, 'Public Group', 'public' );
bprg_test_add_group( 6, 'Second Public Group', 'public' );

bprg_test_set_plugin_options( array(
	'bp_registration_groups_checked_groups' => array( 1 ),
) );

// Simulate a signup that failed validation elsewhere (e.g. username taken):
// the registrant had selected group 6 and unchecked the default group 1.
$_POST = array(
	'signup_submit'    => '1',
	'field_reg_groups' => array( '6' ),
);
bprg_test_reset_signup();
buddypress()->signup->errors['signup_username'] = 'Sorry, that username already exists!';

ob_start();
do_action( 'bp_before_registration_submit_buttons' );
$output = ob_get_clean();

bprg_assert_true(
	1 === preg_match( '/value="6"\s+checked=\'checked\'/', $output ),
	'the group the registrant selected is re-checked on the form re-render'
);
bprg_assert_true(
	0 === preg_match( '/value="1"\s+checked=\'checked\'/', $output ),
	'the admin default the registrant unchecked stays unchecked'
);
bprg_assert_true(
	false === strpos( $output, 'role="alert"' ),
	'no group-selection error is rendered when the failure came from another field'
);

bprg_test_done();
