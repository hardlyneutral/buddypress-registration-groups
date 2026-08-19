<?php
/**
 * Global (non-sections) locked auto-join display: with "Auto-Join Display"
 * on, an auto-join group renders as a pre-checked, disabled entry with no
 * name attribute (so it never submits), a status-hidden auto-join group is
 * never named on the form, an auto-join group the admin also marked
 * per-group Hide is never named either (regression: the global branch used
 * to render it as a locked entry while the sections branch suppressed it —
 * suppressed matches the admin help text), and normal groups stay
 * selectable alongside.
 */

require __DIR__ . '/bootstrap.php';

bprg_test_add_group( 1, 'Books', 'public' );
bprg_test_add_group( 2, 'Cycling', 'public' );
bprg_test_add_group( 3, 'Everyone', 'public' ); // auto-join, shown locked
bprg_test_add_group( 4, 'Ghost Crew', 'hidden' ); // auto-join, status hidden
bprg_test_add_group( 5, 'Backstage', 'public' ); // auto-join AND hidden per-group

bprg_test_set_plugin_options( array(
	'bp_registration_groups_hidden_groups'    => array( 5 ),
	'bp_registration_groups_autojoin_groups'  => array( 3, 4, 5 ),
	'bp_registration_groups_autojoin_display' => 1,
) );

$_POST = array();

ob_start();
do_action( 'bp_before_registration_submit_buttons' );
$output = ob_get_clean();

// The public auto-join group renders as a locked entry.
$locked_input = array();
bprg_assert_true(
	1 === preg_match( '/<input[^>]*id="field_reg_groups_auto_3"[^>]*\/>/', $output, $locked_input ),
	'the auto-join group renders exactly one locked input'
);
$locked_input = isset( $locked_input[0] ) ? $locked_input[0] : '';
bprg_assert_true( false !== strpos( $locked_input, 'checked="checked"' ), 'the locked input is pre-checked' );
bprg_assert_true( false !== strpos( $locked_input, 'disabled="disabled"' ), 'the locked input is disabled' );
bprg_assert_true( false === strpos( $locked_input, 'name=' ), 'the locked input has no name attribute, so it never submits' );
bprg_assert_true( false !== strpos( $output, '(automatic)' ), 'the locked entry carries the (automatic) label' );
bprg_assert_same( 1, substr_count( $output, '>Everyone ' ), 'the auto-join group is named exactly once' );
bprg_assert_same( 0, substr_count( $output, 'value="3"' ), 'the auto-join group does not also appear as a selectable entry' );

// Auto-join groups that must stay off the form entirely.
bprg_assert_true( false === strpos( $output, 'Ghost Crew' ), 'a status-hidden auto-join group is never named on the form' );
bprg_assert_true( false === strpos( $output, 'Backstage' ), 'an auto-join group also marked per-group Hide is never named on the form' );
bprg_assert_same( 0, substr_count( $output, 'field_reg_groups_auto_4' ), 'no locked input is rendered for the status-hidden auto-join group' );
bprg_assert_same( 0, substr_count( $output, 'field_reg_groups_auto_5' ), 'no locked input is rendered for the per-group hidden auto-join group' );

// Normal groups render as selectable inputs alongside the locked entry.
bprg_assert_same( 1, substr_count( $output, 'name="field_reg_groups[]" value="1"' ), 'a normal group still renders as a selectable input' );
bprg_assert_same( 1, substr_count( $output, 'name="field_reg_groups[]" value="2"' ), 'the second normal group is selectable too' );
bprg_assert_same( 1, substr_count( $output, '>Books<' ), 'a normal group renders exactly once' );
bprg_assert_same( 1, substr_count( $output, '>Cycling<' ), 'the second normal group renders exactly once' );

// With "Auto-Join Display" off the same config renders no locked entry.
// (New process would be needed for a second render; assert on the option
// effect via the display helper instead.)
bprg_assert_true( bp_registration_groups_autojoin_shows_locked(), 'the display helper reports locked entries while the option is on' );
bprg_test_set_plugin_options( array(
	'bp_registration_groups_hidden_groups'    => array( 5 ),
	'bp_registration_groups_autojoin_groups'  => array( 3, 4, 5 ),
	'bp_registration_groups_autojoin_display' => 0,
) );
bprg_assert_true( ! bp_registration_groups_autojoin_shows_locked(), 'with the option off the display helper reports no locked entries' );

bprg_test_done();
