# Release QA procedure

This document records the release gate for `local_autobrowsertimezone` 1.1. Evidence is classified explicitly so manual staging results are not presented as automated CI results.

## Automated vs manual evidence

| Area | Mechanism | Status |
|---|---|---|
| Routine formal checks + PHPUnit on Moodle 5.2 x MariaDB | `.github/workflows/moodle-plugin-ci.yml` | PR #15 CI passed before merge |
| Full clean-install + PHPUnit matrix, Moodle 4.5-5.2 x PostgreSQL/MariaDB | `.github/workflows/moodle-plugin-release-qa.yml` | **PENDING execution** |
| Manual deployment/runtime compatibility, Moodle 4.5-5.2 x PostgreSQL/MariaDB | Maintainer staging environments | **USER-VERIFIED 8/8 PASS — 2026-08-21** |
| Manual 0.1.6 → 1.1 upgrade, Moodle 4.5 x MariaDB/PostgreSQL | Maintainer staging environments | **USER-VERIFIED 2/2 PASS — 2026-08-21** |
| Public source/docs/tracker | GitHub repository | **VERIFIED public — 2026-08-21** |
| Real Marketplace screenshots | Maintainer staging capture | **USER-VERIFIED captured — 2026-08-21** |
| Exact merged-source privacy registry re-check | Moodle dev/staging | **PENDING final record** |
| Tag/release/package | GitHub release process | **PENDING** |

## 1. Release QA workflow

`.github/workflows/moodle-plugin-release-qa.yml` is a manual `workflow_dispatch` release gate. It runs:

1. `formal-checks` on Moodle 5.2 x MariaDB: PHP lint, PHPCS with zero warnings, PHPDoc with zero warnings, plugin validation, savepoints, and Grunt.
2. `compatibility`: clean plugin install plus PHPUnit on all eight declared combinations:
   - Moodle 4.5 x MariaDB
   - Moodle 4.5 x PostgreSQL
   - Moodle 5.0 x MariaDB
   - Moodle 5.0 x PostgreSQL
   - Moodle 5.1 x MariaDB
   - Moodle 5.1 x PostgreSQL
   - Moodle 5.2 x MariaDB
   - Moodle 5.2 x PostgreSQL

The workflow is present on `main` and must be executed before the final tag. Manual compatibility evidence below is additional evidence, not a substitute for this repeatable workflow.

### Manual compatibility evidence — 2026-08-21

The maintainer reported deployment and expected runtime behaviour passing on every declared Moodle/database combination:

| Moodle | MariaDB | PostgreSQL |
|---|---|---|
| 4.5 | PASS | PASS |
| 5.0 | PASS | PASS |
| 5.1 | PASS | PASS |
| 5.2 | PASS | PASS |

Evidence classification: **USER-VERIFIED / MANUAL STAGING — 8/8 PASS**.

Exact Moodle/PHP/database patch versions were not supplied for all eight manual environments, so this record does not claim exact build-level reproducibility.

## 2. Install / upgrade QA

The plugin has no custom database schema (`db/install.xml`/`db/upgrade.php` are not required for this release), so upgrade QA verifies Moodle's plugin version transition and runtime behaviour rather than a schema migration.

Immediate predecessor:

```text
0.1.6 / 2026082006
commit 3cf499f860001c19a8452e1cedfcfb3ac5c29fdd
```

Release target:

```text
1.1 / 2026082007 / MATURITY_STABLE
```

A real 0.1.6 → 1.1 upgrade was previously verified on the Moodle 5.2.2 dev environment during #14 staging.

The maintainer additionally confirmed on 2026-08-21 that the documented minimum-version upgrade gate was executed successfully on Moodle 4.5 with both database families:

- Moodle 4.5 x MariaDB: **USER-VERIFIED PASS**
- Moodle 4.5 x PostgreSQL: **USER-VERIFIED PASS**

This satisfies the project's documented manual minimum-version dual-database upgrade scope. The release-QA workflow remains responsible for repeatable clean-install + PHPUnit evidence across all eight combinations.

Developer-debugging release scenarios should still be recorded separately if not already captured against the exact merged release source: enabled mismatch/update, settings page, disabled plugin, login-as, and absence of PHP notices/warnings/fatals/unexpected `debugging()` output.

## 3. Privacy QA

`classes/privacy/provider.php` implements:

- `\core_privacy\local\metadata\provider`, retaining the `core_user` subsystem link for `timezone`;
- `\core_privacy\local\request\plugin\provider`;
- `\core_privacy\local\request\core_userlist_provider`.

The plugin owns no independent personal-data store. Its request/userlist methods therefore report no independently owned contexts/users and do not mutate `user.timezone` or other core profile data.

Issue #14 was fixed by PR #15 and merged to `main` as `feaf98afde1603671a040a1bdf7b6b0e1a381d17`.

Automated regression coverage verifies:

- `core_privacy\manager::component_is_compliant('local_autobrowsertimezone') === true`;
- provider/userlist contracts;
- no profile mutation from privacy export/delete paths;
- `tool_dataprivacy\metadata_registry` does not report `userlistnoncompliance`.

Pre-merge staging on the final PR head was reported clean after the `core_userlist_provider` correction. For the final release record, redeploy the exact merged/release source and confirm in Moodle's Plugin privacy registry that:

- the red non-compliance warning is absent;
- `Userlist provider missing` is absent;
- the `core_user` / `timezone` subsystem link remains present;
- developer debugging shows no related notices/warnings.

Until that exact merged-source check is recorded, this remains a release gate rather than an inferred pass.

## 4. Marketplace metadata and assets

Verified public repository status on 2026-08-21:

- **Source repository**: `https://github.com/LightMoonProjects/moodle-local_autobrowsertimezone`
- **Documentation**: repository `README.md`
- **Issue tracker**: `https://github.com/LightMoonProjects/moodle-local_autobrowsertimezone/issues`

The repository visibility was independently verified as public.

Marketplace draft:

- **Plugin name**: Automatic browser timezone
- **Frankenstyle component**: `local_autobrowsertimezone`
- **Plugin type**: Local plugin
- **Short description**: Keeps each user's Moodle profile timezone aligned with the timezone reported by their browser/device, without GPS, IP geolocation, or external APIs.
- **Supported Moodle versions**: 4.5 through 5.2.
- **Privacy**: no plugin-owned personal-data table and no external transmission; the plugin writes only the existing core `user.timezone` field when Moodle/auth policy permits.

Screenshots: the maintainer reports real staging screenshots were captured on 2026-08-21. This documentation update does not independently inspect or embed those screenshots; use the captured originals for the Marketplace submission/release assets as appropriate.

## 5. Release decision gate

Do not tag/publish 1.1 until all applicable remaining items are complete:

- [ ] Execute the `Moodle Plugin Release QA` workflow from `main` and verify the formal-check job plus all eight compatibility jobs are green.
- [x] Manual declared-range compatibility: Moodle 4.5/5.0/5.1/5.2 x MariaDB/PostgreSQL — **USER-VERIFIED 8/8 PASS**.
- [x] Manual Moodle 4.5 0.1.6 → 1.1 upgrade on MariaDB and PostgreSQL — **USER-VERIFIED 2/2 PASS**.
- [ ] Record developer-debugging QA against the final merged/release source if not already completed.
- [ ] Record exact merged-source Plugin privacy registry re-verification.
- [x] Public source/documentation/tracker — **VERIFIED**.
- [x] Real screenshots captured — **USER-VERIFIED**.
- [ ] Confirm `CHANGES.md`, `README.md`, `version.php`, tag name and release notes are mutually consistent immediately before tagging.
- [ ] Create tag/release and produce/inspect the final package contents.

## Current status

As of 2026-08-21:

- `main`: PR #15 privacy fix merged as `feaf98afde1603671a040a1bdf7b6b0e1a381d17`.
- Plugin metadata: `2026082007 / 1.1 / MATURITY_STABLE`.
- Manual compatibility: **8/8 PASS** across declared Moodle/database combinations.
- Manual minimum-version upgrades: **2/2 PASS** on Moodle 4.5 x MariaDB/PostgreSQL.
- Repository/source/docs/tracker: **public and verified**.
- Screenshots: **captured, maintainer-reported**.
- Release QA workflow: **not yet recorded as executed**.
- Exact merged-source privacy registry check: **not yet recorded**.
- Tag/release/package: **not yet created**.

Issue #6 should remain open until the remaining applicable release gates above are completed and evidenced.
