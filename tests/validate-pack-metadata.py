"""Verify independent pack versions, target references, and distributable licenses."""

import json
import re
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
PACKS = [path for path in ROOT.glob("wrd-*-ability-pack") if path.name != "wrd-wpvivid-ability-pack"]
PACKS += list(ROOT.glob("wrd-wpvivid-ability-pack/wrd-*-ability-pack"))
assert len(PACKS) == 10, "Expected ten independent packs"
slugs = set()


def header(source: str, name: str) -> str:
    """Read one exact WordPress plugin header field."""
    match = re.search(r"^ \* " + re.escape(name) + r": (.+)$", source, re.M)
    assert match, f"Missing header field: {name}"
    return match.group(1)


for pack in PACKS:
    # Read the single entry point and compare its public header with the pack manifest.
    main_files = [path for path in pack.glob("*.php") if "Plugin Name:" in path.read_text()]
    assert len(main_files) == 1, pack
    source = main_files[0].read_text()
    manifest = json.loads((pack / "pack.json").read_text())
    assert manifest["version"] == header(source, "Version") == "0.1.0", pack
    assert manifest["target_plugin"] == header(source, "Agent Connector Target"), pack
    assert manifest["tested_target_plugin_version"] == header(source, "Tested Target Plugin Version"), pack
    assert manifest["license"] == header(source, "License") == "GPL-3.0-or-later", pack
    assert manifest["requires_plugin"] == "agent-connector-for-wp", pack
    assert (pack / "LICENSE").read_bytes() == (ROOT / "LICENSE").read_bytes(), pack
    assert manifest["slug"] not in slugs, pack
    slugs.add(manifest["slug"])

    # Keep the adapter's reviewed source reference aligned with the installable package.
    adapter = (pack / "src/Adapter.php").read_text()
    assert manifest["tested_target_plugin_version"] in adapter, pack

print(f"PASS: {len(PACKS)} independent pack versions, target references, and licenses")
