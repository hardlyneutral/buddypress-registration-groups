=== BuddyPress Registration Groups ===
Plugin URI: http://hardlyneutral.com/wordpress-plugins/
Version: 1.0.1
Tags: wordpress, multisite, buddypress, groups, registration, autojoin
Requires at least: WordPress 3.7.1 / BuddyPress 1.8.1
Tested up to: WordPress 3.8 / BuddyPress 1.9
License: GNU/GPL 2
Author: Eric Johnson
Author URI: http://hardlyneutral.com/wordpress-plugins/
Contributors: hardlyneutral
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=TYJT5VMV8YMVQ
Stable tag: trunk

Allows a new BuddyPress user to select groups to join during the registration process.

== Description ==

This plugin is built to display BuddyPress groups on the new user registration page in a list where the user can
select, via checkbox, which groups they would like to join upon account activation. Options are available in the
admin area to configure the text shown on the registration page, the types of groups shown (public or public AND
private), the order in which groups are displayed, and how many groups will be displayed.

== Installation ==

The plugin is packaged so that you can use the built in plugin installer in the WordPress admin section. Just select the
.zip file and install away! Activate the plugin once it is installed.

If you would like to install manually:

1. Extract the .zip file
2. Upload the extracted directory and all its contents to the '/wp-content/plugins/' directory
3. Activate the plugin through the 'Plugins' menu in WordPress

== Frequently Asked Questions ==

= Does this plugin show Private groups? =

Yes! You can toggle private group visibility on and off in the admin section

= Does this plugin show Hidden groups? =

No, it does not. The BuddyPress core makes it a bit difficult to easily get these groups without being a logged in user. This might change in the future. If it does, hidden groups will be supported.

= What if the plugin doesn't work? =

Hit me up on my website (http://hardlyneutral.com) and let me know. I only do this in my spare time, so don't expect a super quick response :)

== Screenshots ==
1. Screenshot of the plugin listing groups on the new user registration page.
2. Screenshot of the admin settings menu and options.

== Changelog ==

= 1.0.1 =
* Tested plugin as functional with WordPress 3.8 and BuddyPress 1.9

= 1.0 =
* Prepared echoed and printed text for localization
* Added semantic <label> markup to the checkbox list
* Changed the "bp_has_groups()" per_page option to use "groups_get_total_group_count()" instead of a static number
* Added an admin settings page! Woo hoo!
* Added the ability to change the section title that is displayed
* Added the ability to change the description text that is displayed
* Added the ability to display groups sorted by the same options as "bp_has_groups()": active, newest, popular, random, alphabetical, most-forum-topics, most-forum-posts
* Added the ability to toggle the display of private groups
* Added the ability to specify the number of groups to display

= 0.9 =
* Removed all trailing "?>" tags from .php files
* Beefed up the loader a bit
* Enqueued styles correctly
* Added responsive styles
* Styles are now enqueued at all times as guessing the registration template name is not guaranteed
* Replaced deprecated function "update_usermeta" with "update_user_meta"
* Replaced deprecated function "get_usermeta" with "get_user_meta"
* Added a short FAQ

= 0.8 =
* Validated plugin is compatible with BuddyPress 1.5
* Modified plugin listing to remove 20 group limit; limit is now 99999

= 0.7 =
* Validated plugin is compatible with WordPress 3.2.1 and BuddyPress 1.2.9
* Changed default group listing to only show public groups, hidden and private groups are not shown

= 0.6 =
* Fixed a bug where the timeline would not record group names correctly on join
* There is a known issue with user avatars not displaying in the timeline when joining on registration, plugin works fine otherwise

= 0.5 =
* Changed group ordering on the registration page to alphabetical

= 0.4 =
* Replaced static link to plugin .css file with a dynamic one
* Addressed minor styling issue
* Addressed error that was being thrown if no groups were selected

= 0.3 =
* Tested as functional on WordPress 3.0 and BuddyPress 1.2.5.2
* Tested as functional in both WP3 single and multisite installations

= 0.2 =
* Updated plugin to work in single and multiuser environments
* Tested as functional on WordPress 2.9.2 and BuddyPress 1.2.5.2
* Tested as functional on WordPress MU 2.9.2 and BuddyPress 1.2.5.2
* Added a readme.txt
* Added loader.php to prevent plugin from loading if BuddyPress is not active
* Added includes directory
* Moved bp-registration-groups.php to includes directory
* Added plugin specific CSS file to includes directory
* Added code to only load CSS on the registration page

= 0.1 =
* First version!

== Upgrade Notice ==

= 1.0.1 =
* Tested plugin as functional with WordPress 3.8 and BuddyPress 1.9. Safe to upgrade.

= 1.0 =
This version is a major update that adds a brand new admin section!

= 0.9 =
This version is a major update that replaces deprecated calls, fixes compatibility issues with the latest versions of WordPress and BuddyPress, and improves code quality. Upgrade immediately.

= 0.8 =
This version addresses an issue with only showing 20 groups on the registration page. See Changelog for details.

= 0.6 =
This version addresses an issue with group names not displaying correctly in the timeline. Upgrade immediately.

= 0.5 =
This version changes the display order of groups on the registration page to alphabetical.

= 0.4 =
This version addresses a minor styling issue and an error shown on user activation if no groups were selected during registration. Upgrade immediately.

= 0.3 =
This version addresses several functionality issues. Upgrade immediately.