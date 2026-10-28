<?php
/** Register explicit, closed contracts through the Agent Connector host. */
declare(strict_types=1);
namespace WeRock\RealCookieBannerAbilities;
defined('ABSPATH') || exit;

/** Construct WordPress-compatible closed object schemas. */
function objectSchema(array $properties=[],array $required=[]): array
{
    return ['type'=>'object','properties'=>$properties,'required'=>$required,'additionalProperties'=>false];
}

/** Return the complete runtime and documentation contract. */
function definitions(): array
{
    $kind=['type'=>'string','enum'=>['service','blocker']];
    $id=['type'=>'integer','minimum'=>1];
    $write=['kind'=>$kind,'expected_revision'=>['type'=>'string','pattern'=>'^[a-f0-9]{64}$','description'=>'Fresh item revision; use catalog_revision from list-items for creation.'],'reason'=>['type'=>'string','minLength'=>3,'maxLength'=>300],'dry_run'=>['type'=>'boolean','default'=>true]];
    $specs=[
        'get-status'=>['Read reviewed target support and write scope.',objectSchema(),false],
        'get-settings'=>['Read selected banner, blocker, country and page-reference options. No license, contact or consent data.',objectSchema(),false],
        'list-groups'=>['List up to 100 existing service groups and essential flags.',objectSchema(),false],
        'list-items'=>['List service or blocker metadata and a catalog revision. Includes drafts and trash; omits code and descriptions.',objectSchema(['kind'=>$kind,'limit'=>['type'=>'integer','minimum'=>1,'maximum'=>100],'offset'=>['type'=>'integer','minimum'=>0,'maximum'=>100000]],['kind']),false],
        'get-item'=>['Read a typed item and revision. include_details explicitly returns editable content, including code and provider contacts, to the authorized caller.',objectSchema(['kind'=>$kind,'id'=>$id,'include_details'=>['type'=>'boolean','default'=>false]],['kind','id']),false],
        'get-edit-schema'=>['Discover writable fields for the selected native item kind.',objectSchema(['kind'=>$kind],['kind']),false],
    ];
    foreach(['create','update','trash','restore'] as $action) {
        $props=$write;$required=['kind','expected_revision','reason'];
        if($action!=='create'){$props['id']=$id;$required[]='id';}
        if(in_array($action,['create','update'],true)) {
            $props['changes']=['anyOf'=>[objectSchema(Adapter::fields('service'),$action==='create'?['title']:[])+['minProperties'=>1],objectSchema(Adapter::fields('blocker'),$action==='create'?['title']:[])+['minProperties'=>1]]];
            $required[]='changes';
        }
        $specs[$action.'-draft']=[ucfirst($action).' a service or blocker draft through native WordPress operations. Preview by default; published items are protected. Restore accepts only former drafts.',objectSchema($props,$required),true];
    }
    $out=[];
    foreach($specs as $operation=>[$description,$schema,$mutation]) {
        $out['real-cookie-banner/'.$operation]=[
            'label'=>ucwords(str_replace('-',' ',$operation)),'category'=>'real-cookie-banner','description'=>$description,
            'input_schema'=>$schema,'output_schema'=>['type'=>'object','properties'=>['result'=>['type'=>'object']],'required'=>['result'],'additionalProperties'=>false],
            'annotations'=>['readonly'=>!$mutation,'destructive'=>$mutation,'idempotent'=>$operation!=='create-draft'],
            'execute_callback'=>static function(array $args)use($operation,$schema) {
                // Validate again for direct callback use and suppress arbitrary native error details.
                if(is_wp_error(rest_validate_value_from_schema($args,$schema)))return Adapter::error('input','Input does not match the ability schema.');
                try {$r=Adapter::run($operation,$args);return is_wp_error($r)?$r:['result'=>$r];}
                catch(\Throwable $e){return Adapter::error('native','Native operation failed; inspect current state before retrying.');}
            },
            // Never include names, content, provider contacts or free-text reasons in audit summaries.
            'summarize'=>static fn(array $args):array=>['kind'=>$args['kind']??null,'id'=>$args['id']??null,'dry_run'=>$args['dry_run']??true,'fields'=>array_keys($args['changes']??[])],
        ];
    }
    return $out;
}

// The host retains permissions, domain locking and audit ownership.
add_action('wp_abilities_api_categories_init',static function():void {
    if(function_exists('wp_register_ability_category'))wp_register_ability_category('real-cookie-banner',['label'=>'Real Cookie Banner','description'=>'Local consent configuration inventory and draft preparation.']);
});
add_action('wp_abilities_api_init',static function():void {
    if(!function_exists('agent_connector_for_wp_register_ability'))return;
    foreach(definitions() as $name=>$definition)agent_connector_for_wp_register_ability($name,$definition);
});
