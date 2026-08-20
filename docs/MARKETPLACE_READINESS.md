# Moodle Marketplace readiness

This project is being developed for publication in Moodle Marketplace. The repository should be treated as pre-release until the checklist below is complete.

See [RELEASE_QA.md](RELEASE_QA.md) for the concrete, repeatable release-gate procedure (release QA workflow, install/upgrade QA, privacy QA, Marketplace metadata draft) referenced throughout this checklist.

## Baseline already designed into the plugin

- Correct Frankenstyle component: `local_autobrowsertimezone`.
- Conventional repository layout with `version.php` at repository root.
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
- Repository documentation and an issue tracker exist; the current GitHub repository/tracker are private, so public Marketplace URLs remain outstanding.
- Supported Moodle range declared in `version.php`.

## CI: routine vs release QA

**Routine CI** (`.github/workflows/moodle-plugin-ci.yml`) runs automatically
on every push/PR and is intentionally lightweight: a single job on
**Moodle 5.2 x MariaDB**, covering PHP lint, PHPCS, PHPDoc, plugin
validation, savepoints, JS lint/Grunt, and PHPUnit. It does **not** cover
Moodle 4.5/5.0/5.1 or PostgreSQL — running the full matrix on every commit
was found to be disproportionate for routine development.

**Release QA** (`.github/workflows/moodle-plugin-release-qa.yml`) is a
separate, manually-triggered (`workflow_dispatch` only) workflow that proves
the full declared **Moodle 4.5-5.2 x PostgreSQL/MariaDB** range before a
Marketplace release. See [RELEASE_QA.md](RELEASE_QA.md) for how to run it
and its current execution status — a `workflow_dispatch` workflow only
becomes runnable once its file exists on the default branch, so its
release-matrix evidence may be pending post-merge; do not treat "the
workflow exists" as equivalent to "the matrix has passed".

## Required before first Marketplace release

- [ ] Run the release QA workflow and confirm every one of the 8
      Moodle-branch x database legs completed successfully (see
      RELEASE_QA.md; do not mark this complete from the workflow merely
      existing). This provides clean-install and PHPUnit evidence across the
      full declared Moodle/database range.
- [ ] Execute the documented manual 0.1.5 → current upgrade QA on Moodle 4.5 with both MariaDB and PostgreSQL, plus browser/UI install smoke on the minimum and a current supported Moodle release; expand to additional branches if the matrix or staging QA exposes a version-specific concern (RELEASE_QA.md section 2).
- [ ] Run Moodle Code Checker / PHPCS and resolve all actionable findings.
- [ ] Run Moodle JavaScript lint and Grunt build; verify `amd/build` exactly matches `amd/src`.
- [ ] Run PHP lint across all PHP files.
- [ ] Run PHPUnit tests on each supported Moodle branch.
- [ ] Test with developer debugging enabled and confirm no notices/warnings/fatal errors.
- [ ] Test MySQL/MariaDB and PostgreSQL environments (the plugin currently has no custom SQL or tables, but installation/runtime should still be verified).
- [ ] Test users with `Server timezone`, an explicit timezone, and no `moodle/user:editownprofile` capability.
- [ ] Test Moodle forced timezone configuration.
- [ ] Test admin "login as" behaviour.
- [ ] Test authentication methods used by target sites and confirm the plugin does not conflict with externally managed profile policy.
- [ ] Verify privacy metadata with Moodle's Data registry page (RELEASE_QA.md section 3); automated metadata regression coverage already exists (`tests/privacy_provider_test.php`).
- [ ] Confirm publicly accessible Marketplace source, documentation, and issue-tracker URLs. The current GitHub repository and issue tracker are private and must not be described as public (RELEASE_QA.md section 4).
- [ ] Prepare Marketplace short description, full description, and real screenshots (metadata is partially drafted in RELEASE_QA.md section 4; screenshots and public URLs remain outstanding).
- [ ] Change maturity from `MATURITY_ALPHA` only after staging/compatibility QA supports doing so.
- [ ] Create a tagged release and build the Marketplace ZIP from that tag.

## Marketplace metadata draft

See [RELEASE_QA.md](RELEASE_QA.md) section 4 for the current draft and the explicit outstanding gates for public source/documentation/tracker URLs and screenshots.
