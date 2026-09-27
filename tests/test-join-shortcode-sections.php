<?php
/**
 * Tests for the [bp_registration_groups_join] shortcode with curated Group
 * Sections: each section renders with its own title and description, only
 * assigned and eligible groups appear (each once, in the first section that
 * lists it), empty sections are dropped, the display limit is ignored as on
 * the registration form, and submissions for unassigned groups are skipped.
 */

require __DIR__ . '/bootstrap.php';

bprg_test_add_group( 1, 'Announcements', 'public' );  // auto-join
bprg_test_add_group( 2, 'Book Club', 'public' );
bprg_test_add_group( 3, 'Cooking', 'public' );        // per-group hidden
bprg_test_add_group( 4, 'Cycling', 'public' );
bprg_test_add_group( 5, 'Secret Garden', 'private' ); // private is off
bprg_test_add_group( 6, 'Hidden Society', 'hidden' );
bprg_test_add_group( 8, 'Trail Runners', 'public' );
bprg_test_add_group( 10, 'Gardening', 'public' );
bprg_test_add_group( 11, 'Swimming', 'public' );      // in no section

bprg_test_set_plugin_options( array(
	'bp_registration_groups_display_as'       => 1,
	'bp_registration_groups_number_displayed' => 1, // ignored while sections exist
	'bp_registration_groups_autojoin_groups'  => array( 1 ),
	'bp_registration_groups_hidden_groups'    => array( 3 ),
	'bp_registration_groups_sections'         => array(
		array(
			'title'       => 'Interests',
			'description' => 'Pick a few',
			'groups'      => array( 2, 3, 1, 6, 99, 5, 10 ),
		),
		array(
			'title'       => 'Activities',
			'description' => '',
			'groups'      => array( 4, 2, 8 ),
		),
		array(
			'title'       => 'Nothing eligible',
			'description' => '',
			'groups'      => array( 3, 6 ),
		),
	),
) );

bprg_test_set_user( 42 );

$output = bprg_test_do_shortcode( 'bp_registration_groups_join' );

bprg_assert_true( false !== strpos( $output, '<h3 class="reg_groups_section_title" id="reg-groups-join-1-section-title-0">Interests</h3>' ), 'the first section title renders' );
bprg_assert_true( false !== strpos( $output, '<p class="reg_groups_section_description" id="reg-groups-join-1-section-desc-0">Pick a few</p>' ), 'the first section description renders' );
bprg_assert_true( false !== strpos( $output, '<h3 class="reg_groups_section_title" id="reg-groups-join-1-section-title-1">Activities</h3>' ), 'the second section title renders' );
bprg_assert_true(
	false !== strpos( $output, 'aria-labelledby="reg-groups-join-1-section-title-0" aria-describedby="reg-groups-join-1-section-desc-0"' ),
	'the first fieldset is named and described by its section'
);
bprg_assert_true(
	1 === preg_match( '/aria-labelledby="reg-groups-join-1-section-title-1">/', $output ),
	'a section without a description is named by its title and not described'
);
bprg_assert_true( false === strpos( $output, 'Nothing eligible' ), 'a section with no eligible groups is dropped' );

bprg_assert_true( strpos( $output, 'Interests' ) < strpos( $output, 'Activities' ), 'sections render in their configured order' );
bprg_assert_true(
	strpos( $output, 'Book Club' ) < strpos( $output, 'Activities' ) && strpos( $output, 'Gardening' ) < strpos( $output, 'Activities' ),
	'the first section holds its eligible groups (the display limit is ignored)'
);
bprg_assert_same( 1, substr_count( $output, 'value="2"' ), 'a group listed in two sections renders once' );
bprg_assert_true( strpos( $output, 'Cycling' ) > strpos( $output, 'Activities' ) && false !== strpos( $output, 'Trail Runners' ), 'the second section holds its own groups' );

foreach ( array( 'Announcements', 'Cooking', 'Hidden Society', 'Secret Garden', 'Swimming' ) as $bprg_name ) {
	bprg_assert_true( false === strpos( $output, $bprg_name ), "{$bprg_name} is not offered" );
}

// A submission can only reach section-assigned, eligible groups.
$results = bp_registration_groups_join_selected_groups( array( '11', '2', '10', '5', '3' ) );

bprg_assert_same( array( 2, 10 ), array_map( function ( $group ) {
	return $group->id;
}, $results['joined'] ), 'assigned groups are joined' );
bprg_assert_same( 3, $results['skipped'], 'unassigned, private-when-off, and hidden groups are skipped' );
bprg_assert_same( array( array( 2, 42 ), array( 10, 42 ) ), $GLOBALS['bprg_test']['joins'], 'only the assigned groups are joined, for the current user' );
bprg_assert_same( array(), $GLOBALS['bprg_test']['requests_sent'], 'no membership request is sent while private groups are off' );

bprg_test_done();
