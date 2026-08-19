# Repository profile

## Component

- Frankenstyle component: `local_autobrowsertimezone`
- Plugin type: `local`
- Purpose: keep an eligible logged-in user's Moodle profile timezone aligned with the IANA timezone reported by the browser/device
- Current implementation has no custom database tables

Always re-read `version.php` rather than hard-coding its release/version numbers in new code or documentation.

## Runtime path

The main execution path is:

1. `db/hooks.php`
2. `classes/hook_callbacks.php`
3. `classes/local/manager.php`
4. `amd/src/timezone.js`
5. Moodle `core/ajax`
6. `classes/external/update_timezone.php`
7. `classes/local/manager.php`
8. Moodle core user API

Settings are in `settings.php`; privacy metadata is in `classes/privacy/provider.php`; the external-function declaration is in `db/services.php`.

## Repository policy

The plugin is intended for Moodle Marketplace publication. Functional correctness, Moodle API compliance, privacy/security correctness, and formal plugin checks are all acceptance criteria.

The repository contains generated AMD build artefacts in `amd/build/`. Source changes belong in `amd/src/`; generated output must be rebuilt, committed, and verified.

## Development references

Use version-matched Moodle Developer Resources and core source for:

- Local plugins
- Hooks API
- External functions and External API security
- Privacy API
- JavaScript modules and Grunt
- Coding style
- PHPUnit
- Plugin code prechecks
