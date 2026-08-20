<?php
/**
 * Regression tests for bp_registration_groups_cap_selection().
 *
 * The radio "Display As" modes render a single choice (one per curated
 * section), but the limit was client-side only: a crafted request can still
 * submit several eligible IDs at once. cap_selection() enforces the radio
 * cardinality on the server. Checkbox and multiselect modes pass through
 * untouched. Inputs are built through
 * bp_registration_groups_get_valid_submitted_group_ids() so each case mirrors
 * the real save path.
 */

require __DIR__ . '/bootstrap.php';

// Several selectable public groups; 6 is the overlap group used to prove
// first-section attribution below.
bprg_test_add_group( 1, 'Books', 'public' );
bprg_test_add_group( 2, 'Cycling', 'public' );
bprg_test_add_group( 3, 'Running', 'public' );
bprg_test_add_group( 4, 'North Region', 'public' );
bprg_test_add_group( 5, 'South Region', 'public' );
bprg_test_add_group( 6, 'Overlap Group', 'public' );

// The curated-sections config reused by the sections cases: Interests lists
// 1, 2, 3 and the overlap group 6; Regions lists 4, 5, and 6 again (later).
$sections_config = array(
	array( 'title' => 'Interests', 'description' => '', 'groups' => array( 1, 2, 3, 6 ) ),
	array( 'title' => 'Regions', 'description' => '', 'groups' => array( 4, 5, 6 ) ),
);

/*
 * 1. Checkbox (display_as = '1'): the full valid list passes through, so
 *    multi-select is preserved.
 */
bprg_test_set_plugin_options( array(
	'bp_registration_groups_display_as' => '1',
) );
$valid = bp_registration_groups_get_valid_submitted_group_ids( array( '1', '2', '3' ) );
bprg_assert_same( array( 1, 2, 3 ), $valid, 'checkbox: the valid list is built in submitted order' );
bprg_assert_same( array( 1, 2, 3 ), bp_registration_groups_cap_selection( $valid ), 'checkbox: cap_selection preserves the full multi-selection' );

/*
 * 2. Multiselect (display_as = '2'): unchanged.
 */
bprg_test_set_plugin_options( array(
	'bp_registration_groups_display_as' => '2',
) );
$valid = bp_registration_groups_get_valid_submitted_group_ids( array( '1', '2', '3' ) );
bprg_assert_same( array( 1, 2, 3 ), bp_registration_groups_cap_selection( $valid ), 'multiselect: cap_selection preserves the full multi-selection' );

/*
 * 3. Radio (display_as = '3'), no sections: cap to the first submitted valid
 *    ID overall, for a multi-ID input.
 */
bprg_test_set_plugin_options( array(
	'bp_registration_groups_display_as' => '3',
) );
$valid = bp_registration_groups_get_valid_submitted_group_ids( array( '2', '1', '3' ) );
bprg_assert_same( array( 2, 1, 3 ), $valid, 'radio, no sections: submitted order is preserved before capping' );
bprg_assert_same( array( 2 ), bp_registration_groups_cap_selection( $valid ), 'radio, no sections: only the first submitted valid ID is kept' );

/*
 * 4. Radio, no sections, empty input: cap_selection returns empty.
 */
$valid = bp_registration_groups_get_valid_submitted_group_ids( array() );
bprg_assert_same( array(), $valid, 'radio, no sections: an empty submission yields an empty valid list' );
bprg_assert_same( array(), bp_registration_groups_cap_selection( $valid ), 'radio, no sections: cap_selection of an empty list is empty' );

/*
 * 5. Radio, two curated sections. Input carries two eligible IDs from the same
 *    section (2 then 1, both Interests) and one from another section (4,
 *    Regions). cap_selection keeps exactly one per section (2 IDs total), and
 *    the kept Interests ID is the first of its section that appears in the
 *    input.
 */
bprg_test_set_plugin_options( array(
	'bp_registration_groups_display_as' => '3',
	'bp_registration_groups_sections'   => $sections_config,
) );
$valid = bp_registration_groups_get_valid_submitted_group_ids( array( '2', '1', '4' ) );
bprg_assert_same( array( 2, 1, 4 ), $valid, 'radio, sections: the valid list keeps submitted order across sections' );
bprg_assert_same( array( 2, 4 ), bp_registration_groups_cap_selection( $valid ), 'radio, sections: one ID kept per section, and the kept one is the first of its section in the input' );

/*
 * 6. Radio, sections, input from only one section (two Interests IDs): exactly
 *    one ID survives.
 */
$valid = bp_registration_groups_get_valid_submitted_group_ids( array( '3', '1' ) );
bprg_assert_same( array( 3, 1 ), $valid, 'radio, one section: both submitted IDs are valid before capping' );
bprg_assert_same( array( 3 ), bp_registration_groups_cap_selection( $valid ), 'radio, one section: exactly one ID survives' );

/*
 * 7. Section mapping uses the FIRST section that lists a group. Group 6 is
 *    listed by both Interests (index 0) and Regions (index 1), so it belongs
 *    to Interests. With input 1, 6, 5: group 1 takes Interests, group 6 is
 *    dropped as a second Interests pick, and group 5 keeps Regions — proving 6
 *    counts against its first section only. Were 6 attributed to Regions the
 *    result would instead be array( 1, 6 ).
 */
$valid = bp_registration_groups_get_valid_submitted_group_ids( array( '1', '6', '5' ) );
bprg_assert_same( array( 1, 6, 5 ), $valid, 'radio, overlap: the overlapping group is a valid, section-assigned selection' );
bprg_assert_same( array( 1, 5 ), bp_registration_groups_cap_selection( $valid ), 'radio, overlap: a group in two sections is counted against its first section only' );

bprg_test_done();
