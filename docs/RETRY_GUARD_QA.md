# Timezone sync retry guard: manual QA procedure

This procedure verifies the `amd/src/timezone.js` retry-guard behaviour
described in Issue #5. No automated JavaScript test harness exists in this
repository (there is no `package.json`-based JS unit-test setup, and
introducing one solely for this small AMD change would be disproportionate),
so this documented manual procedure is the required evidence per the issue's
acceptance criteria.

## State machine

Each browser/profile timezone mismatch is tracked independently, keyed by
`local_autobrowsertimezone:<current-profile-tz>:<browser-tz>` in
`sessionStorage`. The stored value is one of:

- **(absent)** — no attempt has been made yet; a request may be sent.
- **`guarded`** — this mismatch will not generate another request for the
  rest of the browser session.
- **`retry`** — a first generic/transport failure occurred; exactly one more
  attempt is permitted on a later page load.

Transitions:

1. **(absent) → `guarded`**, immediately, before the request is sent (this is
   also what blocks a concurrent/duplicate call for the same mismatch).
2. Request **succeeds** → state stays `guarded` (moot: the profile timezone
   now matches the browser, so this exact key is never consulted again).
3. Request **rejects with a Moodle `errorcode`** (a deterministic server
   outcome — validation, authorisation, policy) → state stays `guarded`,
   permanently, for the rest of the session.
4. Request **rejects with no `errorcode`** (a generic/transport failure) on a
   **first** attempt → state becomes `retry`.
5. On a later page load, `retry` → `guarded` again (the retry is claimed),
   and exactly one more request is sent:
   - succeeds → moot, as in (2);
   - rejects with an `errorcode` → stays `guarded` (permanent);
   - rejects again with no `errorcode` → stays `guarded` (permanent — the
     one-time retry budget for this mismatch is now spent; it is **not**
     downgraded back to `retry` a second time).

This bounds a *persistent* generic/transport failure (repeated proxy denial,
repeated HTTP 500, malformed/non-JSON response, repeated gateway failure,
etc.) to at most one retryable attempt per mismatch per browser session,
while a genuine one-off transient failure still gets exactly one later
chance to succeed. No timer, recursion, or immediate re-call is used
anywhere in this state machine; a transition to `retry` only takes effect on
a subsequent, independent page load.

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
3. Expect a `sessionStorage` key `local_autobrowsertimezone:<old-tz>:<new-tz>`
   set to `guarded`.
4. Expect the response `changed` to be `true` and the page to reload exactly
   once.
5. Reload the page again manually: expect **no** further AJAX request (the
   profile timezone now matches the browser, so the mismatch no longer
   exists).

## Scenario B — first generic/transient AJAX failure

1. With a fresh session and the same mismatch as Scenario A, use DevTools to
   simulate a failure for the AJAX request: either set the Network tab to
   **Offline** immediately before the page fires the request, or use
   **Block request URL** on `lib/ajax/service.php` for a single load, or
   throttle/abort the request mid-flight.
2. Expect the request to fail at the transport level (the browser console
   shows Moodle's standard exception notification, sourced from
   `core/notification`) — this rejection carries no Moodle `errorcode`.
3. Inspect `sessionStorage`: the `local_autobrowsertimezone:<old-tz>:<new-tz>`
   key must be set to `retry` — one later attempt is now permitted.
4. Confirm no further request fires automatically on the same page (no
   immediate retry, no request storm).

## Scenario C — successful later retry

1. Immediately following Scenario B, restore normal network connectivity
   (remove the Offline/blocked-request condition).
2. Reload the page (a later, independent page load in the same browser
   session).
3. Expect the AJAX request to fire again for the same mismatch (state was
   `retry` after Scenario B).
4. Expect a normal successful response and exactly one reload, as in
   Scenario A.

## Scenario D — Moodle deterministic (`errorcode`) failure

1. With a fresh session, temporarily set the browser/OS timezone to a value
   Moodle does not expose as a supported profile timezone (or, for a
   deterministic repeatable test, temporarily edit
   `classes/local/manager.php::is_supported_timezone()` in a local dev copy
   to reject the current browser timezone — revert afterwards).
2. Load the page: expect one AJAX request, which resolves as a **rejected**
   promise carrying a Moodle exception (`errorcode: 'invalidparameter'`, from
   `invalid_parameter_exception`).
3. Inspect `sessionStorage`: the key **must remain `guarded`** — the
   rejection carries a Moodle `errorcode`, so it is always treated as a
   deterministic, permanent outcome regardless of whether this was a first
   attempt or a retry.
4. Reload the page one or more times: expect **no** further AJAX request for
   the same mismatch while the state remains `guarded`.

## Scenario E — existing duplicate/concurrent guard (unchanged)

1. With a fresh session and a real mismatch, load the page twice in quick
   succession (or open two tabs) before the first request resolves.
2. Expect at most one AJAX request to be sent for that exact mismatch key —
   `beginAttempt()` claims the key as `guarded` synchronously before the
   request is sent, so a duplicate concurrent/immediate request for the same
   mismatch is still blocked, exactly as before.

## Scenario F — persistent generic/transport failure (mandatory)

This is the scenario the retry-bounding fix specifically targets: a failure
that keeps recurring (a persistently misconfigured reverse proxy/WAF, a
repeated HTTP 500, a consistently malformed/non-JSON response, repeated
gateway failures) must **not** generate a fresh AJAX request on every single
page load forever.

1. With a fresh session and a real mismatch, configure DevTools to make the
   AJAX request fail generically on **every** load (for example, **Block
   request URL** left enabled across multiple reloads, rather than removed
   after one attempt as in Scenario B/C).
2. **First page load:** expect one AJAX request, which fails generically (no
   `errorcode`). Expect `sessionStorage` to be set to `retry`.
3. **Second page load** (failure condition still active): expect exactly one
   more AJAX request (the bounded retry being spent). It fails generically
   again. Expect `sessionStorage` to now be `guarded`.
4. **Third, fourth, and further page loads** (failure condition still
   active): expect **no** further AJAX request at all — the mismatch is
   permanently guarded for the rest of the browser session, exactly as a
   deterministic Moodle `errorcode` failure would be.
5. Confirm this holds across at least 3-4 additional reloads to demonstrate
   the guard is not merely delayed but genuinely bounded.

## Expected outcome summary

| Scenario | AJAX result | State after | Retried on next load? |
|---|---|---|---|
| A. Success | resolved, `changed: true` | `guarded` | no (mismatch resolved) |
| B. First generic failure | rejected, no `errorcode` | `retry` | yes (this is C) |
| C. Successful retry | resolved, `changed: true` | `guarded` | no (mismatch resolved) |
| D. Moodle `errorcode` failure | rejected, `errorcode` set | `guarded` | no |
| E. Concurrent load | guard blocks 2nd call | `guarded` by 1st call | n/a |
| F. 2nd generic failure (persistent) | rejected, no `errorcode` | `guarded` | no (budget spent) |
