---
name: moodle-plugin-security-privacy
description: "Reviews and implements Moodle security and privacy for this plugin: external-function contexts and capabilities, untrusted browser timezone input, authentication-plugin profile ownership, forced-timezone and login-as safeguards, core user mutations, Privacy API metadata, and data-minimisation. Use for any profile-write or AJAX change."
compatibility: "Specific security/privacy guidance for a Moodle local plugin that updates the current user's core profile timezone."
---

# Moodle Plugin Security and Privacy

## Threat model

The browser is controlled by the user and its timezone value is untrusted. The plugin has authority to mutate a core user profile field, so authorization and profile-ownership rules are higher priority than convenience.

Load `autobrowsertimezone-domain` for product invariants.

## Security procedure

### 1. Identify the trust boundary

For every browser-to-server path, identify:

- parameter definition and cleaning;
- Moodle context;
- capability;
- current user identity;
- site policy that may override the change;
- authentication source that may own the field;
- persistence API;
- event/audit consequences.

Do not rely on JavaScript checks for authorization.

### 2. External-function security

For external functions:

- validate parameters first;
- validate the Moodle context with `external_api::validate_context()`;
- require the relevant capability;
- do not call `require_login()` inside the external function;
- do not manually set `$PAGE->context`;
- keep declared service capabilities aligned with runtime checks.

For this plugin, denial should be safe and should not cause a profile mutation.

### 3. Timezone input validation

Use layered validation:

- external parameter type;
- Moodle parameter cleaning;
- membership in Moodle's supported profile timezone list.

Reject unsupported identifiers.

Do not introduce custom regular expressions as the sole validation mechanism when Moodle already defines profile timezone semantics.

### 4. User and session safeguards

Automatic mutation must not bypass:

- guest/anonymous checks;
- deleted/suspended checks;
- "login as" protection;
- MNet remote-user ownership;
- forced site timezone;
- `moodle/user:editownprofile`;
- authentication-plugin `can_edit_profile()`;
- timezone field locking/ownership.

If the endpoint enforces a capability, client-side eligibility should normally avoid queuing the request for users already known to lack it. The server-side check remains mandatory.

### 5. Authentication-plugin contract

This is a critical review area.

Moodle's normal profile flow gives the active authentication plugin an opportunity to propagate or reject profile changes.

Do not implement a local `{user}.timezone` change that can silently diverge from an authoritative external authentication source.

The chosen behaviour must:

- respect lock configuration;
- account for upstream update behaviour;
- avoid local commit after upstream rejection;
- be covered by tests using an auth-plugin test double or suitable fixture.

### 6. Core mutation safety

- Use core user APIs.
- Update only `id` and the intended field unless an API explicitly requires more.
- Do not modify password/authentication data.
- Preserve standard `user_updated` event behaviour.
- Keep `$USER` state consistent after success.
- Avoid direct SQL.

### 7. Privacy and data minimisation

The plugin processes a browser-reported timezone and stores it in the existing core user profile.

Requirements:

- no external geolocation/timezone service;
- no GPS/IP collection for timezone inference;
- no plugin-owned tracking table unless a future feature clearly requires it;
- no unnecessary logging of browser timezone or location-related data;
- Privacy API metadata must accurately declare the core subsystem receiving personal data;
- if plugin-owned personal data is introduced, implement the required export/deletion provider interfaces rather than leaving metadata-only handling.

Read `references/review-checklist.md` before security-sensitive release work.

## Verification

A security-sensitive change is incomplete until tests cover:

- unauthorized/capability-denied access;
- invalid timezone;
- forced timezone;
- login-as;
- relevant auth-plugin ownership/lock cases;
- successful permitted update;
- no partial mutation on rejected upstream auth update.

Run the full Marketplace/CI gate before release.
