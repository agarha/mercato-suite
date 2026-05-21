#!/usr/bin/env python3
"""
Validate every module.json in mercato-suite.

Checks:
- Required fields present.
- Slug matches directory name.
- Event names follow canonical taxonomy mercato.<plugin>.<entity>.<verb>.v<N>.
- Dependency declarations parseable as slug@semver-range.
- Capabilities follow mercato_<resource>_<action> pattern.

Returns non-zero on any violation.
"""
from __future__ import annotations
import json, os, re, sys
from pathlib import Path

REPO = Path(__file__).resolve().parent.parent
MODULES = REPO / "apps" / "wordpress" / "wp-content" / "plugins" / "mercato-suite" / "modules"

REQUIRED_FIELDS = {
    "slug", "namespace", "version", "sdk_version", "requires",
    "provides_events", "consumes_events", "capabilities", "tables",
    "tier", "feature_flag",
}
TIER_VALUES = {"foundation", "domain", "adapter"}
EVENT_PATTERN = re.compile(r"^mercato\.[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*){1,4}\.v\d+$")
DEP_PATTERN = re.compile(r"^[a-z0-9-]+(@[\^~><=\d.\-x*\s|]+)?$")
CAP_PATTERN = re.compile(r"^mercato_[a-z][a-z0-9_]*$")
TABLE_PATTERN = re.compile(r"^wp_mercato_[a-z][a-z_0-9]*$")

errors: list[str] = []

def check_module(mod_dir: Path) -> None:
    mf = mod_dir / "module.json"
    if not mf.exists():
        errors.append(f"{mod_dir.name}: missing module.json")
        return
    try:
        data = json.loads(mf.read_text())
    except json.JSONDecodeError as e:
        errors.append(f"{mod_dir.name}: invalid JSON: {e}")
        return

    missing = REQUIRED_FIELDS - data.keys()
    if missing:
        errors.append(f"{mod_dir.name}: missing fields: {sorted(missing)}")

    if data.get("slug") != mod_dir.name:
        errors.append(f"{mod_dir.name}: slug mismatch ({data.get('slug')!r})")

    if data.get("tier") not in TIER_VALUES:
        errors.append(f"{mod_dir.name}: tier must be one of {TIER_VALUES}")

    for event in data.get("provides_events", []) + data.get("consumes_events", []):
        if not EVENT_PATTERN.match(event):
            errors.append(f"{mod_dir.name}: event {event!r} does not match canonical taxonomy")

    for dep in data.get("requires", []):
        if not DEP_PATTERN.match(dep):
            errors.append(f"{mod_dir.name}: dependency {dep!r} unparseable")

    for cap in data.get("capabilities", []):
        if not CAP_PATTERN.match(cap):
            errors.append(f"{mod_dir.name}: capability {cap!r} violates naming pattern")

    for table in data.get("tables", []):
        if not TABLE_PATTERN.match(table):
            errors.append(f"{mod_dir.name}: table {table!r} violates naming pattern")

def main() -> int:
    if not MODULES.is_dir():
        print(f"modules path not found: {MODULES}", file=sys.stderr)
        return 2

    modules = sorted(p for p in MODULES.iterdir() if p.is_dir())
    print(f"Validating {len(modules)} modules...")

    for m in modules:
        check_module(m)

    if errors:
        print(f"\n{len(errors)} validation error(s):")
        for e in errors:
            print(f"  - {e}")
        return 1

    print("All manifests valid.")
    return 0

if __name__ == "__main__":
    sys.exit(main())
