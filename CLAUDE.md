# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a WordPress plugin called "BuddyPress Registration Groups" that allows new BuddyPress users to select groups to join during the registration process. The plugin integrates with BuddyPress to display group selection options on the registration form.

## Architecture

### Core Components

- **loader.php**: Main plugin loader that checks for BuddyPress availability and initializes the plugin
- **includes/bp-registration-groups.php**: Core plugin functionality including:
  - Group display on registration forms
  - User metadata handling for group selections
  - Admin settings interface
  - Group joining logic for multisite and single-site WordPress installations
- **includes/styles.css**: Plugin-specific CSS for registration form styling

### Key Functions

- `bp_registration_groups()`: Displays group selection interface on registration page (includes/bp-registration-groups.php:26)
- `bp_registration_groups_save()`: Saves group selections for multisite environments (includes/bp-registration-groups.php:85)
- `bp_registration_groups_save_s()`: Saves group selections for single-site environments (includes/bp-registration-groups.php:98)
- `bp_registration_groups_join()`: Auto-joins users to selected groups on activation (multisite) (includes/bp-registration-groups.php:111)
- `bp_registration_groups_join_s()`: Auto-joins users to selected groups on activation (single-site) (includes/bp-registration-groups.php:131)

### Plugin Architecture

The plugin uses WordPress hooks and BuddyPress actions:
- Hooks into `bp_after_signup_profile_fields` to display group selection
- Uses `bp_signup_usermeta` filter for multisite group data storage
- Uses `bp_core_activated_user` action to auto-join groups after activation
- Implements separate handlers for multisite vs single-site WordPress installations

### Settings System

The plugin includes a comprehensive admin settings page (`BPRegistrationGroupsSettingsPage` class) that allows configuration of:
- Group list title and description
- Group display order (alphabetical, active, newest, popular, random, etc.)
- Display format (checkboxes, multiselect checkboxes, radio buttons)
- Private group visibility
- Number of groups to display

## Development Guidelines

### WordPress/BuddyPress Integration

- This plugin requires BuddyPress 1.3+ to function
- Uses BuddyPress group functions like `bp_has_groups()`, `groups_join_group()`, and `groups_get_group()`
- Implements proper WordPress internationalization with text domain 'buddypress-registration-groups-1'
- Follows WordPress coding standards for security (sanitization, escaping)

### Multisite Considerations

The plugin handles both single-site and multisite WordPress installations with separate code paths:
- Multisite: Uses `bp_signup_usermeta` filter and `bp_core_activated_user` action
- Single-site: Uses `bp_core_signup_user` action and direct user meta operations

### CSS Styling

The plugin includes responsive CSS that integrates with BuddyPress registration forms:
- Uses specific CSS classes for targeting form elements
- Implements responsive design for mobile devices
- Provides customizable styling for different display modes