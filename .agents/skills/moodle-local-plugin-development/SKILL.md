---
name: moodle-local-plugin-development
description: "Guides Moodle local-plugin implementation in this repository: component structure, hooks, settings, external functions, core user APIs, language strings, JavaScript modules, generated AMD assets, version metadata, and cross-version API compatibility. Use for PHP or JavaScript feature work and refactors."
compatibility: "Targets the Moodle range declared by this plugin. Repository file access and a Moodle development/test environment are recommended."
---

# Moodle Local Plugin Development

## Baseline

This is a `local` plugin. Use Moodle APIs and conventions first; generic PHP/JavaScript patterns are secondary when Moodle defines a subsystem API.

Before editing, run `moodle-plugin-triage`.

## Procedure

### 1. Work from the minimum supported Moodle version

Read `version.php`.

- Treat `$plugin->requires` as the minimum API baseline.
- Verify APIs against the minimum supported branch before using them.
- Also check the maximum supported branch for deprecations or changed semantics.
- Avoid copying code from Moodle `main` when it is not available in the minimum supported release.

### 2. Keep plugin structure conventional

Expected responsibilities in this repository:

- `version.php` — metadata only; no includes or side effects.
- `settings.php` — administrator configuration.
- `db/hooks.php` — hook registrations.
- `db/services.php` — external-function declarations.
- `classes/` — autoloaded namespaced PHP classes.
- `lang/en/local_autobrowsertimezone.php` — user-facing strings.
- `amd/src/` — JavaScript source.
- `amd/build/` — generated/minified JavaScript committed for production.
- `tests/` — PHPUnit tests.
- `classes/privacy/provider.php` — Privacy API metadata.

Do not add `lib.php`, install/upgrade scripts, capabilities, tables, or scheduled tasks unless the feature genuinely requires them.

### 3. Hooks

Use Moodle's Hooks API for the existing page-output integration.

When changing a hook callback:

- remember hooks may run during install/upgrade;
- guard code that assumes a fully installed plugin or database;
- keep callbacks lightweight;
- delegate business logic to testable classes rather than putting it in registration files.

### 4. External functions and AJAX

For Moodle external functions:

- define parameters and return structures explicitly;
- call `validate_parameters()` before using inputs;
- call `validate_context()` for the most specific relevant context;
- enforce the required capabilities;
- do not call `require_login()` inside an external function;
- use Moodle's core AJAX client rather than custom ad-hoc endpoints for this workflow.

Keep `db/services.php` consistent with the implementation and capability requirements.

### 5. Core user profile updates

Read `autobrowsertimezone-domain` and `moodle-plugin-security-privacy` before changing profile-write code.

Rules:

- do not update `{user}` directly;
- preserve authentication-plugin ownership/update semantics;
- use Moodle core user APIs;
- preserve standard user-update event behaviour;
- update only the intended profile field;
- keep `$USER` coherent after a successful mutation when needed for the current request.

### 6. Settings and language strings

- Put user-facing text in the component language file.
- Use Moodle admin setting classes.
- Choose safe defaults; automatic profile mutation should not become enabled implicitly without an explicit product decision.
- If behaviour changes, update setting descriptions and README text so they remain accurate.

### 7. JavaScript

Write source in `amd/src/` using Moodle-supported module syntax.

- Prefer core modules such as `core/ajax` and `core/notification`.
- Keep PHP-to-JS parameters small and JSON-serializable.
- Never edit `amd/build/*.min.js` manually.
- Rebuild using Moodle's Grunt toolchain.
- Commit the regenerated build and source map when the repository currently tracks them.

Read `references/official-moodle-apis.md` for canonical documentation.

### 8. Versioning and changelog

For a deployable plugin-code change:

- increment `$plugin->version` using Moodle's date-based format;
- update `$plugin->release` when appropriate for the release strategy;
- add a concise entry to `CHANGES.md`;
- do not raise `$plugin->maturity` until release QA supports it.

Avoid version churn for documentation-only development aids unless the plugin package/runtime changes.

## Verification

Use `moodle-plugin-testing` for required checks.

At minimum:

- PHP syntax is valid;
- Moodle coding style/PHPDoc checks pass;
- PHPUnit covers changed behaviour;
- JavaScript/Grunt passes when JS changes;
- generated assets are current;
- supported-version CI remains green;
- README/privacy/release metadata match behaviour.
