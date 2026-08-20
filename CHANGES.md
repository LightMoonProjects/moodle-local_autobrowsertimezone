# Changelog

All notable changes to this project will be documented in this file.

## 0.1.2 - 2026-08-20

- Remove the `require_login()` call from the `update_timezone` AJAX external
  function. Moodle's External API contract already enforces login through
  `validate_context()`; `require_login()` is reserved for page scripts and is
  redundant/inappropriate inside external functions.
- No behavioural change to authorisation: parameter validation, system-context
  validation and `moodle/user:editownprofile` capability enforcement are
  unchanged.

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
