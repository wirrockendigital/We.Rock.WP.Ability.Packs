"""Build one independently installable WordPress plugin ZIP from a reviewed pack."""

import argparse
import json
from pathlib import Path
from zipfile import ZIP_DEFLATED, ZipFile, ZipInfo


ROOT = Path(__file__).resolve().parents[1]


def main() -> None:
    """Package only runtime files and user documentation for one target plugin."""
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("pack", help="Repository-relative pack directory")
    parser.add_argument("--output", default="dist", help="Output directory")
    args = parser.parse_args()

    # Require a pack directory within this checkout and a complete manifest.
    pack = (ROOT / args.pack).resolve()
    if not pack.is_relative_to(ROOT) or not pack.is_dir():
        parser.error("pack must be a directory inside this repository")
    manifest_path = pack / "pack.json"
    if not manifest_path.is_file():
        parser.error("pack.json is missing")
    manifest = json.loads(manifest_path.read_text())
    slug, version = manifest["slug"], manifest["version"]
    if not slug or "/" in slug or ".." in slug:
        parser.error("invalid pack slug")

    # Include the plugin entry point, its adapters, and only the files needed at runtime.
    main_files = [path for path in pack.glob("*.php") if "Plugin Name:" in path.read_text()]
    if len(main_files) != 1:
        parser.error("exactly one WordPress plugin entry point is required")
    files = main_files + [pack / "pack.json", pack / "README.md", pack / "LICENSE"]
    files += sorted((pack / "src").rglob("*.php"))
    files += sorted((pack / "includes").rglob("*.php"))
    files += sorted((pack / "abilities").rglob("*.php"))
    if (pack / "bootstrap.php").is_file():
        files.append(pack / "bootstrap.php")
    if any(not path.is_file() for path in files):
        parser.error("a required runtime or documentation file is missing")

    output = (ROOT / args.output).resolve()
    output.mkdir(parents=True, exist_ok=True)
    archive = output / f"{slug}-{version}.zip"
    with ZipFile(archive, "w", ZIP_DEFLATED, compresslevel=9) as zipped:
        for path in sorted(files):
            # Stable names and timestamps keep package hashes reproducible.
            name = f"{slug}/{path.relative_to(pack).as_posix()}"
            info = ZipInfo(name, (1980, 1, 1, 0, 0, 0))
            info.compress_type = ZIP_DEFLATED
            info.external_attr = 0o644 << 16
            zipped.writestr(info, path.read_bytes(), compress_type=ZIP_DEFLATED, compresslevel=9)
    print(archive)


if __name__ == "__main__":
    main()
