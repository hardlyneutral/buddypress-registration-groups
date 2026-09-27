<?php
/**
 * Test bootstrap: minimal WordPress/BuddyPress stubs.
 *
 * Provides just enough of the WordPress plugin API (hooks, options,
 * escaping, the current user, nonces, shortcodes) and the BuddyPress groups
 * API (groups loop, group lookups, memberships, membership requests, bans,
 * group capabilities, signup globals) to load the plugin's includes and
 * exercise its registration-form hooks and join-groups shortcode without a
 * WordPress install.
 *
 * Run the whole suite with: php tests/run-tests.php
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'BP_REGISTRATION_GROUPS_VERSION', 'test' );

$GLOBALS['bprg_test'] = array(
	'options'         => array(),
	'groups'          => array(), // id => object with id, name, status
	'joins'           => array(), // list of array( group_id, user_id )
	'members'         => array(), // user_id => array( group_id => true )
	'requests'        => array(), // user_id => array( group_id => true ), pending membership requests
	'invites'         => array(), // user_id => array( group_id => true ), outstanding invitations
	'banned'          => array(), // user_id => array( group_id => true )
	'requests_sent'   => array(), // list of array( group_id, user_id ), membership request attempts
	'cap_denied'      => array(), // list of array( capability, group_id ) denied by "another plugin"
	'current_user_id' => 0,
	'shortcodes'      => array(), // tag => callback
	'hooks'           => array(), // hook name => list of callbacks
	'loop'            => array(),
	'loop_i'          => -1,
	'assertions'      => 0,
	'failures'        => 0,
);

/* ---------------------------------------------------------------------
 * Hook system (actions and filters share a registry, as in WordPress)
 * ------------------------------------------------------------------ */

function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	// Record priority and accepted_args like real WordPress, so callbacks
	// run low-priority-first and receive exactly the number of arguments they
	// registered for. A callback that registered for fewer args than the hook
	// passes must not silently receive the extras — that models the real
	// engine and lets tests catch an accepted_args regression (e.g. an
	// activation callback registered with the wrong count).
	$GLOBALS['bprg_test']['hooks'][ $hook ][] = array(
		'callback'      => $callback,
		'priority'      => $priority,
		'accepted_args' => $accepted_args,
	);
	return true;
}

function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	return add_filter( $hook, $callback, $priority, $accepted_args );
}

function bprg_test_hook_callbacks( $hook ) {
	$entries = $GLOBALS['bprg_test']['hooks'][ $hook ] ?? array();
	// Stable sort by priority (ascending), preserving registration order
	// within a priority — as WP_Hook does.
	$indexed = array();
	foreach ( $entries as $i => $entry ) {
		$indexed[] = array( $i, $entry );
	}
	usort( $indexed, function ( $a, $b ) {
		return ( $a[1]['priority'] === $b[1]['priority'] ) ? $a[0] - $b[0] : $a[1]['priority'] - $b[1]['priority'];
	} );
	return array_map( function ( $pair ) { return $pair[1]; }, $indexed );
}

function do_action( $hook, ...$args ) {
	foreach ( bprg_test_hook_callbacks( $hook ) as $entry ) {
		call_user_func_array( $entry['callback'], array_slice( $args, 0, $entry['accepted_args'] ) );
	}
}

function apply_filters( $hook, $value, ...$args ) {
	foreach ( bprg_test_hook_callbacks( $hook ) as $entry ) {
		$call_args = array_slice( array_merge( array( $value ), $args ), 0, $entry['accepted_args'] );
		$value     = call_user_func_array( $entry['callback'], $call_args );
	}
	return $value;
}

function bprg_test_hook_has( $hook, $callback ) {
	foreach ( $GLOBALS['bprg_test']['hooks'][ $hook ] ?? array() as $entry ) {
		if ( $entry['callback'] === $callback ) {
			return true;
		}
	}
	return false;
}

/* ---------------------------------------------------------------------
 * WordPress core stubs
 * ------------------------------------------------------------------ */

function is_admin() {
	return false;
}

function get_current_user_id() {
	return (int) $GLOBALS['bprg_test']['current_user_id'];
}

function is_user_logged_in() {
	return get_current_user_id() > 0;
}

/*
 * Nonces are bound to the action and the current user, as in WordPress, so a
 * nonce minted for one user fails verification for another.
 */
function wp_create_nonce( $action = -1 ) {
	return 'nonce-' . md5( $action . '|' . get_current_user_id() );
}

function wp_verify_nonce( $nonce, $action = -1 ) {
	return ( is_string( $nonce ) && '' !== $nonce && hash_equals( wp_create_nonce( $action ), $nonce ) ) ? 1 : false;
}

function add_shortcode( $tag, $callback ) {
	$GLOBALS['bprg_test']['shortcodes'][ $tag ] = $callback;
}

function wp_login_url( $redirect = '' ) {
	$url = 'http://example.test/wp-login.php';
	if ( ! empty( $redirect ) ) {
		$url .= '?redirect_to=' . rawurlencode( $redirect );
	}
	return $url;
}

function get_permalink( $post = 0 ) {
	return 'http://example.test/choose-groups/';
}

/* Minimal WP_Error / is_wp_error, enough for the REST validation path. */

class WP_Error {
	public $code;
	public $message;
	public $data;
	public function __construct( $code = '', $message = '', $data = '' ) {
		$this->code    = $code;
		$this->message = $message;
		$this->data    = $data;
	}
	public function get_error_code() {
		return $this->code;
	}
	public function get_error_message() {
		return $this->message;
	}
}

function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}

/*
 * Minimal WP_REST_Request: only get_param(), which the plugin's REST hooks
 * use to read 'field_reg_groups'. Construct with an array of params.
 */
class WP_REST_Request {
	private $params;
	public function __construct( $params = array() ) {
		$this->params = $params;
	}
	public function get_param( $key ) {
		return array_key_exists( $key, $this->params ) ? $this->params[ $key ] : null;
	}
}

function get_option( $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['bprg_test']['options'] ) ? $GLOBALS['bprg_test']['options'][ $name ] : $default;
}

function absint( $value ) {
	return abs( (int) $value );
}

function wp_unslash( $value ) {
	if ( is_array( $value ) ) {
		return array_map( 'wp_unslash', $value );
	}
	return is_string( $value ) ? stripslashes( $value ) : $value;
}

function sanitize_text_field( $value ) {
	return trim( strip_tags( (string) $value ) );
}

function __( $text, $domain = 'default' ) {
	return $text;
}

function esc_html__( $text, $domain = 'default' ) {
	return htmlspecialchars( $text, ENT_QUOTES );
}

function esc_html_e( $text, $domain = 'default' ) {
	echo esc_html__( $text, $domain );
}

function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES );
}

function esc_attr( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES );
}

function esc_url( $url ) {
	return htmlspecialchars( (string) $url, ENT_QUOTES );
}

function _n( $single, $plural, $number, $domain = 'default' ) {
	return 1 === (int) $number ? $single : $plural;
}

function checked( $checked, $current = true, $echo = true ) {
	$result = ( (string) $checked === (string) $current ) ? " checked='checked'" : '';
	if ( $echo ) {
		echo $result;
	}
	return $result;
}

function wp_register_style( ...$args ) {}
function wp_enqueue_style( ...$args ) {}

function plugins_url( $path = '', $plugin = '' ) {
	return 'http://example.test/wp-content/plugins/buddypress-registration-groups' . $path;
}

/* ---------------------------------------------------------------------
 * BuddyPress stubs
 * ------------------------------------------------------------------ */

function buddypress() {
	if ( ! isset( $GLOBALS['bprg_test']['bp'] ) ) {
		$GLOBALS['bprg_test']['bp'] = new stdClass();
	}
	return $GLOBALS['bprg_test']['bp'];
}

function bp_is_active( $component ) {
	return true;
}

function groups_get_group( $group_id ) {
	$group_id = (int) $group_id;
	if ( isset( $GLOBALS['bprg_test']['groups'][ $group_id ] ) ) {
		return $GLOBALS['bprg_test']['groups'][ $group_id ];
	}
	// BuddyPress returns a group object with an empty id for unknown groups.
	$missing     = new stdClass();
	$missing->id = 0;
	return $missing;
}

function bprg_test_filter_groups( $args ) {
	$matched = array();
	foreach ( $GLOBALS['bprg_test']['groups'] as $group ) {
		if ( empty( $args['show_hidden'] ) && 'hidden' === $group->status ) {
			continue;
		}
		if ( ! empty( $args['status'] ) && ! in_array( $group->status, (array) $args['status'], true ) ) {
			continue;
		}
		if ( ! empty( $args['exclude'] ) && in_array( (int) $group->id, array_map( 'intval', (array) $args['exclude'] ), true ) ) {
			continue;
		}
		// Real BuddyPress matches search terms against name and description;
		// the stub's groups only have names, so match those.
		if ( ! empty( $args['search_terms'] ) && false === stripos( $group->name, (string) $args['search_terms'] ) ) {
			continue;
		}
		$matched[] = $group;
	}
	// Model real BuddyPress ordering for the 'type' values the plugin uses,
	// simplified for determinism: 'newest' sorts by id descending (a proxy
	// for date_created), 'active' and 'popular' sort by id ascending (real
	// BuddyPress uses last_activity / total_member_count, which the stub's
	// groups do not have), and 'random' sorts by name descending (real
	// BuddyPress uses RAND(); a fixed order that differs from every other
	// type lets tests tell the orderings apart).
	$type = isset( $args['type'] ) ? $args['type'] : '';
	if ( 'alphabetical' === $type ) {
		usort( $matched, function ( $a, $b ) {
			return strcasecmp( $a->name, $b->name );
		} );
	} elseif ( 'newest' === $type ) {
		usort( $matched, function ( $a, $b ) {
			return (int) $b->id - (int) $a->id;
		} );
	} elseif ( 'active' === $type || 'popular' === $type ) {
		usort( $matched, function ( $a, $b ) {
			return (int) $a->id - (int) $b->id;
		} );
	} elseif ( 'random' === $type ) {
		usort( $matched, function ( $a, $b ) {
			return strcasecmp( $b->name, $a->name );
		} );
	}
	// per_page 0 or null means "all", as in real BuddyPress; 'page' beyond
	// the first skips earlier pages, as in BP_Groups_Group::get().
	if ( ! empty( $args['per_page'] ) && (int) $args['per_page'] > 0 ) {
		$page    = ( ! empty( $args['page'] ) && (int) $args['page'] > 0 ) ? (int) $args['page'] : 1;
		$matched = array_slice( $matched, ( $page - 1 ) * (int) $args['per_page'], (int) $args['per_page'] );
	}
	return $matched;
}

function groups_get_groups( $args = array() ) {
	$groups = bprg_test_filter_groups( $args );
	return array(
		'groups' => $groups,
		'total'  => count( $groups ),
	);
}

function groups_join_group( $group_id, $user_id ) {
	$GLOBALS['bprg_test']['joins'][] = array( (int) $group_id, (int) $user_id );
	// Tests can force specific group IDs to fail (as BuddyPress can, e.g. on a
	// transient DB error) by listing them in bprg_test.join_failures, so the
	// join-failure action path is exercisable.
	if ( in_array( (int) $group_id, $GLOBALS['bprg_test']['join_failures'] ?? array(), true ) ) {
		return false;
	}
	// As in BuddyPress, joining clears an outstanding request for the group.
	unset( $GLOBALS['bprg_test']['requests'][ (int) $user_id ][ (int) $group_id ] );
	$GLOBALS['bprg_test']['members'][ (int) $user_id ][ (int) $group_id ] = true;
	return true;
}

function groups_is_user_member( $user_id, $group_id ) {
	return ! empty( $GLOBALS['bprg_test']['members'][ (int) $user_id ][ (int) $group_id ] );
}

function groups_is_user_banned( $user_id, $group_id ) {
	return ! empty( $GLOBALS['bprg_test']['banned'][ (int) $user_id ][ (int) $group_id ] );
}

function groups_check_for_membership_request( $user_id, $group_id ) {
	return ! empty( $GLOBALS['bprg_test']['requests'][ (int) $user_id ][ (int) $group_id ] );
}

/*
 * Models BuddyPress 5.0+ groups_send_membership_request(): array arguments
 * only (the positional form is deprecated, so the stub rejects it), guarded by
 * the 'groups_request_membership' capability, and a request for a group the
 * user was already invited to is accepted on the spot as a membership.
 */
function groups_send_membership_request( ...$args ) {
	if ( 1 !== count( $args ) || ! is_array( $args[0] ) ) {
		$GLOBALS['bprg_test']['deprecated_request_calls'] = ( $GLOBALS['bprg_test']['deprecated_request_calls'] ?? 0 ) + 1;
		return false;
	}

	$user_id  = (int) ( $args[0]['user_id'] ?? 0 );
	$group_id = (int) ( $args[0]['group_id'] ?? 0 );

	$GLOBALS['bprg_test']['requests_sent'][] = array( $group_id, $user_id );

	if ( ! bp_user_can( $user_id, 'groups_request_membership', array( 'group_id' => $group_id ) ) ) {
		return false;
	}

	if ( ! empty( $GLOBALS['bprg_test']['invites'][ $user_id ][ $group_id ] ) ) {
		unset( $GLOBALS['bprg_test']['invites'][ $user_id ][ $group_id ] );
		$GLOBALS['bprg_test']['members'][ $user_id ][ $group_id ] = true;
		return true;
	}

	$GLOBALS['bprg_test']['requests'][ $user_id ][ $group_id ] = true;
	return count( $GLOBALS['bprg_test']['requests_sent'] );
}

/*
 * Models the two group capabilities BuddyPress maps in
 * bp_groups_user_can_filter(): joining needs a public group the user is not a
 * member of or banned from; requesting needs a private group with no
 * membership, pending request, or ban. Tests can deny a capability for a
 * group (as another plugin filtering 'bp_user_can' could) via cap_denied.
 */
function bp_user_can( $user_id, $capability, $args = array() ) {
	$group_id = (int) ( $args['group_id'] ?? 0 );

	if ( in_array( array( $capability, $group_id ), $GLOBALS['bprg_test']['cap_denied'], true ) ) {
		return false;
	}

	$group = groups_get_group( $group_id );

	if ( ! $user_id || empty( $group->id ) ) {
		return false;
	}

	switch ( $capability ) {
		case 'groups_join_group':
			return 'public' === $group->status && ! groups_is_user_member( $user_id, $group_id ) && ! groups_is_user_banned( $user_id, $group_id );
		case 'groups_request_membership':
			return 'private' === $group->status && ! groups_is_user_member( $user_id, $group_id ) && ! groups_check_for_membership_request( $user_id, $group_id ) && ! groups_is_user_banned( $user_id, $group_id );
	}

	return false;
}

/* The bp_has_groups() template loop used by the render function. */

function bp_has_groups( $args = array() ) {
	$args['show_hidden'] = false;

	// Model BuddyPress's request-driven loop overrides: real bp_has_groups()
	// feeds per_page and page through bp_sanitize_pagination_arg(), which
	// replaces them with absint( $_REQUEST['num'] ) / absint( $_REQUEST['grpage'] )
	// when those are positive integers, and reads search terms from
	// $_REQUEST['group-filter-box'] or $_REQUEST['s'].
	if ( isset( $_REQUEST['num'] ) && absint( $_REQUEST['num'] ) > 0 ) {
		$args['per_page'] = absint( $_REQUEST['num'] );
	}
	if ( isset( $_REQUEST['grpage'] ) && absint( $_REQUEST['grpage'] ) > 0 ) {
		$args['page'] = absint( $_REQUEST['grpage'] );
	}
	if ( ! empty( $_REQUEST['group-filter-box'] ) ) {
		$args['search_terms'] = (string) $_REQUEST['group-filter-box'];
	} elseif ( ! empty( $_REQUEST['s'] ) ) {
		$args['search_terms'] = (string) $_REQUEST['s'];
	}

	$GLOBALS['bprg_test']['loop']   = array_values( bprg_test_filter_groups( $args ) );
	$GLOBALS['bprg_test']['loop_i'] = -1;
	return ! empty( $GLOBALS['bprg_test']['loop'] );
}

function bp_groups() {
	return $GLOBALS['bprg_test']['loop_i'] < count( $GLOBALS['bprg_test']['loop'] ) - 1;
}

function bp_the_group() {
	$GLOBALS['bprg_test']['loop_i']++;
}

function bprg_test_current_group() {
	return $GLOBALS['bprg_test']['loop'][ $GLOBALS['bprg_test']['loop_i'] ];
}

function bp_get_group_id() {
	return bprg_test_current_group()->id;
}

function bp_get_group_status() {
	return bprg_test_current_group()->status;
}

function bp_get_group_name() {
	return bprg_test_current_group()->name;
}

/* ---------------------------------------------------------------------
 * Test helpers and assertions
 * ------------------------------------------------------------------ */

function bprg_test_add_group( $id, $name, $status ) {
	$group         = new stdClass();
	$group->id     = (int) $id;
	$group->name   = $name;
	$group->status = $status;
	$GLOBALS['bprg_test']['groups'][ (int) $id ] = $group;
}

function bprg_test_set_plugin_options( $options ) {
	$GLOBALS['bprg_test']['options']['bp_registration_groups_option_handle'] = $options;
}

function bprg_test_set_user( $user_id ) {
	$GLOBALS['bprg_test']['current_user_id'] = (int) $user_id;
}

function bprg_test_do_shortcode( $tag, $atts = array() ) {
	return call_user_func( $GLOBALS['bprg_test']['shortcodes'][ $tag ], $atts, '', $tag );
}

function bprg_test_reset_signup() {
	buddypress()->signup         = new stdClass();
	buddypress()->signup->errors = array();
}

function bprg_assert_true( $condition, $label ) {
	$GLOBALS['bprg_test']['assertions']++;
	if ( $condition ) {
		echo "  ok - {$label}\n";
	} else {
		$GLOBALS['bprg_test']['failures']++;
		echo "  FAIL - {$label}\n";
	}
}

function bprg_assert_same( $expected, $actual, $label ) {
	$is_same = ( $expected === $actual );
	if ( ! $is_same ) {
		$label .= ' (expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) . ')';
	}
	bprg_assert_true( $is_same, $label );
}

function bprg_test_done() {
	$assertions = $GLOBALS['bprg_test']['assertions'];
	$failures   = $GLOBALS['bprg_test']['failures'];
	echo "  {$assertions} assertions, {$failures} failures\n";
	exit( $failures > 0 ? 1 : 0 );
}

/* Load the code under test, in the order loader.php loads it. */
require dirname( __DIR__ ) . '/includes/bp-registration-groups.php';
require dirname( __DIR__ ) . '/includes/bp-registration-groups-join.php';
