# Release QA procedure

This document describes the concrete, repeatable release gate for
`local_autobrowsertimezone`, separate from routine per-PR CI. It exists so a
Marketplace release decision does not depend on ad hoc manual steps.

The routine PR CI and automated PHPUnit/privacy regression coverage described
below have run on the development branch where noted. The release-specific
compatibility matrix and staging/manual steps have **not** yet been executed;
see "Current status" at the end. This document is the procedure to follow and
the record of what has/has not actually been run, not a claim that the plugin
is release-ready.

## Automated vs manual vs outstanding

| Area | Mechanism | Trigger |
|---|---|---|
| PHP lint, PHPCS, PHPDoc, plugin validation, savepoints, JS lint/Grunt, PHPUnit on Moodle 5.2 x MariaDB | `.github/workflows/moodle-plugin-ci.yml` | Automatic, every push/PR |
| Same formal checks plus clean plugin install and PHPUnit on the full declared Moodle 4.5-5.2 x PostgreSQL/MariaDB matrix | `.github/workflows/moodle-plugin-release-qa.yml` | Manual (`workflow_dispatch`), before a release |
| Upgrade QA, developer-debugging QA, Privacy API Data registry check | This document | Manual, staging site |
| Public Marketplace source/documentation/tracker URLs, screenshots, tagging, packaging, publishing | This document / repository release process | Manual, explicitly authorised only |

## 1. Release QA workflow

`.github/workflows/moodle-plugin-release-qa.yml` runs only on
`workflow_dispatch` (Actions tab → "Moodle Plugin Release QA" → "Run
workflow", or `gh workflow run moodle-plugin-release-qa.yml`). It does not
run on every push/PR; routine CI intentionally stays the single
`MOODLE_502_STABLE` x MariaDB job.

Two jobs:

1. **`formal-checks`** — runs once, on `MOODLE_502_STABLE` x MariaDB: PHP
   lint, Moodle Code Checker, PHPDoc, plugin validation, savepoints, JS
   lint/Grunt. These checks are not database/Moodle-version-specific, so
   running them on every compatibility leg would be redundant expense.
2. **`compatibility`** (depends on `formal-checks` passing) — performs a
   clean plugin install and runs the PHPUnit suite on all 8 legs of the
   declared support range:
   - Moodle 4.5 x MariaDB
   - Moodle 4.5 x PostgreSQL
   - Moodle 5.0 x MariaDB
   - Moodle 5.0 x PostgreSQL
   - Moodle 5.1 x MariaDB
   - Moodle 5.1 x PostgreSQL
   - Moodle 5.2 x MariaDB
   - Moodle 5.2 x PostgreSQL

A workflow can only be run via `workflow_dispatch` once its YAML file exists
on the repository's default branch, so this workflow cannot be executed
until the pull request introducing it is merged to `main`. Until then, its
release-matrix evidence is **pending post-merge**, not skipped — do not
treat that as equivalent to a passing run.

## 2. Install / upgrade QA

The plugin has no custom database schema (no `db/install.xml`, no
`db/upgrade.php` steps), so install/upgrade QA is about confirming Moodle's
plugin install/upgrade process completes cleanly and the plugin behaves
correctly afterwards, not about a schema migration.

**Clean install across the declared support range** is part of the release QA
workflow. Each of the 8 Moodle/database compatibility legs starts from a
clean Moodle test installation and installs the plugin with
`moodle-plugin-ci install` before running PHPUnit. Do not mark clean-install
coverage complete until all 8 release-QA legs have actually completed
successfully.

**Manual staging smoke after install**: on the staging environments used for
release QA, visit **Site administration → Notifications** as required,
confirm no errors, then confirm the settings page (**Site administration →
Plugins → Local plugins → Automatic browser timezone**) loads and the
`enabled`/`reload` settings are present with their documented defaults
(`enabled` off, `reload` on). At minimum exercise this browser/UI smoke on
the minimum supported Moodle release and one current supported release so
the non-CLI web path is covered.

**Upgrade**: install the immediate previous release first, then upgrade to
the current version in place and confirm the upgrade completes with no
errors, the plugin remains functional, and its version number updates as
expected. For the current 1.1 candidate, the immediate predecessor is the
real 0.1.6 commit:

```
git show 3cf499f860001c19a8452e1cedfcfb3ac5c29fdd:. # 0.1.6 / 2026082006
```

`3cf499f860001c19a8452e1cedfcfb3ac5c29fdd` is the current `main` commit on
which this pull request is based and where `version.php` declares
`2026082006` / `0.1.6`. Use that plugin tree as the previous version and the
tip of the release candidate branch (currently 1.1 / 2026082007) as the
upgrade target. This is an ordinary plugin version-number upgrade -- the
plugin has no `db/upgrade.php` schema step, so no fake migration step is
introduced solely for this version bump.

**Manual upgrade scope**: because the plugin has no plugin-owned schema or
upgrade steps, manually repeating the same 0.1.6 → 1.1 upgrade on all eight
Moodle/database combinations is disproportionate. Execute the manual upgrade
on `MOODLE_405_STABLE` with both MariaDB and PostgreSQL, recording the exact
result. The release-QA matrix separately proves clean installation and
runtime PHPUnit compatibility on all 8 supported branch/database legs. If
that matrix, developer debugging, or the minimum-version upgrade exposes a
version-specific concern, expand upgrade testing to the affected branch(es)
before release.

**Developer debugging**: with `$CFG->debug = DEBUG_DEVELOPER` and
`$CFG->debugdisplay = 1`, exercise: a page load with a browser/profile
timezone mismatch and the plugin enabled; a successful update; the settings
page; a page load with the plugin disabled; and a login-as session. Confirm
no PHP notices/warnings, no fatal errors, and no unexpected `debugging()`
output.

These release/staging steps have not yet been executed; this is the procedure
to run before a release, not a completed step.

## 3. Privacy QA

`classes/privacy/provider.php` implements
`\core_privacy\local\metadata\provider` (declaring a single `core_user`
subsystem link covering the `timezone` field),
`\core_privacy\local\request\plugin\provider` (the narrowest Moodle-supported
request-provider contract for a plugin with no independently owned
personal-data record), and `\core_privacy\local\request\core_userlist_provider`
(required alongside `plugin\provider` — see below). All request-provider
methods, including `get_users_in_context()`/`delete_data_for_users()`, are
no-ops: the plugin owns no independently discoverable users or records of its
own in any context.

This was added to resolve
[#14](https://github.com/LightMoonProjects/moodle-local_autobrowsertimezone/issues/14),
across two real Moodle 5.2.2 staging findings on the same branch:

1. **`component_is_compliant()` false**: the prior 0.1.6 build implemented
   only `metadata\provider`. `core_privacy\manager::component_is_compliant()`
   does not accept a metadata-only provider unless it also implements
   `null_provider` (not appropriate here, since the plugin does process/write
   personal data through `core_user`). Staging confirmed
   `component_is_compliant('local_autobrowsertimezone')` returned `false` and
   the Plugin privacy registry showed a red non-compliance warning. Fixed by
   adding `plugin\provider`.
2. **"Userlist provider missing"**: deploying that fix pre-merge to staging
   confirmed `component_is_compliant()` now returns `true`, but the Plugin
   privacy registry additionally displayed **"Userlist provider missing"**.
   `tool_dataprivacy\metadata_registry::get_registry_metadata()` (verified
   identical on Moodle 4.5 and 5.2 core source) independently flags
   `userlistnoncompliance` for any `core_user_data_provider` descendant —
   which `plugin\provider` is — that does not also implement
   `core_userlist_provider`, regardless of `component_is_compliant()`. An
   earlier assumption that `core_userlist_provider` was "deliberately not
   needed" was disproven by this real staging behaviour and has been
   corrected: `core_userlist_provider` is now implemented.

- **Automated (pre-merge)**: `tests/privacy_provider_test.php` asserts the
  declared metadata collection contains exactly the `core_user` subsystem
  link with the correct field mapping and summary and that the referenced
  lang strings exist; asserts `core_privacy\manager::component_is_compliant('local_autobrowsertimezone')`
  returns `true`; asserts the provider implements `plugin\provider` and
  `core_userlist_provider` and does not use `null_provider`; exercises
  `get_contexts_for_userid()`, `export_user_data()`, `delete_data_for_user()`,
  `delete_data_for_all_users_in_context()`, `get_users_in_context()` and
  `delete_data_for_users()` directly to prove they never mutate the user's
  timezone or an unrelated core profile field; and exercises
  `tool_dataprivacy\metadata_registry::get_registry_metadata()` itself to
  prove the component's registry entry no longer carries
  `userlistnoncompliance`. These are regression checks that run pre-merge;
  they do not replace the manual step below, which requires the fixed build
  to actually be deployed.
- **Manual (staging site, post-merge)**: as an admin, with the fixed/merged
  build deployed, visit **Site administration → Users → Privacy and
  policies → Data registry**, locate "Automatic browser timezone", and
  confirm it shows the declared `core_user` subsystem link rather than "Not
  implemented" or an error; also visit **Plugin privacy registry** and
  confirm both the red non-compliance warning and "Userlist provider missing"
  are gone. Not yet executed for the current commit — the staging site was
  previously used to pre-merge-verify an earlier commit on this same branch
  (`9aad71f3e16ca5378b2656b38b60248404703463`, which is where "Userlist
  provider missing" was found) and must be redeployed with the exact new
  commit before this step can be re-run.
- `moodle-plugin-ci validate` (part of both CI workflows) also validates
  that Privacy API metadata is present when profile data is touched.

## 4. Marketplace metadata draft

Concrete draft content for the Marketplace listing, to review/finalise at
publication time:

- **Plugin name**: Automatic browser timezone
- **Frankenstyle component**: `local_autobrowsertimezone`
- **Plugin type**: Local plugin
- **Short description**: Keeps each user's Moodle profile timezone aligned
  with the timezone reported by their browser/device, without GPS, IP
  geolocation, or external APIs.
- **Full description**: draws from `README.md`'s "Why" and "Behaviour"
  sections — detects the browser's IANA timezone client-side, validates it
  against Moodle's supported timezone list server-side, and updates the
  current user's profile timezone through Moodle's user API only when the
  active authentication plugin's own update policy (capability, field lock,
  `can_edit_profile()`, `user_update()`) permits it. No GPS, IP geolocation,
  MaxMind, or third-party timezone service is used.
- **Current source repository (private)**:
  `https://github.com/LightMoonProjects/moodle-local_autobrowsertimezone`.
  This is the authoritative development repository, but it is currently
  private and therefore is **not yet a public Marketplace source URL**.
- **Public source URL**: **outstanding** — before publication, provide a
  publicly accessible source location (for example by explicitly making the
  intended repository public, or by providing another approved public source
  repository). Repository visibility must not be changed implicitly as part
  of QA work.
- **Documentation URL**: **outstanding as a public URL** while the current
  repository is private. The repository `README.md` is the documentation
  source and can be used once it is publicly accessible, or an approved
  separate public documentation URL can be supplied.
- **Current issue tracker (private)**:
  `https://github.com/LightMoonProjects/moodle-local_autobrowsertimezone/issues`.
- **Public issue tracker URL**: **outstanding** — the current private tracker
  must not be described as public. Provide an accessible public tracker
  before Marketplace publication.
- **Supported Moodle versions**: 4.5 through 5.2 (`version.php`
  `$plugin->supported`).
- **Privacy statement**: see section 3 above and `README.md`'s "Privacy"
  section — no plugin-owned personal data table, no external transmission,
  writes only the existing core `user.timezone` field.
- **Installation/configuration summary**: install into `local/autobrowsertimezone`,
  complete the Moodle upgrade, then enable "Automatic browser timezone" and
  optionally "Reload after timezone change" at **Site administration →
  Plugins → Local plugins → Automatic browser timezone**.
- **Screenshots**: **outstanding** — none captured. Screenshots require a
  staged Moodle installation with the plugin's settings page and a visible
  before/after timezone change; none is available in the environment that
  prepared this document. Do not fabricate screenshots; capture real ones
  from a staging site before publication.

## 5. Release decision gate

`$plugin->maturity` was raised to `MATURITY_STABLE` as part of the 1.1
release metadata target requested in #14. That change reflects this
release's own metadata only. Do not tag a release or publish a Marketplace
package until:

- the release QA workflow has actually run (not merely exists) and every
  leg is confirmed green individually;
- install/upgrade and developer-debugging QA (section 2) have actually been
  executed with results recorded;
- the Data registry / Plugin privacy registry manual check (section 3) has
  actually been executed against the merged/deployed build;
- publicly accessible source, documentation, and issue-tracker URLs have
  been confirmed;
- real screenshots have been captured;
- CHANGES.md and README.md accurately describe the release.

## Current status

As of this document's most recent update (1.1 / #14):

- Routine PR CI for PR #13 (the prior 0.1.6 change): **passed** on Moodle 5.2
  x MariaDB, including formal checks and PHPUnit (30 tests, 70 assertions).
  Routine CI for this 1.1 pull request is tracked separately; see the PR's
  STOP REPORT for its exact result.
- Release QA workflow: **exists on this branch, not yet runnable**
  (`workflow_dispatch` workflows only become runnable once merged to the
  default branch) — pending post-merge execution.
- Clean-install release matrix: **not executed** — covered by the pending
  release QA workflow.
- Manual upgrade/developer-debugging QA: **not executed** — procedure
  documented above.
- Privacy registry compliance fix (#14): **automated regression added and
  passing pre-merge** for `component_is_compliant()`, the `plugin\provider`/
  `core_userlist_provider` contract, and the `metadata_registry`
  `userlistnoncompliance` check. Real pre-merge staging deployment of an
  earlier commit on this branch (`9aad71f3e16ca5378b2656b38b60248404703463`)
  confirmed `component_is_compliant()` returns `true`, but surfaced a second
  finding — the Plugin privacy registry additionally showed **"Userlist
  provider missing"** — which is fixed by this branch's current commit. The
  real staging Plugin privacy registry visual re-check for the *current* PR
  head is **not yet executed** — the staging site must be redeployed with the
  exact new commit before that can be confirmed.
- Public Marketplace source/documentation/tracker URLs: **outstanding** — the
  current GitHub repository and issue tracker are private.
- Screenshots: **not captured**.
- Marketplace metadata: **partially drafted** (section 4), not
  finalised/published.
- `$plugin->maturity`: **MATURITY_STABLE** (release metadata for 1.1 per
  #14) — not equivalent to the release gate above being complete; #6 remains
  open.
