<?php
/**
 * Regression test: the registration form renders an accessible inline error
 * when a required group selection is missing, and does not re-check the
 * admin defaults the registrant unchecked.
 */

require __DIR__ . '/bootstrap.php';

bprg_test_add_group( 1, 'Public Group', 'public' );
bprg_test_add_group( 6, 'Second Public Group', 'public' );

bprg_test_set_plugin_options( array(
	'bp_registration_groups_require_selection' => 1,
	'bp_registration_groups_checked_groups'    => array( 1 ),
) );

// Simulate a signup submitted with no selection: validation fails...
$_POST = array( 'signup_submit' => '1' );
bprg_test_reset_signup();
do_action( 'bp_signup_validate' );
bprg_assert_true( ! empty( buddypress()->signup->errors['field_reg_groups'] ), 'validation failed as expected' );

// ...and BuddyPress re-renders the register template in the same request.
ob_start();
do_action( 'bp_before_registration_submit_buttons' );
$output = ob_get_clean();

bprg_assert_true(
	false !== strpos( $output, 'role="alert"' ),
	'the inline error is rendered with role="alert"'
);
bprg_assert_true(
	false !== strpos( $output, esc_html( bp_registration_groups_required_selection_error_message() ) ),
	'the inline error contains the required-selection message'
);
bprg_assert_true(
	false !== strpos( $output, 'reg_groups_error' ),
	'the inline error carries the reg_groups_error class for styling'
);
bprg_assert_true(
	false === strpos( $output, "checked='checked'" ),
	'the admin default is not re-checked after the registrant submitted with nothing selected'
);

bprg_test_done();
