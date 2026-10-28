"""Verify independent pack versions, target references, and distributable licenses."""

import json
import re
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
PACKS = [path for path in ROOT.glob("wrd-*-ability-pack") if path.name != "wrd-wpvivid-ability-pack"]
PACKS += list(ROOT.glob("wrd-wpvivid-ability-pack/wrd-*-ability-pack"))
assert len(PACKS) == 10, "Expected ten independent packs"
slugs = set()
DISPLAY_NAMES = {
    "wrd-ninjafirewall-ability-pack": "NinjaFirewall",
    "wrd-code-snippets-ability-pack": "CodeSnippetsPro",
    "wrd-polylang-ability-pack": "Polylang",
    "wrd-real-category-management-ability-pack": "RealCategoryManagement",
    "wrd-real-cookie-banner-ability-pack": "RealCookieBanner",
    "wrd-real-media-library-ability-pack": "RealMediaLibrary",
    "wrd-wpvivid-backup-ability-pack": "WPvividBackup",
    "wrd-wpvivid-pro-ability-pack": "WPvividPro",
    "wrd-wpvivid-staging-ability-pack": "WPvividStaging",
    "wrd-wpvivid-imgoptim-free-ability-pack": "WPvividImgoptimFree",
}


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
    # WordPress builds the Plugins list name, version, and byline from these fields.
    assert header(source, "Plugin Name") == f"We.Rock.{DISPLAY_NAMES[pack.name]}.Ability.Pack", pack
    assert header(source, "Author") == "wir.rocken.digital", pack
    assert header(source, "Author URI") == "https://wir.rocken.digital", pack
    assert re.fullmatch(r"\d+\.\d+\.\d+", manifest["version"]), pack
    assert manifest["version"] == header(source, "Version"), pack
    assert header(source, "Update URI") == (
        "https://github.com/wirrockendigital/We.Rock.WP.Ability.Packs/releases?q=" + manifest["slug"]
    ), pack
    assert (pack / "includes/github-updater.php").is_file(), pack
    assert "AbilityPackUpdater\\Updater::register(__FILE__, '" + manifest["slug"] + "')" in source, pack
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
