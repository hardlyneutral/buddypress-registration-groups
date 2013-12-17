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
	
	// set $bp_registration_groups_show_private_groups to array( 'public', 'private' ) if the stored option is "1" or array( 'public', 'private' ) otherwise
	$bp_registration_groups_show_private_groups = ( !isset($bp_registration_groups_options['bp_registration_groups_show_private_groups']) || $bp_registration_groups_options['bp_registration_groups_show_private_groups'] != '1' ) ? array( 'public' ) : array ( 'public', 'private' );
	
	// bp_registration_groups_number_displayed
	$bp_registration_groups_number_displayed = ( isset( $bp_registration_groups_options['bp_registration_groups_number_displayed'] ) && $bp_registration_groups_options['bp_registration_groups_number_displayed'] != NULL ) ? $bp_registration_groups_options['bp_registration_groups_number_displayed'] : groups_get_total_group_count();
	
	/* list groups */ ?>
		<div class="register-section" id="registration-groups-section">
			<h4 class="reg_groups_title"><?php _e( $bp_registration_groups_title, 'buddypress-registration-groups-1' ); ?></h3>
			<p class="reg_groups_description"><?php _e( $bp_registration_groups_description.':', 'buddypress-registration-groups-1' ); ?></p>
			<ul class="reg_groups_list">
				<?php $i = 0; $l = 0; ?>
				<?php if ( bp_has_groups('type='.$bp_registration_groups_display_order.'&per_page='.groups_get_total_group_count() ) ) : while ( bp_groups() && $l < $bp_registration_groups_number_displayed ) : bp_the_group(); ?>
					<?php if ( in_array( bp_get_group_status(), $bp_registration_groups_show_private_groups, true ) ) { ?>
					<li class="reg_groups_item">
						<input type="checkbox" id="field_reg_groups_<?php echo $i; ?>" name="field_reg_groups[]" value="<?php bp_group_id(); ?>" /><label for="field_reg_groups[]"><?php printf( __( '%s', 'buddypress-registration-groups-1' ), bp_get_group_name() ); ?></label>
					</li>
					<?php $l++; ?>
					<?php } ?>
				<?php $i++; ?>
				<?php endwhile; /* endif; */ ?>
				<?php else: ?>
				<p class="reg_groups_none"><?php _e( 'No groups are available at this time.', 'buddypress-registration-groups-1' ); ?></p>
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
            'BP Registration Groups Settings', 
            'BP Registration Groups', 
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
            <?php screen_icon(); ?>
            <h2>BP Registration Groups</h2>           
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
            'bp_registration_groups_display_options_section_id', // ID
            'Display Options', // Title
            array( $this, 'print_display_options_section_info' ), // Callback
            'bp-registration-groups-settings-admin' // Page
        );  

        add_settings_field(
            'bp_registration_groups_title', // ID
            'Title', // Title 
            array( $this, 'bp_registration_groups_title_callback' ), // Callback
            'bp-registration-groups-settings-admin', // Page
            'bp_registration_groups_display_options_section_id' // Section           
        );      

        add_settings_field(
            'bp_registration_groups_description', 
            'Description', 
            array( $this, 'bp_registration_groups_description_callback' ), 
            'bp-registration-groups-settings-admin', 
            'bp_registration_groups_display_options_section_id'
        );   

        add_settings_field(
            'bp_registration_groups_display_order', 
            'Display Order', 
            array( $this, 'bp_registration_groups_display_order_callback' ), 
            'bp-registration-groups-settings-admin', 
            'bp_registration_groups_display_options_section_id'
        );

        add_settings_field(
            'bp_registration_groups_show_private_groups', 
            'Show Private Groups', 
            array( $this, 'bp_registration_groups_show_private_groups_callback' ), 
            'bp-registration-groups-settings-admin', 
            'bp_registration_groups_display_options_section_id'
        );

        add_settings_field(
            'bp_registration_groups_number_displayed', 
            'Number of Groups to Display', 
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
        _e( 'Change the title and description text displayed before the group list:', 'buddypress-registration-groups-1' );
    }

    /** 
     * Get the settings option array and print one of its values
     */
    public function bp_registration_groups_title_callback()
    {
        printf(
            '<input type="text" id="bp_registration_groups_title" name="bp_registration_groups_option_handle[bp_registration_groups_title]" value="%s" />',
            isset( $this->options['bp_registration_groups_title'] ) ? esc_attr( $this->options['bp_registration_groups_title']) : ''
        );
        _e( '<br /><em>Default: Groups</em>' );
    }

    /** 
     * Get the settings option array and print one of its values
     */
    public function bp_registration_groups_description_callback()
    {
        printf(
            '<input type="text" id="bp_registration_groups_description" name="bp_registration_groups_option_handle[bp_registration_groups_description]" value="%s" />',
            isset( $this->options['bp_registration_groups_description'] ) ? esc_attr( $this->options['bp_registration_groups_description']) : ''
        );
        _e( '<br /><em>Default: Check one or more areas of interest</em>' );
    }
    
    /** 
     * Get the settings option array and print one of its values
     *
     * Options are the same as bp_has_groups: active, newest, popular, random, alphabetical, most-forum-topics, most-forum-posts
     */
    public function bp_registration_groups_display_order_callback()
    {
    	printf(
			'<input type="radio" %s name="bp_registration_groups_option_handle[bp_registration_groups_display_order]" value="alphabetical"> Alphabetical (default)',
			!isset($this->options['bp_registration_groups_display_order']) || ( isset($this->options['bp_registration_groups_display_order']) && $this->options['bp_registration_groups_display_order'] == 'alphabetical' ) ? 'checked="checked"' : ''
    	);
    	
    	_e( '<br />' );
    	
    	printf(
			'<input type="radio" %s name="bp_registration_groups_option_handle[bp_registration_groups_display_order]" value="active"> Active',
			isset($this->options['bp_registration_groups_display_order']) && $this->options['bp_registration_groups_display_order'] == 'active' ? 'checked="checked"' : ''
    	);
    	
    	_e( '<br />' );
    	
    	printf(
			'<input type="radio" %s name="bp_registration_groups_option_handle[bp_registration_groups_display_order]" value="newest"> Newest',
			isset($this->options['bp_registration_groups_display_order']) && $this->options['bp_registration_groups_display_order'] == 'newest' ? 'checked="checked"' : ''
    	);
    	
    	_e( '<br />' );
    	
    	printf(
			'<input type="radio" %s name="bp_registration_groups_option_handle[bp_registration_groups_display_order]" value="popular"> Popular',
			isset($this->options['bp_registration_groups_display_order']) && $this->options['bp_registration_groups_display_order'] == 'popular' ? 'checked="checked"' : ''
    	);
    	
    	_e( '<br />' );
    	
    	printf(
			'<input type="radio" %s name="bp_registration_groups_option_handle[bp_registration_groups_display_order]" value="random"> Random',
			isset($this->options['bp_registration_groups_display_order']) && $this->options['bp_registration_groups_display_order'] == 'random' ? 'checked="checked"' : ''
    	);
    	
    	_e( '<br />' );

    	printf(
			'<input type="radio" %s name="bp_registration_groups_option_handle[bp_registration_groups_display_order]" value="most-forum-topics"> Most Forum Topics',
			isset($this->options['bp_registration_groups_display_order']) && $this->options['bp_registration_groups_display_order'] == 'most-forum-topics' ? 'checked="checked"' : ''
    	);
    	
    	_e( '<br />' );
    	
    	printf(
			'<input type="radio" %s name="bp_registration_groups_option_handle[bp_registration_groups_display_order]" value="most-forum-posts"> Most Forum Posts',
			isset($this->options['bp_registration_groups_display_order']) && $this->options['bp_registration_groups_display_order'] == 'most-forum-posts' ? 'checked="checked"' : ''
    	);
    }

    /** 
     * Get the settings option array and print one of its values
     */
    public function bp_registration_groups_show_private_groups_callback()
    {
    	printf(
			'<input type="radio" %s name="bp_registration_groups_option_handle[bp_registration_groups_show_private_groups]" value="1"> Yes',
			isset($this->options['bp_registration_groups_show_private_groups']) && $this->options['bp_registration_groups_show_private_groups'] == '1' ? 'checked="checked"' : ''
    	);
    	
    	_e( '<br />' );
    	
    	printf(
			'<input type="radio" %s name="bp_registration_groups_option_handle[bp_registration_groups_show_private_groups]" value="0"> No (default)',
			!isset($this->options['bp_registration_groups_show_private_groups']) || ( isset($this->options['bp_registration_groups_show_private_groups']) && $this->options['bp_registration_groups_show_private_groups'] != '1' ) ? 'checked="checked"' : ''
    	);
    }
    
    /** 
     * Get the settings option array and print one of its values
     */
    public function bp_registration_groups_number_displayed_callback()
    {
        printf(
            '<input type="text" id="bp_registration_groups_number_displayed" name="bp_registration_groups_option_handle[bp_registration_groups_number_displayed]" value="%s" />',
            isset( $this->options['bp_registration_groups_number_displayed'] ) ? esc_attr( $this->options['bp_registration_groups_number_displayed']) : ''
        );
        _e( '<br /><em>Default: 10;  0 = All</em>' );
    }
}

if( is_admin() )
    $bp_registration_groups_settings_page = new BPRegistrationGroupsSettingsPage();