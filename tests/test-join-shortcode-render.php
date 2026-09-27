<?php
/**
 * Tests for rendering the [bp_registration_groups_join] shortcode (global
 * list, no sections): the logged-out prompt, which settings carry over from
 * the registration form and which do not, per-group membership states,
 * escaping, and unique IDs per instance.
 */

require __DIR__ . '/bootstrap.php';

bprg_test_add_group( 1, 'Announcements', 'public' );   // auto-join
bprg_test_add_group( 2, 'Book Club', 'public' );       // checked by default
bprg_test_add_group( 3, 'Cooking', 'public' );         // per-group hidden
bprg_test_add_group( 4, 'Cycling', 'public' );         // the user is a member
bprg_test_add_group( 5, 'Secret Garden', 'private' );  // requestable
bprg_test_add_group( 6, 'Hidden Society', 'hidden' );  // status hidden
bprg_test_add_group( 7, 'Knitters', 'private' );       // the user has a pending request
bprg_test_add_group( 8, 'Trolls Anonymous', 'public' ); // the user is banned
bprg_test_add_group( 9, 'Yoga <script>alert(1)</script>', 'public' );

$bprg_base_options = array(
	'bp_registration_groups_display_order'       => 'alphabetical',
	'bp_registration_groups_display_as'          => 3, // radio buttons: registration-only
	'bp_registration_groups_show_private_groups' => 1,
	'bp_registration_groups_require_selection'   => 1, // registration-only
	'bp_registration_groups_autojoin_display'    => 1, // registration-only
	'bp_registration_groups_autojoin_groups'     => array( 1 ),
	'bp_registration_groups_checked_groups'      => array( 2 ),
	'bp_registration_groups_hidden_groups'       => array( 3 ),
);
bprg_test_set_plugin_options( $bprg_base_options );

bprg_assert_true( isset( $GLOBALS['bprg_test']['shortcodes']['bp_registration_groups_join'] ), 'the [bp_registration_groups_join] shortcode is registered' );

// -------------------------------------------------------------------
// Logged out: a log-in prompt, no form, no group names.
// -------------------------------------------------------------------
bprg_test_set_user( 0 );
$_SERVER['REQUEST_URI'] = '/choose-groups/?step=2';
$output                 = bprg_test_do_shortcode( 'bp_registration_groups_join' );

bprg_assert_true( false !== strpos( $output, 'Please log in to choose groups to join.' ), 'logged out: the log-in message renders' );
bprg_assert_true(
	false !== strpos( $output, 'href="http://example.test/wp-login.php?redirect_to=' . rawurlencode( '/choose-groups/?step=2' ) . '"' ),
	'logged out: the log-in link returns the visitor to this exact page, outside the loop too'
);
bprg_assert_true( false === strpos( $output, '<form' ), 'logged out: no form renders' );
bprg_assert_true( false === strpos( $output, 'Book Club' ), 'logged out: no group names render' );

$_SERVER['REQUEST_URI'] = '//evil.example/phish';
bprg_assert_same( 'http://example.test/', bp_registration_groups_join_current_url(), 'an off-site request URI falls back to the home page' );
unset( $_SERVER['REQUEST_URI'] );
bprg_assert_same( 'http://example.test/', bp_registration_groups_join_current_url(), 'a missing request URI falls back to the home page' );

// -------------------------------------------------------------------
// Logged in: the offered list with each group's state.
// -------------------------------------------------------------------
bprg_test_set_user( 42 );
$GLOBALS['bprg_test']['members'][42][4]  = true;
$GLOBALS['bprg_test']['requests'][42][7] = true;
$GLOBALS['bprg_test']['banned'][42][8]   = true;

$output = bprg_test_do_shortcode( 'bp_registration_groups_join' );

bprg_assert_true( false !== strpos( $output, '<h2 class="reg_groups_title" id="reg-groups-join-2-title">Groups</h2>' ), 'the default title renders as the heading' );
bprg_assert_true( false !== strpos( $output, 'Check one or more areas of interest' ), 'the default description renders' );
bprg_assert_true(
	false !== strpos( $output, 'aria-labelledby="reg-groups-join-2-title" aria-describedby="reg-groups-join-2-desc"' ),
	'the fieldset is named by the title and described by the description'
);

bprg_assert_true( 1 === preg_match( '/name="bp_registration_groups_join\[\]" value="2"/', $output ), 'a joinable public group renders as a selectable checkbox' );
bprg_assert_true( 0 === preg_match( '/value="2"[^>]*checked/', $output ), 'Checked by default does not carry over: Book Club is not pre-checked' );
bprg_assert_true( false === strpos( $output, 'type="radio"' ), 'Radio Buttons does not carry over: the list renders checkboxes' );
bprg_assert_true( false !== strpos( $output, '<ul class="reg_groups_list">' ), 'Radio Buttons renders as the plain list' );
bprg_assert_true( false === strpos( $output, 'role="alert"' ), 'Require Group Selection does not carry over: no error on a fresh page' );

bprg_assert_true( false === strpos( $output, 'Announcements' ), 'auto-join groups are not offered (even with Auto-Join Display on)' );
bprg_assert_true( false === strpos( $output, 'Cooking' ), 'per-group hidden groups are not offered' );
bprg_assert_true( false === strpos( $output, 'Hidden Society' ), 'status-hidden groups are not offered' );
bprg_assert_true( false === strpos( $output, 'Trolls Anonymous' ), 'groups the user is banned from are not listed' );

bprg_assert_true(
	1 === preg_match( '/id="reg-groups-join-2-group-4" checked="checked" disabled="disabled" \/><label[^>]*>Cycling <em class="reg_groups_status">\(member\)<\/em>/', $output ),
	'a group the user belongs to renders checked, disabled, and labeled (member)'
);
bprg_assert_true( 0 === preg_match( '/name="bp_registration_groups_join\[\]" value="4"/', $output ), 'a member group is not submittable' );
bprg_assert_true(
	1 === preg_match( '/id="reg-groups-join-2-group-7" checked="checked" disabled="disabled" \/><label[^>]*>Knitters <em class="reg_groups_status">\(request pending\)<\/em>/', $output ),
	'a private group with a pending request renders disabled and labeled (request pending)'
);
bprg_assert_true(
	1 === preg_match( '/value="5" \/><label[^>]*>Secret Garden <em class="reg_groups_status">\(request to join\)<\/em>/', $output ),
	'a private group renders as selectable and labeled (request to join)'
);

bprg_assert_true( false === strpos( $output, '<script>' ), 'group names are escaped' );
bprg_assert_true( false !== strpos( $output, 'Yoga &lt;script&gt;alert(1)&lt;/script&gt;' ), 'the escaped group name renders' );

$bprg_positions = array_map( function ( $name ) use ( $output ) {
	return strpos( $output, $name );
}, array( 'Book Club', 'Cycling', 'Knitters', 'Secret Garden', 'Yoga' ) );
$bprg_sorted = $bprg_positions;
sort( $bprg_sorted );
bprg_assert_same( $bprg_sorted, $bprg_positions, 'Display Order carries over: groups render alphabetically' );

bprg_assert_true( false !== strpos( $output, 'name="bp_registration_groups_join_nonce" value="' . wp_create_nonce( 'bp_registration_groups_join' ) . '"' ), 'the form carries a nonce for the current user' );
bprg_assert_true( false !== strpos( $output, 'name="bp_registration_groups_join_action" value="join"' ), 'the form carries the action marker' );
bprg_assert_true( false !== strpos( $output, 'Join selected groups' ), 'the submit button renders' );
bprg_assert_true( false === strpos( $output, 'name="field_reg_groups' ), 'the form never uses the signup field name' );
bprg_assert_true( false === strpos( $output, 'user_id' ), 'the form carries no user ID' );

// A second instance on the same page gets its own IDs.
$second = bprg_test_do_shortcode( 'bp_registration_groups_join' );
preg_match_all( '/ id="([^"]+)"/', $output, $first_ids );
preg_match_all( '/ id="([^"]+)"/', $second, $second_ids );
bprg_assert_same( array(), array_values( array_intersect( $first_ids[1], $second_ids[1] ) ), 'two instances on one page share no element IDs' );
bprg_assert_same( count( $first_ids[1] ), count( array_unique( $first_ids[1] ) ), 'element IDs within an instance are unique' );

// -------------------------------------------------------------------
// Show Private Groups off: private groups disappear entirely.
// -------------------------------------------------------------------
bprg_test_set_plugin_options( array_merge( $bprg_base_options, array( 'bp_registration_groups_show_private_groups' => 0 ) ) );
$output = bprg_test_do_shortcode( 'bp_registration_groups_join' );

bprg_assert_true( false === strpos( $output, 'Secret Garden' ), 'private off: requestable private groups are not offered' );
bprg_assert_true( false === strpos( $output, 'Knitters' ), 'private off: pending private groups are not listed' );
bprg_assert_true( false !== strpos( $output, 'Book Club' ), 'private off: public groups are still offered' );

// -------------------------------------------------------------------
// Number of Groups to Display and Display Order carry over.
// -------------------------------------------------------------------
bprg_test_set_plugin_options( array_merge( $bprg_base_options, array( 'bp_registration_groups_number_displayed' => 2 ) ) );
$output = bprg_test_do_shortcode( 'bp_registration_groups_join' );

bprg_assert_true( false !== strpos( $output, 'Book Club' ) && false !== strpos( $output, 'Cycling' ), 'limit 2: the first two groups in order render' );
bprg_assert_true( false === strpos( $output, 'Knitters' ) && false === strpos( $output, 'Secret Garden' ), 'limit 2: groups past the limit do not render' );

bprg_test_set_plugin_options( array_merge( $bprg_base_options, array(
	'bp_registration_groups_display_order' => 'newest',
	'bp_registration_groups_display_as'    => 2,
) ) );
$output = bprg_test_do_shortcode( 'bp_registration_groups_join' );

bprg_assert_true( strpos( $output, 'Yoga' ) < strpos( $output, 'Book Club' ), 'newest order: the newest group renders first' );
bprg_assert_true( false !== strpos( $output, '<ul class="reg_groups_list_multiselect">' ), 'Display As "Checkboxes Multiselect" carries over' );

// Legacy request-driven overrides do not reshape the list.
$_REQUEST['num'] = '1';
$_REQUEST['s']   = 'Cycling';
$output          = bprg_test_do_shortcode( 'bp_registration_groups_join' );
bprg_assert_true( false !== strpos( $output, 'Book Club' ) && false !== strpos( $output, 'Secret Garden' ), '?num= and ?s= do not reshape the list' );
unset( $_REQUEST['num'], $_REQUEST['s'] );

// -------------------------------------------------------------------
// Nothing left to join, and nothing offered at all.
// -------------------------------------------------------------------
bprg_test_set_plugin_options( $bprg_base_options );
foreach ( array( 2, 5, 9 ) as $bprg_group_id ) {
	$GLOBALS['bprg_test']['members'][42][ $bprg_group_id ] = true;
}
$output = bprg_test_do_shortcode( 'bp_registration_groups_join' );

bprg_assert_true( false !== strpos( $output, 'You have joined or requested every group listed here.' ), 'all joined: the member is told there is nothing left' );
bprg_assert_true( false === strpos( $output, 'Join selected groups' ), 'all joined: no submit button' );
bprg_assert_true( false === strpos( $output, 'bp_registration_groups_join_nonce' ), 'all joined: no nonce' );

bprg_test_set_plugin_options( array_merge( $bprg_base_options, array( 'bp_registration_groups_hidden_groups' => array( 2, 3, 4, 5, 7, 8, 9 ) ) ) );
$output = bprg_test_do_shortcode( 'bp_registration_groups_join' );

bprg_assert_true( false !== strpos( $output, 'No groups are available at this time.' ), 'nothing offered: the no-groups message renders' );
bprg_assert_true( false === strpos( $output, '<form' ), 'nothing offered: no form renders' );

// Custom title and description carry over.
bprg_test_set_plugin_options( array_merge( $bprg_base_options, array(
	'bp_registration_groups_title'       => 'Find your <people>',
	'bp_registration_groups_description' => 'Pick & choose',
) ) );
$output = bprg_test_do_shortcode( 'bp_registration_groups_join' );

bprg_assert_true( false !== strpos( $output, 'Find your &lt;people&gt;</h2>' ), 'a custom title carries over, escaped' );
bprg_assert_true( false !== strpos( $output, 'Pick &amp; choose</p>' ), 'a custom description carries over, escaped' );

bprg_test_done();
