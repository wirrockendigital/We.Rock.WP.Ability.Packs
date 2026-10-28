"""Run operation-specific compatibility regressions in fresh PHP processes."""
from pathlib import Path
import subprocess
root = Path(__file__).resolve().parent
for pack in ['ninja', 'snippets', 'media', 'cookie']:
    # Multilingual sites must retain safe reads while blocking unreviewed writes.
    for scenario in ['current', 'future', 'missing-write', 'missing-read', 'inactive', 'denied', 'multisite'] + (['multilingual'] if pack in ['media', 'cookie'] else []):
        subprocess.run(['php', str(root / 'compatibility.php'), pack, scenario], check=True)
print('PASS: 30 isolated adapter scenarios; native reads, write prerequisites and status checked.')
