# Security and privacy review checklist

## Authorization

- [ ] External parameters validated.
- [ ] Correct Moodle context validated.
- [ ] Required capability enforced server-side.
- [ ] Client eligibility does not knowingly queue forbidden operations.
- [ ] "Login as" cannot mutate the impersonated user's timezone from the operator's browser.
- [ ] MNet remote users are not locally mutated.
- [ ] Deleted/suspended/guest users are excluded.

## Site and authentication policy

- [ ] Forced site timezone prevents conflicting automatic writes.
- [ ] `can_edit_profile()` is respected.
- [ ] `field_lock_timezone = locked` is respected.
- [ ] `field_lock_timezone = unlockedifempty` is respected.
- [ ] External-auth upstream update/rejection semantics are preserved.
- [ ] Failure upstream cannot leave an unintended local-only change.

## Input

- [ ] Browser timezone treated as untrusted.
- [ ] Unsupported timezone rejected server-side.
- [ ] No arbitrary user-controlled string reaches the profile field.
- [ ] No custom SQL is built from browser input.

## Privacy

- [ ] No GPS, IP geolocation, MaxMind, or third-party service.
- [ ] No unnecessary telemetry/logging of timezone/location data.
- [ ] Privacy metadata matches actual storage/processing.
- [ ] New plugin-owned personal data has export and deletion support.

## Client behaviour

- [ ] No infinite reload loop.
- [ ] No AJAX request storm on permanent failure.
- [ ] Transient failures can recover according to the documented retry policy.
