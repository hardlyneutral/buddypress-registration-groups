<?php
/**
 * Private auto-join suppression on the single global list: an auto-join group
 * whose status the form is not allowed to show (a private group while "Show
 * Private Groups" is off) is joined silently and never named on the form,
 * while a public auto-join group still renders as a locked "(automatic)"
 * entry and normal public groups stay selectable.
 *
 * The render function guards itself to one render per PHP process, so the
 * states that need a different option set are locked in with pure
 * (non-render) helper assertions, the way test-sections-render.php does.
 */

require __DIR__ . '/bootstrap.php';

bprg_test_add_group( 1, 'Neighborhood Watch', 'public' );  // normal, selectable
bprg_test_add_group( 2, 'Everyone', 'public' );            // auto-join, shown locked
bprg_test_add_group( 3, 'Inner Circle', 'private' );       // auto-join, private, suppressed

bprg_test_set_plugin_options( array(
	'bp_registration_groups_display_as'          => 1,
	'bp_registration_groups_autojoin_display'    => 1,
	'bp_registration_groups_show_private_groups' => 0,
	'bp_registration_groups_autojoin_groups'     => array( 2, 3 ),
) );

$_POST = array();

ob_start();
do_action( 'bp_before_registration_submit_buttons' );
$output = ob_get_clean();

// The public auto-join group is named as a locked, pre-checked "(automatic)"
// entry with no name attribute (so it is never submitted).
bprg_assert_true( false !== strpos( $output, 'Everyone' ), 'the public auto-join group is named on the form' );
bprg_assert_true( false !== strpos( $output, 'id="field_reg_groups_auto_2"' ), 'the public auto-join group renders as a locked entry (no name attribute)' );
bprg_assert_true( false !== strpos( $output, 'disabled="disabled"' ), 'the locked entry is disabled' );
bprg_assert_true( false !== strpos( $output, 'checked="checked"' ), 'the locked entry is pre-checked' );
bprg_assert_same( 1, substr_count( $output, '(automatic)' ), 'exactly one group renders as a locked automatic entry' );
bprg_assert_same( 0, substr_count( $output, 'name="field_reg_groups[]" value="2"' ), 'the auto-join group is not submittable' );

// The private auto-join group is suppressed everywhere: not named and not
// rendered as a locked entry.
bprg_assert_true( false === strpos( $output, 'Inner Circle' ), 'the private auto-join group is not named anywhere when private groups are hidden' );
bprg_assert_true( false === strpos( $output, 'id="field_reg_groups_auto_3"' ), 'the private auto-join group is not rendered as a locked entry' );

// A normal public group still renders as a real selectable input.
bprg_assert_true( false !== strpos( $output, 'Neighborhood Watch' ), 'the selectable public group is named' );
bprg_assert_true( false !== strpos( $output, 'name="field_reg_groups[]" value="1"' ), 'the selectable public group renders a submittable field_reg_groups[] input' );

// Pure (non-render) assertions locking the contract for states unreachable in
// this one-render process.

// Auto-join locked display is on for this configuration.
bprg_assert_same( true, bp_registration_groups_autojoin_shows_locked(), 'auto-join groups are shown as locked entries in this configuration' );

// With "Show Private Groups" on, private becomes an allowed status, so the
// same private auto-join group WOULD be eligible to be named.
bprg_test_set_plugin_options( array(
	'bp_registration_groups_display_as'          => 1,
	'bp_registration_groups_autojoin_display'    => 1,
	'bp_registration_groups_show_private_groups' => 1,
	'bp_registration_groups_autojoin_groups'     => array( 2, 3 ),
) );
bprg_assert_true( in_array( 'private', bp_registration_groups_allowed_statuses(), true ), 'private is an allowed status when Show Private Groups is on' );

// With it off, private is not allowed, which is why the render above
// suppressed it.
bprg_test_set_plugin_options( array(
	'bp_registration_groups_display_as'          => 1,
	'bp_registration_groups_autojoin_display'    => 1,
	'bp_registration_groups_show_private_groups' => 0,
	'bp_registration_groups_autojoin_groups'     => array( 2, 3 ),
) );
bprg_assert_true( ! in_array( 'private', bp_registration_groups_allowed_statuses(), true ), 'private is not an allowed status when Show Private Groups is off' );

bprg_test_done();
