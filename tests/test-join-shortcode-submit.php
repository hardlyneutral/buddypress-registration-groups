<?php
/**
 * Tests for [bp_registration_groups_join] submissions
 * (bp_registration_groups_join_handle_submission() on 'template_redirect'):
 *
 * - only the logged-in user's memberships change, whatever the request says;
 * - invalid, hidden, private-when-off, auto-join, and nonexistent IDs cannot
 *   be joined by tampering, and are never named in the report;
 * - public groups are joined, private groups get a membership request;
 * - existing members and pending requests are handled idempotently;
 * - bans, other plugins' capability denials, and join failures are reported;
 * - the nonce is required and bound to the user;
 * - the signup flow's field and hooks are not involved.
 */

require __DIR__ . '/bootstrap.php';

bprg_test_add_group( 1, 'Announcements', 'public' );  // auto-join
bprg_test_add_group( 2, 'Book Club', 'public' );
bprg_test_add_group( 3, 'Cooking', 'public' );        // per-group hidden
bprg_test_add_group( 4, 'Cycling', 'public' );        // the user is a member
bprg_test_add_group( 5, 'Secret Garden', 'private' );
bprg_test_add_group( 6, 'Hidden Society', 'hidden' );
bprg_test_add_group( 8, 'Trolls Anonymous', 'public' ); // the user is banned
bprg_test_add_group( 10, 'Gardening', 'public' );
bprg_test_add_group( 13, 'Invited Few', 'private' );  // the user holds an invitation
bprg_test_add_group( 14, 'Book <b>Nook</b>', 'public' );

bprg_test_set_plugin_options( array(
	'bp_registration_groups_show_private_groups' => 1,
	'bp_registration_groups_autojoin_groups'     => array( 1 ),
	'bp_registration_groups_hidden_groups'       => array( 3 ),
) );

$GLOBALS['bprg_test']['members'][42][4]  = true;
$GLOBALS['bprg_test']['banned'][42][8]   = true;
$GLOBALS['bprg_test']['invites'][42][13] = true;

function bprg_test_submit( $post ) {
	$_POST = $post;
	do_action( 'template_redirect' );
	return bp_registration_groups_join_results();
}

function bprg_test_ids( $groups ) {
	return array_map( function ( $group ) {
		return (int) $group->id;
	}, $groups );
}

bprg_assert_true( bprg_test_hook_has( 'template_redirect', 'bp_registration_groups_join_handle_submission' ), 'the submission handler is attached to template_redirect' );

// -------------------------------------------------------------------
// Requests that must not change anything.
// -------------------------------------------------------------------
bprg_test_set_user( 42 );

bprg_test_submit( array( 'bp_registration_groups_join' => array( '2' ) ) );
bprg_assert_same( null, bp_registration_groups_join_results(), 'without the action marker the handler does nothing' );

$_GET = array( 'bp_registration_groups_join_action' => 'join', 'bp_registration_groups_join' => array( '2' ) );
bprg_test_submit( array() );
bprg_assert_same( null, bp_registration_groups_join_results(), 'query-string parameters are ignored' );
$_GET = array();

// Signup-form fields do not trigger the shortcode flow.
bprg_test_submit( array( 'field_reg_groups' => array( '2' ), 'signup_submit' => '1' ) );
bprg_assert_same( null, bp_registration_groups_join_results(), 'a signup-form submission does not trigger the shortcode flow' );

bprg_test_set_user( 0 );
bprg_test_submit( array(
	'bp_registration_groups_join_action' => 'join',
	'bp_registration_groups_join_nonce'  => wp_create_nonce( 'bp_registration_groups_join' ),
	'bp_registration_groups_join'        => array( '2' ),
) );
bprg_assert_same( null, bp_registration_groups_join_results(), 'logged out: the submission is ignored' );

bprg_test_set_user( 42 );
$results = bprg_test_submit( array(
	'bp_registration_groups_join_action' => 'join',
	'bp_registration_groups_join_nonce'  => 'forged',
	'bp_registration_groups_join'        => array( '2' ),
) );
bprg_assert_same( 'expired', $results['error'], 'a bad nonce is rejected' );

// A nonce minted for another user does not verify for this one.
bprg_test_set_user( 7 );
$bprg_other_nonce = wp_create_nonce( 'bp_registration_groups_join' );
bprg_test_set_user( 42 );
$results = bprg_test_submit( array(
	'bp_registration_groups_join_action' => 'join',
	'bp_registration_groups_join_nonce'  => $bprg_other_nonce,
	'bp_registration_groups_join'        => array( '2' ),
) );
bprg_assert_same( 'expired', $results['error'], "another user's nonce is rejected" );

$results = bprg_test_submit( array(
	'bp_registration_groups_join_action' => 'join',
	'bp_registration_groups_join_nonce'  => array( wp_create_nonce( 'bp_registration_groups_join' ) ),
	'bp_registration_groups_join'        => array( '2' ),
) );
bprg_assert_same( 'expired', $results['error'], 'a nonce submitted as an array is rejected' );

bprg_assert_same( array(), $GLOBALS['bprg_test']['joins'], 'no membership changed during the rejected requests' );
bprg_assert_same( array(), $GLOBALS['bprg_test']['requests_sent'], 'no membership request was sent during the rejected requests' );

$output = bprg_test_do_shortcode( 'bp_registration_groups_join' );
bprg_assert_true( 1 === preg_match( '/<div class="error reg_groups_error" role="alert">The form has expired\. Please try again\.<\/div>/', $output ), 'an expired form is reported as an alert' );

$results = bprg_test_submit( array(
	'bp_registration_groups_join_action' => 'join',
	'bp_registration_groups_join_nonce'  => wp_create_nonce( 'bp_registration_groups_join' ),
) );
bprg_assert_same( 'empty', $results['error'], 'a submission with nothing selected is reported' );
$output = bprg_test_do_shortcode( 'bp_registration_groups_join' );
bprg_assert_true( false !== strpos( $output, 'Please select at least one group to join.' ), 'the nothing-selected message renders' );

// -------------------------------------------------------------------
// A tampered submission: eligible groups are processed for the current
// user only; everything else is dropped.
// -------------------------------------------------------------------
$results = bprg_test_submit( array(
	'bp_registration_groups_join_action' => 'join',
	'bp_registration_groups_join_nonce'  => wp_create_nonce( 'bp_registration_groups_join' ),
	'bp_registration_groups_join'        => array( '2', '5', '4', '3', '6', '1', '99', 'abc', '-2', '2.5', array( '10' ) ),
	'user_id'                            => '7',
	'bp_registration_groups_join_user'   => '7',
) );

bprg_assert_same( '', $results['error'], 'a valid submission has no error' );
bprg_assert_same( array( 2 ), bprg_test_ids( $results['joined'] ), 'the public group is joined' );
bprg_assert_same( array( 5 ), bprg_test_ids( $results['requested'] ), 'the private group gets a membership request' );
bprg_assert_same( array( 4 ), bprg_test_ids( $results['member'] ), 'an existing membership is reported, not redone' );
bprg_assert_same( 4, $results['skipped'], 'hidden, status-hidden, auto-join, and nonexistent IDs are skipped (malformed values are dropped outright)' );

bprg_assert_same( array( array( 2, 42 ) ), $GLOBALS['bprg_test']['joins'], 'exactly one join happened: the public group, for the logged-in user' );
bprg_assert_same( array( array( 5, 42 ) ), $GLOBALS['bprg_test']['requests_sent'], 'exactly one request was sent: the private group, for the logged-in user' );
bprg_assert_true( empty( $GLOBALS['bprg_test']['members'][7] ) && empty( $GLOBALS['bprg_test']['requests'][7] ), 'the user ID in the request changed nothing for user 7' );
bprg_assert_true( empty( $GLOBALS['bprg_test']['members'][42][5] ), 'a private group is never joined directly' );
bprg_assert_true( empty( $GLOBALS['bprg_test']['deprecated_request_calls'] ), 'membership requests use the array signature' );

$output = bprg_test_do_shortcode( 'bp_registration_groups_join' );
bprg_assert_true( false !== strpos( $output, '<div class="reg_groups_join_results" role="status">' ), 'the outcome renders as a status region' );
bprg_assert_true( false !== strpos( $output, '<li class="reg_groups_join_result reg_groups_join_result_joined">You joined Book Club.</li>' ), 'the join is reported' );
bprg_assert_true( false !== strpos( $output, 'You requested to join Secret Garden. A group administrator will review your request.' ), 'the request is reported' );
bprg_assert_true( false !== strpos( $output, 'You are already a member of Cycling.' ), 'the existing membership is reported' );
bprg_assert_true( false !== strpos( $output, '4 selections are not available and were skipped.' ), 'skipped IDs are counted' );
foreach ( array( 'Cooking', 'Hidden Society', 'Announcements' ) as $bprg_name ) {
	bprg_assert_true( false === strpos( $output, $bprg_name ), "the report never names {$bprg_name}" );
}
bprg_assert_true(
	1 === preg_match( '/Book Club <em class="reg_groups_status">\(member\)<\/em>/', $output )
	&& 1 === preg_match( '/Secret Garden <em class="reg_groups_status">\(request pending\)<\/em>/', $output ),
	'the list reflects the new membership and the pending request'
);

// -------------------------------------------------------------------
// Resubmitting (e.g. a page refresh) is idempotent.
// -------------------------------------------------------------------
$results = bprg_test_submit( array(
	'bp_registration_groups_join_action' => 'join',
	'bp_registration_groups_join_nonce'  => wp_create_nonce( 'bp_registration_groups_join' ),
	'bp_registration_groups_join'        => array( '2', '5', '4' ),
) );

bprg_assert_same( array( 2, 4 ), bprg_test_ids( $results['member'] ), 'resubmitted: both public groups are reported as memberships' );
bprg_assert_same( array( 5 ), bprg_test_ids( $results['pending'] ), 'resubmitted: the private group is reported as pending' );
bprg_assert_same( array(), array_merge( $results['joined'], $results['requested'] ), 'resubmitted: nothing new is joined or requested' );
bprg_assert_same( 1, count( $GLOBALS['bprg_test']['joins'] ), 'resubmitted: no second join call' );
bprg_assert_same( 1, count( $GLOBALS['bprg_test']['requests_sent'] ), 'resubmitted: no second membership request' );

$output = bprg_test_do_shortcode( 'bp_registration_groups_join' );
bprg_assert_true( false !== strpos( $output, 'Your request to join Secret Garden is still awaiting approval.' ), 'the pending request is reported' );

// -------------------------------------------------------------------
// Groups the user cannot join are reported, not forced.
// -------------------------------------------------------------------
$GLOBALS['bprg_test']['cap_denied'][] = array( 'groups_join_group', 10 );
$results = bp_registration_groups_join_selected_groups( array( '8', '10' ) );

bprg_assert_same( array( 8, 10 ), bprg_test_ids( $results['unavailable'] ), 'a ban and another plugin\'s capability denial make groups unavailable' );
bprg_assert_same( 1, count( $GLOBALS['bprg_test']['joins'] ), 'no join is attempted without the capability' );

$GLOBALS['bprg_test']['cap_denied']    = array();
$GLOBALS['bprg_test']['join_failures'] = array( 10 );
$results = bp_registration_groups_join_selected_groups( array( '10' ) );

bprg_assert_same( array( 10 ), bprg_test_ids( $results['unavailable'] ), 'a failed join is reported as unavailable' );
bprg_assert_true( empty( $GLOBALS['bprg_test']['members'][42][10] ), 'a failed join leaves no membership' );
$GLOBALS['bprg_test']['join_failures'] = array();

$messages = bp_registration_groups_join_result_messages( $results );
bprg_assert_same( 'You could not join Gardening.', $messages['notices'][0]['text'], 'an unavailable group is reported by name' );

// A request for a private group the user was invited to becomes a membership.
$results = bp_registration_groups_join_selected_groups( array( '13' ) );
bprg_assert_same( array( 13 ), bprg_test_ids( $results['joined'] ), 'an invited private group is reported as joined' );
bprg_assert_true( ! empty( $GLOBALS['bprg_test']['members'][42][13] ), 'the invitation turned the request into a membership' );

// Group names in the report are escaped.
bp_registration_groups_join_selected_groups( array( '14' ) );
$messages = bp_registration_groups_join_result_messages( array( 'joined' => array( groups_get_group( 14 ) ), 'skipped' => 1 ) );
bprg_assert_same( 'You joined Book &lt;b&gt;Nook&lt;/b&gt;.', $messages['notices'][0]['text'], 'group names in the report are escaped' );
bprg_assert_same( '1 selection is not available and was skipped.', $messages['notices'][1]['text'], 'a single skipped ID uses the singular message' );

// -------------------------------------------------------------------
// The signup flow is not involved.
// -------------------------------------------------------------------
$_POST = array(
	'bp_registration_groups_join_action' => 'join',
	'bp_registration_groups_join'        => array( '2' ),
);
bprg_assert_same( array(), bp_registration_groups_get_submitted_group_ids(), 'the signup reader does not see shortcode selections' );
bprg_assert_same( array( 'field_1' => 'x' ), apply_filters( 'bp_signup_usermeta', array( 'field_1' => 'x' ) ), 'signup meta is unaffected by a shortcode submission' );

bprg_test_set_user( 0 );
bprg_assert_same( 'logged_out', bp_registration_groups_join_selected_groups( array( '2' ) )['error'], 'processing refuses to run without a logged-in user' );

$_POST = array();

bprg_test_done();
