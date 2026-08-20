# Timezone sync retry guard: manual QA procedure

This procedure verifies the `amd/src/timezone.js` retry-guard behaviour
described in Issue #5. No automated JavaScript test harness exists in this
repository (there is no `package.json`-based JS unit-test setup, and
introducing one solely for this small AMD change would be disproportionate),
so this documented manual procedure is the required evidence per the issue's
acceptance criteria.

## Setup

1. Enable the plugin (**Site administration → Plugins → Local plugins →
   Automatic browser timezone**) with **Reload after timezone change**
   enabled.
2. Log in as a test user whose profile timezone differs from the browser's
   IANA timezone (for example, set the profile to `Europe/London` while the
   browser/OS reports `Australia/Sydney`).
3. Open the browser's developer tools: Network tab (to inspect/control the
   `local_autobrowsertimezone_update_timezone` AJAX call) and Console
   (`sessionStorage` can also be inspected/edited from the Application/Storage
   tab).

## Scenario A — successful update

1. Load any eligible page with a fresh session (clear `sessionStorage` for
   the site first, or open a new private/incognito window).
2. Expect exactly one `local_autobrowsertimezone_update_timezone` AJAX
   request in the Network tab.
3. Expect a `sessionStorage` key
   `local_autobrowsertimezone:<old-tz>:<new-tz>` set to `1`.
4. Expect the response `changed` to be `true` and the page to reload exactly
   once.
5. Reload the page again manually: expect **no** further AJAX request (the
   profile timezone now matches the browser, so the mismatch no longer
   exists).

## Scenario B — transient AJAX failure

1. With a fresh session and the same mismatch as Scenario A, use DevTools to
   simulate a transient failure for the AJAX request: either set the Network
   tab to **Offline** immediately before the page fires the request, or use
   **Block request URL** on `lib/ajax/service.php` for a single load, or
   throttle/abort the request mid-flight.
2. Expect the request to fail at the transport level (the browser console
   shows Moodle's standard exception notification, sourced from
   `core/notification`).
3. Inspect `sessionStorage`: the `local_autobrowsertimezone:<old-tz>:<new-tz>`
   key must **not** be present (or must be absent immediately after the
   failure) — the retry guard was released.
4. Confirm no further request fires automatically on the same page (no
   request storm/immediate retry).

## Scenario C — successful retry

1. Immediately following Scenario B, restore normal network connectivity
   (remove the Offline/blocked-request condition).
2. Reload the page (a later normal page load in the same browser session).
3. Expect the AJAX request to fire again for the same mismatch (the marker
   was cleared in Scenario B).
4. Expect a normal successful response and exactly one reload, as in
   Scenario A.

## Scenario D — permanent failure

1. With a fresh session, temporarily set the browser/OS timezone to a value
   Moodle does not expose as a supported profile timezone (or, for a
   deterministic repeatable test, temporarily edit
   `classes/local/manager.php::is_supported_timezone()` in a local dev copy
   to reject the current browser timezone — revert afterwards).
2. Load the page: expect one AJAX request, which resolves as a **rejected**
   promise carrying a Moodle exception (`errorcode: 'invalidparameter'`, from
   `invalid_parameter_exception`).
3. Inspect `sessionStorage`: the attempt marker **must remain set** — the
   rejection carries a Moodle `errorcode`, so it is treated as a
   deterministic server outcome, not a transient failure.
4. Reload the page one or more times: expect **no** further AJAX request for
   the same mismatch while the marker remains set.

## Scenario E — existing loop guard (unchanged)

1. With a fresh session and a real mismatch, load the page twice in quick
   succession (or open two tabs) before the first request resolves.
2. Expect at most one AJAX request to be sent for that exact mismatch key —
   `markAttempt()` still prevents a duplicate concurrent/immediate request,
   exactly as before Issue #5.

## Expected outcome summary

| Scenario | AJAX result | Marker after | Retried on next load? |
|---|---|---|---|
| A. Success | resolved, `changed: true` | set | no (mismatch resolved) |
| B. Transient failure | rejected, no `errorcode` | cleared | yes (this is C) |
| D. Permanent failure | rejected, `errorcode` set | kept | no |
| E. Concurrent load | guard blocks 2nd call | set by 1st call | n/a |
