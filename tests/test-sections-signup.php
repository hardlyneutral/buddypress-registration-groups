<?php
/**
 * Curated sections, signup pipeline: selections from every section are
 * sanitized and aggregated once into the signup meta, IDs outside any
 * section (or otherwise ineligible) are dropped, and activation joins only
 * groups still assigned to a section.
 */

require __DIR__ . '/bootstrap.php';

bprg_test_add_group( 1, 'Books', 'public' );
bprg_test_add_group( 2, 'Cycling', 'public' );
bprg_test_add_group( 3, 'North Region', 'public' );
bprg_test_add_group( 4, 'Secret Society', 'hidden' );
bprg_test_add_group( 7, 'Unassigned Group', 'public' );

bprg_test_set_plugin_options( array(
	'bp_registration_groups_sections' => array(
		array( 'title' => 'Interests', 'description' => '', 'groups' => array( 1, 2, 4 ) ),
		array( 'title' => 'Regions', 'description' => '', 'groups' => array( 3 ) ),
	),
) );

// A radio-mode style submission: per-section keys, plus hostile extras — an
// unassigned group, a status-hidden assigned group, a nonexistent group, and
// a duplicate.
$_POST = array(
	'signup_submit'    => '1',
	'field_reg_groups' => array(
		'0'     => '1',
		'1'     => '3',
		'dup'   => '1',
		'evil1' => '7',
		'evil2' => '4',
		'evil3' => '99',
	),
);

$usermeta = apply_filters( 'bp_signup_usermeta', array( 'existing' => 'kept' ) );

bprg_assert_same( 'kept', $usermeta['existing'], 'existing signup meta is preserved' );
bprg_assert_same( array( 1, 3 ), $usermeta['field_reg_groups'], 'selections from every section aggregate once; unassigned, hidden, and nonexistent IDs are dropped' );

// Activation: the saved selection joins, but an ID that is no longer
// assigned to any section does not.
$GLOBALS['bprg_test']['joins'] = array();
do_action( 'bp_core_activated_user', 42, '', array( 'meta' => array( 'field_reg_groups' => array( 1, 3, 7 ) ) ) );

bprg_assert_same( array( array( 1, 42 ), array( 3, 42 ) ), $GLOBALS['bprg_test']['joins'], 'activation joins only groups assigned to a section' );

bprg_test_done();
