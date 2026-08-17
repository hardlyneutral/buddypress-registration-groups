---
name: wp-sandbox
description: >
  Stand up a live WordPress + BuddyPress sandbox inside a Claude Code cloud
  session — no MySQL, no Docker — to verify this plugin end-to-end against a
  real BuddyPress signup flow and/or capture the wordpress.org screenshots.
  Use whenever asked to take/refresh screenshots, test a change "live" or "for
  real", reproduce a bug on an actual install, or verify the registration flow
  beyond the unit tests in tests/.
---

# WordPress + BuddyPress sandbox

Everything runs from PHP alone: WordPress on `php -S` with a router script,
the SQLite database drop-in instead of MySQL, and wp-cli as a phar. Total
setup is a few minutes. The same sandbox serves two purposes:

1. **Live end-to-end verification** — the unit suite in `tests/` stubs
   WordPress/BuddyPress, so a real install is the only true test of the full
   signup → activation → group-join flow.
2. **wordpress.org screenshots** — the published set in `.wordpress-org/` was
   captured on a sandbox built exactly this way; follow the conventions below
   and refreshed shots will match it.

Work in a scratch directory outside the repo (e.g. the session scratchpad),
never inside the repo checkout. Nothing from the sandbox gets committed except
refreshed files in `.wordpress-org/`.

## Step 0 — pick versions and a path

Use the latest stable release of each component. Find tags without hitting
wordpress.org, e.g.:

```bash
git ls-remote --tags https://github.com/WordPress/WordPress.git | tail -20
git ls-remote --tags https://github.com/buddypress/buddypress.git | tail -20
git ls-remote --tags https://github.com/WordPress/sqlite-database-integration.git | tail -20
```

Then check whether wordpress.org is reachable:

```bash
curl -sI --max-time 10 https://wordpress.org/ >/dev/null && echo REACHABLE || echo BLOCKED
```

- **Reachable** → use the fast path (Path A).
- **Blocked** (the default "Trusted" network policy blocks wordpress.org) →
  use the GitHub-mirror fallback (Path B). Both paths end at the same place.

> **Environment note (optional, one-time, manual):** switching the cloud
> environment's network access to **Custom** in the claude.ai/code UI —
> keeping the default package-manager list and adding `wordpress.org` and
> `*.wordpress.org` — makes Path A work and also unlocks
> `plugins.svn.wordpress.org` for release deployment. Do not depend on this;
> Path B works under the default policy.

## Step 1 — get wp-cli

`raw.githubusercontent.com` is reachable under the default policy:

```bash
cd "$SANDBOX"   # your scratch dir
curl -sSLo wp https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
chmod +x wp
```

Cloud sessions run as root, so every wp-cli call below needs `--allow-root`
(define `WP() { "$SANDBOX/wp" --path="$SANDBOX/wp" --allow-root "$@"; }` or
similar).

## Step 2 — get WordPress core

**Path A:** `./wp core download --path=wp --allow-root`

**Path B:** the `WordPress/WordPress` GitHub mirror at a release tag is
*built* core, ready to run (unlike `WordPress/wordpress-develop`):

```bash
git clone --depth 1 --branch 6.9.1 https://github.com/WordPress/WordPress.git wp
rm -rf wp/.git
```

## Step 3 — get BuddyPress

**Path A:** `WP plugin install buddypress` (after step 5's core install), or
download the zip from `downloads.wordpress.org/plugin/buddypress.X.Y.Z.zip`.

**Path B:** clone the repo at a release tag straight into the plugins dir.
The repo has no built package, but its root `bp-loader.php` detects a source
checkout and loads BuddyPress from `src/` — it works as-is:

```bash
git clone --depth 1 --branch 14.5.2 https://github.com/buddypress/buddypress.git \
  wp/wp-content/plugins/buddypress
rm -rf wp/wp-content/plugins/buddypress/.git
```

Remember the `src/` layout: some BP files referenced later live under
`buddypress/src/…` in this layout but `buddypress/…` in a release zip.

## Step 4 — SQLite drop-in (replaces MySQL)

Source: `WordPress/sqlite-database-integration` at a release tag.

**Monorepo trap:** inside the repo, the plugin's `wp-includes/database`
directory is a **symlink** to `packages/mysql-on-sqlite/src/`. Only official
release zips flatten it. Copying the plugin out of a checkout carries a
dangling symlink and the drop-in fatals — replace it with a real copy:

```bash
git clone --depth 1 --branch v2.2.4 \
  https://github.com/WordPress/sqlite-database-integration.git sqlite-repo
PLUG=wp/wp-content/plugins/sqlite-database-integration
mkdir -p "$PLUG"
cp -R sqlite-repo/. "$PLUG"/            # adjust if the plugin lives in a subdir of the repo
rm -rf "$PLUG/.git"
rm -f "$PLUG/wp-includes/database"      # the symlink
cp -R sqlite-repo/packages/mysql-on-sqlite/src "$PLUG/wp-includes/database"
# verify: must list real .php files, not a dangling link
ls "$PLUG/wp-includes/database/" | head
```

Then wire the drop-in. The plugin ships a `db.copy` template meant to be
copied to `wp-content/db.php` with its `{…}` placeholders substituted. Open
`db.copy` and check which placeholders your version uses, then substitute the
real paths — recent versions use a single `{SQLITE_MAIN_FILE}` (path to the
plugin's `load.php`); older ones use `{SQLITE_IMPLEMENTATION_FOLDER_PATH}`
(the `wp-includes/database` dir) and `{SQLITE_PLUGIN}`
(`sqlite-database-integration/load.php`):

```bash
sed -e "s#{SQLITE_MAIN_FILE}#$PWD/$PLUG/load.php#" \
    -e "s#{SQLITE_IMPLEMENTATION_FOLDER_PATH}#$PWD/$PLUG/wp-includes/database#" \
    -e "s#{SQLITE_PLUGIN}#sqlite-database-integration/load.php#" \
    "$PLUG/db.copy" > wp/wp-content/db.php
```

The database file lands in `wp-content/database/.ht.sqlite` automatically.

## Step 5 — install WordPress

Create `wp-config.php` (the DB constants are ignored by SQLite but must
exist) and install. `--skip-email` matters — there is no mailer:

```bash
WP config create --dbname=wp --dbuser=wp --dbpass=wp --skip-check
WP core install --url=http://127.0.0.1:8080 --title="BP Sandbox" \
  --admin_user=admin --admin_password=adminpass --admin_email=admin@example.test \
  --skip-email
WP theme activate twentytwentyfive
```

## Step 6 — activate plugins and install the BuddyPress schema

Symlink this repo in as the plugin (a symlink is fine under `php -S`):

```bash
ln -s /path/to/buddypress-registration-groups \
  wp/wp-content/plugins/buddypress-registration-groups
WP plugin activate buddypress buddypress-registration-groups
```

**BuddyPress trap:** BP's schema installer hangs off `admin_init`, which
wp-cli fakes in a way that never runs it — groups tables silently don't
exist. Run the installer explicitly, with the Groups component active,
and enable registration + pretty permalinks while you're in there:

```bash
WP eval '
require_once ABSPATH . "wp-admin/includes/upgrade.php";
$schema = WP_PLUGIN_DIR . "/buddypress/src/bp-core/admin/bp-core-admin-schema.php";
if ( ! file_exists( $schema ) ) { $schema = WP_PLUGIN_DIR . "/buddypress/bp-core/admin/bp-core-admin-schema.php"; }
require_once $schema;
$components = array( "members" => 1, "xprofile" => 1, "settings" => 1, "groups" => 1, "activity" => 1, "notifications" => 1 );
bp_update_option( "bp-active-components", $components );
bp_core_install( $components );
update_option( "users_can_register", 1 );
update_option( "permalink_structure", "/%postname%/" );
flush_rewrite_rules();
'
```

Sanity check: `WP db query` is unavailable under SQLite, so verify via BP
itself, e.g. `WP eval 'var_dump( function_exists("groups_create_group") && bp_is_active("groups") );'`.

## Step 7 — sample data and plugin presets (screenshot set)

The published screenshots all use the same eight public groups, created in
this order, with **Announcements marked auto-join** and **Book Club checked
by default** in the per-group options:

```bash
WP eval '
$names = array( "Announcements", "Book Club", "Cooking", "Cycling", "Gardening", "Swimming", "Trail Runners", "Yoga" );
$ids = array();
foreach ( $names as $name ) {
  $ids[ $name ] = groups_create_group( array(
    "name" => $name, "slug" => sanitize_title( $name ),
    "description" => $name . " group", "status" => "public",
  ) );
}
update_option( "bp_registration_groups_option_handle", array(
  "bp_registration_groups_title"            => "Groups",
  "bp_registration_groups_description"      => "Check one or more areas of interest",
  "bp_registration_groups_display_order"    => "alphabetical",
  "bp_registration_groups_display_as"       => "1",  // 1 checkboxes, 2 multiselect, 3 radio
  "bp_registration_groups_show_private_groups" => "0",
  "bp_registration_groups_number_displayed" => 0,
  "bp_registration_groups_require_selection" => "0",
  "bp_registration_groups_autojoin_display" => "0",
  "bp_registration_groups_checked_groups"   => array( $ids["Book Club"] ),
  "bp_registration_groups_autojoin_groups"  => array( $ids["Announcements"] ),
) );
'
```

The option handle is `bp_registration_groups_option_handle`; per-shot
variations below only flip `display_as`, `require_selection`, and
`autojoin_display` within it.

## Step 8 — serve it

`php -S` needs a mod_rewrite-style router for pretty permalinks. Save as
`$SANDBOX/router.php`:

```php
<?php
$root = __DIR__ . '/wp';
$path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
if ( $path !== '/' && file_exists( $root . $path ) && ! is_dir( $root . $path ) ) {
    return false; // serve the static file / direct .php as-is
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
require $root . '/index.php';
```

```bash
php -S 127.0.0.1:8080 router.php >server.log 2>&1 &
curl -s http://127.0.0.1:8080/register/ | grep -q registration-groups-section && echo OK
```

Watch `server.log` for PHP notices/warnings from the plugin — a clean log is
part of "verified live".

## Live end-to-end verification

Exercise the real flow, not just the render: load `/register/`, submit the
form with groups selected (BuddyPress requires its signup nonce, so drive a
real browser via Playwright or fetch the form and replay the nonce), then
activate and confirm membership:

```bash
WP eval '
$signups = BP_Signup::get( array( "number" => 1 ) );
$key = $signups["signups"][0]->activation_key;
bp_core_activate_signup( $key );
'
WP eval '$u = get_user_by( "login", "testuser" ); var_dump( groups_is_user_member( $u->ID, GROUP_ID ) );'
```

Also verify the negative paths relevant to the change (e.g. with
`require_selection` on, an empty submission must re-render with the inline
`role="alert"` error and preserved selections).

## Screenshot conventions (wordpress.org set)

Captures go to `.wordpress-org/screenshot-N.png`; captions live in the
numbered list under `== Screenshots ==` in `readme.txt` — keep both in sync.
Fixed conventions, matching the published set:

- Theme **Twenty Twenty-Five**, the eight sample groups above.
- Playwright against the preinstalled Chromium:
  `chromium.launch({ executablePath: '/opt/pw-browsers/chromium' })` — never
  `playwright install`.
- `deviceScaleFactor: 2` everywhere (output px = 2 × CSS px).
- **Registration-form shots (1, 2, 3, 5, 6):** logged-out visit to
  `/register/`, clip a **701-CSS-px-wide** region (→ 1402 px output) framing
  the groups section plus a sliver of the password field above and the
  Complete Sign Up button below. Heights vary by content; keep them in the
  roughly 750–960 CSS px range of the existing set.
- **Settings shot (4):** log in, open
  `wp-admin/options-general.php?page=bp-registration-groups`, element
  screenshot of `.wrap` sized to **1078 CSS px wide** (→ 2156 px output).

Per-shot state (everything else stays at the Step 7 baseline):

| # | Shows | Settings |
|---|-------|----------|
| 1 | Checkbox list | `display_as=1`; per-group lists emptied (all 8 plain, unchecked) |
| 2 | Scrollable multiselect | `display_as=2`; per-group lists emptied |
| 3 | Radio buttons | `display_as=3`; per-group lists emptied |
| 4 | Admin settings page | `display_as=1`, `require_selection=1`, `autojoin_display=1`, baseline per-group rows (Announcements auto-join, Book Club checked) |
| 5 | Per-group options live | `display_as=1`, `autojoin_display=1` → Announcements as locked "(automatic)", Book Club pre-checked |
| 6 | Required-selection error | `display_as=1`, `require_selection=1`, `autojoin_display=0` (7 groups, no Announcements); submit with nothing selected, capture the re-render with the inline error |

After capturing, verify dimensions match the set
(`file .wordpress-org/screenshot-*.png` — 1402-wide form shots, 2156-wide
settings shot) and update captions/changelog in `readme.txt` if shots were
added or changed.

## Scope notes

- This skill (and everything under `.claude/`) is source-repo-only and must
  never ship in the plugin zip or be copied to SVN `trunk` — same handling as
  `tests/` and `.wordpress-org/`.
- Deliberately **not** done via environment setup scripts or SessionStart
  hooks: those would burden every session for an occasional need. The sandbox
  is built on demand by following this skill.
