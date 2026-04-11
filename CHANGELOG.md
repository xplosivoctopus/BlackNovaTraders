# Changelog

All notable project changes should be recorded here.

This changelog is organized by project state and change area rather than by dated release tags.

## Current Unreleased Changes

### Security

- Enforced server-side CSRF validation for all `POST` submissions to `public/port2.php`, closing the commodity-trading write path that previously accepted missing or invalid CSRF tokens.
- Removed public access to sensitive admin and server configuration in `public/settings.php`; non-admin players now see only a minimal public game-info view instead of admin identities, addon internals, operational settings, or filesystem paths.
- Removed the legacy request-to-variable bootstrap shim from `public/global_cleanups.php` so `GET`/`POST`/`COOKIE` keys no longer become ambient local variables across the app.
- Reworked `public/teams.php` team sorting to use explicit request parsing and an allowlisted sort map instead of concatenating request-populated values into SQL.
- Added explicit request initialization to legacy pages that previously relied on ambient `$sort`, `$order`, `$type`, `$action`, or related request variables, including `public/admin.php`, `public/corp.php`, `public/create_universe.php`, `public/defence_report.php`, `public/log.php`, `public/ngai_control.php`, `public/planet_report.php`, `public/team_planets.php`, and `public/xenobe_control.php`.
- Stopped using request-derived cookie scope in `public/includes/auth.php`; login cookies now default to a safe root path unless an explicit cookie scope is configured.
- Removed request/query-string admin-secret access from protected flows. `public/scheduler.php` now permits CLI execution instead of `swordfish` URL access, `public/log.php` no longer accepts `swordfish`, and the install docs now document CLI scheduler usage.

### Documentation

- Moved recent fix tracking out of `README.md` and into this changelog so the README stays focused on project overview, setup, and durable architecture/security notes.

## Major Project Changes

### Project structure and packaging

- Reorganized the game into a more standard project layout with:
  - `public/` as the web-serving directory
  - root-level `README.md`, `LICENSE`, and `CHANGELOG.md`
  - Composer-managed dependencies in `vendor/`
  - addon/plugin-style code in `addons/`
- Added `composer.json` and `composer.lock` to formalize dependency management.
- Removed legacy Subversion metadata from the active app tree.
- Added `public/config/db_config.example.php` so database configuration can be created from a tracked example instead of only relying on local untracked config state.

### Authentication and request security

- Added dedicated auth helpers in `public/includes/auth.php`.
- Moved the project toward session-based authenticated access for protected/admin functionality rather than relying on older ambient/shared-access patterns.
- Added CSRF protection helpers and began applying them to sensitive `POST` flows such as mail, contacts, feedback, reset flows, admin actions, and now commodity trading in `public/port2.php`.
- Updated login/logout and session gating behavior in:
  - `public/login2.php`
  - `public/logout.php`
  - `public/includes/checklogin.php`
  - `public/includes/updatecookie.php`

### Database and compatibility work

- Modernized database behavior to support PDO-backed compatibility work while preserving the legacy game code structure.
- Added `public/includes/pdo_adodb_compat.php`.
- Updated core bootstrap/data files including:
  - `public/includes/connectdb.php`
  - `public/global_includes.php`
  - `public/global_defines.php`
  - `public/global_cleanups.php`
  - `public/includes/schema.php`
  - `public/includes/load_languages.php`
  - `public/includes/ini_to_db.php`

### UI and cockpit overhaul

- Reworked the main player dashboard in `public/main.php` into a more modern cockpit-style interface while preserving the core gameplay loop.
- Updated shared presentation/layout files:
  - `public/header.php`
  - `public/footer.php`
  - `public/templates/classic/styles/main.css`
- Refreshed the landing, registration, login, and general shell pages:
  - `public/index.php`
  - `public/new.php`
  - `public/new2.php`
  - `public/server_notice.php`
  - `public/faq.php`
  - `public/news.php`
  - `public/newplayerguide.php`

### Social, messaging, and player-facing systems

- Added a dedicated contacts system:
  - `public/contacts.php`
  - `public/includes/contacts.php`
- Reworked mailbox and messaging flows:
  - `public/readmail.php`
  - `public/mailto2.php`
  - `public/mailto.php`
  - `public/includes/mailbox.php`
  - `public/includes/mailer.php`
- Added a notification center:
  - `public/notifications.php`
  - `public/includes/notifications.php`
- Added or expanded player profile and search systems:
  - `public/profile.php`
  - `public/search.php`
  - `public/includes/search.php`

### Ranking, score, and bounty changes

- Reworked leaderboard and score calculations so rankings better reflect live game state rather than stale or misleading cached values.
- Reworked ranking balance and leaderboard integrity so standings better reflect live net worth, bounty state, and reputation data.
- Fixed dashboard/profile ranking data mismatches so zero-turn players still show correct live score and liquid wealth without being incorrectly inserted into ranked standings.
- Fixed the reputation boards in `public/ranking.php` so `Most Honored` only shows positive-reputation pilots and `Most Notorious` only shows negative-reputation pilots.
- Fixed notification read-state handling in `public/notifications.php` so the default notification view updates unread counters before rendering the page.
- Added `public/includes/rankings.php`.
- Changed ranking-related logic in:
  - `public/ranking.php`
  - `public/profile.php`
  - `public/includes/gen_score.php`
  - `public/bounty.php`
  - `public/includes/collect_bounty.php`

### Addon and extensibility support

- Added addon infrastructure in `public/includes/addons.php` and `public/addon.php`.
- Added broader UX/social/addon support work including cockpit improvements, social/mail improvements, and extensibility hooks.
- Added root-level addon bundles:
  - `addons/event_calendar/`
  - `addons/ops_beacon/`
- Added addon documentation in `addons/README.md`.

### Password reset and account recovery

- Added dedicated reset/account-recovery flows:
  - `public/forgot_password.php`
  - `public/reset_password.php`
  - `public/forced_reset.php`

### Scheduler, AI, and game-system adjustments

- Updated multiple scheduler files, including:
  - `public/scheduler.php`
  - `public/sched_funcs.php`
  - `public/sched_igb.php`
  - `public/sched_planets.php`
  - `public/sched_ports.php`
  - `public/sched_thegovernor.php`
  - `public/sched_tow.php`
  - `public/sched_xenobe.php`
  - `public/sched_defenses.php`
- Added `public/sched_ngai.php` and `public/ngai_control.php`.
- Added `public/includes/ngai_behaviors.php`.

### Broadly modified gameplay pages

- The following gameplay pages were substantially updated:
  - `public/admin.php`
  - `public/bounty.php`
  - `public/create_universe.php`
  - `public/dump.php`
  - `public/feedback.php`
  - `public/log.php`
  - `public/lrscan.php`
  - `public/main.php`
  - `public/move.php`
  - `public/option2.php`
  - `public/options.php`
  - `public/perfmon.php`
  - `public/planet.php`
  - `public/port.php`
  - `public/port2.php`
  - `public/preset.php`
  - `public/readmail.php`
  - `public/rsmove.php`
  - `public/scan.php`
  - `public/server_notice.php`
  - `public/settings.php`
  - `public/setup_info.php`
  - `public/setup_info_class.php`
  - `public/ship.php`
  - `public/teams.php`
  - `public/traderoute.php`
  - `public/xenobe_control.php`
  - `public/zoneinfo.php`
