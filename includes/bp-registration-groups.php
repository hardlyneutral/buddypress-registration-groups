<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
* Enqueue plugin scripts and styles
*/

add_action( 'wp_enqueue_scripts', 'bp_registration_groups_enqueue_scripts' );
function bp_registration_groups_enqueue_scripts() {
	wp_register_style( 'bp_registration_groups_styles', plugins_url( '/styles.css', __FILE__ ), array(), BP_REGISTRATION_GROUPS_VERSION );

	// The group list only appears on the registration page. The render
	// function also enqueues the registered handle itself, as a safety net
	// for setups that show the signup form elsewhere.
	if ( function_exists( 'bp_is_register_page' ) && bp_is_register_page() ) {
		wp_enqueue_style( 'bp_registration_groups_styles' );
	}
}

/**
* bp_registration_groups_allowed_statuses()
*
* The group statuses the site owner allows on the registration form:
* public groups always, private groups only when the option is enabled.
* Hidden groups are never offered.
*/
function bp_registration_groups_allowed_statuses() {
	$options = get_option( 'bp_registration_groups_option_handle' );

	if ( isset( $options['bp_registration_groups_show_private_groups'] ) && '1' == $options['bp_registration_groups_show_private_groups'] ) {
		return array( 'public', 'private' );
	}

	return array( 'public' );
}

/**
* bp_registration_groups_get_id_list_option()
*
* Read one of the per-group ID lists (hidden, checked-by-default, auto-join)
* from the options array as a clean list of group IDs.
*/
function bp_registration_groups_get_id_list_option( $key ) {
	$options = get_option( 'bp_registration_groups_option_handle' );

	if ( empty( $options[ $key ] ) || ! is_array( $options[ $key ] ) ) {
		return array();
	}

	return array_values( array_unique( array_filter( array_map( 'absint', $options[ $key ] ) ) ) );
}

/**
* bp_registration_groups_get_sections()
*
* The ordered, curated group sections the site owner configured, as a clean
* list of arrays with 'title', 'description', and 'groups' (group IDs) keys.
* Returns an empty array when no sections are configured, which means the
* registration form renders the single global section exactly as it always
* has.
*/
function bp_registration_groups_get_sections() {
	$options = get_option( 'bp_registration_groups_option_handle' );

	if ( empty( $options['bp_registration_groups_sections'] ) || ! is_array( $options['bp_registration_groups_sections'] ) ) {
		return array();
	}

	$sections = array();

	foreach ( $options['bp_registration_groups_sections'] as $section ) {
		if ( ! is_array( $section ) ) {
			continue;
		}

		$title       = isset( $section['title'] ) ? trim( (string) $section['title'] ) : '';
		$description = isset( $section['description'] ) ? trim( (string) $section['description'] ) : '';
		$group_ids   = ( isset( $section['groups'] ) && is_array( $section['groups'] ) ) ? array_values( array_unique( array_filter( array_map( 'absint', $section['groups'] ) ) ) ) : array();

		if ( '' === $title && '' === $description && empty( $group_ids ) ) {
			continue;
		}

		$sections[] = array(
			'title'       => $title,
			'description' => $description,
			'groups'      => $group_ids,
		);
	}

	return $sections;
}

/**
* bp_registration_groups_get_section_group_ids()
*
* The union of the group IDs assigned to any configured section. When
* sections are configured, only these groups are offered on the registration
* form; groups left out of every section do not appear.
*/
function bp_registration_groups_get_section_group_ids() {
	$group_ids = array();

	foreach ( bp_registration_groups_get_sections() as $section ) {
		$group_ids = array_merge( $group_ids, $section['groups'] );
	}

	return array_values( array_unique( $group_ids ) );
}

/**
* bp_registration_groups_autojoin_shows_locked()
*
* Whether auto-join groups appear on the registration form as pre-checked,
* locked entries (true) or are left off the form entirely (false, default).
*/
function bp_registration_groups_autojoin_shows_locked() {
	$options = get_option( 'bp_registration_groups_option_handle' );

	return isset( $options['bp_registration_groups_autojoin_display'] ) && '1' == $options['bp_registration_groups_autojoin_display'];
}

/**
* bp_registration_groups_selection_required()
*
* Whether the site owner requires registrants to select at least one group
* before signup can continue. Off by default.
*/
function bp_registration_groups_selection_required() {
	$options = get_option( 'bp_registration_groups_option_handle' );

	return isset( $options['bp_registration_groups_require_selection'] ) && '1' == $options['bp_registration_groups_require_selection'];
}

/**
* bp_registration_groups_get_submitted_group_ids()
*
* The group IDs submitted with the signup form, as a clean list. No
* eligibility checks are applied here; use
* bp_registration_groups_get_valid_submitted_group_ids() for that.
*/
function bp_registration_groups_get_submitted_group_ids() {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- BuddyPress verifies the 'bp_new_signup' nonce before the signup hooks that call this run; the form re-render only reads the values back.
	if ( ! isset( $_POST['field_reg_groups'] ) ) {
		return array();
	}

	$group_ids = array();

	// The form's own inputs only ever submit plain digit strings, so accept
	// exactly that (plus genuine ints, in case another plugin filtered the
	// value). Anything else — nested arrays, negatives, floats — is a forged
	// payload; dropping it here avoids absint() coercing it into an
	// unrelated group ID (an array casts to 1, '-4' flips to 4).
	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	foreach ( (array) wp_unslash( $_POST['field_reg_groups'] ) as $value ) {
		if ( is_int( $value ) && $value > 0 ) {
			$group_ids[] = $value;
		} elseif ( is_string( $value ) && ctype_digit( $value ) && (int) $value > 0 ) {
			$group_ids[] = (int) $value;
		}
	}

	return array_values( array_unique( $group_ids ) );
}

/**
* bp_registration_groups_get_valid_submitted_group_ids()
*
* The submitted group IDs the registration form actually offers: the group
* exists, has an allowed status, is not hidden per-group, is not an
* auto-join group (those are joined automatically and are never selectable),
* and — when curated sections are configured — is assigned to a section.
*
* The "Number of Groups to Display" limit is deliberately NOT enforced here:
* it trims how many groups the form shows, but with the active/popular/random
* orders the displayed subset changes between page loads, so treating it as
* an eligibility rule would reject legitimate selections. Use the per-group
* Hide option (or sections) to make a specific group unselectable.
*/
function bp_registration_groups_get_valid_submitted_group_ids() {
	$group_ids = bp_registration_groups_get_submitted_group_ids();

	if ( empty( $group_ids ) ) {
		return array();
	}

	$allowed_statuses = bp_registration_groups_allowed_statuses();
	$not_selectable   = array_merge(
		bp_registration_groups_get_id_list_option( 'bp_registration_groups_hidden_groups' ),
		bp_registration_groups_get_id_list_option( 'bp_registration_groups_autojoin_groups' )
	);
	$sections_active  = ! empty( bp_registration_groups_get_sections() );
	$section_ids      = $sections_active ? bp_registration_groups_get_section_group_ids() : array();
	$valid_group_ids  = array();

	foreach ( $group_ids as $group_id ) {
		if ( in_array( $group_id, $not_selectable, true ) ) {
			continue;
		}

		if ( $sections_active && ! in_array( $group_id, $section_ids, true ) ) {
			continue;
		}

		$group = groups_get_group( $group_id );

		if ( ! empty( $group->id ) && in_array( $group->status, $allowed_statuses, true ) ) {
			$valid_group_ids[] = $group_id;
		}
	}

	return $valid_group_ids;
}

/**
* bp_registration_groups_has_selectable_groups()
*
* Whether at least one group is selectable on the registration form: allowed
* status, not hidden per-group, and not auto-join. Used to avoid locking
* registration when the requirement is enabled but nothing can be selected.
*/
function bp_registration_groups_has_selectable_groups() {
	$allowed_statuses = bp_registration_groups_allowed_statuses();
	$excluded_ids     = array_merge(
		bp_registration_groups_get_id_list_option( 'bp_registration_groups_hidden_groups' ),
		bp_registration_groups_get_id_list_option( 'bp_registration_groups_autojoin_groups' )
	);

	// When curated sections are configured, only groups assigned to a
	// section are offered, so selectability is decided over that set alone.
	if ( ! empty( bp_registration_groups_get_sections() ) ) {
		foreach ( bp_registration_groups_get_section_group_ids() as $section_group_id ) {
			if ( in_array( $section_group_id, $excluded_ids, true ) ) {
				continue;
			}

			$section_group = groups_get_group( $section_group_id );

			if ( ! empty( $section_group->id ) && in_array( $section_group->status, $allowed_statuses, true ) ) {
				return true;
			}
		}

		return false;
	}

	$query_args = array(
		'type'        => 'alphabetical',
		'per_page'    => null,
		'page'        => null,
		'show_hidden' => false,
		'status'      => $allowed_statuses,
	);
	if ( ! empty( $excluded_ids ) ) {
		$query_args['exclude'] = $excluded_ids;
	}

	$groups = groups_get_groups( $query_args );

	if ( empty( $groups['groups'] ) ) {
		return false;
	}

	// Safety net for BuddyPress versions without the 'status' or 'exclude'
	// query arguments.
	foreach ( $groups['groups'] as $group ) {
		if ( in_array( $group->status, $allowed_statuses, true )
			&& ! in_array( absint( $group->id ), $excluded_ids, true ) ) {
			return true;
		}
	}

	return false;
}

/**
* bp_registration_groups_required_selection_error_message()
*
* The error message shown when the required group selection is missing.
*/
function bp_registration_groups_required_selection_error_message() {
	/* translators: error shown on the registration form when the site requires selecting at least one group and none was selected */
	return __( 'Please select at least one group before completing your registration.', 'buddypress-registration-groups-1' );
}

/**
* bp_registration_groups_validate_signup()
*
* Reject signups with no valid group selection when the site owner requires
* one. BuddyPress fires 'bp_signup_validate' on every registration submission
* — multisite and single site alike — after verifying its own 'bp_new_signup'
* nonce, and blocks account creation when any signup errors are present.
*
* If no selectable groups exist, the requirement is skipped so a
* misconfiguration cannot lock registration; the settings page surfaces a
* warning for that state instead.
*/
add_action( 'bp_signup_validate', 'bp_registration_groups_validate_signup' );
function bp_registration_groups_validate_signup() {
	if ( ! bp_registration_groups_selection_required() ) {
		return;
	}

	if ( ! empty( bp_registration_groups_get_valid_submitted_group_ids() ) ) {
		return;
	}

	if ( ! bp_registration_groups_has_selectable_groups() ) {
		return;
	}

	$bp = buddypress();

	if ( ! isset( $bp->signup ) || ! is_object( $bp->signup ) ) {
		$bp->signup = new stdClass();
	}
	if ( ! isset( $bp->signup->errors ) || ! is_array( $bp->signup->errors ) ) {
		$bp->signup->errors = array();
	}

	$bp->signup->errors['field_reg_groups'] = bp_registration_groups_required_selection_error_message();
}

/**
* bp_registration_groups()
*
* Add list of groups to the registration page. Display a message stating no
* groups are available if none are found.
*
* Hooked to both 'bp_after_signup_profile_fields' (the original location,
* which both template packs only fire when the Extended Profiles component
* is active) and 'bp_before_registration_submit_buttons' (which fires on
* both template packs regardless of Extended Profiles). A static guard
* ensures the list renders only once per request.
*/
add_action( 'bp_after_signup_profile_fields', 'bp_registration_groups' );
add_action( 'bp_before_registration_submit_buttons', 'bp_registration_groups' );
function bp_registration_groups() {
	static $rendered = false;

	if ( $rendered ) {
		return;
	}
	$rendered = true;

	// Safety net for setups that render the signup form outside the
	// registration page: styles enqueued during the body render print in
	// the footer.
	wp_enqueue_style( 'bp_registration_groups_styles' );

	// get the BP Registration Groups options array from the WP options table
	$bp_registration_groups_options = get_option( 'bp_registration_groups_option_handle' );

	// set $bp_registration_groups_title to the stored value; fall back to 'Groups' if no value is stored
	$bp_registration_groups_title = ( isset( $bp_registration_groups_options['bp_registration_groups_title'] ) && $bp_registration_groups_options['bp_registration_groups_title'] != NULL ) ? $bp_registration_groups_options['bp_registration_groups_title'] : __( 'Groups', 'buddypress-registration-groups-1' );

	// set $bp_registration_groups_description to the stored value; fall back to 'Check one or more areas of interest' if no value is stored
	$bp_registration_groups_description = ( isset( $bp_registration_groups_options['bp_registration_groups_description'] ) && $bp_registration_groups_options['bp_registration_groups_description'] != NULL ) ? $bp_registration_groups_options['bp_registration_groups_description'] : __( 'Check one or more areas of interest', 'buddypress-registration-groups-1' );

	// set $bp_registration_groups_display_order to the stored value if it is a supported order; fall back to 'alphabetical' otherwise.
	// The legacy forum orders were removed from BuddyPress, so map them to 'active' (the order BuddyPress silently fell back to).
	$bp_registration_groups_display_order_options = array( 'active', 'newest', 'popular', 'random', 'alphabetical' );
	$bp_registration_groups_display_order = isset( $bp_registration_groups_options['bp_registration_groups_display_order'] ) ? $bp_registration_groups_options['bp_registration_groups_display_order'] : 'alphabetical';
	if ( in_array( $bp_registration_groups_display_order, array( 'most-forum-topics', 'most-forum-posts' ), true ) ) {
		$bp_registration_groups_display_order = 'active';
	}
	if ( ! in_array( $bp_registration_groups_display_order, $bp_registration_groups_display_order_options, true ) ) {
		$bp_registration_groups_display_order = 'alphabetical';
	}

	// set $bp_registration_groups_display_as to 'reg_groups_list_multiselect' if the stored value is 2; set to 'reg_groups_list' otherwise
	$bp_registration_groups_display_as = ( isset( $bp_registration_groups_options['bp_registration_groups_display_as'] ) && $bp_registration_groups_options['bp_registration_groups_display_as'] != '2' ) ? 'reg_groups_list' : 'reg_groups_list_multiselect';

	// set $bp_registration_groups_input_type to 'radio' if the stored value is 3; set to 'checkbox' otherwise
	$bp_registration_groups_input_type = ( isset( $bp_registration_groups_options['bp_registration_groups_display_as'] ) && $bp_registration_groups_options['bp_registration_groups_display_as'] == '3' ) ? 'radio' : 'checkbox';

	// the group statuses the site owner allows on the registration form
	$bp_registration_groups_show_statuses = bp_registration_groups_allowed_statuses();

	// number of groups to display; 0 shows all groups
	$bp_registration_groups_number_displayed = isset( $bp_registration_groups_options['bp_registration_groups_number_displayed'] ) ? absint( $bp_registration_groups_options['bp_registration_groups_number_displayed'] ) : 0;

	// per-group settings
	$bp_registration_groups_hidden_ids   = bp_registration_groups_get_id_list_option( 'bp_registration_groups_hidden_groups' );
	$bp_registration_groups_checked_ids  = bp_registration_groups_get_id_list_option( 'bp_registration_groups_checked_groups' );
	$bp_registration_groups_autojoin_ids = bp_registration_groups_get_id_list_option( 'bp_registration_groups_autojoin_groups' );

	// groups excluded from the selectable list: per-group hidden groups, and
	// auto-join groups (which are either left off the form entirely or
	// rendered separately as locked entries)
	$bp_registration_groups_excluded_ids = array_values( array_unique( array_merge( $bp_registration_groups_hidden_ids, $bp_registration_groups_autojoin_ids ) ) );

	// query args: the 'status' argument (BuddyPress 7.0+) restricts results to
	// the allowed statuses, and per_page 0 returns every matching group
	$bp_registration_groups_query_args = array(
		'type'     => $bp_registration_groups_display_order,
		'per_page' => $bp_registration_groups_number_displayed,
		'status'   => $bp_registration_groups_show_statuses,
	);
	if ( ! empty( $bp_registration_groups_excluded_ids ) ) {
		$bp_registration_groups_query_args['exclude'] = $bp_registration_groups_excluded_ids;
	}

	// When a signup was just submitted (and failed validation, or the form is
	// re-rendering for any other error), the registrant's own selections
	// replace the admin "checked by default" list so their choices — including
	// unchecking a default — survive the round trip.
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- read only to re-check boxes on the form re-render; BuddyPress verified the 'bp_new_signup' nonce for the submission itself.
	$bp_registration_groups_signup_posted = isset( $_POST['signup_submit'] );
	if ( $bp_registration_groups_signup_posted ) {
		$bp_registration_groups_checked_ids = bp_registration_groups_get_submitted_group_ids();
	}

	// inline error set by bp_registration_groups_validate_signup() when a
	// required group selection is missing
	$bp_registration_groups_error = '';
	if ( function_exists( 'buddypress' )
		&& isset( buddypress()->signup->errors['field_reg_groups'] )
		&& is_string( buddypress()->signup->errors['field_reg_groups'] ) ) {
		$bp_registration_groups_error = buddypress()->signup->errors['field_reg_groups'];
	}

	// auto-join groups shown as locked, pre-checked entries
	$bp_registration_groups_locked_groups = array();
	if ( ! empty( $bp_registration_groups_autojoin_ids ) && bp_registration_groups_autojoin_shows_locked() ) {
		foreach ( $bp_registration_groups_autojoin_ids as $bp_registration_groups_autojoin_id ) {
			$bp_registration_groups_autojoin_group = groups_get_group( $bp_registration_groups_autojoin_id );
			// Never name a status-hidden group on the public form; it is
			// still auto-joined at activation.
			if ( ! empty( $bp_registration_groups_autojoin_group->id ) && 'hidden' !== $bp_registration_groups_autojoin_group->status ) {
				$bp_registration_groups_locked_groups[] = $bp_registration_groups_autojoin_group;
			}
		}
	}

	// Curated sections: when at least one section is configured, the form
	// renders each section with its own heading, description, and assigned
	// groups instead of the single global list. Only groups assigned to a
	// section appear, each at most once (the first section that lists a group
	// keeps it), and the same eligibility rules apply as in the global list:
	// per-group hidden and disallowed-status groups are skipped, and
	// auto-join groups appear only as locked entries when that display is on
	// (never when the group's status is hidden).
	$bp_registration_groups_sections     = bp_registration_groups_get_sections();
	$bp_registration_groups_sections_out = array();
	if ( ! empty( $bp_registration_groups_sections ) ) {
		$bp_registration_groups_rendered_ids = array();

		foreach ( $bp_registration_groups_sections as $bp_registration_groups_section ) {
			$bp_registration_groups_section_entries = array();

			foreach ( $bp_registration_groups_section['groups'] as $bp_registration_groups_section_group_id ) {
				if ( in_array( $bp_registration_groups_section_group_id, $bp_registration_groups_rendered_ids, true )
					|| in_array( $bp_registration_groups_section_group_id, $bp_registration_groups_hidden_ids, true ) ) {
					continue;
				}

				$bp_registration_groups_section_group = groups_get_group( $bp_registration_groups_section_group_id );

				if ( empty( $bp_registration_groups_section_group->id ) ) {
					continue;
				}

				if ( in_array( $bp_registration_groups_section_group_id, $bp_registration_groups_autojoin_ids, true ) ) {
					if ( bp_registration_groups_autojoin_shows_locked() && 'hidden' !== $bp_registration_groups_section_group->status ) {
						$bp_registration_groups_section_entries[] = array(
							'group'  => $bp_registration_groups_section_group,
							'locked' => true,
						);
						$bp_registration_groups_rendered_ids[]    = $bp_registration_groups_section_group_id;
					}
					continue;
				}

				if ( ! in_array( $bp_registration_groups_section_group->status, $bp_registration_groups_show_statuses, true ) ) {
					continue;
				}

				$bp_registration_groups_section_entries[] = array(
					'group'  => $bp_registration_groups_section_group,
					'locked' => false,
				);
				$bp_registration_groups_rendered_ids[]    = $bp_registration_groups_section_group_id;
			}

			if ( ! empty( $bp_registration_groups_section_entries ) ) {
				$bp_registration_groups_section['entries'] = $bp_registration_groups_section_entries;
				$bp_registration_groups_sections_out[]     = $bp_registration_groups_section;
			}
		}
	}

	/* list groups */ ?>
		<div class="register-section" id="registration-groups-section">
			<h4 class="reg_groups_title"><?php echo esc_html( $bp_registration_groups_title ); ?></h4>
			<p class="reg_groups_description"><?php echo esc_html( $bp_registration_groups_description ); ?></p>
			<?php if ( '' !== $bp_registration_groups_error ) : ?>
			<div id="reg-groups-error" class="error reg_groups_error" role="alert"><?php echo esc_html( $bp_registration_groups_error ); ?></div>
			<?php endif; ?>
			<?php if ( ! empty( $bp_registration_groups_sections ) ) : ?>
			<?php if ( ! empty( $bp_registration_groups_sections_out ) ) : $i = 0; ?>
			<?php foreach ( $bp_registration_groups_sections_out as $bp_registration_groups_section_index => $bp_registration_groups_section ) : ?>
			<div class="reg_groups_section">
				<?php if ( '' !== $bp_registration_groups_section['title'] ) : ?>
				<h5 class="reg_groups_section_title"><?php echo esc_html( $bp_registration_groups_section['title'] ); ?></h5>
				<?php endif; ?>
				<?php if ( '' !== $bp_registration_groups_section['description'] ) : ?>
				<p class="reg_groups_section_description"><?php echo esc_html( $bp_registration_groups_section['description'] ); ?></p>
				<?php endif; ?>
				<ul class="<?php echo esc_attr( $bp_registration_groups_display_as ); ?>">
					<?php
					// In radio mode each section is its own radio group: the
					// inputs share a per-section name, so the registrant can
					// select one group in every section. Checkboxes share the
					// global name and allow any number of selections.
					$bp_registration_groups_input_name     = ( 'radio' === $bp_registration_groups_input_type ) ? 'field_reg_groups[' . $bp_registration_groups_section_index . ']' : 'field_reg_groups[]';
					$bp_registration_groups_default_checked = false;
					?>
					<?php foreach ( $bp_registration_groups_section['entries'] as $bp_registration_groups_entry ) : ?>
						<?php if ( $bp_registration_groups_entry['locked'] ) : ?>
						<li class="reg_groups_item reg_groups_item_locked">
							<input class="reg_groups_group_checkbox" type="checkbox" id="field_reg_groups_auto_<?php echo esc_attr( $bp_registration_groups_entry['group']->id ); ?>" checked="checked" disabled="disabled" /><label class="reg_groups_group_label" for="field_reg_groups_auto_<?php echo esc_attr( $bp_registration_groups_entry['group']->id ); ?>"><?php echo esc_html( $bp_registration_groups_entry['group']->name ); ?> <em class="reg_groups_automatic"><?php
							/* translators: label shown next to groups that every new user joins automatically */
							esc_html_e( '(automatic)', 'buddypress-registration-groups-1' );
							?></em></label>
						</li>
						<?php else : ?>
						<?php
						// Pre-check groups the admin marked as checked by
						// default (or, on a signup re-render, the registrant's
						// submitted selections); in radio mode only the first
						// per section is checked.
						$bp_registration_groups_is_checked = in_array( absint( $bp_registration_groups_entry['group']->id ), $bp_registration_groups_checked_ids, true )
							&& ( 'radio' !== $bp_registration_groups_input_type || ! $bp_registration_groups_default_checked );
						if ( $bp_registration_groups_is_checked ) {
							$bp_registration_groups_default_checked = true;
						}
						?>
						<li class="reg_groups_item">
							<input class="reg_groups_group_checkbox" type="<?php echo esc_attr( $bp_registration_groups_input_type ); ?>" id="field_reg_groups_<?php echo esc_attr( $i ); ?>" name="<?php echo esc_attr( $bp_registration_groups_input_name ); ?>" value="<?php echo esc_attr( $bp_registration_groups_entry['group']->id ); ?>"<?php checked( $bp_registration_groups_is_checked ); ?> /><label class="reg_groups_group_label" for="field_reg_groups_<?php echo esc_attr( $i ); ?>"><?php echo esc_html( $bp_registration_groups_entry['group']->name ); ?></label>
						</li>
						<?php $i++; ?>
						<?php endif; ?>
					<?php endforeach; ?>
				</ul>
			</div>
			<?php endforeach; ?>
			<?php else : ?>
			<p class="reg_groups_none">
				<?php
				/* translators: text that is displayed on the buddypress user registration form when there are no groups that can be displayed */
				esc_html_e( 'No groups are available at this time.', 'buddypress-registration-groups-1' );
				?>
			</p>
			<?php endif; ?>
			<?php else : ?>
			<?php $bp_registration_groups_has_groups = bp_has_groups( $bp_registration_groups_query_args ); ?>
			<?php if ( $bp_registration_groups_has_groups || ! empty( $bp_registration_groups_locked_groups ) ) : ?>
			<ul class="<?php echo esc_attr( $bp_registration_groups_display_as ); ?>">
				<?php foreach ( $bp_registration_groups_locked_groups as $bp_registration_groups_locked_group ) : ?>
					<li class="reg_groups_item reg_groups_item_locked">
						<input class="reg_groups_group_checkbox" type="checkbox" id="field_reg_groups_auto_<?php echo esc_attr( $bp_registration_groups_locked_group->id ); ?>" checked="checked" disabled="disabled" /><label class="reg_groups_group_label" for="field_reg_groups_auto_<?php echo esc_attr( $bp_registration_groups_locked_group->id ); ?>"><?php echo esc_html( $bp_registration_groups_locked_group->name ); ?> <em class="reg_groups_automatic"><?php
						/* translators: label shown next to groups that every new user joins automatically */
						esc_html_e( '(automatic)', 'buddypress-registration-groups-1' );
						?></em></label>
					</li>
				<?php endforeach; ?>
				<?php $i = 0; $bp_registration_groups_default_checked = false; ?>
				<?php if ( $bp_registration_groups_has_groups ) : while ( bp_groups() ) : bp_the_group(); ?>
					<?php
					// Safety net for BuddyPress versions without the 'status' or
					// 'exclude' query arguments.
					if ( ! in_array( bp_get_group_status(), $bp_registration_groups_show_statuses, true )
						|| in_array( absint( bp_get_group_id() ), $bp_registration_groups_excluded_ids, true ) ) {
						continue;
					}
					// Pre-check groups the admin marked as checked by default
					// (or, on a signup re-render, the registrant's submitted
					// selections); in radio mode only the first is checked.
					$bp_registration_groups_is_checked = in_array( absint( bp_get_group_id() ), $bp_registration_groups_checked_ids, true )
						&& ( 'radio' !== $bp_registration_groups_input_type || ! $bp_registration_groups_default_checked );
					if ( $bp_registration_groups_is_checked ) {
						$bp_registration_groups_default_checked = true;
					}
					?>
					<li class="reg_groups_item">
						<input class="reg_groups_group_checkbox" type="<?php echo esc_attr( $bp_registration_groups_input_type ); ?>" id="field_reg_groups_<?php echo esc_attr( $i ); ?>" name="field_reg_groups[]" value="<?php echo esc_attr( bp_get_group_id() ); ?>"<?php checked( $bp_registration_groups_is_checked ); ?> /><label class="reg_groups_group_label" for="field_reg_groups_<?php echo esc_attr( $i ); ?>"><?php echo esc_html( bp_get_group_name() ); ?></label>
					</li>
					<?php $i++; ?>
				<?php endwhile; endif; ?>
			</ul>
			<?php else : ?>
			<p class="reg_groups_none">
				<?php
				/* translators: text that is displayed on the buddypress user registration form when there are no groups that can be displayed */
				esc_html_e( 'No groups are available at this time.', 'buddypress-registration-groups-1' );
				?>
			</p>
			<?php endif; ?>
			<?php endif; ?>
		</div>
<?php }

/**
* bp_registration_groups_save()
*
* Save the groups selected during registration into the signup meta.
*
* BuddyPress applies the 'bp_signup_usermeta' filter for every signup —
* multisite and single site alike — after verifying its own 'bp_new_signup'
* nonce, and stores the result in the signups table until the account is
* activated.
*/
add_filter( 'bp_signup_usermeta', 'bp_registration_groups_save' );
function bp_registration_groups_save( $usermeta ) {

	// Only keep groups the registration form actually offers: allowed status,
	// not hidden per-group, and not an auto-join group (those are joined
	// automatically and are never selectable).
	$valid_group_ids = bp_registration_groups_get_valid_submitted_group_ids();

	if ( ! empty( $valid_group_ids ) ) {
		$usermeta['field_reg_groups'] = $valid_group_ids;
	}

	return $usermeta;

}

/**
* bp_registration_groups_join()
*
* Join the selected groups when the account is activated.
*
* BuddyPress fires 'bp_core_activated_user' for every activation — multisite
* and single site alike — and passes the signup meta, including the group
* selection saved by bp_registration_groups_save(), as $user['meta'].
*/
add_action( 'bp_core_activated_user', 'bp_registration_groups_join', 10, 3 );
function bp_registration_groups_join( $user_id, $key = '', $user = false ) {

	$selected_group_ids = ( ! empty( $user['meta']['field_reg_groups'] ) && is_array( $user['meta']['field_reg_groups'] ) ) ? $user['meta']['field_reg_groups'] : array();

	// Re-check each selected group against the current settings so
	// activations cannot join groups the registration form no longer offers.
	$allowed_statuses = bp_registration_groups_allowed_statuses();
	$hidden_group_ids = bp_registration_groups_get_id_list_option( 'bp_registration_groups_hidden_groups' );
	$sections_active  = ! empty( bp_registration_groups_get_sections() );
	$section_ids      = $sections_active ? bp_registration_groups_get_section_group_ids() : array();

	foreach ( $selected_group_ids as $group_id ) {
		$group_id = absint( $group_id );

		if ( ! $group_id || in_array( $group_id, $hidden_group_ids, true ) ) {
			continue;
		}

		if ( $sections_active && ! in_array( $group_id, $section_ids, true ) ) {
			continue;
		}

		$group = groups_get_group( $group_id );

		if ( empty( $group->id ) || ! in_array( $group->status, $allowed_statuses, true ) ) {
			continue;
		}

		groups_join_group( $group_id, $user_id );
	}

	// Every new user joins the admin-designated auto-join groups, whether or
	// not they selected anything. These are exempt from the status check
	// because the admin chose them explicitly.
	foreach ( bp_registration_groups_get_id_list_option( 'bp_registration_groups_autojoin_groups' ) as $autojoin_group_id ) {
		$autojoin_group = groups_get_group( $autojoin_group_id );

		if ( ! empty( $autojoin_group->id ) ) {
			groups_join_group( $autojoin_group_id, $user_id );
		}
	}

}

/**
 * Admin menus and settings
 *
 * Create custom administration menus and options pages for BP Registration Groups
 */
class BPRegistrationGroupsSettingsPage
{
  /**
   * Holds the values to be used in the fields callbacks
   */
  private $options;

  /**
   * Start up
   */
  public function __construct()
  {
    add_action( 'admin_menu', array( $this, 'bp_registration_groups_add_plugin_page' ) );
    add_action( 'admin_init', array( $this, 'bp_registration_groups_page_init' ) );
    add_action( 'admin_notices', array( $this, 'bp_registration_groups_require_selection_warning' ) );
  }

  /**
   * Warn when "Require Group Selection" is enabled but no selectable groups
   * exist. In that state the requirement is skipped at signup so registration
   * is never blocked; this notice tells the admin the setting is idle.
   */
  public function bp_registration_groups_require_selection_warning()
  {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return;
		}

		$screen = get_current_screen();

		if ( empty( $screen->id ) || 'settings_page_bp-registration-groups-settings-admin' !== $screen->id ) {
			return;
		}

		if ( ! bp_registration_groups_selection_required() || bp_registration_groups_has_selectable_groups() ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			/* translators: warning shown on the plugin settings page when a group selection is required but no group can be selected on the registration form */
			esc_html__( 'BP Registration Groups: "Require Group Selection" is enabled, but no selectable groups exist (every group is hidden, auto-join, or not visible on the registration form). The requirement is being skipped so registration is not blocked. Create or unhide a selectable group to enforce it.', 'buddypress-registration-groups-1' )
		);
  }

  /**
   * Add options page
   */
  public function bp_registration_groups_add_plugin_page()
  {
    // This page will be under "Settings"
    add_options_page(
			/* translators: the text to be displayed in the title tags of the page when the menu is selected */
			__('BP Registration Groups Settings', 'buddypress-registration-groups-1'),
			/* translators: the text to be used for the menu */
			__('BP Registration Groups', 'buddypress-registration-groups-1'),
      'manage_options',
      'bp-registration-groups-settings-admin',
      array( $this, 'bp_registration_groups_create_admin_page' )
    );
  }

  /**
   * Options page callback
   */
  public function bp_registration_groups_create_admin_page()
  {
		// Set class property
    $this->options = get_option( 'bp_registration_groups_option_handle' );
    ?>
    <div class="wrap">
      <h2><?php esc_html_e('BP Registration Groups', 'buddypress-registration-groups-1'); ?></h2>
      <form method="post" action="options.php">
      <?php
        // This prints out all hidden setting fields
        settings_fields( 'bp_registration_groups_option_group' );
        do_settings_sections( 'bp-registration-groups-settings-admin' );
        submit_button();
      ?>
      </form>
    </div>
    <?php
  }

  /**
   * Register and add settings
   */
  public function bp_registration_groups_page_init()
  {
    register_setting(
      'bp_registration_groups_option_group', // Option group
      'bp_registration_groups_option_handle', // Option name
      array( $this, 'sanitize' ) // Sanitize
    );

    add_settings_section(
      'bp_registration_groups_display_options_section_id',
			/* translators: displays the page title for the plugin admin page */
			__('Display Options', 'buddypress-registration-groups-1'),
      array( $this, 'print_display_options_section_info' ),
      'bp-registration-groups-settings-admin'
    );

    add_settings_field(
      'bp_registration_groups_title',
			/* translators: displays the title text for the "Title" section of the plugin admin page */
			__('Title', 'buddypress-registration-groups-1'),
      array( $this, 'bp_registration_groups_title_callback' ),
      'bp-registration-groups-settings-admin',
      'bp_registration_groups_display_options_section_id'
    );

    add_settings_field(
      'bp_registration_groups_description',
			/* translators: displays the title text for the "Description" section of the plugin admin page */
			__('Description', 'buddypress-registration-groups-1'),
      array( $this, 'bp_registration_groups_description_callback' ),
      'bp-registration-groups-settings-admin',
      'bp_registration_groups_display_options_section_id'
    );

    add_settings_field(
      'bp_registration_groups_display_order',
			/* translators: displays the title text for the "Display Order" section of the plugin admin page */
			__('Display Order', 'buddypress-registration-groups-1'),
      array( $this, 'bp_registration_groups_display_order_callback' ),
      'bp-registration-groups-settings-admin',
      'bp_registration_groups_display_options_section_id'
    );

		add_settings_field(
			'bp_registration_groups_display_as',
			/* translators: displays the title text for the "Display As" section of the plugin admin page */
			__('Display As', 'buddypress-registration-groups-1'),
			array( $this, 'bp_registration_groups_display_as_callback' ),
			'bp-registration-groups-settings-admin',
			'bp_registration_groups_display_options_section_id'
		);

    add_settings_field(
      'bp_registration_groups_show_private_groups',
			/* translators: displays the title text for the "Show Private Groups" section of the plugin admin page */
			__('Show Private Groups', 'buddypress-registration-groups-1'),
      array( $this, 'bp_registration_groups_show_private_groups_callback' ),
      'bp-registration-groups-settings-admin',
      'bp_registration_groups_display_options_section_id'
    );

    add_settings_field(
      'bp_registration_groups_number_displayed',
			/* translators: displays the title text for the "Number of Groups to Display" section of the plugin admin page */
			__('Number of Groups to Display', 'buddypress-registration-groups-1'),
      array( $this, 'bp_registration_groups_number_displayed_callback' ),
      'bp-registration-groups-settings-admin',
      'bp_registration_groups_display_options_section_id'
    );

    add_settings_field(
      'bp_registration_groups_require_selection',
			/* translators: displays the title text for the "Require Group Selection" setting of the plugin admin page */
			__('Require Group Selection', 'buddypress-registration-groups-1'),
      array( $this, 'bp_registration_groups_require_selection_callback' ),
      'bp-registration-groups-settings-admin',
      'bp_registration_groups_display_options_section_id'
    );

    add_settings_section(
      'bp_registration_groups_per_group_options_section_id',
			/* translators: displays the section title for the per-group options on the plugin admin page */
			__('Per-Group Options', 'buddypress-registration-groups-1'),
      array( $this, 'print_per_group_options_section_info' ),
      'bp-registration-groups-settings-admin'
    );

    add_settings_field(
      'bp_registration_groups_per_group_settings',
			/* translators: displays the title text for the per-group settings table on the plugin admin page */
			__('Group Settings', 'buddypress-registration-groups-1'),
      array( $this, 'bp_registration_groups_per_group_settings_callback' ),
      'bp-registration-groups-settings-admin',
      'bp_registration_groups_per_group_options_section_id'
    );

    add_settings_field(
      'bp_registration_groups_autojoin_display',
			/* translators: displays the title text for the "Auto-Join Display" setting of the plugin admin page */
			__('Auto-Join Display', 'buddypress-registration-groups-1'),
      array( $this, 'bp_registration_groups_autojoin_display_callback' ),
      'bp-registration-groups-settings-admin',
      'bp_registration_groups_per_group_options_section_id'
    );

    add_settings_section(
      'bp_registration_groups_sections_section_id',
			/* translators: displays the section title for the curated group sections options on the plugin admin page */
			__('Group Sections', 'buddypress-registration-groups-1'),
      array( $this, 'print_sections_section_info' ),
      'bp-registration-groups-settings-admin'
    );

    add_settings_field(
      'bp_registration_groups_sections',
			/* translators: displays the title text for the curated group sections editor on the plugin admin page */
			__('Sections', 'buddypress-registration-groups-1'),
      array( $this, 'bp_registration_groups_sections_callback' ),
      'bp-registration-groups-settings-admin',
      'bp_registration_groups_sections_section_id'
    );
  }

  /**
   * Sanitize each setting field as needed
   *
   * @param array $input Contains all settings fields as array keys
   */
  public function sanitize( $input )
  {
    $new_input = array();
    if( isset( $input['bp_registration_groups_title'] ) )
        $new_input['bp_registration_groups_title'] = sanitize_text_field( $input['bp_registration_groups_title'] );

    if( isset( $input['bp_registration_groups_description'] ) )
        $new_input['bp_registration_groups_description'] = sanitize_text_field( $input['bp_registration_groups_description'] );

    if( isset( $input['bp_registration_groups_display_order'] ) ) {
        $display_order = sanitize_text_field( $input['bp_registration_groups_display_order'] );
        $new_input['bp_registration_groups_display_order'] = in_array( $display_order, array( 'active', 'newest', 'popular', 'random', 'alphabetical' ), true ) ? $display_order : 'alphabetical';
    }

		if( isset( $input['bp_registration_groups_display_as'] ) ) {
        // only the values the Display As radios actually post; anything else
        // (including absint-coercible junk like '-3') falls back to the default
        $display_as = is_scalar( $input['bp_registration_groups_display_as'] ) ? (string) $input['bp_registration_groups_display_as'] : '';
        $new_input['bp_registration_groups_display_as'] = in_array( $display_as, array( '1', '2', '3' ), true ) ? (int) $display_as : 2;
    }

    if( isset( $input['bp_registration_groups_show_private_groups'] ) )
        $new_input['bp_registration_groups_show_private_groups'] = absint( $input['bp_registration_groups_show_private_groups'] );

    if( isset( $input['bp_registration_groups_number_displayed'] ) )
        $new_input['bp_registration_groups_number_displayed'] = absint( $input['bp_registration_groups_number_displayed'] );

    if( isset( $input['bp_registration_groups_require_selection'] ) )
        $new_input['bp_registration_groups_require_selection'] = absint( $input['bp_registration_groups_require_selection'] );

    // per-group ID lists; absent keys mean no boxes were checked
    foreach ( array( 'bp_registration_groups_hidden_groups', 'bp_registration_groups_checked_groups', 'bp_registration_groups_autojoin_groups' ) as $id_list_key ) {
        if ( isset( $input[ $id_list_key ] ) && is_array( $input[ $id_list_key ] ) ) {
            $new_input[ $id_list_key ] = array_values( array_unique( array_filter( array_map( 'absint', $input[ $id_list_key ] ) ) ) );
        }
    }

    if( isset( $input['bp_registration_groups_autojoin_display'] ) )
        $new_input['bp_registration_groups_autojoin_display'] = absint( $input['bp_registration_groups_autojoin_display'] );

    // curated group sections: clean each row, drop removed and empty rows
    // (the blank "add a new section" row submits empty), order by the
    // requested position (stable on the submitted order), and keep a group
    // in only the first section that lists it
    if ( isset( $input['bp_registration_groups_sections'] ) && is_array( $input['bp_registration_groups_sections'] ) ) {
        $sections  = array();
        $submitted = 0;

        foreach ( $input['bp_registration_groups_sections'] as $section ) {
            $submitted++;

            if ( ! is_array( $section ) || ! empty( $section['remove'] ) ) {
                continue;
            }

            $title       = isset( $section['title'] ) ? sanitize_text_field( $section['title'] ) : '';
            $description = isset( $section['description'] ) ? sanitize_text_field( $section['description'] ) : '';
            $group_ids   = ( isset( $section['groups'] ) && is_array( $section['groups'] ) ) ? array_values( array_unique( array_filter( array_map( 'absint', $section['groups'] ) ) ) ) : array();

            if ( '' === $title && '' === $description && empty( $group_ids ) ) {
                continue;
            }

            $sections[] = array(
                'position'    => ( isset( $section['position'] ) && absint( $section['position'] ) > 0 ) ? absint( $section['position'] ) : $submitted,
                'submitted'   => $submitted,
                'title'       => $title,
                'description' => $description,
                'groups'      => $group_ids,
            );
        }

        usort( $sections, function ( $a, $b ) {
            return ( $a['position'] === $b['position'] ) ? $a['submitted'] - $b['submitted'] : $a['position'] - $b['position'];
        } );

        $assigned_group_ids = array();
        foreach ( $sections as $index => $section ) {
            $sections[ $index ]['groups'] = array_values( array_diff( $section['groups'], $assigned_group_ids ) );
            $assigned_group_ids           = array_merge( $assigned_group_ids, $sections[ $index ]['groups'] );
            unset( $sections[ $index ]['position'], $sections[ $index ]['submitted'] );
        }

        $new_input['bp_registration_groups_sections'] = array_values( $sections );
    }

    return $new_input;
  }

  /**
   * Print the Section text
   */
  public function print_display_options_section_info()
  {
		/* translators: displays the help text for the "Display Options" section of the plugin admin page */
		esc_html_e( 'These options allow you to customize the list of groups on the new user registration form.', 'buddypress-registration-groups-1' );
  }

  /**
   * Get the settings option array and print one of its values
   */
  public function bp_registration_groups_title_callback()
  {
		printf( '<input type="text" id="bp_registration_groups_title" name="bp_registration_groups_option_handle[bp_registration_groups_title]" value="%s" />', isset( $this->options['bp_registration_groups_title'] ) ? esc_attr( $this->options['bp_registration_groups_title'] ) : '' );

		/* translators: displays the help text for the "Title" section of the plugin admin page */
		echo '<br /><em>' . esc_html__('Default: Groups', 'buddypress-registration-groups-1') . '</em>';
  }

  /**
   * Get the settings option array and print one of its values
   */
  public function bp_registration_groups_description_callback()
  {
		printf( '<input type="text" id="bp_registration_groups_description" name="bp_registration_groups_option_handle[bp_registration_groups_description]" value="%s" />', isset( $this->options['bp_registration_groups_description'] ) ? esc_attr( $this->options['bp_registration_groups_description'] ) : '' );

		/* translators: displays the help text for the "Description" section of the plugin admin page */
		echo '<br /><em>' . esc_html__('Default: Check one or more areas of interest', 'buddypress-registration-groups-1') . '</em>';
  }

  /**
   * Get the settings option array and print one of its values
   *
   * Options match the orders supported by bp_has_groups: active, newest,
   * popular, random, alphabetical. (The legacy forum orders were removed
   * from BuddyPress.)
   */
  public function bp_registration_groups_display_order_callback()
  {
		$display_orders = array(
			/* translators: displays the text "Alphabetical (default)" in the "Display Order" section of the plugin admin page */
			'alphabetical' => __( 'Alphabetical (default)', 'buddypress-registration-groups-1' ),
			/* translators: displays the text "Active" in the "Display Order" section of the plugin admin page */
			'active'       => __( 'Active', 'buddypress-registration-groups-1' ),
			/* translators: displays the text "Newest" in the "Display Order" section of the plugin admin page */
			'newest'       => __( 'Newest', 'buddypress-registration-groups-1' ),
			/* translators: displays the text "Popular" in the "Display Order" section of the plugin admin page */
			'popular'      => __( 'Popular', 'buddypress-registration-groups-1' ),
			/* translators: displays the text "Random" in the "Display Order" section of the plugin admin page */
			'random'       => __( 'Random', 'buddypress-registration-groups-1' ),
		);

		$current = isset( $this->options['bp_registration_groups_display_order'] ) ? $this->options['bp_registration_groups_display_order'] : 'alphabetical';

		// The legacy forum orders were removed from BuddyPress and behaved like 'active'.
		if ( in_array( $current, array( 'most-forum-topics', 'most-forum-posts' ), true ) ) {
			$current = 'active';
		}

		if ( ! array_key_exists( $current, $display_orders ) ) {
			$current = 'alphabetical';
		}

		$rows = array();
		foreach ( $display_orders as $value => $label ) {
			$rows[] = sprintf(
				'<label><input type="radio" %s name="bp_registration_groups_option_handle[bp_registration_groups_display_order]" value="%s"> %s</label>',
				checked( $current, $value, false ),
				esc_attr( $value ),
				esc_html( $label )
			);
		}
		echo implode( '<br />', $rows ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rows are escaped above.
  }

	/**
   * Get the settings option array and print one of its values
   */
  public function bp_registration_groups_display_as_callback()
  {
		$display_as_options = array(
			/* translators: displays the text "Checkboxes Multiselect (default)" in the "Display As" section of the plugin admin page */
			'2' => __( 'Checkboxes Multiselect (default)', 'buddypress-registration-groups-1' ),
			/* translators: displays the text "Checkboxes" in the "Display As" section of the plugin admin page */
			'1' => __( 'Checkboxes', 'buddypress-registration-groups-1' ),
			/* translators: displays the text "Radio Buttons" in the "Display As" section of the plugin admin page */
			'3' => __( 'Radio Buttons', 'buddypress-registration-groups-1' ),
		);

		$current = isset( $this->options['bp_registration_groups_display_as'] ) ? (string) $this->options['bp_registration_groups_display_as'] : '2';

		if ( ! array_key_exists( $current, $display_as_options ) ) {
			$current = '2';
		}

		$rows = array();
		foreach ( $display_as_options as $value => $label ) {
			$rows[] = sprintf(
				'<label><input type="radio" %s name="bp_registration_groups_option_handle[bp_registration_groups_display_as]" value="%s"> %s</label>',
				checked( $current, $value, false ),
				esc_attr( $value ),
				esc_html( $label )
			);
		}
		echo implode( '<br />', $rows ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rows are escaped above.
  }

  /**
   * Get the settings option array and print one of its values
   */
  public function bp_registration_groups_show_private_groups_callback()
  {
		$show_private_options = array(
			/* translators: displays the text "Yes" in the "Show Private Groups" section of the plugin admin page */
			'1' => __( 'Yes', 'buddypress-registration-groups-1' ),
			/* translators: displays the text "No (default)" in the "Show Private Groups" section of the plugin admin page */
			'0' => __( 'No (default)', 'buddypress-registration-groups-1' ),
		);

		$current = ( isset( $this->options['bp_registration_groups_show_private_groups'] ) && '1' == $this->options['bp_registration_groups_show_private_groups'] ) ? '1' : '0';

		$rows = array();
		foreach ( $show_private_options as $value => $label ) {
			$rows[] = sprintf(
				'<label><input type="radio" %s name="bp_registration_groups_option_handle[bp_registration_groups_show_private_groups]" value="%s"> %s</label>',
				checked( $current, $value, false ),
				esc_attr( $value ),
				esc_html( $label )
			);
		}
		echo implode( '<br />', $rows ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rows are escaped above.
  }

  /**
   * Get the settings option array and print one of its values
   */
  public function bp_registration_groups_number_displayed_callback()
  {
		printf( '<input type="text" id="bp_registration_groups_number_displayed" name="bp_registration_groups_option_handle[bp_registration_groups_number_displayed]" value="%d" />', isset( $this->options['bp_registration_groups_number_displayed'] ) ? absint( $this->options['bp_registration_groups_number_displayed'] ) : 0 );

		/* translators: displays the help text for the "Number of Groups to Display" section of the plugin admin page */
		echo '<br /><em>' . esc_html__('Default: 0 (show all groups)', 'buddypress-registration-groups-1') . '</em>';
  }

  /**
   * Get the settings option array and print one of its values
   */
  public function bp_registration_groups_require_selection_callback()
  {
		$require_selection_options = array(
			/* translators: displays the text "Yes" for the "Require Group Selection" setting of the plugin admin page */
			'1' => __( 'Yes — registration cannot be completed without selecting at least one group', 'buddypress-registration-groups-1' ),
			/* translators: displays the text "No (default)" for the "Require Group Selection" setting of the plugin admin page */
			'0' => __( 'No (default)', 'buddypress-registration-groups-1' ),
		);

		$current = ( isset( $this->options['bp_registration_groups_require_selection'] ) && '1' == $this->options['bp_registration_groups_require_selection'] ) ? '1' : '0';

		$rows = array();
		foreach ( $require_selection_options as $value => $label ) {
			$rows[] = sprintf(
				'<label><input type="radio" %s name="bp_registration_groups_option_handle[bp_registration_groups_require_selection]" value="%s"> %s</label>',
				checked( $current, $value, false ),
				esc_attr( $value ),
				esc_html( $label )
			);
		}
		echo implode( '<br />', $rows ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rows are escaped above.

		/* translators: displays the help text for the "Require Group Selection" setting of the plugin admin page */
		echo '<br /><em>' . esc_html__( 'Validated when the signup form is submitted. Hidden and auto-join groups do not count. If no selectable groups exist, the requirement is skipped so registration is never blocked.', 'buddypress-registration-groups-1' ) . '</em>';
  }

  /**
   * Print the per-group options section text
   */
  public function print_per_group_options_section_info()
  {
		/* translators: displays the help text for the "Per-Group Options" section of the plugin admin page */
		esc_html_e( 'Fine-tune individual groups: hide a group from the registration form, pre-check it, or make every new user join it automatically. Auto-join groups are never selectable on the form, and hidden groups marked auto-join are joined silently — their names are never displayed on the registration form.', 'buddypress-registration-groups-1' );
  }

  /**
   * Render the per-group settings table
   */
  public function bp_registration_groups_per_group_settings_callback()
  {
		$groups = groups_get_groups( array(
			'type'        => 'alphabetical',
			'show_hidden' => true,
			'per_page'    => null,
			'page'        => null,
		) );

		if ( empty( $groups['groups'] ) ) {
			/* translators: shown on the plugin admin page when the site has no groups yet */
			echo '<em>' . esc_html__( 'No groups exist yet.', 'buddypress-registration-groups-1' ) . '</em>';
			return;
		}

		$hidden_ids   = isset( $this->options['bp_registration_groups_hidden_groups'] ) ? array_map( 'absint', (array) $this->options['bp_registration_groups_hidden_groups'] ) : array();
		$checked_ids  = isset( $this->options['bp_registration_groups_checked_groups'] ) ? array_map( 'absint', (array) $this->options['bp_registration_groups_checked_groups'] ) : array();
		$autojoin_ids = isset( $this->options['bp_registration_groups_autojoin_groups'] ) ? array_map( 'absint', (array) $this->options['bp_registration_groups_autojoin_groups'] ) : array();

		echo '<div style="max-height: 320px; overflow-y: auto; border: 1px solid #c3c4c7; background: #fff; padding: 0 12px; max-width: 640px;">';
		echo '<table class="widefat striped" style="border: none;"><thead><tr>';
		/* translators: column header for the group name in the per-group settings table */
		echo '<th>' . esc_html__( 'Group', 'buddypress-registration-groups-1' ) . '</th>';
		/* translators: column header for hiding a group from the registration form */
		echo '<th>' . esc_html__( 'Hide', 'buddypress-registration-groups-1' ) . '</th>';
		/* translators: column header for pre-checking a group on the registration form */
		echo '<th>' . esc_html__( 'Checked by default', 'buddypress-registration-groups-1' ) . '</th>';
		/* translators: column header for automatically joining new users to a group */
		echo '<th>' . esc_html__( 'Auto-join', 'buddypress-registration-groups-1' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $groups['groups'] as $group ) {
			$group_id     = absint( $group->id );
			$is_hidden    = 'hidden' === $group->status;
			$status_label = $is_hidden ? ' <em>(' . esc_html( $group->status ) . ')</em>' : ( 'private' === $group->status ? ' <em>(' . esc_html( $group->status ) . ')</em>' : '' );

			echo '<tr>';
			echo '<td>' . esc_html( $group->name ) . $status_label . '</td>';
			// Status-hidden groups never appear on the form, so the Hide and
			// Checked columns do not apply to them; Auto-join still does.
			if ( $is_hidden ) {
				echo '<td>&mdash;</td><td>&mdash;</td>';
			} else {
				printf(
					'<td><input type="checkbox" name="bp_registration_groups_option_handle[bp_registration_groups_hidden_groups][]" value="%1$d"%2$s aria-label="%3$s" /></td>',
					$group_id,
					checked( in_array( $group_id, $hidden_ids, true ), true, false ),
					/* translators: %s: group name. Accessible label for the per-group Hide checkbox */
					esc_attr( sprintf( __( 'Hide %s from the registration form', 'buddypress-registration-groups-1' ), $group->name ) )
				);
				printf(
					'<td><input type="checkbox" name="bp_registration_groups_option_handle[bp_registration_groups_checked_groups][]" value="%1$d"%2$s aria-label="%3$s" /></td>',
					$group_id,
					checked( in_array( $group_id, $checked_ids, true ), true, false ),
					/* translators: %s: group name. Accessible label for the per-group Checked-by-default checkbox */
					esc_attr( sprintf( __( 'Check %s by default on the registration form', 'buddypress-registration-groups-1' ), $group->name ) )
				);
			}
			printf(
				'<td><input type="checkbox" name="bp_registration_groups_option_handle[bp_registration_groups_autojoin_groups][]" value="%1$d"%2$s aria-label="%3$s" /></td>',
				$group_id,
				checked( in_array( $group_id, $autojoin_ids, true ), true, false ),
				/* translators: %s: group name. Accessible label for the per-group Auto-join checkbox */
				esc_attr( sprintf( __( 'Automatically join new users to %s', 'buddypress-registration-groups-1' ), $group->name ) )
			);
			echo '</tr>';
		}

		echo '</tbody></table></div>';
  }

  /**
   * Get the settings option array and print one of its values
   */
  public function bp_registration_groups_autojoin_display_callback()
  {
		$autojoin_display_options = array(
			/* translators: displays the text for hiding auto-join groups from the registration form list */
			'0' => __( 'Do not show auto-join groups on the registration form (default)', 'buddypress-registration-groups-1' ),
			/* translators: displays the text for showing auto-join groups as locked entries on the registration form */
			'1' => __( 'Show auto-join groups as pre-checked, locked entries labeled "(automatic)"', 'buddypress-registration-groups-1' ),
		);

		$current = ( isset( $this->options['bp_registration_groups_autojoin_display'] ) && '1' == $this->options['bp_registration_groups_autojoin_display'] ) ? '1' : '0';

		$rows = array();
		foreach ( $autojoin_display_options as $value => $label ) {
			$rows[] = sprintf(
				'<label><input type="radio" %s name="bp_registration_groups_option_handle[bp_registration_groups_autojoin_display]" value="%s"> %s</label>',
				checked( $current, $value, false ),
				esc_attr( $value ),
				esc_html( $label )
			);
		}
		echo implode( '<br />', $rows ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rows are escaped above.
  }

  /**
   * Print the curated group sections section text
   */
  public function print_sections_section_info()
  {
		/* translators: displays the help text for the "Group Sections" section of the plugin admin page */
		esc_html_e( 'Optionally organize the registration form into multiple titled sections (for example "Interests" and "Regions"), each offering only the groups you assign to it. While at least one section exists, the form shows only assigned groups, in the section order below; a group assigned to more than one section stays in the first section that lists it. With the Radio Buttons display, registrants can select one group per section. The per-group Hide, Checked by default, and Auto-join settings above still apply, and the "Number of Groups to Display" limit is ignored. Leave all sections empty to keep the single global list.', 'buddypress-registration-groups-1' );
  }

  /**
   * Render the curated group sections editor: every saved section plus one
   * blank row for adding a new section. Sections are reordered with the
   * Position number and removed with the Remove checkbox; both take effect
   * when the settings are saved.
   */
  public function bp_registration_groups_sections_callback()
  {
		$groups = groups_get_groups( array(
			'type'        => 'alphabetical',
			'show_hidden' => true,
			'per_page'    => null,
			'page'        => null,
		) );
		$groups = ! empty( $groups['groups'] ) ? $groups['groups'] : array();

		$sections    = bp_registration_groups_get_sections();
		$saved_count = count( $sections );

		// one blank trailing row to add a new section
		$sections[] = array( 'title' => '', 'description' => '', 'groups' => array() );

		foreach ( $sections as $index => $section ) {
			$is_new = $index >= $saved_count;
			$legend = $is_new
				/* translators: legend of the blank row used to add a new group section on the plugin admin page */
				? __( 'Add a new section', 'buddypress-registration-groups-1' )
				/* translators: %d: section position. Legend of an existing group section row on the plugin admin page */
				: sprintf( __( 'Section %d', 'buddypress-registration-groups-1' ), $index + 1 );

			echo '<fieldset style="border: 1px solid #c3c4c7; background: #fff; padding: 8px 12px 12px; margin: 0 0 12px; max-width: 640px;">';
			echo '<legend style="font-weight: 600; padding: 0 4px;">' . esc_html( $legend ) . '</legend>';

			printf(
				'<p><label>%1$s <input type="number" min="1" step="1" style="width: 5em;" name="bp_registration_groups_option_handle[bp_registration_groups_sections][%2$d][position]" value="%3$d" /></label></p>',
				/* translators: label of the ordering field of a group section row on the plugin admin page */
				esc_html__( 'Position', 'buddypress-registration-groups-1' ),
				$index,
				$index + 1
			);

			printf(
				'<p><label>%1$s<br /><input type="text" class="regular-text" name="bp_registration_groups_option_handle[bp_registration_groups_sections][%2$d][title]" value="%3$s" /></label></p>',
				/* translators: label of the title field of a group section row on the plugin admin page */
				esc_html__( 'Section title', 'buddypress-registration-groups-1' ),
				$index,
				esc_attr( $section['title'] )
			);

			printf(
				'<p><label>%1$s<br /><input type="text" class="regular-text" name="bp_registration_groups_option_handle[bp_registration_groups_sections][%2$d][description]" value="%3$s" /></label></p>',
				/* translators: label of the optional description field of a group section row on the plugin admin page */
				esc_html__( 'Section description (optional)', 'buddypress-registration-groups-1' ),
				$index,
				esc_attr( $section['description'] )
			);

			/* translators: label of the group assignment list of a group section row on the plugin admin page */
			echo '<p style="margin-bottom: 4px;">' . esc_html__( 'Groups in this section', 'buddypress-registration-groups-1' ) . '</p>';
			if ( empty( $groups ) ) {
				/* translators: shown in a group section row on the plugin admin page when the site has no groups yet */
				echo '<em>' . esc_html__( 'No groups exist yet.', 'buddypress-registration-groups-1' ) . '</em>';
			} else {
				echo '<div style="max-height: 160px; overflow-y: auto; border: 1px solid #c3c4c7; padding: 4px 8px;">';
				foreach ( $groups as $group ) {
					// Status-hidden groups are never shown on the registration
					// form, so offering them here would only mislead.
					if ( 'hidden' === $group->status ) {
						continue;
					}
					printf(
						'<label style="display: block;"><input type="checkbox" name="bp_registration_groups_option_handle[bp_registration_groups_sections][%1$d][groups][]" value="%2$d"%3$s /> %4$s%5$s</label>',
						$index,
						absint( $group->id ),
						checked( in_array( absint( $group->id ), $section['groups'], true ), true, false ),
						esc_html( $group->name ),
						'private' === $group->status ? ' <em>(' . esc_html( $group->status ) . ')</em>' : ''
					);
				}
				echo '</div>';
			}

			if ( ! $is_new ) {
				printf(
					'<p style="margin-bottom: 0;"><label><input type="checkbox" name="bp_registration_groups_option_handle[bp_registration_groups_sections][%1$d][remove]" value="1" /> %2$s</label></p>',
					$index,
					/* translators: label of the checkbox that removes a group section row on the plugin admin page when the settings are saved */
					esc_html__( 'Remove this section on save', 'buddypress-registration-groups-1' )
				);
			}

			echo '</fieldset>';
		}
  }
}

if( is_admin() )
    $bp_registration_groups_settings_page = new BPRegistrationGroupsSettingsPage();
