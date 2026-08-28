#!/usr/bin/env python3
"""Validate the repository scaffold without third-party dependencies."""

from __future__ import annotations

import hashlib
import json
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

EXPECTED_SUBMODULES = {
    ".licensing-policy": "6e4c2627717c079827ed4aa9044a5346b3ea3ddb",
    "code-constitution": "220dc9c286ae06f8b6ed60cdda75112eed0408ed",
}

CANONICAL_CONSTITUTION = (
    "code-constitution/locales/ru/CONSTITUTION_CODE.md",
    "864bf72a9072d394d77d09de40089f8f0f3ecc0ff8f77c41ef173f0852adf2ee",
)

REQUIRED_ROOT_FILES = (
    "AGENTS.md",
    "CONSTITUTION.md",
    "LICENSE.md",
    "LICENSING-OVERRIDES.md",
    "README.md",
    "SECURITY.md",
    "governance/PROFILE.md",
    "governance/acts.json",
    "governance/legislation/WORDPRESS_PLUGIN_DEVELOPMENT.md",
    "docs/IRONCREED-SUITE-PROTOCOL.md",
    "docs/WORDPRESS-ORG-RELEASE-GATE.md",
)


def fail(message: str) -> None:
    raise SystemExit(message)


def submodule_head(path: Path) -> str:
    result = subprocess.run(
        ["git", "-C", str(path), "rev-parse", "HEAD"],
        check=True,
        capture_output=True,
        text=True,
    )
    return result.stdout.strip()


def validate_required_files() -> None:
    missing = [path for path in REQUIRED_ROOT_FILES if not (ROOT / path).is_file()]
    if missing:
        fail(f"Missing required files: {', '.join(missing)}")


def validate_submodules() -> None:
    for relative_path, expected_sha in EXPECTED_SUBMODULES.items():
        path = ROOT / relative_path
        if not path.is_dir():
            fail(f"Submodule is not initialized: {relative_path}")
        actual_sha = submodule_head(path)
        if actual_sha != expected_sha:
            fail(
                f"Unexpected submodule revision for {relative_path}: "
                f"{actual_sha}; expected {expected_sha}"
            )


def validate_constitution() -> None:
    relative_path, expected_hash = CANONICAL_CONSTITUTION
    path = ROOT / relative_path
    actual_hash = hashlib.sha256(path.read_bytes()).hexdigest()
    if actual_hash != expected_hash:
        fail(
            f"Canonical constitution hash mismatch: {actual_hash}; "
            f"expected {expected_hash}"
        )


def validate_acts() -> None:
    registry = json.loads((ROOT / "governance/acts.json").read_text("utf-8"))
    acts = registry.get("acts")
    if not isinstance(acts, list) or not acts:
        fail("The act registry must contain a non-empty acts list")

    seen_ids: set[str] = set()
    for act in acts:
        act_id = act.get("id")
        if not isinstance(act_id, str) or not act_id.startswith("ics-"):
            fail(f"Invalid act id: {act_id!r}")
        if act_id in seen_ids:
            fail(f"Duplicate act id: {act_id}")
        seen_ids.add(act_id)

        act_path = act.get("path")
        if not isinstance(act_path, str) or not (ROOT / act_path).is_file():
            fail(f"Act {act_id} points to a missing file: {act_path!r}")

    for act in acts:
        for basis_id in act.get("basis", []):
            if basis_id not in seen_ids:
                fail(f"Act {act['id']} has an unknown basis: {basis_id}")


def validate_plugins() -> None:
    plugins_root = ROOT / "plugins"
    for directory in plugins_root.iterdir():
        if not directory.is_dir():
            continue
        if directory.name != directory.name.lower() or "_" in directory.name:
            fail(f"Plugin directory is not a lowercase slug: {directory.name}")
        for required_name in ("AGENTS.md", "IMPLEMENTATION-BRIEF.md", "README.md"):
            if not (directory / required_name).is_file():
                fail(f"{directory.name} is missing {required_name}")


def validate_no_private_logs() -> None:
    forbidden_suffixes = (".log", ".har")
    violations = [
        str(path.relative_to(ROOT))
        for path in ROOT.rglob("*")
        if path.is_file()
        and path.suffix.lower() in forbidden_suffixes
        and ".git" not in path.parts
    ]
    if violations:
        fail(f"Private-log-like files are prohibited: {', '.join(violations)}")


def main() -> None:
    validate_required_files()
    validate_submodules()
    validate_constitution()
    validate_acts()
    validate_plugins()
    validate_no_private_logs()
    print("Repository contracts validated.")


if __name__ == "__main__":
    main()
