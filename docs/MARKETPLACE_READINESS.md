# Moodle Marketplace readiness

This project is being developed for publication in Moodle Marketplace. The repository should be treated as pre-release until the checklist below is complete.

## Baseline already designed into the plugin

- Correct Frankenstyle component: `local_autobrowsertimezone`.
- Conventional public repository layout with `version.php` at repository root.
- GNU GPL v3-or-later licensing.
- English language pack with user-facing strings outside PHP/JavaScript logic.
- Moodle Hooks API rather than a legacy global callback.
- Moodle external-function/AJAX API with login, context, and capability checks.
- No direct superglobal access and no custom SQL.
- Browser timezone input is validated against Moodle-supported timezone identifiers.
- No GPS, IP geolocation, or third-party service.
- Privacy metadata declares that the plugin causes data to be stored by the `core_user` subsystem.
- Forced site timezone policy is respected.
- Authentication-plugin ownership and locking of the timezone profile field is respected.
- "Login as" and MNet remote-user sessions are excluded from automatic writes.
- Public GitHub issue tracker and repository documentation.
- Supported Moodle range declared in `version.php`.
- GitHub Actions uses Moodle Plugin CI across Moodle 4.5, 5.0, 5.1 and 5.2 with PostgreSQL and MariaDB.

## Required before first Marketplace release

- [ ] Test installation and upgrade on clean Moodle 4.5, 5.0, 5.1, and 5.2 sites.
- [ ] Run Moodle Code Checker / PHPCS and resolve all actionable findings.
- [ ] Run Moodle JavaScript lint and Grunt build; verify `amd/build` exactly matches `amd/src`.
- [ ] Run PHP lint across all PHP files.
- [ ] Run PHPUnit tests on each supported Moodle branch.
- [ ] Test with developer debugging enabled and confirm no notices/warnings.
- [ ] Test MySQL/MariaDB and PostgreSQL environments (the plugin currently has no custom SQL or tables, but installation/runtime should still be verified).
- [ ] Test users with `Server timezone`, an explicit timezone, and no `moodle/user:editownprofile` capability.
- [ ] Test Moodle forced timezone configuration.
- [ ] Test admin "login as" behaviour.
- [ ] Test authentication methods used by target sites and confirm the plugin does not conflict with externally managed profile policy.
- [ ] Verify privacy metadata with Moodle Privacy API utilities.
- [ ] Prepare Marketplace short description, full description, screenshots, documentation URL, source URL, and public tracker URL.
- [ ] Change maturity from `MATURITY_ALPHA` only after staging/compatibility QA supports doing so.
- [ ] Create a tagged release and build the Marketplace ZIP from that tag.

## Marketplace metadata draft

**Name:** Automatic browser timezone

**Short description:** Keeps each user's Moodle profile timezone aligned with the timezone reported by their browser/device, without GPS, IP geolocation, or external APIs.

**Plugin type:** Local plugin

**Component:** `local_autobrowsertimezone`

**License:** GNU GPL v3 or later

**Source / documentation / issue tracker:** GitHub repository and GitHub Issues.
