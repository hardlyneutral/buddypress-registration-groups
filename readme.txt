=== BuddyPress Registration Groups ===
Contributors: hardlyneutral
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=TYJT5VMV8YMVQ
Tags: buddypress, groups, registration, autojoin, multisite
Requires at least: 6.1
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.5.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Allows a new BuddyPress user to select groups to join during the registration process.

== Description ==

This plugin is built to display BuddyPress groups on the new user registration page. Groups can be displayed as:

* a list of checkboxes
* a list of checkboxes in a scrollable container
* a list of radio buttons

New users will automatically join any of the groups selected during the registration process.

Options are available in the admin area to configure the title of the groups list on the registration page, the
description of the groups list, whether private groups are visible to new users, the order in which groups are
displayed, and how many groups will be visible.

Per-group options let you fine-tune individual groups: hide a group from the registration form, pre-check a group
by default, or mark a group as auto-join so every new user becomes a member automatically. Auto-join groups can be
left off the form entirely or shown as pre-checked, locked entries labeled "(automatic)".

An optional "Require Group Selection" setting (off by default) prevents signup from completing until the registrant
selects at least one group. The requirement is validated on the server when the form is submitted, works with both
checkbox and radio button display modes, and is skipped automatically if no selectable groups exist so registration
is never blocked by a misconfiguration.

Optional Group Sections let you organize the registration form into multiple titled sections — for example
"Interests" and "Regions" — each with its own description and its own curated set of groups. While at least one
section is configured, the form offers only the groups you assigned, each in exactly one section, and with the
radio button display registrants can select one group per section. Leave the sections empty to keep the classic
single list; existing installations render exactly as before.

Requires BuddyPress with the Groups component enabled (BuddyPress 7.0 or newer recommended; tested with BuddyPress 14.5).

== Installation ==

The plugin is packaged so that you can use the built in plugin installer in the WordPress admin section. Just select the
.zip file and install away! Activate the plugin once it is installed.

If you would like to install manually:

1. Extract the .zip file
2. Upload the extracted directory and all its contents to the '/wp-content/plugins/' directory
3. Activate the plugin through the 'Plugins' menu in WordPress

== Custom Styles ==
This plugin includes specific CSS for changing the way content is displayed. The default CSS will place the group after the "Profile Details" section and before the "Complete Sign Up" button on the register page. You can view `includes/styles.css` in this plugin directory to see how to target each element.

Here is a list of the current selectors used in `includes/styles.css`.

**Groups section:**
`
#registration-groups-section {
	float: right;
	width: 48%;
}
`

**Checkboxes:**
`
.reg_groups_group_checkbox {
	margin: 0 5px 0 0;
}
`

**Form labels:**
`
#buddypress .standard-form label.reg_groups_group_label,
.reg_groups_group_label {
	display: inline;
	font-weight: normal;
}
`

**Groups section - responsive:**
`
@media only screen and (max-width: 480px) {
	#registration-groups-section {
		float: none;
		width: 100%;
	}
}
`

**List items:**
`
.reg_groups_item {
	list-style: none;
}
`

**No groups message:**
`
.reg_groups_none {
	font-style: italic;
	color: gray;
}
`

**Multiselect:**
`
.reg_groups_list_multiselect {
    height: 8em;
		border: 1px solid #ccc;
		background: #fafafa;
		padding: 6px;
    overflow: auto;
}
`

**Accessibility fieldsets (invisible wrappers naming each group of inputs for screen readers):**
`
#buddypress #registration-groups-section .reg_groups_fieldset,
#registration-groups-section .reg_groups_fieldset {
	border: 0;
	padding: 0;
	margin: 0;
	min-width: 0;
	background: transparent;
}
`

**Curated group sections:**
`
#buddypress #registration-groups-section .reg_groups_section,
#registration-groups-section .reg_groups_section {
	margin: 0 0 12px;
}

#buddypress .reg_groups_section_title,
.reg_groups_section_title {
	margin: 0 0 2px;
	font-size: 1.2em;
	font-weight: 600;
}

.reg_groups_section_description {
	margin: 0 0 6px;
	font-size: 0.85em;
	font-style: italic;
	color: gray;
}
`

**Groups section - BP Nouveau template pack:**
`
#buddypress .layout-wrap #registration-groups-section {
	float: none;
	width: 100%;
	flex: 1 100%;
}
`

== Frequently Asked Questions ==

= Does this plugin show Private groups? =

Yes! You can toggle private group visibility on and off in the admin section

= Does this plugin show Hidden groups? =

No. Hidden groups are never displayed on the registration form. If you mark a hidden group as auto-join in the per-group options, new users join it silently at activation — its name is still never shown on the form.

= Can I require new users to select a group? =

Yes. Enable "Require Group Selection" on the plugin settings page. Signup then cannot be completed until the
registrant selects at least one group offered on the form; an inline error appears next to the group list otherwise.
Hidden and auto-join groups do not count toward the requirement. If no selectable groups exist, the requirement is
skipped (and a warning is shown on the settings page) so registration is never locked. The requirement is enforced
on the server for both the browser signup form and the BuddyPress REST signup endpoint.

= Can I split the group list into more than one box, like "Interests" and "Regions"? =

Yes. Use the "Group Sections" panel on the plugin settings page to create ordered sections, each with a title, an
optional description, and the groups you assign to it. While at least one section exists, the registration form
shows only assigned groups, grouped under their section headings; a group can live in only one section (the first
section that lists it wins). Checkboxes allow any number of selections across sections, and radio buttons allow
one selection per section. Selections from every section are combined and joined at activation, and the per-group
Hide, Checked by default, and Auto-join settings still apply.

= Does "Number of Groups to Display" limit which groups can be joined? =

No — it only trims how many groups the form shows. With the Active, Popular, and Random display orders the visible
subset changes between page loads, so the limit is not treated as an eligibility rule when a signup is validated.
To make a specific group unselectable at registration, use the per-group Hide option (or leave it out of every
Group Section).

= What if the plugin doesn't work? =

Use the WordPress plugin support form (http://wordpress.org/support/plugin/buddypress-registration-groups-1). I only do this in my spare time, so don't expect a super quick response :)

= Why don't I see the group list on the registration page? =

Make sure BuddyPress is installed and active, the Groups component is enabled (Settings > BuddyPress > Components), and at least one public group exists. The plugin shows an admin notice when BuddyPress is active but the Groups component is disabled.

== Screenshots ==
1. Groups shown as a list of checkboxes on the new user registration page.
2. Groups shown as a list of checkboxes in a scrollable container on the new user registration page.
3. Groups shown as a list of radio buttons on the new user registration page.
4. The admin settings page, including the per-group options table and the Group Sections editor.
5. Per-group options in action: an auto-join group shown as a locked "(automatic)" entry and a group checked by default.
6. The "Require Group Selection" setting in action: signup blocked with an inline error until at least one group is selected.
7. Group Sections in action: the registration form organized into curated "Interests" and "Activities" sections, each with its own title, description, and assigned groups.

== Changelog ==
= 1.5.2 =
* Security and hardening follow-up to 1.5.1, after a second independent review found signup paths the browser-form hooks did not cover. Every fix was verified on a live WordPress 7.0 + BuddyPress 14.5 install with forged browser and REST submissions.
* Security: signups created through the BuddyPress REST API are now held to the same rules as the browser form. "Require Group Selection" is enforced on REST signups (the REST endpoint does not run the browser form's validation, so this was previously bypassable), and REST group selections are validated and, in radio mode, capped exactly as on the form.
* Fix: with a radio-button display, the "one choice" (or one choice per Group Section) rule is now enforced on the server, not just in the browser. A crafted submission can no longer join several groups from a single radio list.
* Fix: a private group marked Auto-join with locked display on is no longer named on the registration form while "Show Private Groups" is off (it is still joined silently). A group marked both Hide and Auto-join is likewise never named. Both now match the settings help text.
* Hardening: submitted group IDs — from the form, the REST API, and the settings screen alike — are parsed strictly (plain digit values only); forged payload shapes are dropped instead of being coerced into unrelated group IDs.
* Hardening: the "Display As", "Show Private Groups", "Require Group Selection", and "Auto-Join Display" settings now store only their documented values, so a forged value cannot make the admin screen and the registration form disagree.
* New: a `bp_registration_groups_join_failed` action fires if a group membership cannot be created at activation, so a silent failure can be logged, alerted, or retried.
* Expanded the regression suite (four new test files) and upgraded the test stubs to model REST requests, hook priority and accepted-argument counts, and injectable membership-join failures.

= 1.5.1 =
* Hardening, bug-fix, and accessibility release following a full end-to-end audit of the signup flow on a live WordPress 7.0 + BuddyPress 14.5 install, including adversarial testing with forged form submissions.
* Hardening: submitted group IDs are now parsed strictly (plain digit values only); forged payload shapes are dropped instead of being coerced into unrelated group IDs. The coerced IDs always had to pass the full eligibility checks, so this was never a way into a hidden or private group.
* Fix: a group marked both Hide and Auto-join is no longer named as a locked "(automatic)" entry in the single-list display — it is joined silently, as the sections display and the settings help text always said.
* Fix: the registration form now ignores BuddyPress's group-directory query-string arguments (?num=, ?grpage=, ?s=), which could resize or swap the configured group list.
* Fix: with a display limit and a shifting order (Active, Popular, Random), a failed signup no longer loses the registrant's group selection on the re-rendered form — a still-eligible selection the requery left out is appended, checked.
* Accessibility: each group list is now wrapped in an invisible fieldset named by the visible title, section titles and descriptions, and the inline required-selection error, so screen readers announce which question each checkbox or radio group answers. The visual layout is unchanged.
* The "Display As" setting now stores only its three supported values, and the Title/Description fallback logic states its intent explicitly (an emptied field restores the advertised default, exactly as before).
* Refreshed the translation template (.pot), which had not been regenerated since 1.3.0 and was missing every string added in 1.4.0 and 1.5.0.
* Expanded the regression suite (six new test files) covering strict ID parsing, locked auto-join display in the single list, query-string override neutralization, selection preservation under display limits, and the require-selection and sections interactions.

= 1.5.0 =
* New: Group Sections. Organize the registration form into multiple ordered, titled sections (each with an optional description), and curate exactly which groups each section offers. Requested in the wordpress.org support forum.
* While at least one section is configured, only assigned groups appear on the form; a group assigned to more than one section is kept in the first section (duplicates never render). No taxonomy plugin is required — groups are assigned explicitly.
* With the radio button display, each section is its own radio group, so registrants can select one group per section; checkboxes allow any number of selections across sections. Selections from every section are sanitized, aggregated once into the signup meta, and joined at activation.
* The per-group Hide, Checked by default, and Auto-join settings apply inside sections, and hidden, private (when not shown), or nonexistent groups are never exposed even when assigned to a section.
* Existing installations are unaffected until sections are configured: with no sections defined, the single global list renders exactly as before.
* Refreshed all screenshots on current WordPress/BuddyPress and added a seventh showing Group Sections in action; the settings screenshot now includes the Group Sections editor.

= 1.4.0 =
* New: "Require Group Selection" setting (off by default). When enabled, signup cannot be completed until the registrant selects at least one group, validated server-side on the BuddyPress signup flow (single site and multisite). Requested in the wordpress.org support forum.
* An accessible inline error is shown next to the group list when the requirement is not met, and hidden, private (when not shown), nonexistent, and auto-join group IDs never satisfy it.
* The registrant's group selections are now preserved when signup validation fails for any reason (previously the list reset to the admin defaults).
* If the requirement is enabled but no selectable groups exist, it is skipped so registration is never blocked, and a warning is shown on the plugin settings page.
* Added a regression test suite for the signup validation, signup meta, and group eligibility logic (in the tests directory of the source repository; not shipped in the plugin zip).
* Refreshed the admin settings screenshot to include the new setting and added a sixth screenshot showing the inline error in action.

= 1.3.0 =
* Compatibility release. Tested as working with WordPress 7.0, BuddyPress 14.5, and PHP 8.4, on both single site and multisite.
* New: per-group options. Hide individual groups from the registration form, pre-check groups by default, or mark groups as auto-join so every new user becomes a member automatically at activation. Auto-join groups can be left off the form or shown as pre-checked, locked entries labeled "(automatic)".
* New: plugin icon artwork for the wordpress.org directory (in the .wordpress-org directory of the source repository).
* Refreshed all screenshots on current WordPress/BuddyPress and added a fifth showing the per-group options in action. Screenshots moved to the wordpress.org assets directory and no longer ship inside the plugin zip.
* Fixed group joining on single-site (non-multisite) installs. BuddyPress 14 stopped creating the user account at signup time, which caused group selections to be silently lost. Selections are now stored in the signup meta and applied when the account is activated — the same flow on single site and multisite.
* Fixed the group list not appearing when the Extended Profiles component is disabled. The list now also hooks 'bp_before_registration_submit_buttons' as a fallback location.
* Fixed the groups section being squeezed into a narrow column with the BP Nouveau template pack.
* Security hardening: submitted group selections are now sanitized and validated against the groups the form actually offers, so crafted submissions can no longer auto-join hidden groups (or private groups when "Show Private Groups" is off).
* Removed the "Most Forum Topics" and "Most Forum Posts" display orders; BuddyPress removed these years ago and silently sorted by activity instead. Saved settings using them are treated as "Active".
* Groups are now queried with the modern BuddyPress 'status' argument and without a separate count query.
* Fixed an admin settings bug where the "Display As" setting always rendered "Checkboxes Multiselect" as selected.
* Fixed HTML validity and accessibility issues: mismatched heading tags, labels not linked to their inputs, and the "no groups" message nested inside the list element.
* Escaped all output and sanitized all input per current WordPress plugin guidelines.
* The stylesheet now loads only on the registration page and carries a version for cache busting.
* Updated plugin headers and readme metadata (Requires PHP, License URI, tags, plain-version stable tag).

= 1.2.1 =
* Maintenance update.
* Added CSS documentation to the readme.
* Removed the minimum BuddyPress version number to address version management in the WordPress plugins repository.

= 1.2.0 =
* New minor version!
* BP Registration Groups has been internationalized and can now be translated into other languages!
* Anyone can help translate this plugin by suggesting new translations [here](https://translate.wordpress.org/projects/wp-plugins/buddypress-registration-groups-1).
* You can find more information about [WordPress internationalization here](https://codex.wordpress.org/I18n_for_WordPress_Developers).

= 1.1.3 =
* Maintenance release. Tested plugin as functional with both WordPress 4.9.2 and BuddyPress 2.9.2. Safe to upgrade.
* Removed a deprecated call to screen_icon() in the admin settings

= 1.1.2 =
* Maintenance release. Tested plugin as functional with the recent BuddyPress 2.4.3 update. Safe to upgrade.
* Updated screenshots.
* Updated plugin description text.

= 1.1.1 =
* Added an option to display groups as radio buttons

= 1.1.0 =
* Tested plugin as functional with WordPress 4.4 and BuddyPress 2.4.2
* Fixed a bug where list bullets would show up in some themes
* Added the ability to switch between a list of checkboxes and a list of checkboxes in a scrollable container

= 1.0.3 =
* Tested plugin as functional with WordPress 4.2.2 and BuddyPress 2.3.1

= 1.0.2 =
* Tested plugin as functional with WordPress 4.1.1 and BuddyPress 2.2.2.1
* Addressed an issue with labels appearing below their checkboxes caused by a change to the BuddyPress core CSS
* Added reg_groups_group_checkbox and reg_groups_group_label CSS classes to their respective form elements for easier targeting

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

= 1.5.2 =
* Security and hardening follow-up to 1.5.1: closes a REST-signup bypass of "Require Group Selection" and enforces radio-button limits on the server. No new settings and no visual changes. Recommended for all sites.

= 1.5.1 =
* Hardening, bug-fix, and accessibility release after a full end-to-end audit on live WordPress and BuddyPress. No new settings and no visual changes. Safe to upgrade.

= 1.5.0 =
* Adds optional Group Sections for organizing the registration form into multiple titled group lists. No behavior changes unless sections are configured. Safe to upgrade.

= 1.4.0 =
* Adds the optional "Require Group Selection" setting and preserves group selections when signup validation fails. No behavior changes unless the new setting is enabled. Safe to upgrade.

= 1.3.0 =
* Major compatibility release for current WordPress and BuddyPress. Fixes group joining on single-site installs, which had been broken since BuddyPress 14. Upgrade immediately.

= 1.2.1 =
* Maintenance update. No changes to core functionality. Safe to upgrade.

= 1.2.0 =
* New minor version! Includes internationalization. No changes to core functionality. Safe to upgrade.

= 1.1.3 =
* Maintenance release. Tested plugin as functional with both WordPress 4.9.2 and BuddyPress 2.9.2. Safe to upgrade.

= 1.1.2 =
* Maintenance release. Tested plugin as functional with the recent BuddyPress 2.4.3 update. Safe to upgrade.

= 1.1.1 =
* Added an option to display groups as radio buttons. Safe to upgrade.

= 1.1.0 =
* Tested plugin as functional with WordPress 4.4 and BuddyPress 2.4.2. Safe to upgrade.

= 1.0.3 =
* Tested plugin as functional with WordPress 4.2.2 and BuddyPress 2.3.1. Safe to upgrade.

= 1.0.2 =
* Bug fix for CSS display issue. Tested plugin as functional with WordPress 4.1.1 and BuddyPress 2.2.2.1. Safe to upgrade.

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
