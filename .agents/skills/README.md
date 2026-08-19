# Project Agent Skills

These skills follow the open Agent Skills `SKILL.md` format and are intentionally project-scoped.

## Skills

| Skill | Use for |
|---|---|
| `moodle-plugin-triage` | Classifying a task and choosing the correct Moodle workflow |
| `autobrowsertimezone-domain` | Plugin-specific behaviour and invariants |
| `moodle-local-plugin-development` | Moodle local-plugin implementation work |
| `moodle-plugin-security-privacy` | Security, authorization, profile ownership, and privacy |
| `moodle-plugin-testing` | PHPUnit, CI, JavaScript builds, and compatibility testing |
| `moodle-marketplace-readiness` | Marketplace release gates and packaging |

Each skill keeps its main procedure in `SKILL.md` and pushes deeper detail into `references/` where useful. This follows the progressive-disclosure pattern used by the open Agent Skills specification.

These files are development aids. The Marketplace release package should exclude `.agents/` and other repository-only development metadata unless there is a specific reason to ship them.
