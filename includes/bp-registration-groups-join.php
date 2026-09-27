<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
* Join Groups shortcode: [bp_registration_groups_join]
*
* Lets a logged-in member review the groups the registration form offers and
* join them after registration: public groups are joined directly, private
* groups (offered only when "Show Private Groups" is on) get a membership
* request. It is a separate, current-user-only flow; the signup and
* activation hooks in bp-registration-groups.php are not involved.
*
* Eligibility is exactly the registration form's (see
* bp_registration_groups_get_valid_submitted_group_ids()), and only display
* settings carry over. The registration-only settings do not apply: Require
* Group Selection, the Radio Buttons display (rendered as checkboxes, since
* "pick one at signup" has no meaning on a page members revisit), Checked by
* default, and Auto-Join Display. Auto-join groups are never offered, just as
* they are never selectable on the form.
*/

add_shortcode( 'bp_registration_groups_join', 'bp_registration_groups_join_shortcode' );

/**
* bp_registration_groups_join_enqueue_styles()
*
* Load the plugin stylesheet on singular content that contains the
* shortcode, so it prints in the head. The shortcode also enqueues it while
* rendering, as a safety net for other placements (widgets, templates).
* Runs after bp_registration_groups_enqueue_scripts() registers the handle.
*/
add_action( 'wp_enqueue_scripts', 'bp_registration_groups_join_enqueue_styles', 11 );
function bp_registration_groups_join_enqueue_styles() {
	$post = get_post();

	if ( is_singular() && $post instanceof WP_Post && has_shortcode( $post->post_content, 'bp_registration_groups_join' ) ) {
		wp_enqueue_style( 'bp_registration_groups_styles' );
	}
}

/**
* bp_registration_groups_join_get_offered_sections()
*
* The groups the shortcode offers, organized like the registration form: one
* entry per configured Group Section (title, description, groups), or a
* single untitled entry holding the global list when no sections exist. Each
* entry's 'groups' is an ordered list of group objects.
*
* The global list honors Display Order and Number of Groups to Display, as the
* form does; sections ignore the limit and keep each group in the first
* section that lists it. Every group passes the form's eligibility rules.
*/
function bp_registration_groups_join_get_offered_sections() {
	$sections = bp_registration_groups_get_sections();

	if ( empty( $sections ) ) {
		$options      = get_option( 'bp_registration_groups_option_handle' );
		$number       = isset( $options['bp_registration_groups_number_displayed'] ) ? absint( $options['bp_registration_groups_number_displayed'] ) : 0;
		$excluded_ids = array_values( array_unique( array_merge(
			bp_registration_groups_get_id_list_option( 'bp_registration_groups_hidden_groups' ),
			bp_registration_groups_get_id_list_option( 'bp_registration_groups_autojoin_groups' )
		) ) );

		// groups_get_groups() never reads the query string, unlike the
		// bp_has_groups() template loop, so ?num= and ?s= cannot reshape it.
		$query_args = array(
			'type'        => bp_registration_groups_get_display_order(),
			'per_page'    => $number > 0 ? $number : null,
			'page'        => $number > 0 ? 1 : null,
			'show_hidden' => false,
			'status'      => bp_registration_groups_allowed_statuses(),
		);
		if ( ! empty( $excluded_ids ) ) {
			$query_args['exclude'] = $excluded_ids;
		}

		$queried = groups_get_groups( $query_args );
		$by_id   = array();

		foreach ( ( ! empty( $queried['groups'] ) ? $queried['groups'] : array() ) as $group ) {
			$by_id[ absint( $group->id ) ] = $group;
		}

		// The same eligibility check a submission gets; it also covers
		// BuddyPress versions without the 'status' or 'exclude' arguments.
		$groups = array();
		foreach ( bp_registration_groups_get_valid_submitted_group_ids( array_keys( $by_id ) ) as $group_id ) {
			$groups[] = $by_id[ $group_id ];
		}

		return empty( $groups ) ? array() : array(
			array(
				'title'       => '',
				'description' => '',
				'groups'      => $groups,
			),
		);
	}

	$offered  = array();
	$seen_ids = array();

	foreach ( $sections as $section ) {
		$groups = array();

		foreach ( bp_registration_groups_get_valid_submitted_group_ids( $section['groups'] ) as $group_id ) {
			if ( isset( $seen_ids[ $group_id ] ) ) {
				continue;
			}
			$seen_ids[ $group_id ] = true;

			$groups[] = groups_get_group( $group_id );
		}

		if ( ! empty( $groups ) ) {
			$offered[] = array(
				'title'       => $section['title'],
				'description' => $section['description'],
				'groups'      => $groups,
			);
		}
	}

	return $offered;
}

/**
* bp_registration_groups_join_get_group_state()
*
* Where the user stands with an offered group:
*
* - 'member'      already a member
* - 'pending'     a membership request is awaiting approval (private groups)
* - 'joinable'    a public group the user may join
* - 'requestable' a private group the user may request to join
* - 'unavailable' anything else, e.g. the user is banned from the group
*
* Joinability comes from BuddyPress's own capability checks, so bans and any
* plugin filtering 'bp_user_can' are respected.
*/
function bp_registration_groups_join_get_group_state( $group, $user_id ) {
	if ( empty( $group->id ) || ! $user_id ) {
		return 'unavailable';
	}

	if ( groups_is_user_member( $user_id, $group->id ) ) {
		return 'member';
	}

	if ( 'private' === $group->status ) {
		if ( groups_check_for_membership_request( $user_id, $group->id ) ) {
			return 'pending';
		}

		return bp_user_can( $user_id, 'groups_request_membership', array( 'group_id' => $group->id ) ) ? 'requestable' : 'unavailable';
	}

	if ( 'public' === $group->status ) {
		return bp_user_can( $user_id, 'groups_join_group', array( 'group_id' => $group->id ) ) ? 'joinable' : 'unavailable';
	}

	return 'unavailable';
}

/**
* bp_registration_groups_join_selected_groups()
*
* Join (or request) the submitted groups for the current user, and report
* what happened. Only ever acts on the logged-in user: no user ID is read from
* the request. Submitted IDs the shortcode does not offer are dropped without
* being looked up for display, so the report cannot name hidden groups.
*
* Returns an array with 'joined', 'requested', 'member', 'pending', and
* 'unavailable' lists of group objects, a 'skipped' count of IDs that were not
* offered, and an 'error' code ('' on success, 'logged_out', or 'empty').
*/
function bp_registration_groups_join_selected_groups( $submitted_ids ) {
	$results = array(
		'joined'      => array(),
		'requested'   => array(),
		'member'      => array(),
		'pending'     => array(),
		'unavailable' => array(),
		'skipped'     => 0,
		'error'       => '',
	);

	$user_id = get_current_user_id();

	if ( ! $user_id ) {
		$results['error'] = 'logged_out';
		return $results;
	}

	$group_ids = bp_registration_groups_clean_id_list( $submitted_ids );

	if ( empty( $group_ids ) ) {
		$results['error'] = 'empty';
		return $results;
	}

	$valid_group_ids    = bp_registration_groups_get_valid_submitted_group_ids( $group_ids );
	$results['skipped'] = count( $group_ids ) - count( $valid_group_ids );

	foreach ( $valid_group_ids as $group_id ) {
		$group = groups_get_group( $group_id );

		switch ( bp_registration_groups_join_get_group_state( $group, $user_id ) ) {
			case 'member':
				$results['member'][] = $group;
				break;

			case 'pending':
				$results['pending'][] = $group;
				break;

			case 'joinable':
				$results[ groups_join_group( $group_id, $user_id ) ? 'joined' : 'unavailable' ][] = $group;
				break;

			case 'requestable':
				groups_send_membership_request( array(
					'user_id'  => $user_id,
					'group_id' => $group_id,
				) );

				// BuddyPress turns a request for a group the user was already
				// invited to into a membership, so report what actually
				// happened rather than trusting the return value.
				if ( groups_is_user_member( $user_id, $group_id ) ) {
					$results['joined'][] = $group;
				} elseif ( groups_check_for_membership_request( $user_id, $group_id ) ) {
					$results['requested'][] = $group;
				} else {
					$results['unavailable'][] = $group;
				}
				break;

			default:
				$results['unavailable'][] = $group;
		}
	}

	return $results;
}

/**
* bp_registration_groups_join_results()
*
* Hold the outcome of this request's shortcode submission so the shortcode
* can report it while rendering. Pass an array to store it; call with no
* argument to read it (null when nothing was submitted).
*/
function bp_registration_groups_join_results( $results = null ) {
	static $stored = null;

	if ( null !== $results ) {
		$stored = $results;
	}

	return $stored;
}

/**
* bp_registration_groups_join_handle_submission()
*
* Process a shortcode form submission before any output is sent. The form
* posts back to the page it is on; this verifies the logged-in user and the
* nonce, then joins/requests the selected groups for that user only.
*/
add_action( 'template_redirect', 'bp_registration_groups_join_handle_submission' );
function bp_registration_groups_join_handle_submission() {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- only detects the shortcode form; the nonce is verified below before anything changes.
	if ( ! isset( $_POST['bp_registration_groups_join_action'] ) ) {
		return;
	}

	// Logged-out visitors are shown the log-in message instead of the form.
	if ( ! is_user_logged_in() ) {
		return;
	}

	$nonce = ( isset( $_POST['bp_registration_groups_join_nonce'] ) && is_string( $_POST['bp_registration_groups_join_nonce'] ) ) ? sanitize_text_field( wp_unslash( $_POST['bp_registration_groups_join_nonce'] ) ) : '';

	if ( ! wp_verify_nonce( $nonce, 'bp_registration_groups_join' ) ) {
		bp_registration_groups_join_results( array( 'error' => 'expired' ) );
		return;
	}

	// Parsed strictly by bp_registration_groups_clean_id_list(): only plain
	// digit strings survive, anything else is dropped.
	$submitted_ids = isset( $_POST['bp_registration_groups_join'] ) ? (array) wp_unslash( $_POST['bp_registration_groups_join'] ) : array();

	bp_registration_groups_join_results( bp_registration_groups_join_selected_groups( $submitted_ids ) );
}

/**
* bp_registration_groups_join_result_messages()
*
* Turn a submission outcome into escaped, translated messages. Returns an
* array with 'error' (a single message or '') and 'notices' (a list of
* per-group messages).
*/
function bp_registration_groups_join_result_messages( $results ) {
	$messages = array(
		'error'   => '',
		'notices' => array(),
	);

	$error = isset( $results['error'] ) ? $results['error'] : '';

	if ( 'expired' === $error ) {
		/* translators: error shown by the join groups shortcode when its security token is missing or expired */
		$messages['error'] = esc_html__( 'The form has expired. Please try again.', 'buddypress-registration-groups-1' );
		return $messages;
	}

	if ( 'empty' === $error ) {
		/* translators: error shown by the join groups shortcode when it is submitted with no group selected */
		$messages['error'] = esc_html__( 'Please select at least one group to join.', 'buddypress-registration-groups-1' );
		return $messages;
	}

	if ( '' !== $error ) {
		return $messages;
	}

	$formats = array(
		/* translators: %s: group name. Shown by the join groups shortcode after the member joined a public group */
		'joined'      => __( 'You joined %s.', 'buddypress-registration-groups-1' ),
		/* translators: %s: group name. Shown by the join groups shortcode after the member requested to join a private group */
		'requested'   => __( 'You requested to join %s. A group administrator will review your request.', 'buddypress-registration-groups-1' ),
		/* translators: %s: group name. Shown by the join groups shortcode when the member selected a group they already belong to */
		'member'      => __( 'You are already a member of %s.', 'buddypress-registration-groups-1' ),
		/* translators: %s: group name. Shown by the join groups shortcode when the member already has a pending request for a private group */
		'pending'     => __( 'Your request to join %s is still awaiting approval.', 'buddypress-registration-groups-1' ),
		/* translators: %s: group name. Shown by the join groups shortcode when a selected group could not be joined, e.g. because the member is banned from it */
		'unavailable' => __( 'You could not join %s.', 'buddypress-registration-groups-1' ),
	);

	foreach ( $formats as $key => $format ) {
		foreach ( ( isset( $results[ $key ] ) ? (array) $results[ $key ] : array() ) as $group ) {
			$messages['notices'][] = array(
				'type' => $key,
				'text' => esc_html( sprintf( $format, $group->name ) ),
			);
		}
	}

	if ( ! empty( $results['skipped'] ) ) {
		$skipped = absint( $results['skipped'] );

		$messages['notices'][] = array(
			'type' => 'skipped',
			/* translators: %d: number of submitted selections that are not offered by the join groups shortcode (nonexistent, hidden, or otherwise unavailable groups) */
			'text' => esc_html( sprintf( _n( '%d selection is not available and was skipped.', '%d selections are not available and were skipped.', $skipped, 'buddypress-registration-groups-1' ), $skipped ) ),
		);
	}

	return $messages;
}

/**
* bp_registration_groups_join_shortcode()
*
* Render [bp_registration_groups_join]. Logged-out visitors get a log-in
* prompt; members get the offered groups with their current status, a form
* to join the ones they have not joined yet, and the outcome of their last
* submission.
*/
function bp_registration_groups_join_shortcode( $atts = array() ) {
	// Each instance gets its own ID prefix so a page can hold the shortcode
	// more than once without duplicate IDs.
	static $instance = 0;
	$instance++;

	wp_enqueue_style( 'bp_registration_groups_styles' );

	if ( ! is_user_logged_in() ) {
		return sprintf(
			'<div class="reg_groups_join"><p class="reg_groups_join_login">%1$s <a href="%2$s">%3$s</a></p></div>',
			/* translators: shown by the join groups shortcode to visitors who are not logged in */
			esc_html__( 'Please log in to choose groups to join.', 'buddypress-registration-groups-1' ),
			esc_url( wp_login_url( (string) get_permalink() ) ),
			/* translators: link text shown by the join groups shortcode to visitors who are not logged in */
			esc_html__( 'Log in', 'buddypress-registration-groups-1' )
		);
	}

	$user_id = get_current_user_id();
	$prefix  = 'reg-groups-join-' . $instance;
	$options = get_option( 'bp_registration_groups_option_handle' );

	// "Display As": the scrollable box (the default) or a plain list. Radio
	// Buttons is a registration-only mode and renders as the plain list.
	$list_class = ( isset( $options['bp_registration_groups_display_as'] ) && '2' != $options['bp_registration_groups_display_as'] ) ? 'reg_groups_list' : 'reg_groups_list_multiselect';

	// Report the outcome of this request's submission. Every instance shows
	// it: some plugins render post content early (e.g. for meta
	// descriptions), so "only the first instance" could be an invisible one.
	$messages = array(
		'error'   => '',
		'notices' => array(),
	);
	if ( null !== bp_registration_groups_join_results() ) {
		$messages = bp_registration_groups_join_result_messages( bp_registration_groups_join_results() );
	}

	// Resolve every offered group's status up front, so groups the member
	// cannot join (e.g. banned) and sections left empty are not rendered.
	$sections   = array();
	$selectable = 0;

	foreach ( bp_registration_groups_join_get_offered_sections() as $section ) {
		$entries = array();

		foreach ( $section['groups'] as $group ) {
			$state = bp_registration_groups_join_get_group_state( $group, $user_id );

			if ( 'unavailable' === $state ) {
				continue;
			}

			if ( 'joinable' === $state || 'requestable' === $state ) {
				$selectable++;
			}

			$entries[] = array(
				'group' => $group,
				'state' => $state,
			);
		}

		if ( ! empty( $entries ) ) {
			$section['entries'] = $entries;
			$sections[]         = $section;
		}
	}

	$status_labels = array(
		/* translators: label shown by the join groups shortcode next to groups the member already belongs to */
		'member'      => __( '(member)', 'buddypress-registration-groups-1' ),
		/* translators: label shown by the join groups shortcode next to private groups the member has asked to join */
		'pending'     => __( '(request pending)', 'buddypress-registration-groups-1' ),
		/* translators: label shown by the join groups shortcode next to private groups, which are joined by membership request */
		'requestable' => __( '(request to join)', 'buddypress-registration-groups-1' ),
	);

	ob_start();
	?>
	<div class="reg_groups_join" id="<?php echo esc_attr( $prefix ); ?>">
		<h2 class="reg_groups_title" id="<?php echo esc_attr( $prefix . '-title' ); ?>"><?php echo esc_html( bp_registration_groups_get_title() ); ?></h2>
		<p class="reg_groups_description" id="<?php echo esc_attr( $prefix . '-desc' ); ?>"><?php echo esc_html( bp_registration_groups_get_description() ); ?></p>
		<?php if ( '' !== $messages['error'] ) : ?>
		<div class="error reg_groups_error" role="alert"><?php echo $messages['error']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by bp_registration_groups_join_result_messages(). ?></div>
		<?php endif; ?>
		<?php if ( ! empty( $messages['notices'] ) ) : ?>
		<div class="reg_groups_join_results" role="status">
			<ul>
				<?php foreach ( $messages['notices'] as $notice ) : ?>
				<li class="reg_groups_join_result reg_groups_join_result_<?php echo esc_attr( $notice['type'] ); ?>"><?php echo $notice['text']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by bp_registration_groups_join_result_messages(). ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php endif; ?>
		<?php if ( empty( $sections ) ) : ?>
		<p class="reg_groups_none">
			<?php
			/* translators: text that is displayed on the buddypress user registration form when there are no groups that can be displayed */
			esc_html_e( 'No groups are available at this time.', 'buddypress-registration-groups-1' );
			?>
		</p>
		<?php else : ?>
		<form class="reg_groups_join_form" method="post">
			<?php foreach ( $sections as $section_index => $section ) : ?>
			<?php
			// Name each fieldset after its section title (falling back to the
			// main title) and describe it with the section description (the
			// main description for the single global list).
			$section_title_id = '' !== $section['title'] ? $prefix . '-section-title-' . $section_index : '';
			$section_desc_id  = '' !== $section['description'] ? $prefix . '-section-desc-' . $section_index : '';
			$labelledby       = '' !== $section_title_id ? $section_title_id : $prefix . '-title';
			$describedby      = '' !== $section_desc_id ? $section_desc_id : ( '' === $section['title'] ? $prefix . '-desc' : '' );
			?>
			<fieldset class="reg_groups_fieldset<?php echo '' !== $section['title'] || '' !== $section['description'] ? ' reg_groups_section' : ''; ?>" aria-labelledby="<?php echo esc_attr( $labelledby ); ?>"<?php if ( '' !== $describedby ) : ?> aria-describedby="<?php echo esc_attr( $describedby ); ?>"<?php endif; ?>>
				<?php if ( '' !== $section_title_id ) : ?>
				<h3 class="reg_groups_section_title" id="<?php echo esc_attr( $section_title_id ); ?>"><?php echo esc_html( $section['title'] ); ?></h3>
				<?php endif; ?>
				<?php if ( '' !== $section_desc_id ) : ?>
				<p class="reg_groups_section_description" id="<?php echo esc_attr( $section_desc_id ); ?>"><?php echo esc_html( $section['description'] ); ?></p>
				<?php endif; ?>
				<ul class="<?php echo esc_attr( $list_class ); ?>">
					<?php foreach ( $section['entries'] as $entry ) : ?>
					<?php $input_id = $prefix . '-group-' . absint( $entry['group']->id ); ?>
					<?php if ( 'member' === $entry['state'] || 'pending' === $entry['state'] ) : ?>
					<li class="reg_groups_item reg_groups_item_locked reg_groups_item_<?php echo esc_attr( $entry['state'] ); ?>">
						<input class="reg_groups_group_checkbox" type="checkbox" id="<?php echo esc_attr( $input_id ); ?>" checked="checked" disabled="disabled" /><label class="reg_groups_group_label" for="<?php echo esc_attr( $input_id ); ?>"><?php echo esc_html( $entry['group']->name ); ?> <em class="reg_groups_status"><?php echo esc_html( $status_labels[ $entry['state'] ] ); ?></em></label>
					</li>
					<?php else : ?>
					<li class="reg_groups_item">
						<input class="reg_groups_group_checkbox" type="checkbox" id="<?php echo esc_attr( $input_id ); ?>" name="bp_registration_groups_join[]" value="<?php echo esc_attr( absint( $entry['group']->id ) ); ?>" /><label class="reg_groups_group_label" for="<?php echo esc_attr( $input_id ); ?>"><?php echo esc_html( $entry['group']->name ); ?><?php if ( 'requestable' === $entry['state'] ) : ?> <em class="reg_groups_status"><?php echo esc_html( $status_labels['requestable'] ); ?></em><?php endif; ?></label>
					</li>
					<?php endif; ?>
					<?php endforeach; ?>
				</ul>
			</fieldset>
			<?php endforeach; ?>
			<?php if ( $selectable > 0 ) : ?>
			<input type="hidden" name="bp_registration_groups_join_action" value="join" />
			<input type="hidden" name="bp_registration_groups_join_nonce" value="<?php echo esc_attr( wp_create_nonce( 'bp_registration_groups_join' ) ); ?>" />
			<p class="reg_groups_join_submit">
				<?php // 'wp-element-button' picks up block themes' button styles; 'button' classic themes'. ?>
				<button type="submit" class="button wp-element-button">
					<?php
					/* translators: submit button of the join groups shortcode */
					esc_html_e( 'Join selected groups', 'buddypress-registration-groups-1' );
					?>
				</button>
			</p>
			<?php else : ?>
			<p class="reg_groups_none">
				<?php
				/* translators: shown by the join groups shortcode when the member already belongs to (or has requested) every group it lists */
				esc_html_e( 'You have joined or requested every group listed here.', 'buddypress-registration-groups-1' );
				?>
			</p>
			<?php endif; ?>
		</form>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}
