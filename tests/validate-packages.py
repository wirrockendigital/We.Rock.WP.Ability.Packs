"""Check that all ten installable ZIPs contain only runtime and user files."""

import json
import subprocess
import tempfile
from pathlib import Path
from zipfile import ZipFile


ROOT = Path(__file__).resolve().parents[1]
PACKS = [path for path in ROOT.glob("wrd-*-ability-pack") if path.name != "wrd-wpvivid-ability-pack"]
PACKS += list(ROOT.glob("wrd-wpvivid-ability-pack/wrd-*-ability-pack"))
assert len(PACKS) == 10

with tempfile.TemporaryDirectory() as temporary:
    for pack in PACKS:
        manifest = json.loads((pack / "pack.json").read_text())
        # Build from the public script, then inspect the actual WordPress ZIP root.
        subprocess.run(
            ["python3", str(ROOT / "scripts/package_pack.py"), str(pack.relative_to(ROOT)), "--output", temporary],
            check=True,
            capture_output=True,
        )
        archive = Path(temporary) / f"{manifest['slug']}-{manifest['version']}.zip"
        with ZipFile(archive) as zipped:
            names = zipped.namelist()
            assert all(name.startswith(manifest["slug"] + "/") for name in names), pack
            assert not any("/tests/" in name or "/coverage/" in name for name in names), pack
            assert f"{manifest['slug']}/pack.json" in names, pack
            assert f"{manifest['slug']}/LICENSE" in names, pack
            assert len(names) >= 5, pack
            assert zipped.testzip() is None, pack

print("PASS: ten installable ZIPs with isolated roots and no development tests")
