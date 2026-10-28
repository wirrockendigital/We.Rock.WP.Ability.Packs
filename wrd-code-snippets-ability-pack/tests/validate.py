"""Validate PHP, strict schemas and non-executing validation using reviewed source."""
import json,pathlib,subprocess,sys
import jsonschema
root=pathlib.Path(__file__).resolve().parents[1]
for path in root.rglob('*.php'):
    subprocess.run(['php','-l',str(path)],check=True,capture_output=True)
defs=json.loads(subprocess.check_output(['php',str(root/'tests/export-schemas.php')]))
assert len(defs)==10
# Validate a local source inventory when licensed source evidence is available.
if (root/'coverage/inventory.json').exists():
    jsonschema.validate(json.loads((root/'coverage/inventory.json').read_text()),json.loads((root/'coverage/inventory.schema.json').read_text()))
for definition in defs.values():
    jsonschema.Draft7Validator.check_schema(definition['input_schema'])
    assert definition['input_schema']['additionalProperties'] is False
    assert 'permission_callback' not in definition
schema=defs['code-snippets/create-snippet']['input_schema']
valid={'expected_revision':'a'*64,'reason':'Verification','changes':{'name':'Example','code':'/* Inactive. */','scope':'global'}}
jsonschema.validate(valid,schema)
for bad in [dict(valid,active=True),dict(valid,expected_revision='stale'),dict(valid,changes={'name':'Example','code':'','scope':'single-use'}),dict(valid,changes={'name':'Example','code':'','scope':'global','active':True})]:
    assert not jsonschema.Draft7Validator(schema).is_valid(bad)
(root/'coverage/ability-schemas.json').write_text(json.dumps(defs,indent=2)+'\n')
if len(sys.argv)>1:
    # The real vendor parser check is available when a licensed source path is supplied.
    subprocess.run(['php',str(root/'tests/parser.php'),sys.argv[1]],check=True)
print('PASS: PHP lint, 10 schemas, input rejection')
