<?php
/**
 * Curated sections, settings sanitization: rows are cleaned and reordered by
 * their Position field, removed and empty rows are dropped, and a group
 * listed in more than one section is kept only in the first (lowest
 * position) section.
 */

require __DIR__ . '/bootstrap.php';

$settings_page = new BPRegistrationGroupsSettingsPage();

$sanitized = $settings_page->sanitize( array(
	'bp_registration_groups_sections' => array(
		// submitted second by position, shares group 2 with the row below
		array(
			'position'    => '2',
			'title'       => '  Interests <script>alert(1)</script> ',
			'description' => 'Pick some',
			'groups'      => array( '1', '2', '2', '0', 'junk' ),
		),
		// submitted first by position
		array(
			'position'    => '1',
			'title'       => 'Regions',
			'description' => '',
			'groups'      => array( '3', '2' ),
		),
		// removed on save
		array(
			'position' => '3',
			'title'    => 'Old Section',
			'remove'   => '1',
			'groups'   => array( '5' ),
		),
		// the blank "add a new section" row
		array(
			'position'    => '4',
			'title'       => '',
			'description' => '',
		),
	),
) );

$sections = $sanitized['bp_registration_groups_sections'];

bprg_assert_same( 2, count( $sections ), 'removed and blank rows are dropped' );
bprg_assert_same( 'Regions', $sections[0]['title'], 'sections are reordered by their position field' );
bprg_assert_same( 'Interests alert(1)', $sections[1]['title'], 'section titles are sanitized' );
bprg_assert_same( 'Pick some', $sections[1]['description'], 'section descriptions round-trip' );
bprg_assert_same( array( 3, 2 ), $sections[0]['groups'], 'the first section by position keeps the shared group' );
bprg_assert_same( array( 1 ), $sections[1]['groups'], 'a group already assigned to an earlier section is dropped, along with invalid IDs' );
bprg_assert_true( ! isset( $sections[0]['position'] ) && ! isset( $sections[0]['submitted'] ), 'ordering scaffolding is not stored' );

// Round-trip: the sanitized value is what the reader returns.
bprg_test_set_plugin_options( $sanitized );
bprg_assert_same( $sections, bp_registration_groups_get_sections(), 'sanitized sections round-trip through the getter' );

bprg_test_done();
