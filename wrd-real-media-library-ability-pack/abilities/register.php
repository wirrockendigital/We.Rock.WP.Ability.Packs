<?php
/** Closed input schemas and registration through the native governed host. */
declare(strict_types=1);
namespace WeRock\RealMediaLibraryAbilities;
defined('ABSPATH') || exit;
/** Build strict WordPress-compatible object schemas. */
function objectSchema(array $properties=[],array $required=[]):array {return ['type'=>'object','properties'=>$properties,'required'=>$required,'additionalProperties'=>false];}
/** Enumerate the entire public ability surface for runtime and coverage export. */
function definitions():array
{
    $folder=['type'=>'integer','minimum'=>-1,'not'=>['enum'=>[0]],'description'=>'Native folder ID; -1 is unorganized/root.'];
    $name=['type'=>'string','minLength'=>1,'maxLength'=>100,'pattern'=>'^[^/\\\\\x00-\x1f]+$'];
    $page=['limit'=>['type'=>'integer','minimum'=>1,'maximum'=>100],'offset'=>['type'=>'integer','minimum'=>0,'maximum'=>100000]];
    $write=['expected_revision'=>['type'=>'string','pattern'=>'^[a-f0-9]{64}$','description'=>'Fresh whole-library revision from a read; re-read after any uncertain outcome.'],'reason'=>['type'=>'string','minLength'=>3,'maxLength'=>300],'dry_run'=>['type'=>'boolean','default'=>true]];
    $specs=[
        'get-status'=>['Read target version, native edition and support.',objectSchema(),false],
        'list-folders'=>['Read paged folder hierarchy metadata and whole-library revision.',objectSchema($page),false],
        'get-folder'=>['Read a folder and whole-library revision.',objectSchema(['folder_id'=>$folder],['folder_id']),false],
        'list-attachments'=>['Read original media IDs, titles and MIME types in a folder, excluding shortcuts and file URLs.',objectSchema(['folder_id'=>$folder]+$page,['folder_id']),false],
        'create-folder'=>['Create a regular virtual folder. Subfolders require Pro. Preview checks structural constraints; native hooks run on execution.',objectSchema($write+['parent_id'=>$folder,'name'=>$name],['expected_revision','reason','parent_id','name']),true],
        'rename-folder'=>['Rename a regular virtual folder through the vendor API. Preview by default.',objectSchema($write+['folder_id'=>$folder,'name'=>$name],['expected_revision','reason','folder_id','name']),true],
        'move-attachments'=>['Move up to 50 existing original media assignments into a regular folder or unorganized root. Native validation remains enabled; disk paths and URLs are unchanged by this pack.',objectSchema($write+['folder_id'=>$folder,'attachment_ids'=>['type'=>'array','minItems'=>1,'maxItems'=>50,'uniqueItems'=>true,'items'=>['type'=>'integer','minimum'=>1]]],['expected_revision','reason','folder_id','attachment_ids']),true],
        'delete-empty-folder'=>['Permanently remove only an empty regular virtual folder, never media files. Rejects children, original assignments and shortcuts.',objectSchema($write+['folder_id'=>$folder],['expected_revision','reason','folder_id']),true],
    ];
    $out=[];
    foreach($specs as $op=>[$description,$schema,$mutation])$out['real-media-library/'.$op]=[
        'label'=>ucwords(str_replace('-',' ',$op)),'category'=>'real-media-library','description'=>$description,
        'input_schema'=>$schema,'output_schema'=>['type'=>'object','properties'=>['result'=>['type'=>'object']],'required'=>['result'],'additionalProperties'=>false],
        'annotations'=>['readonly'=>!$mutation,'destructive'=>$mutation,'idempotent'=>$op!=='create-folder'],
        'execute_callback'=>static function(array $args)use($op,$schema) {
            // Repeat schema checks for direct callback invocation; avoid forwarding vendor errors.
            if(is_wp_error(rest_validate_value_from_schema($args,$schema)))return Adapter::error('input','Input does not match the ability schema.');
            try{$r=Adapter::run($op,$args);return is_wp_error($r)?$r:['result'=>$r];}
            catch(\Throwable $e){return Adapter::error('native','Native operation failed; inspect current state before retrying.');}
        },
        // Free text, folder names and media titles never enter the audit summary.
        'summarize'=>static fn(array $args):array=>['folder_id'=>$args['folder_id']??$args['parent_id']??null,'dry_run'=>$args['dry_run']??true,'attachment_count'=>count($args['attachment_ids']??[])],
    ];
    return $out;
}
// The host owns permissions, domain checks and audit; private names avoid pack collisions.
add_action('wp_abilities_api_categories_init',static function():void {
    if(function_exists('wp_register_ability_category'))wp_register_ability_category('real-media-library',['label'=>'Real Media Library','description'=>'Virtual folder and original-media organization.']);
});
add_action('wp_abilities_api_init',static function():void {
    if(!function_exists('agent_connector_for_wp_register_ability'))return;
    foreach(definitions() as $name=>$args)agent_connector_for_wp_register_ability($name,$args);
});
