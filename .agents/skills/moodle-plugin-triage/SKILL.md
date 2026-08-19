---
name: moodle-plugin-triage
description: "Classifies Moodle plugin work in this repository, determines affected APIs and supported-version constraints, and routes the agent to the correct development, security, testing, or Marketplace skill. Use before non-trivial fixes, features, refactors, compatibility work, or releases."
compatibility: "Project-scoped Moodle plugin skill. Requires repository file access; GitHub issue access is useful but optional."
---

# Moodle Plugin Triage

## When to use

Use this skill before substantial work on `local_autobrowsertimezone`, especially when the task touches more than one of PHP, Moodle APIs, JavaScript, security, privacy, CI, or release metadata.

## Procedure

### 1. Establish the repository baseline

Read, at minimum:

- `version.php`
- `README.md`
- `CHANGES.md`
- `docs/MARKETPLACE_READINESS.md`
- `.github/workflows/moodle-plugin-ci.yml`
- the files directly affected by the task

If GitHub issue access is available, inspect relevant open issues before changing code. Do not assume a previously observed issue list is still current.

Read `references/repo-profile.md` for the stable project facts and current architectural map.

### 2. Identify compatibility constraints

Derive the minimum and maximum supported Moodle versions from `version.php`.

Rules:

- The minimum supported Moodle release is the API baseline.
- Do not introduce APIs added after the minimum release without an explicit compatibility strategy.
- If an API's availability or semantics are uncertain, verify it in version-matched Moodle Developer Resources or Moodle core source for both ends of the supported range.
- Treat current online documentation as versioned documentation; do not silently apply a later branch's API to an older supported branch.

### 3. Classify the change

Route to one or more focused skills:

- Core plugin architecture, hooks, settings, external functions, JavaScript, versioning:
  - `moodle-local-plugin-development`
- User profile mutation, capabilities, context, untrusted input, authentication plugins, privacy:
  - `moodle-plugin-security-privacy`
- Any behavioural change or bug fix:
  - `moodle-plugin-testing`
- Release, maturity, metadata, packaging, formal checks:
  - `moodle-marketplace-readiness`
- Any change to what automatic timezone synchronisation is allowed to do:
  - `autobrowsertimezone-domain`

### 4. Define acceptance criteria before editing

Acceptance criteria should include:

- intended user-visible behaviour
- security/authorization expectations
- supported-version expectations
- test coverage
- generated asset requirements if JavaScript changes
- documentation/privacy/release metadata impact
- Marketplace implications

Do not treat "works on one development site" as sufficient for a release-targeted change.

### 5. Prefer the smallest compliant change

Avoid unrelated modernization or architectural churn in the same patch. Moodle Marketplace readiness benefits from changes that are easy to audit and regression-test.

## Verification

Before considering triage complete, be able to state:

- affected files and APIs
- minimum-version compatibility risks
- required focused skills
- required tests/checks
- whether the change affects Marketplace release readiness
