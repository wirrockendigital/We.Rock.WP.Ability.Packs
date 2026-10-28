"""Validate PHP, registration isolation and strict mutation schemas."""
import json,pathlib,subprocess
import jsonschema
root=pathlib.Path(__file__).resolve().parents[1]
for p in root.rglob('*.php'):subprocess.run(['php','-l',str(p)],check=True,capture_output=True)
defs=json.loads(subprocess.check_output(['php',str(root/'tests/export-schemas.php')]))
assert len(defs)==8
for d in defs.values():
    jsonschema.Draft7Validator.check_schema(d['input_schema'])
    assert d['input_schema']['additionalProperties'] is False
schema=defs['real-media-library/create-folder']['input_schema']
valid={'name':'Folder','parent_id':-1,'reason':'Verification','expected_revision':'a'*64}
jsonschema.validate(valid,schema)
for bad in [dict(valid,name='../escape'),dict(valid,parent_id=0),dict(valid,parent_id=-2),dict(valid,supress_validation=True),dict(valid,expected_revision='stale')]:assert not jsonschema.Draft7Validator(schema).is_valid(bad)
move=defs['real-media-library/move-attachments']['input_schema']
for ids in [[],[1,1],list(range(1,52))]:assert not jsonschema.Draft7Validator(move).is_valid({'folder_id':-1,'attachment_ids':ids,'reason':'Verification','expected_revision':'a'*64})
for suffix in [[],['no-host']]:subprocess.run(['php',str(root/'tests/registration.php')]+suffix,check=True)
(root/'coverage/ability-schemas.json').write_text(json.dumps(defs,indent=2)+'\n')
if(root/'coverage/inventory.json').exists():jsonschema.validate(json.loads((root/'coverage/inventory.json').read_text()),json.loads((root/'coverage/inventory.schema.json').read_text()))
print('PASS: PHP, 8 schemas, forbidden inputs and batch bounds')
