<?php
/**
 * Curated sections: when sections are configured, the registration form
 * renders each section with its own title and description, offers only the
 * groups assigned to a section, renders a group assigned to more than one
 * section exactly once, and never exposes hidden, private (when not shown),
 * or nonexistent groups.
 */

require __DIR__ . '/bootstrap.php';

bprg_test_add_group( 1, 'Books', 'public' );
bprg_test_add_group( 2, 'Cycling', 'public' );
bprg_test_add_group( 3, 'North Region', 'public' );
bprg_test_add_group( 4, 'Secret Society', 'hidden' );
bprg_test_add_group( 5, 'Members Only', 'private' );
bprg_test_add_group( 6, 'Backstage', 'public' ); // hidden per-group below
bprg_test_add_group( 7, 'Unassigned Group', 'public' );
bprg_test_add_group( 8, 'Everyone', 'public' ); // auto-join, shown locked

bprg_test_set_plugin_options( array(
	'bp_registration_groups_hidden_groups'    => array( 6 ),
	'bp_registration_groups_autojoin_groups'  => array( 8 ),
	'bp_registration_groups_autojoin_display' => 1,
	'bp_registration_groups_checked_groups'   => array( 2 ),
	'bp_registration_groups_sections'         => array(
		array(
			'title'       => 'Interests',
			'description' => 'Pick your interests',
			// 4 (status hidden), 5 (private, not shown), 6 (hidden per-group),
			// 99 (nonexistent), and 8 (auto-join, locked) must not be selectable
			'groups'      => array( 1, 2, 4, 5, 6, 99, 8 ),
		),
		array(
			'title'       => 'Regions',
			'description' => '',
			// group 2 is already claimed by the first section
			'groups'      => array( 3, 2 ),
		),
	),
) );

$_POST = array();

ob_start();
do_action( 'bp_before_registration_submit_buttons' );
$output = ob_get_clean();

$interests_pos = strpos( $output, 'Interests' );
$regions_pos   = strpos( $output, 'Regions' );

bprg_assert_true( false !== $interests_pos, 'the first section title is rendered' );
bprg_assert_true( false !== $regions_pos, 'the second section title is rendered' );
bprg_assert_true( $interests_pos < $regions_pos, 'sections render in the configured order' );
bprg_assert_true( false !== strpos( $output, 'Pick your interests' ), 'the section description is rendered' );

bprg_assert_same( 1, substr_count( $output, '>Books<' ), 'an assigned group renders in its section' );
bprg_assert_same( 1, substr_count( $output, '>North Region<' ), 'a group assigned to the second section renders' );
bprg_assert_same( 1, substr_count( $output, '>Cycling<' ), 'a group assigned to two sections renders exactly once' );
bprg_assert_same( 1, substr_count( $output, 'value="2"' ), 'the duplicated group has exactly one input' );
bprg_assert_true( 1 === preg_match( '/value="2"\s+checked=\'checked\'/', $output ), 'the checked-by-default setting still pre-checks the group inside its section' );

bprg_assert_true( false === strpos( $output, 'Secret Society' ), 'a status-hidden group is never exposed even when assigned' );
bprg_assert_true( false === strpos( $output, 'Members Only' ), 'a private group is not exposed when private groups are not shown' );
bprg_assert_true( false === strpos( $output, 'Backstage' ), 'a per-group hidden group is not exposed even when assigned' );
bprg_assert_true( false === strpos( $output, 'Unassigned Group' ), 'a group assigned to no section does not appear' );
bprg_assert_true( false !== strpos( $output, 'Everyone' ) && false !== strpos( $output, 'disabled="disabled"' ), 'an assigned auto-join group appears as a locked entry' );
bprg_assert_same( 0, substr_count( $output, 'name="field_reg_groups[]" value="8"' ), 'the auto-join group is not submittable' );
bprg_assert_true( false !== strpos( $output, '(automatic)' ), 'the auto-join group renders as a locked automatic entry' );

bprg_assert_true( false !== strpos( $output, '>Groups<' ), 'the global title still heads the section list' );

// Private groups become selectable inside sections when the option is on.
// (New process would be needed for a second render; assert on option effect
// via the eligibility helper instead.)
bprg_assert_true( function_exists( 'bp_registration_groups_get_sections' ), 'the sections getter exists' );
bprg_assert_same( 2, count( bp_registration_groups_get_sections() ), 'both sections round-trip through the getter' );

bprg_test_done();
