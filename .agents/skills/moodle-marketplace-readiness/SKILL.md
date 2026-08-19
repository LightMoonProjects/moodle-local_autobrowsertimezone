---
name: moodle-marketplace-readiness
description: "Gates this Moodle plugin for Marketplace publication: formal plugin code prechecks, metadata and licensing, privacy declarations, supported-version CI, installation/upgrade QA, developer-debugging checks, generated assets, documentation, release maturity, clean tagged packaging, and exclusion of development-only files. Use before any release or Marketplace submission."
compatibility: "Release-management skill for local_autobrowsertimezone. GitHub and Moodle Plugin CI access are recommended."
---

# Moodle Marketplace Readiness

## Principle

Marketplace publication is a release gate, not an afterthought. A plugin must be fit for purpose and pass Moodle-oriented formal, compatibility, security/privacy, packaging, and documentation checks.

Moodle's current formal plugin code prechecks are useful release criteria, but they do not prove functionality or security. Keep project-owned behavioural QA as a separate gate.

Read `references/release-gate.md`.

## Procedure

### 1. Freeze the intended release baseline

Confirm:

- component name and plugin type;
- release/version number;
- minimum Moodle version;
- supported Moodle range;
- maturity;
- GPL-compatible licensing;
- source, documentation, issue tracker, and security-reporting information;
- user-facing description matches actual behaviour.

Do not increase maturity simply to make a submission look finished.

### 2. Resolve release-blocking issues

Inspect open repository issues.

Any unresolved finding that affects:

- Moodle API compliance;
- authorization/security;
- privacy;
- profile ownership/data correctness;
- supported-version compatibility;
- install/upgrade safety;
- required formal checks

is a release blocker unless there is a documented, technically justified decision otherwise.

### 3. Run formal Moodle plugin prechecks

Applicable checks include:

- `phplint`
- `phpcs`
- JavaScript lint
- CSS lint if CSS exists
- `phpdoc`
- savepoint checks
- third-party library checks if libraries exist
- Grunt/build checks
- Mustache checks if templates exist

Use the repository's Moodle Plugin CI workflow as the repeatable implementation of these checks.

Zero actionable errors/warnings is the target for first publication.

### 4. Run project-owned functional/security tests

Use `moodle-plugin-testing` and `moodle-plugin-security-privacy`.

At minimum:

- PHPUnit passes on every declared supported Moodle branch;
- authentication/profile policy cases pass;
- external-function security cases pass;
- browser mismatch/no-op/reload/retry behaviours are exercised;
- no developer-debugging notices/warnings appear during normal flows.

### 5. Installation and upgrade QA

On clean supported Moodle environments:

- install from the intended package;
- confirm settings page loads;
- enable and exercise the plugin;
- upgrade from the previous releasable version when applicable;
- uninstall if the plugin supports/needs uninstall behaviour;
- confirm the site remains usable when the plugin is disabled.

Use PostgreSQL and MariaDB/MySQL-family coverage. A non-default DB prefix is a useful QA guard even though this plugin currently has no custom SQL.

### 6. Privacy and documentation QA

Confirm:

- Privacy API metadata matches actual processing/storage;
- README describes data source and exclusions accurately;
- no third-party geolocation service is implied or used;
- security reporting instructions are usable;
- CHANGES includes the release's notable changes;
- screenshots/Marketplace copy describe the current UI and settings.

### 7. JavaScript/package integrity

If AMD source exists:

- build with Moodle's Grunt toolchain;
- confirm tracked build artefacts correspond to source;
- do not ship stale generated code.

Build the release from a clean tag/commit, not from an uncommitted working tree.

### 8. Keep development-only files out of the Marketplace package

The Git repository may contain development aids that users do not need at runtime.

Unless Marketplace packaging policy or a deliberate decision says otherwise, exclude repository-only items such as:

- `.git/`
- `.github/`
- `.agents/`
- `AGENTS.md`
- local CI caches/tooling output

Do not exclude required runtime files, language packs, generated AMD builds, license, README, or other files expected by Moodle/plugin review.

### 9. Final release decision

Do not publish when:

- a required supported branch is red;
- a security/privacy blocker remains;
- generated assets are stale;
- version/release metadata is inconsistent;
- install/upgrade has not been exercised;
- formal prechecks have unresolved actionable failures.

Record exceptions explicitly rather than silently waiving them.

## Marketplace guidance caveat

Moodle has moved from the legacy Plugins Directory workflow to Marketplace. Some older QA checklist pages are explicitly marked legacy. Use current Moodle Developer Resources and current Marketplace instructions as authoritative; older QA practices can still be used as conservative engineering checks where they remain technically relevant.
