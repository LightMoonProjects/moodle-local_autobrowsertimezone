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
- Repository documentation and issue tracker are publicly accessible at the project GitHub repository.
- Supported Moodle range declared in `version.php`.

## CI: routine vs release QA

**Routine CI** (`.github/workflows/moodle-plugin-ci.yml`) runs automatically on every push/PR and is intentionally lightweight: a single job on **Moodle 5.2 x MariaDB**, covering PHP lint, PHPCS, PHPDoc, plugin validation, savepoints, JS lint/Grunt, and PHPUnit.

**Release QA** (`.github/workflows/moodle-plugin-release-qa.yml`) is a separate, manually-triggered (`workflow_dispatch`) workflow for the full declared **Moodle 4.5-5.2 x PostgreSQL/MariaDB** range. Its actual execution remains a separate release gate until a run is recorded green.

**Manual compatibility evidence**: on 2026-08-21 the maintainer reported successful deployment and expected runtime behaviour on all eight declared Moodle/database combinations: Moodle 4.5, 5.0, 5.1 and 5.2 on both MariaDB and PostgreSQL. Evidence classification: **USER-VERIFIED / MANUAL STAGING — 8/8 PASS**.

## Required before first Marketplace release

- [ ] Run the release QA workflow and confirm the formal-check job plus all 8 Moodle/database compatibility legs complete successfully.
- [x] Execute the documented manual 0.1.6 → 1.1 upgrade QA on Moodle 4.5 with both MariaDB and PostgreSQL — **USER-VERIFIED PASS on 2026-08-21**.
- [ ] Run/record the release-candidate formal checks through the release QA workflow: PHP lint, PHPCS, PHPDoc, plugin validation, savepoints and Grunt.
- [ ] Run/record PHPUnit on every declared supported Moodle branch through the release QA workflow.
- [ ] Test with developer debugging enabled and confirm no notices/warnings/fatal errors for the documented release scenarios.
- [x] Test MySQL/MariaDB and PostgreSQL environments — **USER-VERIFIED manual deployment/runtime smoke 8/8 PASS on 2026-08-21** across Moodle 4.5/5.0/5.1/5.2 x both database families.
- [ ] Verify privacy metadata and Plugin privacy registry compliance against the exact merged/deployed release source. Automated regression coverage exists and pre-merge staging on the final PR head was reported clean; retain a post-merge check as the final release record.
- [x] Confirm publicly accessible Marketplace source, documentation, and issue-tracker URLs — repository visibility independently verified as public on 2026-08-21.
- [x] Capture real Marketplace screenshots — **maintainer-reported complete on 2026-08-21**. Screenshot contents were not independently inspected in this documentation update.
- [x] `$plugin->maturity` is `MATURITY_STABLE` for the 1.1 release target.
- [ ] Create the final tag/release only after the remaining release-QA and merged-source verification gates pass.

## Marketplace metadata draft

- **Source**: `https://github.com/LightMoonProjects/moodle-local_autobrowsertimezone`
- **Documentation**: repository `README.md`
- **Issue tracker**: `https://github.com/LightMoonProjects/moodle-local_autobrowsertimezone/issues`
- **Supported Moodle versions**: 4.5 through 5.2.
- **Screenshots**: maintainer reports real staging screenshots captured; attach/select the appropriate images during Marketplace submission.

See [RELEASE_QA.md](RELEASE_QA.md) for the detailed evidence and release decision gate.
