"""Check that every pack can resolve its own release tag."""

import json
import subprocess
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
PACKS = [path for path in ROOT.glob("wrd-*-ability-pack") if path.name != "wrd-wpvivid-ability-pack"]
PACKS += list(ROOT.glob("wrd-wpvivid-ability-pack/wrd-*-ability-pack"))

for pack in PACKS:
    # A valid tag resolves only this pack and its declared independent version.
    manifest = json.loads((pack / "pack.json").read_text())
    tag = f"{manifest['slug']}-v{manifest['version']}"
    result = subprocess.run(
        ["python3", str(ROOT / "scripts/verify_release_tag.py"), tag],
        capture_output=True, text=True, check=True,
    )
    assert result.stdout.strip() == str(pack.relative_to(ROOT)), tag

# A tag with a version not in the pack must never publish an installable ZIP.
invalid = subprocess.run(
    ["python3", str(ROOT / "scripts/verify_release_tag.py"), "wrd-code-snippets-ability-pack-v9.9.9"],
    capture_output=True, text=True,
)
assert invalid.returncode != 0 and "must match" in invalid.stderr

print("PASS: eleven isolated release tags and mismatched version rejection")
