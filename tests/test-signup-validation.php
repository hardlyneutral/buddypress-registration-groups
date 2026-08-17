<?php
/**
 * Regression tests for the "Require Group Selection" signup validation
 * hooked to BuddyPress's 'bp_signup_validate' action.
 */

require __DIR__ . '/bootstrap.php';

// The site's groups: one selectable public group, a private group, a
// status-hidden group, a public group hidden per-group, and an auto-join
// group.
bprg_test_add_group( 1, 'Public Group', 'public' );
bprg_test_add_group( 2, 'Private Group', 'private' );
bprg_test_add_group( 3, 'Hidden Group', 'hidden' );
bprg_test_add_group( 4, 'Suppressed Group', 'public' );
bprg_test_add_group( 5, 'Autojoin Group', 'public' );

$base_options = array(
	'bp_registration_groups_hidden_groups'   => array( 4 ),
	'bp_registration_groups_autojoin_groups' => array( 5 ),
);
$required_options = array_merge( $base_options, array( 'bp_registration_groups_require_selection' => 1 ) );

function bprg_run_validation( $options, $post ) {
	bprg_test_set_plugin_options( $options );
	$_POST = $post;
	bprg_test_reset_signup();
	do_action( 'bp_signup_validate' );
	return buddypress()->signup->errors;
}

bprg_assert_true(
	bprg_test_hook_has( 'bp_signup_validate', 'bp_registration_groups_validate_signup' ),
	'validator is attached to the bp_signup_validate action'
);

// With the setting off, current behavior is unchanged.
$errors = bprg_run_validation( $base_options, array( 'signup_submit' => '1' ) );
bprg_assert_true( empty( $errors ), 'setting off: submitting with no selection adds no signup error' );

// With the setting on, zero selections prevents account creation.
$errors = bprg_run_validation( $required_options, array( 'signup_submit' => '1' ) );
bprg_assert_true( ! empty( $errors['field_reg_groups'] ), 'setting on: submitting with no selection adds a signup error' );
bprg_assert_same(
	bp_registration_groups_required_selection_error_message(),
	$errors['field_reg_groups'] ?? null,
	'the signup error uses the required-selection message'
);

// A valid selection allows signup.
$errors = bprg_run_validation( $required_options, array( 'field_reg_groups' => array( '1' ) ) );
bprg_assert_true( empty( $errors ), 'setting on: selecting an eligible public group passes validation' );

// A scalar submission (as posted by a lone radio input) also counts.
$errors = bprg_run_validation( $required_options, array( 'field_reg_groups' => '1' ) );
bprg_assert_true( empty( $errors ), 'setting on: a scalar (radio-style) submission of an eligible group passes' );

// Ineligible group IDs cannot satisfy the requirement.
$ineligible = array(
	'a status-hidden group'                => 3,
	'a per-group hidden group'             => 4,
	'an auto-join-only group'              => 5,
	'a nonexistent group'                  => 99,
	'a private group when private is off'  => 2,
);
foreach ( $ineligible as $label => $group_id ) {
	$errors = bprg_run_validation( $required_options, array( 'field_reg_groups' => array( (string) $group_id ) ) );
	bprg_assert_true( ! empty( $errors['field_reg_groups'] ), "setting on: {$label} does not satisfy the requirement" );
}

// A mix of ineligible IDs plus one eligible ID passes.
$errors = bprg_run_validation( $required_options, array( 'field_reg_groups' => array( '3', '4', '5', '99', '1' ) ) );
bprg_assert_true( empty( $errors ), 'setting on: one eligible selection among ineligible IDs passes' );

// A private group counts when "Show Private Groups" is enabled.
$errors = bprg_run_validation(
	array_merge( $required_options, array( 'bp_registration_groups_show_private_groups' => 1 ) ),
	array( 'field_reg_groups' => array( '2' ) )
);
bprg_assert_true( empty( $errors ), 'setting on: a private group satisfies the requirement when private groups are shown' );

// When no selectable groups exist, the requirement is skipped so
// registration is never locked.
$locked_out_options = array(
	'bp_registration_groups_require_selection' => 1,
	'bp_registration_groups_hidden_groups'     => array( 1, 4 ),
	'bp_registration_groups_autojoin_groups'   => array( 5 ),
);
bprg_test_set_plugin_options( $locked_out_options );
bprg_assert_same( false, bp_registration_groups_has_selectable_groups(), 'no selectable groups are detected in the locked-out configuration' );
$errors = bprg_run_validation( $locked_out_options, array( 'signup_submit' => '1' ) );
bprg_assert_true( empty( $errors ), 'setting on: the requirement is skipped when no selectable groups exist' );

// Sanity check the opposite: selectable groups are detected normally.
bprg_test_set_plugin_options( $required_options );
bprg_assert_same( true, bp_registration_groups_has_selectable_groups(), 'selectable groups are detected in the normal configuration' );

bprg_test_done();
