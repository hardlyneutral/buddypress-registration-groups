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

	// auto-join groups shown as locked, pre-checked entries
	$bp_registration_groups_locked_groups = array();
	if ( ! empty( $bp_registration_groups_autojoin_ids ) && bp_registration_groups_autojoin_shows_locked() ) {
		foreach ( $bp_registration_groups_autojoin_ids as $bp_registration_groups_autojoin_id ) {
			$bp_registration_groups_autojoin_group = groups_get_group( $bp_registration_groups_autojoin_id );
			if ( ! empty( $bp_registration_groups_autojoin_group->id ) ) {
				$bp_registration_groups_locked_groups[] = $bp_registration_groups_autojoin_group;
			}
		}
	}

	/* list groups */ ?>
		<div class="register-section" id="registration-groups-section">
			<h4 class="reg_groups_title"><?php echo esc_html( $bp_registration_groups_title ); ?></h4>
			<p class="reg_groups_description"><?php echo esc_html( $bp_registration_groups_description ); ?></p>
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
					// Pre-check groups the admin marked as checked by default;
					// in radio mode only the first such group is checked.
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

	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- BuddyPress verifies the 'bp_new_signup' nonce before this filter runs.
	if ( ! isset( $_POST['field_reg_groups'] ) ) {
		return $usermeta;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	$group_ids = array_unique( array_filter( array_map( 'absint', (array) wp_unslash( $_POST['field_reg_groups'] ) ) ) );

	// Only keep groups the registration form actually offers: allowed status,
	// not hidden per-group, and not an auto-join group (those are joined
	// automatically and are never selectable).
	$allowed_statuses = bp_registration_groups_allowed_statuses();
	$not_selectable   = array_merge(
		bp_registration_groups_get_id_list_option( 'bp_registration_groups_hidden_groups' ),
		bp_registration_groups_get_id_list_option( 'bp_registration_groups_autojoin_groups' )
	);
	$valid_group_ids  = array();

	foreach ( $group_ids as $group_id ) {
		if ( in_array( $group_id, $not_selectable, true ) ) {
			continue;
		}

		$group = groups_get_group( $group_id );

		if ( ! empty( $group->id ) && in_array( $group->status, $allowed_statuses, true ) ) {
			$valid_group_ids[] = $group_id;
		}
	}

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

	foreach ( $selected_group_ids as $group_id ) {
		$group_id = absint( $group_id );

		if ( ! $group_id || in_array( $group_id, $hidden_group_ids, true ) ) {
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

		if( isset( $input['bp_registration_groups_display_as'] ) )
        $new_input['bp_registration_groups_display_as'] = absint( $input['bp_registration_groups_display_as'] );

    if( isset( $input['bp_registration_groups_show_private_groups'] ) )
        $new_input['bp_registration_groups_show_private_groups'] = absint( $input['bp_registration_groups_show_private_groups'] );

    if( isset( $input['bp_registration_groups_number_displayed'] ) )
        $new_input['bp_registration_groups_number_displayed'] = absint( $input['bp_registration_groups_number_displayed'] );

    // per-group ID lists; absent keys mean no boxes were checked
    foreach ( array( 'bp_registration_groups_hidden_groups', 'bp_registration_groups_checked_groups', 'bp_registration_groups_autojoin_groups' ) as $id_list_key ) {
        if ( isset( $input[ $id_list_key ] ) && is_array( $input[ $id_list_key ] ) ) {
            $new_input[ $id_list_key ] = array_values( array_unique( array_filter( array_map( 'absint', $input[ $id_list_key ] ) ) ) );
        }
    }

    if( isset( $input['bp_registration_groups_autojoin_display'] ) )
        $new_input['bp_registration_groups_autojoin_display'] = absint( $input['bp_registration_groups_autojoin_display'] );

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
   * Print the per-group options section text
   */
  public function print_per_group_options_section_info()
  {
		/* translators: displays the help text for the "Per-Group Options" section of the plugin admin page */
		esc_html_e( 'Fine-tune individual groups: hide a group from the registration form, pre-check it, or make every new user join it automatically. Auto-join groups are never selectable on the form.', 'buddypress-registration-groups-1' );
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
}

if( is_admin() )
    $bp_registration_groups_settings_page = new BPRegistrationGroupsSettingsPage();
