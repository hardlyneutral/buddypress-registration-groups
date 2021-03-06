buddypress-registration-groups
==============================
Contributors: @hardlyneutral

Tags: wordpress, multisite, buddypress, groups, registration, autojoin

Requires at least: WordPress 3.7.1

Tested up to: WordPress 4.9.2

License: GNU/GPL 2

Stable tag: 1.2.1

The BuddyPress Registration Groups plugin also lives in the official WordPress plugins repository here: [http://wordpress.org/plugins/buddypress-registration-groups-1/](http://wordpress.org/plugins/buddypress-registration-groups-1/).

Description
-----------
This plugin is built to display BuddyPress groups on the new user registration page. Groups can be displayed as:

* a list of checkboxes
* a list of checkboxes in a scrollable container
* a list of radio buttons

New users will automatically join any of the groups selected during the registration process.

Options are available in the admin area to configure the title of the groups list on the registration page, the
description of the groups list, whether private groups are visible to new users, the order in which groups are
displayed, and how many groups will be visible.

Installation
------------
The plugin is packaged so that you can use the built in plugin installer in the WordPress admin section. Just select the .zip file and install away! Activate the plugin once it is installed.

If you would like to install manually:

1. Extract the .zip file
2. Upload the extracted directory and all its contents to the '/wp-content/plugins/' directory
3. Activate the plugin through the 'Plugins' menu in WordPress

Custom Styles
-------------
This plugin includes specific CSS for changing the way content is displayed. The default CSS will place the group after the "Profile Details" section and before the "Complete Sign Up" button on the register page. You can view ```includes/styles.css``` in this plugin directory to see how to target each element.

Here is a list of the current selectors used in ```includes/styles.css```.

**Groups section:**
```
#registration-groups-section {
	float: right;
	width: 48%;
}
```

**Checkboxes:**
```
.reg_groups_group_checkbox {
	margin: 0 5px 0 0;
}
```

**Form labels:**
```
#buddypress .standard-form label.reg_groups_group_label,
.reg_groups_group_label {
	display: inline;
	font-weight: normal;
}
```

**Groups section - responsive:**
```
@media only screen and (max-width: 480px) {
	#registration-groups-section {
		float: none;
		width: 100%;
	}
}
```

**List items:**
```
.reg_groups_item {
	list-style: none;
}
```

**No groups message:**
```
.reg_groups_none {
	font-style: italic;
	color: gray;
}
```

**Multiselect:**
```
.reg_groups_list_multiselect {
    height: 8em;
		border: 1px solid #ccc;
		background: #fafafa;
		padding: 6px;
    overflow: auto;
}
```

Frequently Asked Questions
--------------------------
### Does this plugin show Private groups?
Yes! You can toggle private group visibility on and off in the admin section

### Does this plugin show Hidden groups?
No, it does not. The BuddyPress core makes it a bit difficult to easily get these groups without being a logged in user. This might change in the future. If it does, hidden groups will be supported.

### What if the plugin doesn't work?
Use the WordPress plugin [support form here](http://wordpress.org/support/plugin/buddypress-registration-groups-1). I only do this in my spare time, so don't expect a super quick response :)

Changelog
---------
### 1.2.1
* Maintenance update.
* Added CSS documentation to the readme.
* Removed the minimum BuddyPress version number to address version management in the WordPress plugins repository.

### 1.2.0
* New minor version!
* BP Registration Groups has been internationalized and can now be translated into other languages!
* Anyone can help translate this plugin by suggesting new translations [here](https://translate.wordpress.org/projects/wp-plugins/buddypress-registration-groups-1).
* You can find more information about [WordPress internationalization here](https://codex.wordpress.org/I18n_for_WordPress_Developers).

### 1.1.3
* Maintenance release. Tested plugin as functional with both WordPress 4.9.2 and BuddyPress 2.9.2. Safe to upgrade.
* Removed a deprecated call to screen_icon() in the admin settings

### 1.1.2
* Maintenance release. Tested plugin as functional with the recent BuddyPress 2.4.3 update. Safe to upgrade.
* Updated screenshots.
* Updated plugin description text.

### 1.1.1
* Added an option to display groups as radio buttons

### 1.1.0
* Tested plugin as functional with WordPress 4.4 and BuddyPress 2.4.2
* Fixed a bug where list bullets would show up in some themes
* Added the ability to switch between a list of checkboxes and a list of checkboxes in a scrollable container

### 1.0.3
* Tested plugin as functional with WordPress 4.2.2 and BuddyPress 2.3.1

### 1.0.2
* Tested plugin as functional with WordPress 4.1.1 and BuddyPress 2.2.2.1
* Addressed an issue with labels appearing below their checkboxes caused by a change to the BuddyPress core CSS
* Added reg_groups_group_checkbox and reg_groups_group_label CSS classes to their respective form elements for easier targeting

### 1.0.1
* Tested plugin as functional with WordPress 3.8 and BuddyPress 1.9

### 1.0
* Prepared echoed and printed text for localization
* Added semantic <label> markup to the checkbox list
* Changed the "bp_has_groups()" per_page option to use "groups_get_total_group_count()" instead of a static number
* Added an admin settings page! Woo hoo!
* Added the ability to change the section title that is displayed
* Added the ability to change the description text that is displayed
* Added the ability to display groups sorted by the same options as "bp_has_groups()": active, newest, popular, random, alphabetical, most-forum-topics, most-forum-posts
* Added the ability to toggle the display of private groups
* Added the ability to specify the number of groups to display

### 0.9
* Removed all trailing "?>" tags from .php files
* Beefed up the loader a bit
* Enqueued styles correctly
* Added responsive styles
* Styles are now enqueued at all times as guessing the registration template name is not guaranteed
* Replaced deprecated function "update_usermeta" with "update_user_meta"
* Replaced deprecated function "get_usermeta" with "get_user_meta"
* Added a short FAQ

### 0.8
* Validated plugin is compatible with BuddyPress 1.5
* Modified plugin listing to remove 20 group limit; limit is now 99999

### 0.7
* Validated plugin is compatible with WordPress 3.2.1 and BuddyPress 1.2.9
* Changed default group listing to only show public groups, hidden and private groups are not shown

### 0.6
* Fixed a bug where the timeline would not record group names correctly on join
* There is a known issue with user avatars not displaying in the timeline when joining on registration, plugin works fine otherwise

### 0.5
* Changed group ordering on the registration page to alphabetical

### 0.4
* Replaced static link to plugin .css file with a dynamic one
* Addressed minor styling issue
* Addressed error that was being thrown if no groups were selected

### 0.3
* Tested as functional on WordPress 3.0 and BuddyPress 1.2.5.2
* Tested as functional in both WP3 single and multisite installations

### 0.2
* Updated plugin to work in single and multiuser environments
* Tested as functional on WordPress 2.9.2 and BuddyPress 1.2.5.2
* Tested as functional on WordPress MU 2.9.2 and BuddyPress 1.2.5.2
* Added a readme.txt
* Added loader.php to prevent plugin from loading if BuddyPress is not active
* Added includes directory
* Moved bp-registration-groups.php to includes directory
* Added plugin specific CSS file to includes directory
* Added code to only load CSS on the registration page

### 0.1
* First version!
