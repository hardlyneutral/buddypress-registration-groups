<?php
/**
 * Curated sections, radio mode: each section is its own radio group (inputs
 * share a per-section name), so the registrant can select one group in every
 * section, and the checked-by-default setting checks at most one group per
 * section.
 */

require __DIR__ . '/bootstrap.php';

bprg_test_add_group( 1, 'Books', 'public' );
bprg_test_add_group( 2, 'Cycling', 'public' );
bprg_test_add_group( 3, 'North Region', 'public' );
bprg_test_add_group( 4, 'South Region', 'public' );

bprg_test_set_plugin_options( array(
	'bp_registration_groups_display_as'     => 3, // radio buttons
	// two defaults in the first section (only the first may be checked) and
	// one in the second section (checked independently of the first section)
	'bp_registration_groups_checked_groups' => array( 1, 2, 4 ),
	'bp_registration_groups_sections'       => array(
		array( 'title' => 'Interests', 'description' => '', 'groups' => array( 1, 2 ) ),
		array( 'title' => 'Regions', 'description' => '', 'groups' => array( 3, 4 ) ),
	),
) );

$_POST = array();

ob_start();
do_action( 'bp_before_registration_submit_buttons' );
$output = ob_get_clean();

bprg_assert_same( 2, substr_count( $output, 'name="field_reg_groups[0]"' ), 'the first section radios share the per-section name' );
bprg_assert_same( 2, substr_count( $output, 'name="field_reg_groups[1]"' ), 'the second section radios share their own per-section name' );
bprg_assert_same( 0, substr_count( $output, 'name="field_reg_groups[]"' ), 'no input uses the shared checkbox name in radio mode' );

bprg_assert_true( 1 === preg_match( '/value="1"\s+checked=\'checked\'/', $output ), 'the first default in the first section is checked' );
bprg_assert_true( 0 === preg_match( '/value="2"\s+checked=\'checked\'/', $output ), 'only one radio per section is checked by default' );
bprg_assert_true( 1 === preg_match( '/value="4"\s+checked=\'checked\'/', $output ), 'the second section checks its own default independently' );

bprg_test_done();
