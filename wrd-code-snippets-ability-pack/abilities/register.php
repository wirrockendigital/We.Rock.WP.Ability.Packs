<?php
/** Strict schemas and governed registration for local Code Snippets administration. */
declare(strict_types=1);
namespace WeRock\CodeSnippetsAbilities;
defined('ABSPATH') || exit;

/** Build closed input objects using WordPress-compatible schema arrays. */
function objectSchema(array $properties=[],array $required=[]): array
{
    return ['type'=>'object','properties'=>$properties,'required'=>$required,'additionalProperties'=>false];
}

/** Enumerate all public contracts for runtime registration and schema export. */
function definitions(): array
{
    $text=static fn(int $max)=>['type'=>'string','maxLength'=>$max];
    $scope=['type'=>'string','enum'=>Adapter::SCOPES];
    $fields=['name'=>['type'=>'string','minLength'=>1,'maxLength'=>200],'description'=>$text(10000),'code'=>$text(100000),'scope'=>$scope,'priority'=>['type'=>'integer','minimum'=>1,'maximum'=>999],'tags'=>['type'=>'array','maxItems'=>30,'uniqueItems'=>true,'items'=>['type'=>'string','minLength'=>1,'maxLength'=>100]]];
    $id=['type'=>'integer','minimum'=>1];
    $write=['expected_revision'=>['type'=>'string','pattern'=>'^[a-f0-9]{64}$','description'=>'Fresh snippet revision, or catalog_revision for creation. Re-read after any uncertain outcome.'],'dry_run'=>['type'=>'boolean','default'=>true],'reason'=>['type'=>'string','minLength'=>3,'maxLength'=>300]];
    $specs=[
        'get-status'=>['Read reviewed target availability and supported scopes.',objectSchema(),false],
        'list-snippets'=>['List metadata without code; includes catalog revision for creation.',objectSchema(['limit'=>['type'=>'integer','minimum'=>1,'maximum'=>100],'offset'=>['type'=>'integer','minimum'=>0,'maximum'=>100000]]),false],
        'get-snippet'=>['Read metadata and revision. include_code explicitly returns unredacted code and description to the authorized caller.',objectSchema(['id'=>$id,'include_code'=>['type'=>'boolean','default'=>false]],['id']),false],
        'validate-code'=>['Parse PHP and run native identifier checks without executing code. Does not prove runtime safety; HTML/CSS/JavaScript require separate review.',objectSchema(['code'=>$fields['code'],'scope'=>$scope],['code','scope']),false],
        'create-snippet'=>['Create an inactive local snippet. Preview by default. Uses a fresh catalog revision to reject stale retries.',objectSchema($write+['changes'=>objectSchema($fields,['name','code','scope'])],['expected_revision','reason','changes']),true],
        'update-snippet'=>['Patch an inactive local snippet. Preserves other fields and requires a fresh revision.',objectSchema($write+['id'=>$id,'changes'=>objectSchema($fields)+['minProperties'=>1]],['id','expected_revision','reason','changes']),true],
    ];
    foreach (['activate','deactivate','trash','restore'] as $action) {
        $props=$write+['id'=>$id];
        if($action==='activate')$props['acknowledge_execution']=['type'=>'boolean','default'=>false,'description'=>'Explicitly acknowledge that activation enables code execution on subsequent requests and native hooks.'];
        $specs[$action.'-snippet']=[ucfirst($action).' a local snippet with revision checking. Preview by default; trash requires inactive state. Restore leaves the snippet inactive.',objectSchema($props,['id','expected_revision','reason']),true];
    }
    $definitions=[];
    foreach($specs as $operation=>[$description,$schema,$writeAbility]) {
        $definitions['code-snippets/'.$operation]=[
            'label'=>ucwords(str_replace('-',' ',$operation)), 'description'=>$description,'category'=>'code-snippets',
            'input_schema'=>$schema,'output_schema'=>['type'=>'object','properties'=>['result'=>['type'=>'object']],'required'=>['result'],'additionalProperties'=>false],
            'annotations'=>['readonly'=>!$writeAbility,'destructive'=>$writeAbility,'idempotent'=>$operation!=='create-snippet'],
            'execute_callback'=>static function(array $args) use($operation,$schema) {
                // Repeat host validation for direct callback invocations and suppress native error details.
                if(is_wp_error(rest_validate_value_from_schema($args,$schema)))return Adapter::error('input','Input does not match the ability schema.');
                try {$result=Adapter::run($operation,$args);return is_wp_error($result)?$result:['result'=>$result];}
                catch(\Throwable $e){return Adapter::error('native','Native operation failed; re-read current state before retrying.');}
            },
            // Audit summaries deliberately omit code, names, tags, descriptions and free-text reasons.
            'summarize'=>static fn(array $args):array=>['id'=>$args['id']??null,'dry_run'=>$args['dry_run']??true,'fields'=>array_keys($args['changes']??[])],
        ];
    }
    return $definitions;
}

// The host owns permission checks, domain locking and auditing.
add_action('wp_abilities_api_categories_init',static function():void {
    if(function_exists('wp_register_ability_category'))wp_register_ability_category('code-snippets',['label'=>'Code Snippets','description'=>'Governed local snippet administration.']);
});
add_action('wp_abilities_api_init',static function():void {
    if(!function_exists('agent_connector_for_wp_register_ability'))return;
    foreach(definitions() as $name=>$definition)agent_connector_for_wp_register_ability($name,$definition);
});
