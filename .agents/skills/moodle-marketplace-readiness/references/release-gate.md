# Release gate

## A. Repository state

- [ ] Working tree/release commit is known and clean.
- [ ] Open issues reviewed for release blockers.
- [ ] `version.php` metadata is internally consistent.
- [ ] `CHANGES.md` contains the release changes.
- [ ] README matches behaviour.
- [ ] Security reporting information is present.
- [ ] License is GPL v3 or later / compatible as required.

## B. Moodle formal checks

- [ ] PHP lint.
- [ ] Moodle Code Checker / PHPCS.
- [ ] PHPDoc.
- [ ] Plugin validation.
- [ ] Upgrade savepoint validation.
- [ ] JavaScript lint and Grunt where applicable.
- [ ] CSS lint where applicable.
- [ ] Mustache checks where applicable.
- [ ] Third-party library metadata where applicable.

## C. Tests

- [ ] PHPUnit on every supported Moodle branch.
- [ ] PostgreSQL coverage.
- [ ] MariaDB/MySQL-family coverage.
- [ ] External-function security coverage.
- [ ] Eligibility/profile-ownership coverage.
- [ ] Browser/reload/retry QA.
- [ ] Developer debugging shows no unexpected notices/warnings.

## D. Install/upgrade

- [ ] Clean install tested.
- [ ] Upgrade path tested when applicable.
- [ ] Settings page tested.
- [ ] Disabled plugin is inert.
- [ ] Forced timezone tested.
- [ ] Login-as tested.
- [ ] Representative external-auth policy tested.

## E. Privacy/security

- [ ] Browser input is validated server-side.
- [ ] Capability and context checks are correct.
- [ ] Auth-plugin profile ownership/update semantics are preserved.
- [ ] No GPS/IP/third-party geolocation.
- [ ] Privacy API metadata is accurate.
- [ ] No unnecessary personal-data logging/storage.

## F. Package

- [ ] AMD generated files are current.
- [ ] Package is built from the release tag/commit.
- [ ] `.git/`, `.github/`, `.agents/`, `AGENTS.md`, caches, and unrelated development artefacts are excluded.
- [ ] Runtime-required files, LICENSE, README, and generated assets are included.
- [ ] Marketplace metadata, documentation/source/tracker URLs, and screenshots are ready.

## Current official references

- Plugin code prechecks:
  - https://moodledev.io/general/community/plugincontribution/codeprechecks
- Coding style:
  - https://moodledev.io/general/development/policies/codingstyle
- Privacy API:
  - https://moodledev.io/docs/5.2/apis/subsystems/privacy
- External API security:
  - https://moodledev.io/docs/5.2/apis/subsystems/external/security
- Moodle development tools:
  - https://moodledev.io/general/development/tools

Always verify that Marketplace-specific submission guidance has not changed since this file was written.
