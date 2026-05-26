#!/usr/bin/env python3
from pathlib import Path
import json
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
DOCS = ROOT.parent / "docs_v2" / "07_openapi"
OPENAPI = DOCS / "OpenAPI.yaml"
ASYNCAPI = DOCS / "AsyncAPI.yaml"
MODULES = ROOT / "apps/wordpress/wp-content/plugins/mercato-suite/modules"
CONTRACT = ROOT / "packages/contracts/mercato-mvp-contract.json"
RATE_LIMITS = ROOT / "apps/wordpress/wp-content/plugins/mercato-suite/config/rate-limits.json"


def normalize_route(route: str) -> str:
    route = re.sub(r"\(\?P<([^>]+)>[^)]+\)", r"{\1}", route)
    return route.replace("\\/", "/")


def collect_implemented_routes() -> set[str]:
    routes: set[str] = set()
    for path in MODULES.glob("*/src/*.php"):
        text = path.read_text(encoding="utf-8")
        for match in re.finditer(r"register_rest_route\('mercato/v1',\s*'([^']+)'", text):
            routes.add(normalize_route(match.group(1)))
    return routes


def collect_manifest_events() -> set[str]:
    events: set[str] = set()
    for manifest in MODULES.glob("*/module.json"):
        data = json.loads(manifest.read_text(encoding="utf-8"))
        events.update(data.get("provides_events", []))
    return events


def main() -> int:
    if not OPENAPI.exists() or not ASYNCAPI.exists() or not CONTRACT.exists() or not RATE_LIMITS.exists():
        print("OpenAPI/AsyncAPI documents or MVP contract overlay are missing.", file=sys.stderr)
        return 1

    openapi_text = OPENAPI.read_text(encoding="utf-8")
    asyncapi_text = ASYNCAPI.read_text(encoding="utf-8")
    contract = json.loads(CONTRACT.read_text(encoding="utf-8"))
    rate_limits = json.loads(RATE_LIMITS.read_text(encoding="utf-8"))
    rest_routes = contract["rest_routes"]
    events = contract["events"]
    implemented_routes = collect_implemented_routes()
    manifest_events = collect_manifest_events()
    errors: list[str] = []
    for bucket in ["default", "read", "manage", "webhook", "public_register", "health"]:
        policy = rate_limits.get(bucket)
        if not isinstance(policy, dict) or int(policy.get("limit", 0)) < 1 or int(policy.get("window_seconds", 0)) < 1:
            errors.append(f"Invalid rate-limit policy: {bucket}")

    for route in rest_routes:
        if route not in implemented_routes:
            errors.append(f"Route not implemented: {route}")

    for event in events:
        if event not in manifest_events:
            errors.append(f"Event missing from module manifests: {event}")

    docs_route_hits = sum(1 for route in rest_routes if route in openapi_text)
    docs_event_hits = sum(1 for event in events if event in asyncapi_text)

    if errors:
        for error in errors:
            print(error, file=sys.stderr)
        return 1

    print(json.dumps({
        "routes_checked": len(rest_routes),
        "events_checked": len(events),
        "implemented_routes": len(implemented_routes),
        "manifest_events": len(manifest_events),
        "docs_route_hits": docs_route_hits,
        "docs_event_hits": docs_event_hits,
        "rate_limit_buckets": len(rate_limits),
    }))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
