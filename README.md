# ReadMeWP

> Create role- and user-specific README documents visible in the WordPress admin menu.

[![CI](https://github.com/welbinator/readmewp/actions/workflows/ci.yml/badge.svg)](https://github.com/welbinator/readmewp/actions/workflows/ci.yml)
[![License: GPL v2](https://img.shields.io/badge/License-GPLv2-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![WordPress](https://img.shields.io/badge/WordPress-6.4%2B-blue)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-purple)](https://www.php.net/)

---

## What Is ReadMeWP?

ReadMeWP lets WordPress admins create internal README documents (guides, SOPs, onboarding docs, etc.) that appear directly in the admin sidebar. Each README is assigned to specific **roles** and/or **users**, so the right people see the right documents — and nothing else.

### Key Features

- **Custom post type** — READMEs are a native WP post type, edited in the block editor
- **Role-based access** — assign a README to one or more roles (Editor, Author, Subscriber, etc.)
- **Per-user access** — grant access to individual users regardless of role
- **Owner lock** — optionally restrict editing to the README's creator (even from admins)
- **Creator permissions** — control which roles/users can create new READMEs via the Settings page
- **Admin menu integration** — READMEs appear as submenu items; non-permissioned users see nothing
- **Read-only viewer** — clean admin-page viewer for non-editor users; no front-end exposure

---

## Requirements

| Requirement | Minimum |
|---|---|
| WordPress | 6.4 |
| PHP | 8.1 |

---

## Installation

1. Download the latest release ZIP from the [Releases page](https://github.com/welbinator/readmewp/releases).
2. In your WordPress admin, go to **Plugins → Add New → Upload Plugin**.
3. Upload the ZIP and click **Install Now**, then **Activate**.

Or via WP-CLI:
```bash
wp plugin install https://github.com/welbinator/readmewp/releases/latest/download/readmewp.zip --activate
```

---

## Usage

### Creating a README

1. Go to **ReadMeWP** in the admin sidebar and click **Add New README**.
2. Write your content in the block editor.
3. In the **Who Can Read?** meta box, check the roles and/or add specific users who should see this README.
4. In the **Edit Access** meta box, optionally enable **Owner Only** to lock editing to yourself.
5. Publish.

The README will appear as a submenu item under **ReadMeWP** for every permitted user.

### Controlling Who Can Create READMEs

Go to **Settings → ReadMeWP** to grant create access to specific roles or individual users. By default, only Administrators can create READMEs.

### Administrator Access

Administrators see a README only if the **Administrator** role checkbox is checked in that README's "Who Can Read?" panel. This checkbox is on by default for new READMEs but can be unchecked by the owner.

---

## Architecture

```
readmewp.php                  Bootstrap — constants, autoloader, Plugin class
includes/
  class-post-type.php         Registers readmewp CPT (admin-only, block editor)
  class-permissions.php       Read-access meta box, user_can_read(), get_readable_posts()
  class-ownership.php         Edit-access meta box; owner ID + owner-only flag
  class-settings.php          Settings page; creator role/user grants; AJAX user search
  class-access.php            user_has_cap filter — enforces caps and owner lock
  class-menu.php              Admin menu; CPT list + per-README submenus
  class-viewer.php            Read-only viewer page; access guard; asset enqueue
admin/
  css/admin.css               Meta box and viewer styles
  js/admin.js                 User-search autocomplete, chip UI
uninstall.php                 Removes all plugin data on deletion
```

---

## Development

### Local Setup (DDEV)

```bash
# Clone into your DDEV WordPress site's plugins directory
git clone https://github.com/welbinator/readmewp.git wp-content/plugins/readmewp
cd wp-content/plugins/readmewp

# Install PHP deps
composer install

# Install JS deps (for Playwright E2E tests)
npm install

# Start DDEV
export PATH=$HOME/bin:$PATH
ddev start
ddev wp plugin activate readmewp
```

### Running PHPUnit Tests

```bash
# First-time setup (run once per container session)
ddev setup-tests

# Run all tests
ddev test

# Run a specific test class
ddev test -- --filter TestPermissions
```

### Running Playwright E2E Tests

```bash
npm run test:e2e          # headless
npm run test:e2e:ui       # headed with Playwright UI
npm run test:e2e:debug    # interactive debug mode
```

### Code Quality

```bash
# PHP CodeSniffer (WordPress Coding Standards)
./vendor/bin/phpcs

# PHPStan static analysis
./vendor/bin/phpstan analyse
```

---

## License

[GPLv2 or later](https://www.gnu.org/licenses/gpl-2.0.html)
