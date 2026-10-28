<?php
/** Task-level schemas for the native category management surface. */
declare(strict_types=1);
namespace WeRock\RealCategoryManagementAbilities;
defined('ABSPATH') || exit;
/** Keep all request objects closed and validate direct callback invocations too. */
function schema(array $properties=[], array $required=[]):array {
    return ['type'=>'object','properties'=>$properties,'required'=>$required,'additionalProperties'=>false];
}
$id=['type'=>'integer','minimum'=>1];
$root=['type'=>'integer','minimum'=>0];
$key=['type'=>'string','pattern'=>'^[a-zA-Z0-9_-]+$','maxLength'=>64];
$scope=['post_type'=>$key,'taxonomy'=>$key];
$write=['expected_revision'=>['type'=>'string','pattern'=>'^[a-f0-9]{64}$'],'reason'=>['type'=>'string','minLength'=>3,'maxLength'=>300],'dry_run'=>['type'=>'boolean','default'=>true]];
$ids=['type'=>'array','minItems'=>1,'maxItems'=>50,'uniqueItems'=>true,'items'=>$id];
$name=['type'=>'string','minLength'=>1,'maxLength'=>200];
$specs=[
 'get-status'=>['Read informative source version and native operation availability. Versions alone never block operations.',schema(),false],
 'list-contexts'=>['Discover available post types, hierarchical taxonomies, sorting support and current tree settings.',schema(),false],
 'get-tree'=>['Read the native category tree plus complete term metadata and a tree revision. Does not remember a UI selection.',schema($scope,['post_type','taxonomy']),false],
 'create-term'=>['Create one category through the native plugin controller. Get expected_revision from get-tree. Parent defaults to root.',schema($scope+$write+['name'=>$name,'parent'=>$root],['post_type','taxonomy','expected_revision','reason','name']),true],
 'rename-term'=>['Rename a category through the native plugin controller. Native behavior also changes its slug and therefore may change archive URLs.',schema($scope+$write+['term_id'=>$id,'name'=>$name],['post_type','taxonomy','expected_revision','reason','term_id','name']),true],
 'relocate-term'=>['Move a category to parent (0=root), before before_id (0=end), through native ordering. Rejects cycles and invalid siblings. Get expected_revision from get-tree.',schema($scope+$write+['term_id'=>$id,'parent'=>$root,'before_id'=>$root],['post_type','taxonomy','expected_revision','reason','term_id','parent','before_id']),true],
 'delete-empty-term'=>['Delete only an empty, childless non-default category. Checks actual relationships, not cached public counts. Get expected_revision from get-tree.',schema($scope+$write+['term_id'=>$id],['post_type','taxonomy','expected_revision','reason','term_id']),true],
 'get-assignments'=>['Read taxonomy assignments of up to 50 individually editable posts/pages and the revision needed for assign-posts.',schema($scope+['post_ids'=>$ids],['post_type','taxonomy','post_ids']),false],
 'assign-posts'=>['Move posts to one category (replace this taxonomy only) or copy (append category without duplicating content). target_id=0 removes assignments; WordPress may restore its default category. Get expected_revision from get-assignments using the same post_ids.',schema($scope+$write+['post_ids'=>$ids,'target_id'=>$root,'mode'=>['type'=>'string','enum'=>['move','copy']]],['post_type','taxonomy','expected_revision','reason','post_ids','target_id','mode']),true],
 'get-settings'=>['Read active/fastMode for one available post type and its settings revision.',schema(['post_type'=>$key],['post_type']),false],
 'update-settings'=>['Patch only supplied active/fastMode flags through the native plugin controller. Requires manage_options. Get expected_revision from get-settings.',schema($write+['post_type'=>$key,'changes'=>schema(['active'=>['type'=>'boolean'],'fastMode'=>['type'=>'boolean']])],['post_type','expected_revision','reason','changes']),true],
];
ACFW_Pack::category('real-category-management',['label'=>'Real Category Management','description'=>'Native categories, ordering and content assignments.']);
foreach($specs as $operation=>[$description,$input,$mutation]){
    ACFW_Pack::ability([
        'name'=>'real-category-management/'.$operation,'label'=>ucwords(str_replace('-',' ',$operation)),
        'description'=>$description,'category'=>'real-category-management','input_schema'=>$input,
        'output_schema'=>['type'=>'object','properties'=>['result'=>['type'=>'object']],'required'=>['result'],'additionalProperties'=>false],
        'annotations'=>['readonly'=>!$mutation,'destructive'=>$mutation,'idempotent'=>$operation!=='create-term'],
        'execute_callback'=>static function(array $args)use($operation,$input,$mutation){
            if(is_wp_error(rest_validate_value_from_schema($args,$input)))return Adapter::error('input','Input does not match the schema.');
            try{$result=Adapter::run($operation,$args,$mutation);return is_wp_error($result)?$result:['result'=>$result];}
            catch(\Throwable $e){return Adapter::error('native','Native execution failed. Re-read state before retrying; native hooks may have run.');}
        },
        // Audit identifiers only; names and reasons remain outside the summary.
        'summarize'=>static fn(array $a):array=>['post_type'=>$a['post_type']??null,'taxonomy'=>$a['taxonomy']??null,'term_id'=>$a['term_id']??null,'dry_run'=>$a['dry_run']??true],
    ]);
}
