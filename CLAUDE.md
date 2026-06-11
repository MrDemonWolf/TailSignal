# TailSignal - Project Conventions

## Overview

TailSignal is a self-hosted WordPress plugin that sends push notifications to mobile devices via the Expo Push Service. It replaces third-party services like OneSignal, keeping all data on the site owner's server.

## Tech Stack

- PHP 7.4+ / WordPress 6.0+
- Expo Push API via `ctwillie/expo-server-sdk-php`
- PHPUnit 9.x + Brain Monkey + Mockery for tests
- Tailwind CSS (pre-compiled with `tw-` prefix) for admin UI
- Fumadocs + Next.js for documentation site

## Repository Structure

```
├── src/                              # WordPress plugin source
│   ├── tailsignal.php                # Plugin bootstrap (defines constants, loads files)
│   ├── uninstall.php                 # Clean removal of all tables, options, capabilities, post meta
│   ├── composer.json                 # Production deps only (Expo SDK)
│   ├── includes/
│   │   ├── class-tailsignal.php            # Core orchestrator (loads deps, registers hooks)
│   │   ├── class-tailsignal-loader.php     # Hook/filter registration helper
│   │   ├── class-tailsignal-i18n.php       # Internationalization
│   │   ├── class-tailsignal-db.php         # All database schema + CRUD (static methods)
│   │   ├── class-tailsignal-activator.php  # Activation: create tables, set defaults, add caps
│   │   ├── class-tailsignal-deactivator.php# Deactivation: clear cron events
│   │   ├── class-tailsignal-expo.php       # Expo Push Service wrapper (singleton)
│   │   ├── class-tailsignal-notification.php # Notification builder, sender, scheduler
│   │   └── class-tailsignal-cron.php       # WP-Cron receipt checking + scheduled sends
│   ├── rest-api/
│   │   └── class-tailsignal-rest-controller.php  # All REST endpoints under tailsignal/v1
│   ├── admin/
│   │   ├── class-tailsignal-admin.php            # Menu registration, script/style enqueueing
│   │   ├── class-tailsignal-admin-dashboard.php  # Dashboard stats page
│   │   ├── class-tailsignal-admin-send.php       # Send/schedule notification + AJAX handlers
│   │   ├── class-tailsignal-admin-devices.php    # Devices list (WP_List_Table)
│   │   ├── class-tailsignal-admin-groups.php     # Groups management + AJAX handlers
│   │   ├── class-tailsignal-admin-history.php    # Notification history (WP_List_Table)
│   │   ├── class-tailsignal-admin-settings.php   # Settings page (WordPress Settings API)
│   │   ├── class-tailsignal-meta-box.php         # Post editor meta box (auto-notify + quick send)
│   │   ├── css/tailsignal-admin.css
│   │   ├── js/tailsignal-admin.js
│   │   └── partials/*.php                        # Page template files
│   └── vendor/                       # Composer dependencies (committed for distribution)
├── docs/                             # Fumadocs documentation site
│   ├── content/docs/                 # MDX documentation pages
│   ├── src/                          # Next.js app source
│   └── package.json
├── tests/
│   ├── bootstrap.php       # Defines WP constants, stub classes, loads autoloader from src/
│   ├── TestCase.php         # Base class with Brain Monkey setUp/tearDown + common stubs
│   └── test-*.php           # 19 test files
├── composer.json             # Dev dependencies (PHPUnit, Brain Monkey, Mockery)
├── package.json              # npm workspaces (src + docs)
├── Makefile                  # Build commands
└── phpunit.xml.dist          # PHPUnit configuration
```

## Code Style

- WordPress PHP Coding Standards (tabs, Yoda conditions, etc.)
- Class-based architecture: `TailSignal_ClassName` naming convention
- Files: `class-tailsignal-name.php` (lowercase, hyphenated)
- All DB tables use `{$wpdb->prefix}tailsignal_` prefix
- All WP options use `tailsignal_` prefix
- Custom capability: `tailsignal_manage` (added to administrator role on activation)
- REST namespace: `tailsignal/v1`

## Database

6 custom tables (all prefixed `{$wpdb->prefix}tailsignal_`):

| Table | Purpose |
|-------|---------|
| `devices` | Registered push tokens with device metadata |
| `device_meta` | Extensible key/value metadata per device (WP meta pattern) |
| `groups` | Named device groups (e.g., "Beta Testers") |
| `device_groups` | Pivot table linking devices to groups |
| `notifications` | All notification records with status, targeting, receipts |
| `notification_history` | Links posts to their notification sends |

- Schema version tracked via `tailsignal_db_version` option
- Clean uninstall drops all 6 tables, deletes all `tailsignal_*` options, removes capabilities, cleans post meta
- All DB operations are static methods on `TailSignal_DB`

## WordPress Options

| Option | Default | Purpose |
|--------|---------|---------|
| `tailsignal_auto_notify` | `'1'` | Auto-send on new post publish |
| `tailsignal_expo_access_token` | `''` | Optional Expo auth token |
| `tailsignal_default_title` | `'New from {site_name}'` | Title template |
| `tailsignal_default_body` | `'{post_title}'` | Body template |
| `tailsignal_use_featured_image` | `'1'` | Include post featured image |
| `tailsignal_dev_mode` | `'0'` | Only send to is_dev=1 devices |
| `tailsignal_db_version` | `TAILSIGNAL_VERSION` | Schema migration tracking |
| `tailsignal_portfolio_auto_notify` | `'1'` | Auto-send on portfolio publish |
| `tailsignal_portfolio_default_title` | `'New Project: {post_title}'` | Portfolio title template |
| `tailsignal_portfolio_default_body` | `'{post_title} by {author_name}'` | Portfolio body template |
| `tailsignal_portfolio_use_featured_image` | `'1'` | Portfolio featured image |

## REST API Endpoints

| Method | Route | Auth | Purpose |
|--------|-------|------|---------|
| POST | `/tailsignal/v1/register` | Public | Register/update device token |
| DELETE | `/tailsignal/v1/register` | Public | Unregister device |
| POST | `/tailsignal/v1/send` | `tailsignal_manage` | Send or schedule notification |
| GET | `/tailsignal/v1/stats` | `tailsignal_manage` | Dashboard statistics |
| GET | `/tailsignal/v1/devices/export` | `tailsignal_manage` | Export devices CSV |
| POST | `/tailsignal/v1/devices/import` | `tailsignal_manage` | Import devices CSV |

## Template Placeholders

Used in notification title/body templates:

| Placeholder | Resolves to |
|-------------|-------------|
| `{post_title}` | Post title |
| `{post_excerpt}` | First 20 words of content |
| `{site_name}` | WordPress site name |
| `{author_name}` | Post author display name |
| `{category}` | Primary category name |

## Testing

- Run tests: `make test` or `composer test`
- 295 tests, 527 assertions across 19 test files
- Brain Monkey mocks all WordPress functions (`add_action`, `get_option`, etc.)
- Mockery mocks `$wpdb` and Expo SDK
- `tests/bootstrap.php` defines stub classes: `WP_Error`, `WP_REST_Server`, `WP_REST_Response`, `WP_REST_Request`
- `tests/TestCase.php` stubs common WP functions: `sanitize_text_field`, `esc_html`, `wp_parse_args`, `current_time`, etc.
- `tests/bootstrap.php` loads autoloader from `src/vendor/autoload.php`

### Testing patterns

- DB tests: `global $wpdb; $wpdb = Mockery::mock('wpdb'); $wpdb->prefix = 'wp_';`
- WP function mocking: `Functions\expect('get_option')->with('key')->andReturn('value');`
- Multiple calls to same WP function with different args: use `andReturnUsing()` callback instead of multiple `expect()` calls
- PHP constants (`define()`) persist across test methods — avoid testing `DOING_AUTOSAVE` or similar without process isolation
- Activator tests need `$wpdb->shouldReceive('get_charset_collate')->andReturn('')`

## Building

- `make css` — compiles Tailwind CSS (via npm workspace)
- `make zip` — produces `build/tailsignal.zip` from `src/` (production, no dev deps)
- `make test` — installs root dev deps and runs PHPUnit
- `make clean` — removes build directory
- `src/.distignore` controls what's excluded from the ZIP
- `npm run docs:dev` — local docs dev server
- `npm run docs:build` — static docs export to `docs/out/`

## CI/CD (GitHub Actions)

| Workflow | Trigger | What it does |
|----------|---------|--------------|
| `test.yml` | Push/PR to main | Tests on PHP 7.4, 8.0, 8.1, 8.2, 8.3 + composer validate + composer audit |
| `build-zip.yml` | PR to main only | Builds plugin ZIP as artifact (dev builds) |
| `release.yml` | GitHub release published | Runs full test matrix, then builds and attaches ZIP to release |
| `deploy-docs.yml` | Push to main (docs/**) | Builds and deploys docs to GitHub Pages |

## Key Patterns

- Hooks registered via `TailSignal_Loader` (actions + filters arrays, fired in `run()`)
- Post notifications hook into `transition_post_status` (detects `publish` transition, not updates)
- Portfolio post type supported alongside posts (filterable via `tailsignal_post_types`)
- Post-type-specific templates: portfolio uses `tailsignal_portfolio_*` options
- Notification data includes `post_id`, `post_type`, and `url` for deep linking
- Scheduling uses `wp_schedule_single_event` with `tailsignal_send_scheduled` hook
- Scheduled datetimes are site-local; always convert via `get_gmt_from_date( $dt, 'U' )` (WP pins PHP to UTC, so `strtotime()` skews by the site's UTC offset)
- Receipt checking via WP-Cron 15 minutes after each send
- TailSignal_Expo chunks pushes to 100 messages/request and receipt checks to 1000 IDs/request (the bundled SDK does NOT chunk; Expo rejects oversized requests)
- `notifications.ticket_ids` stores a `{ticket_id: expo_token}` JSON map (legacy rows may hold a plain list) so receipt checking can deactivate stale tokens
- `DeviceNotRegistered` errors auto-deactivate stale tokens (at send time and via receipts)
- A send with zero successes is recorded as `status='failed'`, not `'sent'`
- Admin pages use pre-compiled Tailwind CSS with `tw-` prefix to avoid WP conflicts
- All admin content wrapped in `#tailsignal-app` container
- AJAX handlers use `wp_send_json_success` / `wp_send_json_error`
- Local asset bundling: no CDN dependencies (Tailwind CSS compiled locally, Chart.js vendored)
- Batch DB queries: `get_device_summary_stats()`, `get_devices_groups_bulk()` to avoid N+1
- Additional DB methods: `get_monthly_notification_stats()`, `delete_all_notifications()`, `import_devices()`
- History page has `handle_delete_all()` AJAX handler for bulk notification deletion
- Send page has iOS/Android preview toggle

## CRITICAL DOCUMENTATION PATTERN

**ALWAYS ADD IMPORTANT DOCS HERE!** When you create or discover:

- Architecture diagrams → Add reference path here
- Database schemas → Add reference path here
- Problem solutions → Add reference path here
- Setup guides → Add reference path here

This prevents context loss! Update this file IMMEDIATELY when creating important docs.
