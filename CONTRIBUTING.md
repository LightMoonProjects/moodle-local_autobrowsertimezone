# Contributing

Contributions are welcome through GitHub issues and pull requests.

## Development expectations

- Follow the Moodle coding style and PHPDoc conventions.
- Keep all user-visible text in the English language pack rather than hard-coding it in PHP or JavaScript.
- Use Moodle APIs for authentication, capability checks, validation, database access, and user updates.
- Do not introduce external tracking, geolocation services, or telemetry without explicit documentation and Privacy API updates.
- Add or update automated tests for behaviour changes.
- Keep `amd/src` and deployable `amd/build` JavaScript in sync.
- Increment `version.php` when a release or Moodle upgrade needs to detect a new version.

## Pull requests

Please describe the problem, the proposed behaviour, Moodle versions tested, and any security/privacy implications.
