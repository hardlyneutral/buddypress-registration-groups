<?php
/**
 * Regression test: bp_registration_groups_has_groups() neutralizes
 * BuddyPress's request-driven loop overrides. Real bp_has_groups() reads
 * 'num' and search terms straight from the query string, so ?num=99 or
 * ?s=... could swap the registration list for one the site owner never
 * configured. The wrapper unsets those keys around the loop query and
 * restores them afterwards. Also covers radio mode in the global (no
 * sections) branch and the legacy 'most-forum-topics' display order
 * remapping to 'active' (asserted through the rendered ordering, since
 * the stub sorts 'active' by id ascending and 'alphabetical' by name).
 */

require __DIR__ . '/bootstrap.php';

// Names chosen so 'active' order (id ascending: 1, 2) and 'alphabetical'
// order (Apple Fans, Bread Bakers: 3, 4) pick different groups under the
// display limit of 2.
bprg_test_add_group( 1, 'Zebra Club', 'public' );
bprg_test_add_group( 2, 'Yak Society', 'public' );
bprg_test_add_group( 3, 'Apple Fans', 'public' );
bprg_test_add_group( 4, 'Bread Bakers', 'public' );
bprg_test_add_group( 5, 'Cheese Circle', 'public' );

bprg_test_set_plugin_options( array(
	'bp_registration_groups_number_displayed' => 2,
	'bp_registration_groups_display_as'       => 3, // radio buttons
	'bp_registration_groups_display_order'    => 'most-forum-topics', // legacy; remaps to 'active'
	'bp_registration_groups_checked_groups'   => array( 1, 2 ),
) );

$_POST = array();

// A visitor-supplied query string trying to widen the list past the limit.
$_GET['num']     = '99';
$_REQUEST['num'] = '99';

$bprg_test_query_args = array(
	'type'     => 'active',
	'per_page' => 2,
	'status'   => array( 'public' ),
);

// First, the raw stub: bp_has_groups() called directly must honor the
// override (per_page 2 becomes 99, returning all five groups), proving the
// stub models the threat the wrapper exists for.
bprg_assert_true( bp_has_groups( $bprg_test_query_args ), 'the direct stub call finds groups' );
bprg_assert_same( 5, count( $GLOBALS['bprg_test']['loop'] ), 'the direct stub call honors the ?num=99 override' );

// A search term naming a group the limit would otherwise exclude.
$_REQUEST['s'] = 'Apple Fans';

bp_has_groups( $bprg_test_query_args );
bprg_assert_same(
	array( 3 ),
	array_map( function ( $group ) {
		return (int) $group->id;
	}, $GLOBALS['bprg_test']['loop'] ),
	'the direct stub call honors the ?s= search override'
);

// The wrapper with the same args and the same request state must ignore
// both overrides and query exactly what was configured.
bprg_assert_true( bp_registration_groups_has_groups( $bprg_test_query_args ), 'the wrapper finds groups' );
bprg_assert_same(
	array( 1, 2 ),
	array_map( function ( $group ) {
		return (int) $group->id;
	}, $GLOBALS['bprg_test']['loop'] ),
	'the wrapper neutralizes the num and search overrides'
);
bprg_assert_same( '99', $_REQUEST['num'], 'the wrapper restores $_REQUEST["num"] after the query' );
bprg_assert_same( '99', $_GET['num'], 'the wrapper restores $_GET["num"] after the query' );
bprg_assert_same( 'Apple Fans', $_REQUEST['s'], 'the wrapper restores $_REQUEST["s"] after the query' );

// The one render this process gets, with the overrides still in place.
ob_start();
do_action( 'bp_before_registration_submit_buttons' );
$output = ob_get_clean();

bprg_assert_same( 2, substr_count( $output, 'type="radio"' ), 'exactly two selectable inputs render despite ?num=99' );
bprg_assert_same( 2, substr_count( $output, 'name="field_reg_groups[]"' ), 'the radios share the single global name' );

bprg_assert_true(
	false !== strpos( $output, 'value="1"' ) && false !== strpos( $output, 'value="2"' ),
	'the legacy most-forum-topics order remaps to active (ids 1 and 2 render, not the alphabetical pair)'
);
bprg_assert_true(
	1 === preg_match( '/value="1"\s+checked=\'checked\'/', $output ),
	'the first checked-default renders checked'
);
bprg_assert_true(
	0 === preg_match( '/value="2"\s+checked=\'checked\'/', $output ),
	'only one radio is checked in the global branch'
);
bprg_assert_true(
	false === strpos( $output, 'value="3"' ),
	'the ?s= search override did not leak the limit-excluded group into the render'
);
bprg_assert_true(
	false === strpos( $output, 'value="4"' ) && false === strpos( $output, 'value="5"' ),
	'no group beyond the configured limit renders'
);

// The wrapper puts back exactly what it unset, so later code (BuddyPress's
// own directory loops, other plugins) still sees the original request.
bprg_assert_same( '99', $_REQUEST['num'], 'after the render $_REQUEST["num"] still holds its original value' );
bprg_assert_same( '99', $_GET['num'], 'after the render $_GET["num"] still holds its original value' );
bprg_assert_same( 'Apple Fans', $_REQUEST['s'], 'after the render $_REQUEST["s"] still holds its original value' );

bprg_test_done();
