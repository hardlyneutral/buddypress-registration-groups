<?php
/**
 * Regression tests for group joining at activation
 * (bp_registration_groups_join(), hooked to 'bp_core_activated_user') and the
 * 'bp_registration_groups_join_failed' action.
 *
 * Membership-join failures used to be discarded silently. The action now fires
 * once per failed join, tagged with the context ('selected' vs 'autojoin'), so
 * a site can log, alert, or retry. These tests pin down that every failure —
 * and only a genuine failure — fires the action with the right arguments.
 */

require __DIR__ . '/bootstrap.php';

// One selectable public group, another selectable public group, and an
// auto-join public group.
bprg_test_add_group( 3, 'Books', 'public' );
bprg_test_add_group( 4, 'Cycling', 'public' );
bprg_test_add_group( 5, 'Announcements', 'public' );

bprg_test_set_plugin_options( array(
	'bp_registration_groups_autojoin_groups' => array( 5 ),
) );

bprg_assert_true(
	bprg_test_hook_has( 'bp_core_activated_user', 'bp_registration_groups_join' ),
	'activation handler is attached to bp_core_activated_user'
);

// Record every (group_id, context) tuple the failed-join action reports, and
// keep the raw argument list so the argument contract can be asserted.
$GLOBALS['bprg_join_failed'] = array();

function bprg_test_record_join_failed( $group_id, $user_id, $context ) {
	$GLOBALS['bprg_join_failed'][] = array(
		'args'     => func_get_args(),
		'group_id' => $group_id,
		'user_id'  => $user_id,
		'context'  => $context,
	);
}
// The action is fired with three args; register for exactly that count so the
// listener mirrors the documented contract.
add_action( 'bp_registration_groups_join_failed', 'bprg_test_record_join_failed', 10, 3 );

// -------------------------------------------------------------------
// 1. Success path: no join is forced to fail.
// -------------------------------------------------------------------
$GLOBALS['bprg_test']['joins']         = array();
$GLOBALS['bprg_test']['join_failures'] = array();
$GLOBALS['bprg_join_failed']           = array();

do_action( 'bp_core_activated_user', 101, '', array( 'meta' => array( 'field_reg_groups' => array( 3, 4 ) ) ) );

bprg_assert_same(
	array( array( 3, 101 ), array( 4, 101 ), array( 5, 101 ) ),
	$GLOBALS['bprg_test']['joins'],
	'success path: the selected groups and the auto-join group are all joined'
);
bprg_assert_same( 0, count( $GLOBALS['bprg_join_failed'] ), 'success path: the failed-join action fires zero times' );

// -------------------------------------------------------------------
// 2. Failure path: force the second selected group and the auto-join
//    group to fail; the first selected group still succeeds.
// -------------------------------------------------------------------
$GLOBALS['bprg_test']['joins']         = array();
$GLOBALS['bprg_test']['join_failures'] = array( 4, 5 );
$GLOBALS['bprg_join_failed']           = array();

do_action( 'bp_core_activated_user', 202, '', array( 'meta' => array( 'field_reg_groups' => array( 3, 4 ) ) ) );

// A join was still attempted for every eligible group.
bprg_assert_same(
	array( array( 3, 202 ), array( 4, 202 ), array( 5, 202 ) ),
	$GLOBALS['bprg_test']['joins'],
	'failure path: a join is attempted for every eligible group, failures included'
);

// The action fired exactly for the two failures, tagged by context.
$fired_tuples = array_map(
	function ( $entry ) {
		return array( $entry['group_id'], $entry['context'] );
	},
	$GLOBALS['bprg_join_failed']
);
bprg_assert_same(
	array( array( 4, 'selected' ), array( 5, 'autojoin' ) ),
	$fired_tuples,
	'failure path: the action fires for the failed selected group and the failed auto-join group'
);
bprg_assert_true(
	! in_array( array( 3, 'selected' ), $fired_tuples, true ),
	'failure path: the action does not fire for the group that joined successfully'
);

// -------------------------------------------------------------------
// 3. Argument contract: each firing passes exactly three args — an int
//    group_id, the activated user_id, and a known context string.
// -------------------------------------------------------------------
foreach ( $GLOBALS['bprg_join_failed'] as $entry ) {
	bprg_assert_same( 3, count( $entry['args'] ), 'the failed-join action passes exactly three arguments' );
	bprg_assert_true( is_int( $entry['group_id'] ), 'the failed-join group_id is an int' );
	bprg_assert_same( 202, $entry['user_id'], 'the failed-join user_id matches the activated user' );
	bprg_assert_true( is_int( $entry['user_id'] ), 'the failed-join user_id is an int' );
	bprg_assert_true(
		in_array( $entry['context'], array( 'selected', 'autojoin' ), true ),
		'the failed-join context is one of the documented strings'
	);
}

// Leave the forced-failure state clean for any later run.
$GLOBALS['bprg_test']['join_failures'] = array();

bprg_test_done();
