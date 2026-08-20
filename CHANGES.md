# Changelog

All notable changes to this project will be documented in this file.

## 0.1.6 - 2026-08-20

- Fixed: `manager::should_run()` called a non-existent global `isloggedinas()`
  function, which would throw a fatal error for any real (non-CLI) request
  by a logged-in, non-guest, non-deleted/suspended user with the plugin
  enabled -- the plugin's entire intended common case. Replaced with the
  correct `\core\session\manager::is_loggedinas()`. This was never caught by
  automated tests because Moodle PHPUnit defines `CLI_SCRIPT`, which
  `should_run()` short-circuits on before reaching this check. Discovered
  and fixed while expanding automated test coverage for Issue #6.
- Expanded PHPUnit coverage: plugin-disabled, guest, deleted, suspended,
  login-as, MNet remote user, forced timezone, and
  auth-plugin `can_edit_profile() = false` eligibility branches; invalid
  timezone rejection and the unchanged-timezone no-op at the actual
  production mutation boundary; `core\event\user_updated` emission on
  successful persistence (and non-emission on a no-op); and Privacy API
  metadata regression coverage.
- `manager::should_run()` and `manager::update_current_user_timezone()` were
  each split into a thin public CLI/eligibility-gated wrapper plus a private,
  independently testable policy method (`is_eligible_for_sync()`,
  `apply_validated_timezone_request()`); `can_update_timezone_for_auth_plugin()`
  now delegates to a new `auth_plugin_permits_timezone_edit()` that accepts
  an explicit auth-plugin instance. These are pure extractions with no
  behavioural change to any public method.
- Added a manually-triggered `Moodle Plugin Release QA` GitHub Actions
  workflow (`.github/workflows/moodle-plugin-release-qa.yml`,
  `workflow_dispatch` only) covering the full declared Moodle 4.5-5.2 x
  PostgreSQL/MariaDB compatibility matrix plus formal prechecks, without
  adding cost to routine per-PR CI.
- Corrected `docs/RETRY_GUARD_QA.md` Scenario E: `sessionStorage` is scoped
  per top-level browsing context, so two independently opened tabs cannot
  reliably demonstrate the concurrent-request guard; the scenario now
  reproduces it within a single tab.
- Updated `docs/MARKETPLACE_READINESS.md` to accurately distinguish routine
  CI (Moodle 5.2 x MariaDB, every push/PR) from release QA (full declared
  range, manually triggered before a Marketplace release); added
  `docs/RELEASE_QA.md` documenting the concrete, repeatable release-gate
  procedure.

## 0.1.5 - 2026-08-20

- The browser timezone sync retry guard now distinguishes a generic AJAX
  transport failure (network drop, timeout, temporary server error) from a
  deterministic Moodle server outcome (for example an unsupported timezone).
  A deterministic outcome keeps the per-mismatch session marker set for the
  rest of the browser session, exactly as before. A generic transport
  failure gets exactly one bounded retry on a later page load; if that retry
  also fails generically, the mismatch is likewise guarded for the rest of
  the session, so a persistently failing request cannot repeat on every page
  load indefinitely.
- No change to server-side authorization, eligibility, or persistence logic.
- `amd/build/timezone.min.js` regenerated from `amd/src/timezone.js` via
  Moodle's Grunt build.

## 0.1.4 - 2026-08-20

- `manager::should_run()` now checks `moodle/user:editownprofile` at system
  context, so browser timezone synchronisation is never queued for a user who
  is known in advance to lack permission to edit their own profile.
- The `update_timezone` external function's independent `require_capability()`
  check (Issue #3) is unchanged and remains the authoritative security
  boundary; this only avoids emitting a known-forbidden request.

## 0.1.3 - 2026-08-20

- Remove the `require_login()` call from the `update_timezone` AJAX external
  function. Moodle's External API contract already enforces login through
  `validate_context()`; `require_login()` is reserved for page scripts and is
  redundant/inappropriate inside external functions.
- No behavioural change to authorisation: parameter validation, system-context
  validation and `moodle/user:editownprofile` capability enforcement are
  unchanged.

## 0.1.2 - 2026-08-20

- Route automatic timezone updates through the active authentication plugin's
  `user_update($olduser, $newuser)` contract before persisting to Moodle,
  matching the same old/new user pattern Moodle's own profile-edit page uses.
- A rejected/failed authentication-plugin update now leaves the Moodle profile
  timezone completely unchanged instead of committing a local-only value.

## 0.1.1 - 2026-08-20

- Align bootstrap metadata with Moodle 4.5–5.2 support.
- Register a dedicated local-plugin administration settings page.
- Replace the Privacy API null provider with a `core_user` subsystem metadata declaration.
- Add full Moodle GPL/PHPDoc boilerplate and copyright metadata.
- Skip timezone changes during "login as" sessions and for MNet remote users.
- Respect authentication-plugin ownership and locking of the timezone profile field.
- Add baseline PHPUnit coverage for timezone validation.
- Add marketplace readiness, security, and contribution documentation.

## 0.1.0 - 2026-08-20

- Initial browser timezone detection and Moodle profile synchronisation bootstrap.
