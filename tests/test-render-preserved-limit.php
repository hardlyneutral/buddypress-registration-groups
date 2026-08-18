<?php
/**
 * Regression test: on a signup re-render, a submitted still-eligible
 * selection that the fresh groups query omitted (display limit plus a
 * shifted order) is appended to the list and rendered checked, so the
 * registrant's choice survives resubmission. Ineligible submitted IDs
 * are not appended, and the appended entry is never duplicated.
 */

require __DIR__ . '/bootstrap.php';

bprg_test_add_group( 1, 'Alpha Group', 'public' );
bprg_test_add_group( 6, 'Zeta Group', 'public' );
bprg_test_add_group( 7, 'Hidden Group', 'hidden' );

// Display limit of 1: the alphabetical query returns only 'Alpha Group',
// omitting the group the registrant actually selected.
bprg_test_set_plugin_options( array(
	'bp_registration_groups_number_displayed' => 1,
) );

// Simulate a signup that failed validation elsewhere (e.g. username taken):
// the registrant had selected group 6, and a forged/stale entry for the
// status-hidden group 7 rode along in the same submission.
$_POST = array(
	'signup_submit'    => '1',
	'field_reg_groups' => array( '6', '7' ),
);
bprg_test_reset_signup();
buddypress()->signup->errors['signup_username'] = 'Sorry, that username already exists!';

ob_start();
do_action( 'bp_before_registration_submit_buttons' );
$output = ob_get_clean();

bprg_assert_true(
	false !== strpos( $output, 'value="1"' ),
	'the display limit still renders the first group in the configured order'
);
bprg_assert_true(
	1 === preg_match( '/name="field_reg_groups\[\]" value="6"\s+checked=\'checked\'/', $output ),
	'the submitted group the query omitted is appended, checked, under the form field name'
);
bprg_assert_same(
	1,
	substr_count( $output, 'value="6"' ),
	'the appended group is rendered exactly once'
);
bprg_assert_true(
	false === strpos( $output, 'value="7"' ),
	'the ineligible status-hidden submitted id is not appended'
);
bprg_assert_true(
	0 === preg_match( '/value="1"\s+checked=\'checked\'/', $output ),
	'the naturally rendered first group stays unchecked (the registrant did not select it)'
);

bprg_test_done();
