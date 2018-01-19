<?php

/**
* Enqueue plugin scripts and styles
*/

add_action( 'wp_enqueue_scripts', 'bp_registration_groups_enqueue_scripts' );
function bp_registration_groups_enqueue_scripts() {
	wp_register_style( 'bp_registration_groups_styles', plugins_url('/styles.css', __FILE__) );
 	wp_enqueue_style( 'bp_registration_groups_styles' );
}

if (is_multisite()) { add_filter( 'bp_signup_usermeta', 'bp_registration_groups_save' ); }
else { add_action( 'bp_core_signup_user', 'bp_registration_groups_save_s' ); }

if (is_multisite()) { add_action( 'bp_core_activated_user', 'bp_registration_groups_join', 10, 3 ); }
else { add_action( 'bp_core_activated_user', 'bp_registration_groups_join_s' ); }

/**
* bp_registration_groups
*
* Add list of public groups to registration page. Display a message
* stating no groups are available if no public groups are found.
*/
add_action('bp_after_signup_profile_fields', 'bp_registration_groups');
function bp_registration_groups(){

	// get the BP Registration Groups options array from the WP options table
	$bp_registration_groups_options = get_option('bp_registration_groups_option_handle');

	// set $bp_registration_groups_title to the stored value; fall back to 'Groups' if no value is stored
	$bp_registration_groups_title = ( isset( $bp_registration_groups_options['bp_registration_groups_title'] ) && $bp_registration_groups_options['bp_registration_groups_title'] != NULL ) ? $bp_registration_groups_options['bp_registration_groups_title'] : 'Groups';

	// set $bp_registration_groups_description to the stored value; fall back to 'Check one or more areas of interest' if no value is stored
	$bp_registration_groups_description = ( isset( $bp_registration_groups_options['bp_registration_groups_description'] ) && $bp_registration_groups_options['bp_registration_groups_description'] != NULL ) ? $bp_registration_groups_options['bp_registration_groups_description'] : 'Check one or more areas of interest';

	// set $bp_registration_groups_display_order to the stored value if it is in the $bp_registration_groups_display_order_options array; fall back to 'alphabetical' otherwise
	$bp_registration_groups_display_order_options = array( 'active', 'newest', 'popular', 'random', 'alphabetical', 'most-forum-topics', 'most-forum-posts' );
	$bp_registration_groups_display_order = ( isset( $bp_registration_groups_options['bp_registration_groups_display_order'] ) && in_array($bp_registration_groups_options['bp_registration_groups_display_order'], $bp_registration_groups_display_order_options, true) ) ? $bp_registration_groups_options['bp_registration_groups_display_order'] : 'alphabetical';

	// set $bp_registration_groups_display_as to 'reg_groups_list_multiselect' if the stored value is 2; set to 'reg_groups_list' otherwise
	$bp_registration_groups_display_as = ( isset( $bp_registration_groups_options['bp_registration_groups_display_as'] ) && $bp_registration_groups_options['bp_registration_groups_display_as'] != '2' ) ? 'reg_groups_list' : 'reg_groups_list_multiselect';

	// set $bp_registration_groups_input_type to 'radio' if the stored value is not 1; set to 'checkbox' otherwise
	$bp_registration_groups_input_type = ( isset( $bp_registration_groups_options['bp_registration_groups_display_as'] ) && $bp_registration_groups_options['bp_registration_groups_display_as'] == '3' ) ? 'radio' : 'checkbox';

	// set $bp_registration_groups_show_private_groups to array( 'public', 'private' ) if the stored option is "1" or array( 'public', 'private' ) otherwise
	$bp_registration_groups_show_private_groups = ( !isset($bp_registration_groups_options['bp_registration_groups_show_private_groups']) || $bp_registration_groups_options['bp_registration_groups_show_private_groups'] != '1' ) ? array( 'public' ) : array ( 'public', 'private' );

	// bp_registration_groups_number_displayed
	$bp_registration_groups_number_displayed = ( isset( $bp_registration_groups_options['bp_registration_groups_number_displayed'] ) && $bp_registration_groups_options['bp_registration_groups_number_displayed'] != NULL ) ? $bp_registration_groups_options['bp_registration_groups_number_displayed'] : groups_get_total_group_count();

	/* list groups */ ?>
		<div class="register-section" id="registration-groups-section">
			<h4 class="reg_groups_title"><?php echo $bp_registration_groups_title; ?></h3>
			<p class="reg_groups_description"><?php echo $bp_registration_groups_description; ?></p>
			<ul class="<?php echo $bp_registration_groups_display_as; ?>">
				<?php $i = 0; $l = 0; ?>
				<?php if ( bp_has_groups('type='.$bp_registration_groups_display_order.'&per_page='.groups_get_total_group_count() ) ) : while ( bp_groups() && $l < $bp_registration_groups_number_displayed ) : bp_the_group(); ?>
					<?php if ( in_array( bp_get_group_status(), $bp_registration_groups_show_private_groups, true ) ) { ?>
					<li class="reg_groups_item">
						<input class="reg_groups_group_checkbox" type="<?php echo $bp_registration_groups_input_type; ?>" id="field_reg_groups_<?php echo $i; ?>" name="field_reg_groups[]" value="<?php bp_group_id(); ?>" /><label class="reg_groups_group_label" for="field_reg_groups[]"><?php echo bp_get_group_name(); ?></label>
					</li>
					<?php $l++; ?>
					<?php } ?>
				<?php $i++; ?>
				<?php endwhile; /* endif; */ ?>
				<?php else: ?>
				<p class="reg_groups_none">
					<?php
					/* translators: text that is displayed on the buddypress user registration form when there are no groups that can be displayed */
					_e( 'No groups are available at this time.', 'buddypress-registration-groups-1' );
					?>
				</p>
				<?php endif; ?>
			</ul>
		</div>
<?php }

/**
* bp_registration_groups_save()
*
* Save groups selected during registration in a multisite environment
*/
function bp_registration_groups_save( $usermeta ) {

	$usermeta['field_reg_groups'] = $_POST['field_reg_groups'];

	return $usermeta;

}

/**
* bp_registration_groups_save_s()
*
* Save groups selected during registration in a non-multisite environment
*/
function bp_registration_groups_save_s( $user_id ) {

	update_user_meta( $user_id, 'field_reg_groups', $_POST['field_reg_groups'] );

	return $user_id;

}

/**
* bp_registration_groups_join()
*
* Join groups when account is activated in a multisite environment
*/
function bp_registration_groups_join( $user_id, $key, $user ) {
	global $bp, $wpdb;

	$reg_groups = $user['meta']['field_reg_groups'];

	//only join groups if field_reg_groups contains any groups
	if ($reg_groups != '') {
		foreach ($reg_groups as $group_id) {
			$bp->groups->current_group = groups_get_group(array('group_id' => $group_id));
			groups_join_group($group_id, $user_id);
		}
	}

}

/**
* bp_registration_groups_join_s()
*
* Join groups when account is activate in a non-multisite user environment
*/
function bp_registration_groups_join_s( $user_id ) {
	global $bp, $wpdb;

	$reg_groups = get_user_meta( $user_id, 'field_reg_groups', true );

	//only join groups if field_reg_groups contains any groups
	if ($reg_groups != '') {
		foreach ($reg_groups as $group_id) {
			$bp->groups->current_group = groups_get_group(array('group_id' => $group_id));
			groups_join_group($group_id, $user_id);
		}
	}

	return $user_id;
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
      <h2><?php _e('BP Registration Groups', 'buddypress-registration-groups-1'); ?></h2>
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

    if( isset( $input['bp_registration_groups_display_order'] ) )
        $new_input['bp_registration_groups_display_order'] = sanitize_text_field( $input['bp_registration_groups_display_order'] );

		if( isset( $input['bp_registration_groups_display_as'] ) )
        $new_input['bp_registration_groups_display_as'] = absint( $input['bp_registration_groups_display_as'] );

    if( isset( $input['bp_registration_groups_show_private_groups'] ) )
        $new_input['bp_registration_groups_show_private_groups'] = absint( $input['bp_registration_groups_show_private_groups'] );

    if( isset( $input['bp_registration_groups_number_displayed'] ) )
        $new_input['bp_registration_groups_number_displayed'] = absint( $input['bp_registration_groups_number_displayed'] );

    return $new_input;
  }

  /**
   * Print the Section text
   */
  public function print_display_options_section_info()
  {
		/* translators: displays the help text for the "Display Options" section of the plugin admin page */
		_e( 'These options allow you to customize the list of groups on the new user registration form.', 'buddypress-registration-groups-1' );
  }

  /**
   * Get the settings option array and print one of its values
   */
  public function bp_registration_groups_title_callback()
  {
		printf( '<input type="text" id="bp_registration_groups_title" name="bp_registration_groups_option_handle[bp_registration_groups_title]" value="%s" />', isset( $this->options['bp_registration_groups_title'] ) ? esc_attr( $this->options['bp_registration_groups_title'] ) : '' );

		/* translators: displays the help text for the "Title" section of the plugin admin page */
		echo '<br /><em>' . __('Default: Groups', 'buddypress-registration-groups-1') . '</em>';
  }

  /**
   * Get the settings option array and print one of its values
   */
  public function bp_registration_groups_description_callback()
  {
		printf( '<input type="text" id="bp_registration_groups_description" name="bp_registration_groups_option_handle[bp_registration_groups_description]" value="%s" />', isset( $this->options['bp_registration_groups_description'] ) ? esc_attr( $this->options['bp_registration_groups_description'] ) : '' );

		/* translators: displays the help text for the "Description" section of the plugin admin page */
		echo '<br /><em>' . __('Default: Check one or more areas of interest', 'buddypress-registration-groups-1') . '</em>';
  }

  /**
   * Get the settings option array and print one of its values
   *
   * Options are the same as bp_has_groups: active, newest, popular, random, alphabetical, most-forum-topics, most-forum-posts
   */
  public function bp_registration_groups_display_order_callback()
  {
		/* translators: displays the text "Alphabetical (default)" in the "Display Order" section of the plugin admin page */
		printf( '<input type="radio" %s name="bp_registration_groups_option_handle[bp_registration_groups_display_order]" value="alphabetical"> %s', !isset($this->options['bp_registration_groups_display_order']) || ( isset($this->options['bp_registration_groups_display_order']) && $this->options['bp_registration_groups_display_order'] == 'alphabetical' ) ? 'checked="checked"' : '', __('Alphabetical (default)', 'buddypress-registration-groups-1') );

  	echo '<br />';

		/* translators: displays the text "Active" in the "Display Order" section of the plugin admin page */
		printf( '<input type="radio" %s name="bp_registration_groups_option_handle[bp_registration_groups_display_order]" value="active"> %s', isset($this->options['bp_registration_groups_display_order']) && $this->options['bp_registration_groups_display_order'] == 'active' ? 'checked="checked"' : '', __('Active', 'buddypress-registration-groups-1') );

  	echo '<br />';

		/* translators: displays the text "Newest" in the "Display Order" section of the plugin admin page */
		printf( '<input type="radio" %s name="bp_registration_groups_option_handle[bp_registration_groups_display_order]" value="newest"> %s', isset($this->options['bp_registration_groups_display_order']) && $this->options['bp_registration_groups_display_order'] == 'newest' ? 'checked="checked"' : '', __('Newest', 'buddypress-registration-groups-1') );

  	echo '<br />';

		/* translators: displays the text "Popular" in the "Display Order" section of the plugin admin page */
		printf( '<input type="radio" %s name="bp_registration_groups_option_handle[bp_registration_groups_display_order]" value="popular"> %s', isset($this->options['bp_registration_groups_display_order']) && $this->options['bp_registration_groups_display_order'] == 'popular' ? 'checked="checked"' : '', __('Popular', 'buddypress-registration-groups-1') );

  	echo '<br />';

		/* translators: displays the text "Random" in the "Display Order" section of the plugin admin page */
		printf( '<input type="radio" %s name="bp_registration_groups_option_handle[bp_registration_groups_display_order]" value="random"> %s', isset($this->options['bp_registration_groups_display_order']) && $this->options['bp_registration_groups_display_order'] == 'random' ? 'checked="checked"' : '', __('Random', 'buddypress-registration-groups-1') );

  	echo '<br />';

		/* translators: displays the text "Most Forum Topics" in the "Display Order" section of the plugin admin page */
		printf( '<input type="radio" %s name="bp_registration_groups_option_handle[bp_registration_groups_display_order]" value="most-forum-topics"> %s', isset($this->options['bp_registration_groups_display_order']) && $this->options['bp_registration_groups_display_order'] == 'most-forum-topics' ? 'checked="checked"' : '', __('Most Forum Topics', 'buddypress-registration-groups-1') );

  	echo '<br />';

		/* translators: displays the text "Most Forum Posts" in the "Display Order" section of the plugin admin page */
		printf( '<input type="radio" %s name="bp_registration_groups_option_handle[bp_registration_groups_display_order]" value="most-forum-posts"> %s', isset($this->options['bp_registration_groups_display_order']) && $this->options['bp_registration_groups_display_order'] == 'most-forum-posts' ? 'checked="checked"' : '', __('Most Forum Posts', 'buddypress-registration-groups-1') );
  }

	/**
   * Get the settings option array and print one of its values
   */
  public function bp_registration_groups_display_as_callback()
  {
		/* translators: displays the text "Checkboxes Multiselect (default)" in the "Display As" section of the plugin admin page */
		printf( '<input type="radio" %s name="bp_registration_groups_option_handle[bp_registration_groups_display_as]" value="2"> %s', !isset($this->options['bp_registration_groups_display_as_checkboxes']) || isset($this->options['bp_registration_groups_display_as']) && $this->options['bp_registration_groups_display_as'] == '2' ? 'checked="checked"' : '', __('Checkboxes Multiselect (default)', 'buddypress-registration-groups-1') );

  	echo '<br />';

		/* translators: displays the text "Checkboxes" in the "Display As" section of the plugin admin page */
		printf( '<input type="radio" %s name="bp_registration_groups_option_handle[bp_registration_groups_display_as]" value="1"> %s', isset($this->options['bp_registration_groups_display_as']) && $this->options['bp_registration_groups_display_as'] == '1' ? 'checked="checked"' : '', __('Checkboxes', 'buddypress-registration-groups-1') );

		echo '<br />';

		/* translators: displays the text "Radio Buttons" in the "Display As" section of the plugin admin page */
		printf( '<input type="radio" %s name="bp_registration_groups_option_handle[bp_registration_groups_display_as]" value="3"> %s', isset($this->options['bp_registration_groups_display_as']) && $this->options['bp_registration_groups_display_as'] == '3' ? 'checked="checked"' : '', __('Radio Buttons', 'buddypress-registration-groups-1') );
  }

  /**
   * Get the settings option array and print one of its values
   */
  public function bp_registration_groups_show_private_groups_callback()
  {
		/* translators: displays the text "Yes" in the "Show Private Groups" section of the plugin admin page */
		printf( '<input type="radio" %s name="bp_registration_groups_option_handle[bp_registration_groups_show_private_groups]" value="1"> %s', isset($this->options['bp_registration_groups_show_private_groups']) && $this->options['bp_registration_groups_show_private_groups'] == '1' ? 'checked="checked"' : '', __('Yes', 'buddypress-registration-groups-1') );

  	echo '<br />';

		/* translators: displays the text "No (default)" in the "Show Private Groups" section of the plugin admin page */
		printf(	'<input type="radio" %s name="bp_registration_groups_option_handle[bp_registration_groups_show_private_groups]" value="0"> %s', !isset($this->options['bp_registration_groups_show_private_groups']) || ( isset($this->options['bp_registration_groups_show_private_groups']) && $this->options['bp_registration_groups_show_private_groups'] != '1' ) ? 'checked="checked"' : '', __('No (default)', 'buddypress-registration-groups-1') );
  }

  /**
   * Get the settings option array and print one of its values
   */
  public function bp_registration_groups_number_displayed_callback()
  {
		printf( '<input type="text" id="bp_registration_groups_number_displayed" name="bp_registration_groups_option_handle[bp_registration_groups_number_displayed]" value="%d" />', isset( $this->options['bp_registration_groups_number_displayed'] ) ? esc_attr( $this->options['bp_registration_groups_number_displayed'] ) : '' );

		/* translators: displays the help text for the "Number of Groups to Display" section of the plugin admin page */
		echo '<br /><em>' . __('Default: 0 (show all groups)', 'buddypress-registration-groups-1') . '</em>';
  }
}

if( is_admin() )
    $bp_registration_groups_settings_page = new BPRegistrationGroupsSettingsPage();
