"""Validate all PHP and closed public schemas before deployment."""
import pathlib,subprocess,json
import jsonschema
root=pathlib.Path(__file__).resolve().parents[1]
for p in root.rglob('*.php'):subprocess.run(['php','-l',str(p)],capture_output=True,check=True)
defs=json.loads(subprocess.check_output(['php',str(root/'tests/export-schemas.php')]))
assert len(defs)==10
for d in defs.values():
    jsonschema.Draft7Validator.check_schema(d['input_schema'])
    assert d['input_schema']['additionalProperties'] is False
    assert 'permission_callback' not in d
schema=defs['real-cookie-banner/create-draft']['input_schema']
valid={'kind':'service','expected_revision':'a'*64,'reason':'Verification','changes':{'title':'Example','meta':{'provider':'Example'}}}
jsonschema.validate(valid,schema)
for bad in [dict(valid,status='publish'),dict(valid,expected_revision='stale'),dict(valid,changes={'title':'Example','status':'publish'}),dict(valid,changes={'title':'Example','meta':{'license_key':'forbidden'}}),dict(valid,kind='page')]:
    assert not jsonschema.Draft7Validator(schema).is_valid(bad)
(root/'coverage/ability-schemas.json').write_text(json.dumps(defs,indent=2)+'\n')
if (root/'coverage/inventory.json').exists():jsonschema.validate(json.loads((root/'coverage/inventory.json').read_text()),json.loads((root/'coverage/inventory.schema.json').read_text()))
print('PASS: PHP lint, 10 closed schemas, forbidden inputs rejected')
