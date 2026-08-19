---
name: autobrowsertimezone-domain
description: "Defines the functional contract and invariants of local_autobrowsertimezone. Use when changing timezone detection, eligibility, profile updates, reload behaviour, authentication-plugin interaction, or any user-visible synchronisation policy."
compatibility: "Specific to LightMoonProjects/moodle-local_autobrowsertimezone."
---

# Automatic Browser Timezone Domain

## Goal

Keep an eligible user's Moodle profile timezone aligned with the browser/device IANA timezone while respecting Moodle site policy, account ownership, authentication sources, and privacy expectations.

## Non-negotiable invariants

### Data source

- Detect timezone locally in the browser.
- Do not use GPS.
- Do not use IP geolocation.
- Do not use MaxMind or another geolocation database.
- Do not call a third-party timezone/geolocation API.
- Send the browser timezone only to the same Moodle site when synchronisation is needed.

### Eligibility

Automatic mutation must not run for:

- guests or unauthenticated users
- deleted or suspended users
- CLI requests
- initial installation
- "login as" sessions
- MNet remote users
- users who are not allowed to edit their own profile
- users whose authentication plugin owns or locks the timezone field
- sites with a forced timezone policy that overrides individual profile timezone

If a future Moodle API changes how any of these states are represented, preserve the policy using the supported core API rather than removing the safeguard.

### Input trust and validation

The browser value is untrusted.

Server-side code must:

1. validate the external-function parameter definition;
2. clean/validate the timezone using Moodle-supported parameter handling;
3. confirm the resulting zone is a timezone Moodle exposes as a supported profile choice; and
4. reject unsupported values rather than persisting arbitrary strings.

Do not rely on client-side comparison or `Intl` output as a security boundary.

### Profile ownership

A timezone write must preserve Moodle's profile-management contract.

- Respect `auth_plugin_base::can_edit_profile()`.
- Respect `field_lock_timezone`, including `locked` and `unlockedifempty`.
- Do not silently bypass an authentication plugin's upstream profile-update policy.
- If the active auth plugin can reject or propagate profile changes, the automatic update policy must explicitly account for that behaviour.
- Avoid partial success where Moodle is changed locally after an upstream update failed.

### Core user mutation

Use Moodle's user APIs rather than direct SQL.

After a successful update:

- keep the current request/session user state coherent;
- preserve Moodle event semantics;
- do not modify unrelated user fields;
- do not touch passwords or authentication secrets.

### Browser behaviour

- Do nothing when browser and profile timezones already match.
- On a successful change, reload only when the setting requests it.
- Prevent infinite AJAX/reload loops.
- A transient transport/server failure should be recoverable; a permanent validation/permission failure should not create a request storm.
- Multiple tabs or repeated requests must remain effectively idempotent.

## Edge cases

Read `references/edge-cases.md` before changing comparison, normalization, or retry behaviour.

## Change checklist

For any behavioural change:

- update PHPUnit/external-function coverage;
- rebuild AMD assets if JavaScript changed;
- test with `Server timezone` (`99`) and an explicit profile timezone;
- test capability-denied and forced-timezone cases;
- test relevant external-auth behaviour;
- update README/CHANGES when user-visible semantics change;
- run the Marketplace release checks if the change is intended for release.
