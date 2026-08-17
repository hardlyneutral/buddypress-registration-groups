<?php
/**
 * Test bootstrap: minimal WordPress/BuddyPress stubs.
 *
 * Provides just enough of the WordPress plugin API (hooks, options,
 * escaping) and the BuddyPress groups API (groups loop, group lookups,
 * signup globals) to load includes/bp-registration-groups.php and exercise
 * its registration-form hooks without a WordPress install.
 *
 * Run the whole suite with: php tests/run-tests.php
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'BP_REGISTRATION_GROUPS_VERSION', 'test' );

$GLOBALS['bprg_test'] = array(
	'options'    => array(),
	'groups'     => array(), // id => object with id, name, status
	'joins'      => array(), // list of array( group_id, user_id )
	'hooks'      => array(), // hook name => list of callbacks
	'loop'       => array(),
	'loop_i'     => -1,
	'assertions' => 0,
	'failures'   => 0,
);

/* ---------------------------------------------------------------------
 * Hook system (actions and filters share a registry, as in WordPress)
 * ------------------------------------------------------------------ */

function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['bprg_test']['hooks'][ $hook ][] = $callback;
	return true;
}

function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	return add_filter( $hook, $callback, $priority, $accepted_args );
}

function do_action( $hook, ...$args ) {
	foreach ( $GLOBALS['bprg_test']['hooks'][ $hook ] ?? array() as $callback ) {
		call_user_func_array( $callback, $args );
	}
}

function apply_filters( $hook, $value, ...$args ) {
	foreach ( $GLOBALS['bprg_test']['hooks'][ $hook ] ?? array() as $callback ) {
		$value = call_user_func_array( $callback, array_merge( array( $value ), $args ) );
	}
	return $value;
}

function bprg_test_hook_has( $hook, $callback ) {
	return in_array( $callback, $GLOBALS['bprg_test']['hooks'][ $hook ] ?? array(), true );
}

/* ---------------------------------------------------------------------
 * WordPress core stubs
 * ------------------------------------------------------------------ */

function is_admin() {
	return false;
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
		$matched[] = $group;
	}
	if ( ! empty( $args['per_page'] ) && (int) $args['per_page'] > 0 ) {
		$matched = array_slice( $matched, 0, (int) $args['per_page'] );
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
	return true;
}

/* The bp_has_groups() template loop used by the render function. */

function bp_has_groups( $args = array() ) {
	$args['show_hidden']              = false;
	$GLOBALS['bprg_test']['loop']     = array_values( bprg_test_filter_groups( $args ) );
	$GLOBALS['bprg_test']['loop_i']   = -1;
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

/* Load the code under test. */
require dirname( __DIR__ ) . '/includes/bp-registration-groups.php';
