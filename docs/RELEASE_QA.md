# Release QA procedure

This document describes the concrete, repeatable release gate for
`local_autobrowsertimezone`, separate from routine per-PR CI. It exists so a
Marketplace release decision does not depend on ad hoc manual steps.

Nothing in this document has been executed as part of preparing it (see
"Current status" at the end). It is the procedure to follow, and the record
of what has/has not actually been run, not a claim that the plugin is
release-ready.

## Automated vs manual vs outstanding

| Area | Mechanism | Trigger |
|---|---|---|
| PHP lint, PHPCS, PHPDoc, plugin validation, savepoints, JS lint/Grunt, PHPUnit on Moodle 5.2 x MariaDB | `.github/workflows/moodle-plugin-ci.yml` | Automatic, every push/PR |
| Same formal checks plus PHPUnit on the full declared Moodle 4.5-5.2 x PostgreSQL/MariaDB matrix | `.github/workflows/moodle-plugin-release-qa.yml` | Manual (`workflow_dispatch`), before a release |
| Install/upgrade QA, developer-debugging QA, Privacy API Data registry check | This document | Manual, staging site |
| Marketplace metadata, screenshots, tagging, packaging, publishing | This document / repository release process | Manual, explicitly authorised only |

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
2. **`compatibility`** (depends on `formal-checks` passing) — installs the
   plugin and runs the PHPUnit suite on all 8 legs of the declared support
   range:
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
own plugin install/upgrade process completes cleanly and the plugin behaves
correctly afterwards, not about a schema migration.

**Clean install**: install the plugin fresh into a supported Moodle site,
visit **Site administration → Notifications** to complete the install,
confirm no errors, then confirm the settings page (**Site administration →
Plugins → Local plugins → Automatic browser timezone**) loads and the
`enabled`/`reload` settings are present with their documented defaults
(`enabled` off, `reload` on).

**Upgrade**: install the previous releasable version first, then upgrade to
the current version in place, and confirm the upgrade completes with no
errors, the plugin remains functional, and its version number updates as
expected. The previous version is available as a real commit rather than a
constructed fixture:

```
git show 43850ca86c8c9f6608c45b71a2fa64551b5904b3:. # 0.1.4, the last release before this PR
```

(`43850ca86c8c9f6608c45b71a2fa64551b5904b3` is the commit where
`version.php` last declared `2026082004` / `0.1.4`, immediately before the
work in this pull request. Check out that commit's plugin tree as the
"previous version" and the tip of this branch as the "current version".)

**Scope**: exercising this on every one of the 8 supported branch/database
combinations is disproportionate for a plugin with zero schema — Moodle's
own upgrade/install code path is what would differ between those, not
anything this plugin controls. A single MariaDB leg and a single PostgreSQL
leg (both on the minimum supported branch, `MOODLE_405_STABLE`, since that
is where the widest API gap to current code exists) is proportionate
coverage; the release-QA compatibility matrix already separately proves
plugin installation succeeds on every branch/database combination as a
side effect of `moodle-plugin-ci install`.

**Developer debugging**: with `$CFG->debug = DEBUG_DEVELOPER` and
`$CFG->debugdisplay = 1`, exercise: a page load with a browser/profile
timezone mismatch and the plugin enabled; a successful update; the settings
page; and a page load with the plugin disabled. Confirm no PHP
notices/warnings and no debugging() output.

This has not been executed (no staging Moodle site is available in the
environment that prepared this document); it is the procedure to run
before a release, not a completed step.

## 3. Privacy QA

`classes/privacy/provider.php` implements
`\core_privacy\local\metadata\provider` only (no plugin-owned personal data,
so no `core_userlist_provider`/`plugin\provider` export/delete interfaces
are applicable) and declares a single `core_user` subsystem link covering
the `timezone` field.

- **Automated**: `tests/privacy_provider_test.php` asserts the declared
  metadata collection contains exactly this subsystem link with the correct
  field mapping and summary, and that the referenced lang strings exist.
  This is a regression check on the declaration; it does not replace the
  manual step below.
- **Manual (staging site)**: as an admin, visit **Site administration →
  Users → Privacy and policies → Data registry**, locate "Automatic browser
  timezone", and confirm it shows the declared `core_user` subsystem link
  rather than "Not implemented" or an error. Not yet executed (requires a
  staging site).
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
- **Source repository**: `https://github.com/LightMoonProjects/moodle-local_autobrowsertimezone`
- **Documentation URL**: the repository `README.md` (no separate hosted
  documentation site exists; do not fabricate one).
- **Public issue tracker**: `https://github.com/LightMoonProjects/moodle-local_autobrowsertimezone/issues`
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

Do not change `$plugin->maturity` from `MATURITY_ALPHA`, tag a release, or
publish a Marketplace package until:

- the release QA workflow has actually run (not merely exists) and every
  leg is confirmed green individually;
- install/upgrade QA (section 2) has actually been executed with a result
  recorded;
- the Data registry manual privacy check (section 3) has actually been
  executed;
- real screenshots have been captured;
- CHANGES.md and README.md accurately describe the release.

## Current status

As of this document's introduction:

- Release QA workflow: **exists on this branch, not yet runnable**
  (`workflow_dispatch` workflows only become runnable once merged to the
  default branch) — pending post-merge execution.
- Install/upgrade QA: **not executed** — procedure documented above.
- Privacy Data registry check: **not executed** — procedure documented
  above; automated metadata regression test added.
- Screenshots: **not captured**.
- Marketplace metadata: **drafted** (section 4), not finalised/published.
