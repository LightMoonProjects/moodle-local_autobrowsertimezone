#!/usr/bin/env python3
"""Fast repository preflight for a Moodle plugin checkout."""

from __future__ import annotations

import re
import shutil
import subprocess
import sys
from pathlib import Path


def repo_root() -> Path:
    return Path(__file__).resolve().parents[4]


def fail(message: str, errors: list[str]) -> None:
    errors.append(message)
    print(f"ERROR: {message}")


def warn(message: str) -> None:
    print(f"WARN:  {message}")


def ok(message: str) -> None:
    print(f"OK:    {message}")


def main() -> int:
    root = repo_root()
    errors: list[str] = []

    required = [
        "version.php",
        "README.md",
        "LICENSE",
        "CHANGES.md",
        "lang/en/local_autobrowsertimezone.php",
        "classes/privacy/provider.php",
        ".github/workflows/moodle-plugin-ci.yml",
    ]
    for relative in required:
        path = root / relative
        if path.exists():
            ok(f"found {relative}")
        else:
            fail(f"missing required repository file: {relative}", errors)

    version_path = root / "version.php"
    if version_path.exists():
        text = version_path.read_text(encoding="utf-8")
        fields = {
            "component": r"\$plugin->component\s*=",
            "version": r"\$plugin->version\s*=",
            "requires": r"\$plugin->requires\s*=",
            "maturity": r"\$plugin->maturity\s*=",
        }
        for name, pattern in fields.items():
            if re.search(pattern, text):
                ok(f"version.php declares {name}")
            else:
                fail(f"version.php does not declare {name}", errors)

        component = re.search(r"\$plugin->component\s*=\s*['\"]([^'\"]+)['\"]", text)
        if component and component.group(1) != "local_autobrowsertimezone":
            fail(
                f"unexpected component {component.group(1)!r}; expected "
                "'local_autobrowsertimezone'",
                errors,
            )

    src_dir = root / "amd" / "src"
    build_dir = root / "amd" / "build"
    if src_dir.exists():
        for source in sorted(src_dir.glob("*.js")):
            built = build_dir / f"{source.stem}.min.js"
            if built.exists():
                ok(f"generated AMD file exists for {source.name}")
            else:
                fail(f"missing generated AMD file: {built.relative_to(root)}", errors)

    conflict_markers = ("<<<<<<<", "=======", ">>>>>>>")
    for path in root.rglob("*"):
        if not path.is_file() or ".git" in path.parts:
            continue
        if path.suffix.lower() not in {".php", ".js", ".md", ".yml", ".yaml"}:
            continue
        try:
            text = path.read_text(encoding="utf-8")
        except UnicodeDecodeError:
            continue
        if all(marker in text for marker in conflict_markers):
            fail(f"merge-conflict markers in {path.relative_to(root)}", errors)

    php = shutil.which("php")
    if php:
        php_files = sorted(
            path for path in root.rglob("*.php") if ".git" not in path.parts
        )
        for path in php_files:
            result = subprocess.run(
                [php, "-l", str(path)],
                stdout=subprocess.PIPE,
                stderr=subprocess.STDOUT,
                text=True,
                check=False,
            )
            if result.returncode != 0:
                fail(
                    f"PHP lint failed for {path.relative_to(root)}: "
                    f"{result.stdout.strip()}",
                    errors,
                )
        if php_files and not errors:
            ok(f"PHP lint passed for {len(php_files)} files")
    else:
        warn("php executable not available; skipped PHP lint")

    if (root / ".agents").exists():
        warn(
            ".agents/ contains development-only skills; exclude it from the "
            "Marketplace release package"
        )

    if errors:
        print(f"\nPreflight failed with {len(errors)} error(s).")
        return 1

    print("\nPreflight passed.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
