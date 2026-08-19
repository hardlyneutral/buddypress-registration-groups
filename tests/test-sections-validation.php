<?php
/**
 * Regression tests for the "Require Group Selection" signup validation when
 * curated sections are configured: only groups assigned to a section can
 * satisfy the requirement, and the no-selectable-groups escape hatch is
 * decided over the sectioned groups alone.
 */

require __DIR__ . '/bootstrap.php';

// The site's groups: two eligible public groups (only one ever assigned to a
// section), a status-hidden group, a public group hidden per-group, and an
// auto-join group.
bprg_test_add_group( 1, 'Books', 'public' );
bprg_test_add_group( 2, 'Cycling', 'public' );
bprg_test_add_group( 3, 'Secret Society', 'hidden' );
bprg_test_add_group( 4, 'Suppressed Group', 'public' );
bprg_test_add_group( 5, 'Autojoin Group', 'public' );

function bprg_run_validation( $options, $post ) {
	bprg_test_set_plugin_options( $options );
	$_POST = $post;
	bprg_test_reset_signup();
	do_action( 'bp_signup_validate' );
	return buddypress()->signup->errors;
}

// Sections active: only group 1 is assigned; group 2 would be eligible in the
// global list but is offered by no section.
$sectioned_options = array(
	'bp_registration_groups_require_selection' => 1,
	'bp_registration_groups_autojoin_groups'   => array( 5 ),
	'bp_registration_groups_sections'          => array(
		array( 'title' => 'Interests', 'description' => '', 'groups' => array( 1 ) ),
	),
);

// An eligible group left out of every section cannot satisfy the requirement.
$errors = bprg_run_validation( $sectioned_options, array( 'field_reg_groups' => array( '2' ) ) );
bprg_assert_true( ! empty( $errors['field_reg_groups'] ), 'sections active: an eligible group outside every section does not satisfy the requirement' );

// A group assigned to a section does.
$errors = bprg_run_validation( $sectioned_options, array( 'field_reg_groups' => array( '1' ) ) );
bprg_assert_true( empty( $errors ), 'sections active: a group assigned to a section satisfies the requirement' );

// A section whose assigned groups are all auto-join offers nothing to select,
// so the requirement is skipped and registration is never locked.
$autojoin_only_options = array(
	'bp_registration_groups_require_selection' => 1,
	'bp_registration_groups_autojoin_groups'   => array( 5 ),
	'bp_registration_groups_sections'          => array(
		array( 'title' => 'Automatic', 'description' => '', 'groups' => array( 5 ) ),
	),
);
bprg_test_set_plugin_options( $autojoin_only_options );
bprg_assert_same( false, bp_registration_groups_has_selectable_groups(), 'a section of only auto-join groups has no selectable groups' );
$errors = bprg_run_validation( $autojoin_only_options, array( 'signup_submit' => '1' ) );
bprg_assert_true( empty( $errors ), 'sections active: the requirement is skipped when every sectioned group is auto-join' );

// Mixing one selectable group into the section restores the requirement.
$mixed_options = array(
	'bp_registration_groups_require_selection' => 1,
	'bp_registration_groups_autojoin_groups'   => array( 5 ),
	'bp_registration_groups_sections'          => array(
		array( 'title' => 'Mixed', 'description' => '', 'groups' => array( 1, 5 ) ),
	),
);
bprg_test_set_plugin_options( $mixed_options );
bprg_assert_same( true, bp_registration_groups_has_selectable_groups(), 'a section mixing a selectable and an auto-join group has selectable groups' );
$errors = bprg_run_validation( $mixed_options, array( 'signup_submit' => '1' ) );
bprg_assert_true( ! empty( $errors['field_reg_groups'] ), 'sections active: an empty submission errors when a section offers a selectable group' );

// Sections whose only groups are status-hidden or hidden per-group offer
// nothing to select either, so the requirement is skipped there too.
$hidden_only_options = array(
	'bp_registration_groups_require_selection' => 1,
	'bp_registration_groups_hidden_groups'     => array( 4 ),
	'bp_registration_groups_sections'          => array(
		array( 'title' => 'Status Hidden', 'description' => '', 'groups' => array( 3 ) ),
		array( 'title' => 'Per-Group Hidden', 'description' => '', 'groups' => array( 4 ) ),
	),
);
bprg_test_set_plugin_options( $hidden_only_options );
bprg_assert_same( false, bp_registration_groups_has_selectable_groups(), 'sections of only status-hidden and per-group hidden groups have no selectable groups' );
$errors = bprg_run_validation( $hidden_only_options, array( 'signup_submit' => '1' ) );
bprg_assert_true( empty( $errors ), 'sections active: the requirement is skipped when every sectioned group is hidden' );

bprg_test_done();
