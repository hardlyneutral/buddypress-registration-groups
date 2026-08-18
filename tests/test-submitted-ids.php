<?php
/**
 * Regression tests for submitted-group-ID parsing and settings sanitization.
 *
 * The signup form's own inputs only ever post plain digit strings, so the
 * parser accepts exactly that; forged payloads (nested arrays, negatives,
 * floats) must be dropped rather than coerced into unrelated group IDs.
 */

require __DIR__ . '/bootstrap.php';

function bprg_submitted_for( $value ) {
	$_POST = array( 'field_reg_groups' => $value );
	return bp_registration_groups_get_submitted_group_ids();
}

// Plain digit strings — what real checkbox/radio inputs post.
bprg_assert_same( array( 3, 7 ), bprg_submitted_for( array( '3', '7' ) ), 'digit strings are accepted' );
bprg_assert_same( array( 5 ), bprg_submitted_for( '5' ), 'a scalar radio-style submission is accepted' );
bprg_assert_same( array( 4, 6 ), bprg_submitted_for( array( 'a' => '4', 'b' => '6' ) ), 'associative radio-per-section submissions are accepted' );
bprg_assert_same( array( 7 ), bprg_submitted_for( array( 7 ) ), 'genuine integers are accepted' );
bprg_assert_same( array( 7 ), bprg_submitted_for( array( '007' ) ), 'leading zeros are tolerated' );

// Duplicates collapse and the result is reindexed.
bprg_assert_same( array( 3, 4 ), bprg_submitted_for( array( '3', '3', '4' ) ), 'duplicate submissions collapse' );

// Forged payloads are dropped, not coerced.
bprg_assert_same( array(), bprg_submitted_for( array( array( '5' ) ) ), 'a nested array is dropped (not coerced to group 1)' );
bprg_assert_same( array(), bprg_submitted_for( array( array( 'x' => array( '6' ) ) ) ), 'a deeply nested array is dropped' );
bprg_assert_same( array(), bprg_submitted_for( array( '-4' ) ), 'a negative is dropped (not flipped to group 4)' );
bprg_assert_same( array(), bprg_submitted_for( array( '2.9' ) ), 'a float string is dropped (not truncated to group 2)' );
bprg_assert_same( array(), bprg_submitted_for( array( '4e2' ) ), 'scientific notation is dropped' );
bprg_assert_same( array(), bprg_submitted_for( array( 'abc', '', '0', ' 3' ) ), 'non-numeric, empty, zero, and padded values are dropped' );
bprg_assert_same( array(), bprg_submitted_for( array( true, null, 3.0 ) ), 'non-int non-string scalars are dropped' );

// A forged entry among real ones only loses the forged entry.
bprg_assert_same( array( 3 ), bprg_submitted_for( array( array( '5' ), '3', '-4' ) ), 'forged entries are dropped without losing real ones' );

unset( $_POST['field_reg_groups'] );
bprg_assert_same( array(), bp_registration_groups_get_submitted_group_ids(), 'no submission yields an empty list' );

// Settings sanitization: "Display As" only stores the supported values.
$settings_page = new BPRegistrationGroupsSettingsPage();
foreach ( array( '1' => 1, '2' => 2, '3' => 3, '99' => 2, '0' => 2, 'abc' => 2, '-3' => 2 ) as $posted => $expected ) {
	$sanitized = $settings_page->sanitize( array( 'bp_registration_groups_display_as' => (string) $posted ) );
	bprg_assert_same( $expected, $sanitized['bp_registration_groups_display_as'], "display_as '{$posted}' sanitizes to {$expected}" );
}

bprg_test_done();
