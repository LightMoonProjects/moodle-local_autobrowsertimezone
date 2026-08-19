# Agent instructions

This repository contains the Moodle local plugin `local_autobrowsertimezone`. Its acceptance criteria are both functional correctness and publishability through Moodle Marketplace.

## Skill routing

Project-scoped Agent Skills live in `.agents/skills/`.

Start with `moodle-plugin-triage` for non-trivial development work, then load the focused skill(s) that match the task:

- `autobrowsertimezone-domain` — plugin purpose, behavioural invariants, and user-profile policy.
- `moodle-local-plugin-development` — Moodle local-plugin architecture, hooks, external functions, settings, JavaScript, versioning, and core APIs.
- `moodle-plugin-security-privacy` — capabilities, contexts, untrusted browser input, authentication-plugin ownership, Privacy API, and security review.
- `moodle-plugin-testing` — PHPUnit, Moodle Plugin CI, JavaScript/Grunt, compatibility testing, and regression coverage.
- `moodle-marketplace-readiness` — Marketplace-oriented release gates, code prechecks, metadata, packaging, and staging QA.

## Repository invariants

- Preserve the declared supported Moodle range in `version.php` unless the change explicitly alters compatibility.
- Treat Moodle 4.5 as the minimum API baseline while it remains the minimum supported release.
- Do not use GPS, IP geolocation, MaxMind, or third-party timezone services.
- Treat every browser-reported timezone as untrusted input.
- Never bypass Moodle capability checks, context validation, authentication-plugin profile ownership, forced-timezone policy, or "login as" safeguards.
- Do not edit generated files under `amd/build/` by hand. Change `amd/src/` and rebuild with Moodle's Grunt toolchain.
- Use Moodle APIs rather than direct database writes for core user-profile mutations.
- Add or update automated tests for behaviour changes.
- Treat failing Marketplace/code-precheck/compatibility checks as release blockers, not documentation-only concerns.
- Keep `README.md`, `CHANGES.md`, privacy metadata, and release metadata consistent with actual behaviour.

## Source of truth

Prefer version-matched Moodle Developer Resources and Moodle core source over memory or generic PHP/JavaScript conventions when Moodle-specific behaviour is involved. Inspect the minimum and maximum supported Moodle branches when API compatibility is uncertain.
