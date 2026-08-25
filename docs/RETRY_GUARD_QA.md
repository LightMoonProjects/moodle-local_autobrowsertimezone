# Timezone sync retry guard: QA procedure

This document records the required QA for the lifecycle-safe retry guard in
`amd/src/timezone.js`.

Focused automated regression coverage now exists in
`tests/js/timezone_test.mjs` and is suitable for fast CLI verification with:

```text
node --test tests/js/timezone_test.mjs
```

Those automated tests cover the client-side state-machine branches that can
be exercised without a real browser lifecycle. Real browser/manual QA is
still required for unload/navigation and session-expiry redirect behaviour,
because those depend on the document actually unloading or Moodle actually
redirecting the tab.

## State model

Each browser/profile timezone mismatch, for the currently authenticated
Moodle user, is keyed by:

```text
local_autobrowsertimezone:<user-id>:<current-profile-tz>:<browser-tz>
```

The `<user-id>` segment preserves Issue #18 isolation: one account's retry
or guarded state must not suppress a different account in the same tab.

There are two distinct layers of state:

- **Document-local `inFlight` set** — prevents duplicate concurrent requests
  for the same mismatch inside the currently loaded document only. It is not
  persisted and disappears naturally on unload/navigation.
- **Persisted `sessionStorage` state** — records only settled cross-page
  outcomes:
  - **(absent)** — no settled failure state exists yet; a request may run.
  - **`retry`** — a first generic/transport failure occurred; exactly one
    later page load may retry.
  - **`guarded`** — a deterministic outcome or a spent retry budget means the
    mismatch must not be retried again in this tab session.

Settled transitions:

1. **No state + request starts**:
   - `inFlight` claims the key in memory only.
   - `sessionStorage` remains unchanged.
2. **Success (`changed: true`)**:
   - reload occurs at most once when enabled;
   - any stale persisted `retry` marker is cleared.
3. **Resolved no-op (`changed: false`, `reason: unchanged`)**:
   - any stale persisted `retry` marker is cleared.
4. **Resolved deterministic application outcome**:
   - `changed: false`, `reason: authrejected` or `reason: disabled`
   - persisted state becomes `guarded`.
5. **Rejected Moodle/application exception**:
   - rejection carries `errorcode`;
   - persisted state becomes `guarded`.
6. **First generic/transport rejection**:
   - rejection has no Moodle `errorcode`;
   - persisted state becomes `retry`.
7. **Second generic/transport rejection**:
   - the retry attempt rejects generically again;
   - persisted state becomes `guarded`.
8. **Unload/navigation before settlement**:
   - no new persistent pre-request `guarded` state is written;
   - a first attempt stays absent;
   - a retry attempt stays `retry` until a settled outcome exists.

## Setup

1. Enable the plugin at **Site administration → Plugins → Local plugins →
   Automatic browser timezone**.
2. Enable **Reload after timezone change** for the scenarios below unless a
   scenario explicitly says otherwise.
3. Use a browser/OS reporting `Australia/Sydney`.
4. Prepare test users whose Moodle profile timezone can be set to
   `Asia/Tehran`, `99`, or another value as required.
5. Open browser DevTools:
   - **Network** tab for throttling/blocking/observing
     `local_autobrowsertimezone_update_timezone`.
   - **Application/Storage** tab or **Console** for inspecting
     `sessionStorage`.

## Scenario A — normal success

1. Use a fresh tab or clear `sessionStorage` for the Moodle origin.
2. Set the test user's profile timezone to `Asia/Tehran`.
3. Load an eligible page with the browser reporting `Australia/Sydney`.
4. Expect exactly one AJAX request.
5. Expect the request to complete with `changed: true` and the profile to
   become `Australia/Sydney`.
6. Expect the page to reload at most once.
7. Reload the page manually afterwards: expect no further AJAX request
   because the profile/browser mismatch is resolved.

## Scenario B — unload before request settles

1. Start from a genuine mismatch such as `Asia/Tehran` vs
   `Australia/Sydney` with a fresh tab session.
2. Use DevTools throttling or a breakpoint so the timezone AJAX request
   remains pending.
3. Before the request settles, navigate to another eligible Moodle page in
   the same tab.
4. Expect no stranded persistent `guarded` value for the mismatch key.
   For a first attempt the key should remain absent; for a retry attempt it
   should remain `retry`.
5. On the next eligible page, expect another legitimate synchronization
   opportunity for the same mismatch.
6. Allow one of those later attempts to settle successfully and confirm the
   profile eventually becomes `Australia/Sydney`.

## Scenario C — rapid navigation

1. Keep the same genuine mismatch and slow the AJAX request significantly.
2. Navigate repeatedly across eligible Moodle pages in the same tab before
   each outstanding request settles.
3. Confirm prior navigation does not poison later attempts with a permanent
   pre-request `guarded` marker.
4. Once one request is finally allowed to complete, confirm the profile
   synchronizes and reload occurs at most once.

## Scenario D — session-expiry redirect

1. Start with a genuine mismatch and a session that is expired or about to
   expire.
2. Trigger the timezone request so Moodle responds with
   `servicerequireslogin` and redirects the tab to login.
3. Log back in in the same tab.
4. Confirm `sessionStorage` did not gain a stranded permanent pre-request
   `guarded` value merely because the request started.
5. Load an eligible page again and confirm the mismatch remains eligible for
   synchronization.

## Scenario E — duplicate `init()` in one document

`sessionStorage` is not the duplicate guard anymore; the duplicate guard is
the in-memory `inFlight` set inside the loaded document.

1. With a fresh session and real mismatch, load an eligible page while
   delaying the first AJAX request so it remains pending.
2. In the same tab, manually invoke the AMD module a second time with the
   same arguments, for example:

   ```js
   require(['local_autobrowsertimezone/timezone'], function(m) {
       m.init({currentTimezone: 'Asia/Tehran', reload: true, userid: 123});
   });
   ```

3. Expect at most one concurrent AJAX request for that exact mismatch.
4. Confirm no persistent `guarded` state was written solely because the first
   request started.

## Scenario F — first generic failure

1. With a fresh tab session and a real mismatch, force a generic
   transport/server failure without unloading the page:
   - browser Offline mode,
   - blocked `lib/ajax/service.php`,
   - aborted request,
   - or another failure that does not produce a Moodle `errorcode`.
2. Expect one failed AJAX request and a standard Moodle exception
   notification.
3. Inspect `sessionStorage`: the mismatch key must become `retry`.
4. Confirm the same page does not retry immediately.
5. Reload or visit another eligible page in the same tab after restoring the
   network/server path: expect exactly one later retry attempt.

## Scenario G — second generic failure

1. Starting from Scenario F's `retry` state, keep the generic failure active
   for the next eligible page load as well.
2. Expect exactly one more AJAX request for the same mismatch.
3. Expect that second generic failure to change persisted state to
   `guarded`.
4. Continue loading additional eligible pages in the same tab: expect no
   endless request stream.

## Scenario H — deterministic server rejection

1. With a fresh session, force a deterministic Moodle/application failure:
   - unsupported browser timezone, or
   - another reproducible server rejection carrying `errorcode`.
2. Expect one AJAX request and a rejected promise with Moodle `errorcode`.
3. Expect the mismatch key to persist as `guarded`.
4. Reload additional eligible pages and confirm repeated requests are
   suppressed.
5. Also verify the resolved deterministic branch if practical:
   `changed: false`, `reason: authrejected` must likewise remain guarded
   under the current server contract.

## Scenario I — cross-account isolation

1. Log in as **Account A** in a fresh tab, with profile timezone `99` and
   browser `Australia/Sydney`.
2. Force either a successful synchronization or a guarded failure so Account
   A leaves a persisted state for:

   ```text
   local_autobrowsertimezone:<A-user-id>:99:Australia/Sydney
   ```

3. Log out in the same tab and log in as **Account B**, also with profile
   timezone `99`.
4. Load an eligible page as Account B.
5. Expect Account B to get its own synchronization attempt; Account A's key
   must not suppress it.
6. Confirm Account A's stored key, if any, remains untouched and separate.

## Expected summary

| Scenario | Result | Persisted state after settlement | Later pages retry? |
|---|---|---|---|
| A. Normal success | resolved, `changed: true` | cleared/absent | no mismatch remains |
| B. Unload before settlement | no settled promise | unchanged from pre-attempt state | yes, still eligible |
| C. Rapid navigation | repeated unload before settlement | unchanged until one settles | yes, until settled |
| D. Session-expiry redirect | redirect before settlement | unchanged from pre-attempt state | yes, after login |
| E. Duplicate init in one document | 2nd call blocked by `inFlight` | no pre-request persistent state | n/a |
| F. First generic failure | rejected, no `errorcode` | `retry` | yes, exactly once |
| G. Second generic failure | rejected again, no `errorcode` | `guarded` | no |
| H. Deterministic rejection | rejected with `errorcode`, or resolved deterministic `changed:false` | `guarded` | no |
| I. Cross-account same-tab | per-account outcome only | keyed by `<user-id>` | independent per user |
