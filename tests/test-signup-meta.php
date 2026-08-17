<?php
/**
 * Regression tests for saving group selections into the signup meta
 * ('bp_signup_usermeta' filter) and joining groups at activation
 * ('bp_core_activated_user' action).
 */

require __DIR__ . '/bootstrap.php';

bprg_test_add_group( 1, 'Public Group', 'public' );
bprg_test_add_group( 2, 'Private Group', 'private' );
bprg_test_add_group( 3, 'Hidden Group', 'hidden' );
bprg_test_add_group( 4, 'Suppressed Group', 'public' );
bprg_test_add_group( 5, 'Autojoin Group', 'public' );
bprg_test_add_group( 6, 'Second Public Group', 'public' );

bprg_test_set_plugin_options( array(
	'bp_registration_groups_hidden_groups'   => array( 4 ),
	'bp_registration_groups_autojoin_groups' => array( 5 ),
) );

bprg_assert_true(
	bprg_test_hook_has( 'bp_signup_usermeta', 'bp_registration_groups_save' ),
	'signup meta filter is attached to bp_signup_usermeta'
);
bprg_assert_true(
	bprg_test_hook_has( 'bp_core_activated_user', 'bp_registration_groups_join' ),
	'activation handler is attached to bp_core_activated_user'
);

// Valid selections are stored in the signup meta; ineligible IDs are
// filtered out, and duplicates collapse.
$_POST    = array( 'field_reg_groups' => array( '1', '1', '2', '3', '4', '5', '6', '99', 'abc' ) );
$usermeta = apply_filters( 'bp_signup_usermeta', array( 'existing' => 'value' ) );
bprg_assert_same( array( 1, 6 ), $usermeta['field_reg_groups'] ?? null, 'only eligible submitted group IDs are saved to signup meta' );
bprg_assert_same( 'value', $usermeta['existing'] ?? null, 'existing signup meta entries are preserved' );

// With only ineligible IDs submitted, no selection key is added at all.
$_POST    = array( 'field_reg_groups' => array( '3', '4', '5', '99' ) );
$usermeta = apply_filters( 'bp_signup_usermeta', array() );
bprg_assert_true( ! isset( $usermeta['field_reg_groups'] ), 'no signup meta key is added when every submitted ID is ineligible' );

// With nothing submitted, the meta passes through untouched.
$_POST    = array();
$usermeta = apply_filters( 'bp_signup_usermeta', array( 'existing' => 'value' ) );
bprg_assert_same( array( 'existing' => 'value' ), $usermeta, 'signup meta passes through unchanged when nothing was submitted' );

// At activation, saved selections are joined after re-validation, and
// auto-join groups are always joined.
$GLOBALS['bprg_test']['joins'] = array();
do_action( 'bp_core_activated_user', 42, '', array( 'meta' => array( 'field_reg_groups' => array( 1, 2, 3, 4, 99 ) ) ) );
bprg_assert_same(
	array( array( 1, 42 ), array( 5, 42 ) ),
	$GLOBALS['bprg_test']['joins'],
	'activation joins only the still-eligible selection plus the auto-join group'
);

bprg_test_done();
