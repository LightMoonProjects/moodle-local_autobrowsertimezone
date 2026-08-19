---
name: moodle-plugin-testing
description: "Defines test and CI workflows for this Moodle plugin: PHPUnit, external-function security tests, Moodle Plugin CI, PHP lint, PHPCS, PHPDoc, JavaScript/Grunt, generated AMD verification, supported-version coverage, MariaDB/PostgreSQL testing, and developer-debugging regression checks. Use for every behavioural change and before release."
compatibility: "Uses the repository's Moodle Plugin CI workflow and a Moodle development environment when available."
---

# Moodle Plugin Testing

## Testing principle

A Marketplace-targeted plugin must prove both behaviour and formal compliance. Passing a linter does not prove security or functionality; a manual browser test does not prove compatibility.

Run `scripts/repo_preflight.py` for a fast deterministic repository check before heavier CI.

## Test layers

### 1. Fast local checks

Run what the environment supports:

```bash
find . -name '*.php' -type f -print0 | xargs -0 -n1 php -l
python3 .agents/skills/moodle-plugin-testing/scripts/repo_preflight.py
```

When Moodle Plugin CI is installed:

```bash
moodle-plugin-ci phplint
moodle-plugin-ci phpcs --max-warnings 0
moodle-plugin-ci phpdoc --max-warnings 0
moodle-plugin-ci validate
moodle-plugin-ci savepoints
moodle-plugin-ci grunt --max-lint-warnings 0
moodle-plugin-ci phpunit --fail-on-warning
```

Run only applicable checks, but do not omit an applicable release check merely because the code is small.

### 2. PHPUnit behavioural coverage

Use `advanced_testcase` for code that modifies Moodle state.

Tests should cover the policy branches in `manager::should_run()` and the profile update path, including:

- plugin disabled;
- guest/anonymous;
- deleted/suspended user;
- login-as;
- MNet remote user where practical;
- forced site timezone;
- capability denied;
- auth plugin cannot edit profile;
- timezone field locked;
- `unlockedifempty`;
- invalid timezone;
- unchanged timezone;
- successful timezone update;
- auth-plugin update rejection/propagation policy;
- standard user-updated event behaviour.

Reset Moodle state correctly between tests. Avoid order-dependent tests.

### 3. External-function tests

Test the public server boundary, not only the manager:

- valid authenticated call;
- invalid parameter;
- invalid/unsupported timezone;
- capability denied;
- context/security behaviour;
- returned structure.

Do not add `require_login()` to make a test pass; external functions use `validate_context()` plus capability checks.

### 4. JavaScript and generated assets

When `amd/src/` changes:

- run Moodle's Grunt build/lint;
- confirm the corresponding `amd/build/*.min.js` is regenerated;
- commit source maps if this repository tracks them;
- never hand-edit minified output;
- manually test mismatch, no-op, success+reload, success without reload, and failure/retry behaviour.

### 5. Supported-version matrix

The GitHub Actions matrix is the authoritative automated compatibility declaration and should align with `version.php`.

At release time, every declared supported Moodle branch must pass.

Be aware that Moodle 4.5 and Moodle 5.x may use different PHPUnit major versions. Do not rely on test-framework features without verifying they are harmless/available across the declared range.

### 6. Database and environment coverage

Even without custom SQL, install/runtime should be exercised on:

- PostgreSQL;
- MariaDB/MySQL-family environment represented by CI;
- developer debugging enabled.

A useful staging configuration uses a non-default database prefix to catch accidental hard-coded `mdl_` usage.

### 7. Functional browser QA

For this plugin, manually verify at least:

- profile set to Server timezone (`99`);
- explicit profile timezone;
- browser timezone already matches;
- browser timezone differs;
- reload setting on/off;
- forced site timezone;
- capability denied;
- login-as;
- representative external-auth configuration used by target sites.

## Completion rule

A behaviour change is not complete because one PHPUnit test passes. It is complete when the changed policy is covered at the appropriate layers and the supported-version CI remains green.
