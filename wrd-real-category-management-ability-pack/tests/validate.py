"""Validate actual ability contracts, malformed input rejection and isolated behavior."""
import json, pathlib, subprocess
from jsonschema import Draft7Validator
root=pathlib.Path(__file__).resolve().parents[1]
# Always lint every PHP file, including test programs.
for path in root.rglob('*.php'):
    if not path.name.startswith('._'):subprocess.run(['php','-l',str(path)],check=True,capture_output=True)
definitions=json.loads(subprocess.check_output(['php',str(root/'tests/export.php')]))
assert len(definitions)==11
for name,definition in definitions.items():
    schema=definition['input_schema']
    if schema.get('properties')==[]:schema['properties']={}
    Draft7Validator.check_schema(schema)
    assert schema['additionalProperties'] is False
    if not definition['annotations']['readonly']:
        assert {'reason','expected_revision'}<=set(schema['required'])
        assert schema['properties']['dry_run']['default'] is True
    assert not Draft7Validator(schema).is_valid({'unexpected':'field'})
# Exercise representative hostile or malformed inputs against exported public schemas.
base={'post_type':'page','taxonomy':'page_cat','post_ids':[1],'reason':'test','expected_revision':'a'*64,'target_id':1,'mode':'move'}
validator=Draft7Validator(definitions['real-category-management/assign-posts']['input_schema'])
assert validator.is_valid(base)
for bad in [base|{'post_ids':[0]},base|{'post_ids':[1,1]},base|{'target_id':-1},base|{'dry_run':'false'},base|{'expected_revision':'bad'}]:assert not validator.is_valid(bad)
subprocess.run(['php',str(root/'tests/behavior.php')],check=True)
if (root/'coverage/inventory.json').exists():
    Draft7Validator(json.loads((root/'coverage/inventory.schema.json').read_text())).validate(json.loads((root/'coverage/inventory.json').read_text()))
print(json.dumps({'abilities':len(definitions),'syntax':'passed','schemas':'passed','invalid_inputs':'rejected'}))
