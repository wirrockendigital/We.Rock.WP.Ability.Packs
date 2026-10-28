"""Resolve an independently versioned pack from a GitHub release tag."""

import json
import re
import sys
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]


def main() -> None:
    """Require an exact pack slug and matching manifest/header version."""
    if len(sys.argv) != 2:
        raise SystemExit("usage: verify_release_tag.py <pack-slug>-v<semver>")
    tag = sys.argv[1]
    match = re.fullmatch(r"([a-z][a-z0-9-]+)-v(\d+\.\d+\.\d+)", tag)
    if match is None:
        raise SystemExit("release tag must be <pack-slug>-v<semver>")
    slug, version = match.groups()
    packs = [path for path in ROOT.glob("wrd-*-ability-pack") if path.name != "wrd-wpvivid-ability-pack"]
    packs += list(ROOT.glob("wrd-wpvivid-ability-pack/wrd-*-ability-pack"))

    # Match the manifest instead of inferring the installed plugin folder from source paths.
    matches = [path for path in packs if json.loads((path / "pack.json").read_text())["slug"] == slug]
    if len(matches) != 1:
        raise SystemExit("tag does not identify exactly one pack")
    pack = matches[0]
    manifest = json.loads((pack / "pack.json").read_text())
    main_files = [path for path in pack.glob("*.php") if "Plugin Name:" in path.read_text()]
    if len(main_files) != 1 or manifest["version"] != version or f" * Version: {version}\n" not in main_files[0].read_text():
        raise SystemExit("tag version must match the pack manifest and plugin header")
    print(pack.relative_to(ROOT))


if __name__ == "__main__":
    main()
