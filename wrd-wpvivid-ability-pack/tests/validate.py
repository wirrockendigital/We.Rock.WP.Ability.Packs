#!/usr/bin/env python3
"""Validate all independent packs, schemas and coverage consistency without network access."""
from pathlib import Path
import json
import subprocess
import re
import os
import jsonschema

ROOT = Path(__file__).resolve().parents[1]

def run(*args: str, future_versions: bool = False) -> str:
    """Stop on a failing PHP gate and retain readable diagnostics."""
    env = os.environ.copy()
    # Each process gets fresh constants so newer vendor versions exercise real adapter paths.
    env['WPVIVID_TEST_FUTURE_VERSIONS'] = '1' if future_versions else ''
    result = subprocess.run(args, cwd=ROOT, capture_output=True, text=True, env=env)
    if result.returncode:
        raise SystemExit(result.stdout + result.stderr)
    return result.stdout

assertions = 0
inventories = 0
names: set[str] = set()
files = list(ROOT.rglob('*.php'))
for file in files:
    run('php', '-l', str(file))
for folder in sorted(ROOT.glob('*-ability-pack')):
    for future_versions in [False, True]:
        result = run('php', str(folder / 'tests/behavior.php'), future_versions=future_versions)
        assertions += int(re.search(r'PASS: (\d+)', result).group(1))
    schemas = json.loads(run('php', str(folder / 'tests/export-schemas.php')))
    # Private source inventories remain optional in the public checkout.
    if (folder / 'coverage/inventory.json').exists():
        inventory = json.loads((folder / 'coverage/inventory.json').read_text())
        inventory_schema = json.loads((folder / 'coverage/inventory.schema.json').read_text())
        jsonschema.Draft7Validator.check_schema(inventory_schema)
        jsonschema.validate(inventory, inventory_schema)
        inventories += 1
    catalog = json.loads((folder / 'coverage/abilities.json').read_text())
    if set(schemas) != {entry['name'] for entry in catalog}:
        raise SystemExit('Coverage differs from registered abilities: ' + folder.name)
    for name, definition in schemas.items():
        if name in names:
            raise SystemExit('Duplicate ability: ' + name)
        names.add(name)
        for direction in ['input_schema', 'output_schema']:
            jsonschema.Draft7Validator.check_schema(definition[direction])
        if definition['input_schema']['additionalProperties'] is not False:
            raise SystemExit('Open input contract: ' + name)
    # Keep the distributable schema snapshot byte-consistent with the actual PHP definitions.
    snapshot = json.dumps(schemas, indent=2, ensure_ascii=False) + '\n'
    (folder / 'coverage/ability-schemas.json').write_text(snapshot)
for variant in [[], ['no-host']]:
    run('php', str(ROOT / 'tests/coexistence.php'), *variant)
print(f'PASS: {len(files)} PHP files; {assertions} behavior assertions; {len(names)} unique ability contracts; {inventories} valid local inventories; coexistence with/without host.')
