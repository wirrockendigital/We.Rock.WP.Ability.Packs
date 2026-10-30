"""Validate public JSON schemas and run native-adapter regression scenarios."""
import json
import subprocess
from pathlib import Path
from jsonschema import Draft4Validator

ROOT = Path(__file__).resolve().parent
# Export actual registered definitions rather than copying schemas into the validator.
definitions = json.loads(subprocess.check_output(['php', str(ROOT / 'behavior.php'), 'export']))
assert len(definitions) == 5
for definition in definitions.values():
    Draft4Validator.check_schema(definition['input_schema'])
    Draft4Validator.check_schema(definition['output_schema'])
    assert definition['input_schema']['additionalProperties'] is False
schema = definitions['litespeed-cache/update-settings']['input_schema']
validator = Draft4Validator(schema)
valid = {'reason': 'Adjust minimization', 'expected_revision': 'a' * 64, 'changes': {'optm-css_min': True}}
assert validator.is_valid(valid)
# Reject coercion, unsupported settings, oversized lists and out-of-range native switches.
for changes in [{}, {'api_key': 'private'}, {'cache': 'false'}, {'optm-js_defer': 3}, {'cache-ttl_priv': 0}, {'cache-exc': ['x'] * 101}]:
    assert not validator.is_valid({**valid, 'changes': changes}), changes
for scenario in ['current', 'future', 'inactive']:
    subprocess.run(['php', str(ROOT / 'behavior.php'), scenario], check=True)
subprocess.run(['php', str(ROOT / 'missing-native.php')], check=True)
print('PASS: five public schemas and independent input fixtures')
