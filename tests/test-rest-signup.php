<?php
/**
 * Regression tests for the BuddyPress REST signup path.
 *
 * The REST signup endpoint never fires 'bp_signup_validate' and reads its
 * body through WP_REST_Request, not $_POST, so without these filters a REST
 * signup would bypass "Require Group Selection" the browser form enforces and
 * lose the selection entirely. These are pure filter calls (no render), so
 * many scenarios share one process; options and $_POST are reset between them.
 */

require __DIR__ . '/bootstrap.php';

// The same site layout the browser-form tests use: one selectable public
// group, a private group, a status-hidden group, a public group hidden
// per-group, and an auto-join group.
bprg_test_add_group( 1, 'Public Group', 'public' );
bprg_test_add_group( 2, 'Private Group', 'private' );
bprg_test_add_group( 3, 'Hidden Group', 'hidden' );
bprg_test_add_group( 4, 'Suppressed Group', 'public' );
bprg_test_add_group( 5, 'Autojoin Group', 'public' );

$base_options     = array(
	'bp_registration_groups_hidden_groups'   => array( 4 ),
	'bp_registration_groups_autojoin_groups' => array( 5 ),
);
$required_options = array_merge( $base_options, array( 'bp_registration_groups_require_selection' => 1 ) );

// REST requests never touch $_POST; keep it empty so nothing leaks in through
// the $_POST-based reader.
$_POST = array();

// 1. Both REST filters are attached.
bprg_assert_true(
	bprg_test_hook_has( 'bp_rest_signup_create_item_permissions_check', 'bp_registration_groups_rest_validate_signup' ),
	'the REST validator is attached to bp_rest_signup_create_item_permissions_check'
);
bprg_assert_true(
	bprg_test_hook_has( 'bp_rest_signup_create_item_meta', 'bp_registration_groups_rest_save' ),
	'the REST meta saver is attached to bp_rest_signup_create_item_meta'
);

// 2. require OFF: the permissions check is left untouched.
bprg_test_set_plugin_options( $base_options );
$retval = apply_filters( 'bp_rest_signup_create_item_permissions_check', true, new WP_REST_Request( array() ) );
bprg_assert_same( true, $retval, 'setting off: the permissions check passes through as true' );

// 3. require ON, no field_reg_groups param: rejected with our error code.
bprg_test_set_plugin_options( $required_options );
$retval = apply_filters( 'bp_rest_signup_create_item_permissions_check', true, new WP_REST_Request( array() ) );
bprg_assert_true( is_wp_error( $retval ), 'setting on: a request with no selection is rejected' );
bprg_assert_same(
	'bp_rest_registration_groups_selection_required',
	is_wp_error( $retval ) ? $retval->get_error_code() : null,
	'the rejection uses the required-selection error code'
);

// 4. require ON, only ineligible IDs (status-hidden, per-group hidden, auto-join,
// nonexistent, and a nested array element): still rejected.
$request = new WP_REST_Request( array( 'field_reg_groups' => array( '3', '4', '5', '99', array( '1' ) ) ) );
$retval  = apply_filters( 'bp_rest_signup_create_item_permissions_check', true, $request );
bprg_assert_true( is_wp_error( $retval ), 'setting on: only ineligible IDs (plus a nested array) does not satisfy the requirement' );

// 5. require ON, one eligible ID among ineligible ones: allowed.
$request = new WP_REST_Request( array( 'field_reg_groups' => array( '3', '4', '5', '99', '1' ) ) );
$retval  = apply_filters( 'bp_rest_signup_create_item_permissions_check', true, $request );
bprg_assert_same( true, $retval, 'setting on: one eligible ID among ineligible ones passes' );

// 6. require ON but NO selectable groups exist: skip so registration is never
// locked. Hiding the only selectable public group leaves nothing to select
// (2 is private with private off, 3 is status-hidden, 5 is auto-join).
$locked_out_options = array(
	'bp_registration_groups_require_selection' => 1,
	'bp_registration_groups_hidden_groups'     => array( 1, 4 ),
	'bp_registration_groups_autojoin_groups'   => array( 5 ),
);
bprg_test_set_plugin_options( $locked_out_options );
bprg_assert_same( false, bp_registration_groups_has_selectable_groups(), 'no selectable groups exist in the locked-out configuration' );
$retval = apply_filters( 'bp_rest_signup_create_item_permissions_check', true, new WP_REST_Request( array() ) );
bprg_assert_same( true, $retval, 'setting on: the requirement is skipped when no selectable groups exist' );

// 7. require ON, another plugin already returned a WP_Error: pass it through
// unchanged rather than overriding an existing rejection.
bprg_test_set_plugin_options( $required_options );
$incoming = new WP_Error( 'some_other_plugin_error', 'blocked elsewhere' );
$retval   = apply_filters( 'bp_rest_signup_create_item_permissions_check', $incoming, new WP_REST_Request( array() ) );
bprg_assert_true( $retval === $incoming, 'setting on: an existing WP_Error rejection is passed through unchanged' );

// 8. rest_save meta.
bprg_test_set_plugin_options( $required_options );

// A valid eligible ID is stored under field_reg_groups.
$request = new WP_REST_Request( array( 'field_reg_groups' => array( '1' ) ) );
$meta    = apply_filters( 'bp_rest_signup_create_item_meta', array(), $request );
bprg_assert_same( array( 1 ), $meta['field_reg_groups'] ?? null, 'a valid eligible ID is saved into the REST signup meta' );

// Only ineligible IDs: no field_reg_groups key is added.
$request = new WP_REST_Request( array( 'field_reg_groups' => array( '3', '4', '5', '99' ) ) );
$meta    = apply_filters( 'bp_rest_signup_create_item_meta', array(), $request );
bprg_assert_true( ! isset( $meta['field_reg_groups'] ), 'no meta key is added when every submitted REST ID is ineligible' );

// An incoming meta already carrying field_reg_groups (as the $_POST reader can
// pick up from a form-encoded REST body) is CLEARED when the request's own
// selection is empty/ineligible, so both body styles behave identically.
$request = new WP_REST_Request( array( 'field_reg_groups' => array( '3', '99' ) ) );
$meta    = apply_filters( 'bp_rest_signup_create_item_meta', array( 'field_reg_groups' => array( 2 ) ), $request );
bprg_assert_true( ! isset( $meta['field_reg_groups'] ), 'an incoming field_reg_groups is cleared when the request selection is empty/ineligible' );

bprg_test_done();
