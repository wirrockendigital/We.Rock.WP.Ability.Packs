<?php
/** Strict task-level schemas registered through the generator scaffold. */
declare(strict_types=1);
namespace WeRock\PolylangAbilities;
defined('ABSPATH') || exit;
/** Define a closed object rather than accepting arbitrary plugin storage. */
function object_schema(array $properties=[],array $required=[]):array {
    return ['type'=>'object','properties'=>$properties,'required'=>$required,'additionalProperties'=>false];
}
$slug=['type'=>'string','pattern'=>'^[a-z][a-z0-9_-]*$','maxLength'=>32];
$id=['type'=>'integer','minimum'=>1];
$revision=['type'=>'string','pattern'=>'^[a-f0-9]{64}$'];
$write=['expected_revision'=>$revision,'reason'=>['type'=>'string','minLength'=>3,'maxLength'=>300],'dry_run'=>['type'=>'boolean','default'=>true]];
$kind=['type'=>'string','enum'=>['post','term']];
$content=['kind'=>$kind,'id'=>$id];
$page=['limit'=>['type'=>'integer','minimum'=>1,'maximum'=>100],'offset'=>['type'=>'integer','minimum'=>0,'maximum'=>100000]];
$language=['name'=>['type'=>'string','minLength'=>1,'maxLength'=>100],'is_rtl'=>['type'=>'boolean'],'term_group'=>['type'=>'integer','minimum'=>0,'maximum'=>1000],'flag_code'=>['type'=>'string','pattern'=>'^[a-z0-9_-]{2,30}$']];
$specs=[
 'get-status'=>['Read Polylang edition, informative source version and per-operation native availability.',object_schema(),false],
 'list-languages'=>['Read all configured languages and configuration revision.',object_schema(),false],
 'create-language'=>['Add a language through the native controller. Does not create a default category. The first language becomes default. Preview checks schema and uniqueness; lifecycle hooks run only on execution.',object_schema($write+['locale'=>['type'=>'string','pattern'=>'^[a-z]{2,3}(?:_[A-Za-z0-9]+)*$','maxLength'=>40],'slug'=>$slug]+$language,['expected_revision','reason','locale','slug','name','is_rtl']),true],
 'update-language'=>['Change display name, direction, order or flag of a language. Supply only changed fields; slug and locale migrations are excluded.',object_schema($write+['language'=>$slug,'changes'=>object_schema($language)],['expected_revision','reason','language','changes']),true],
 'get-settings'=>['Read native settings and writable schemas, configured and available content types, plus configuration revision.',object_schema(),false],
 'update-setting'=>['Change one supported setting via native validation and save hooks. Get schemas from get-settings. URL host/domain mode and raw menu maps are excluded. Preview validates without executing hooks.',object_schema($write+['name'=>['type'=>'string','enum'=>Adapter::SETTINGS],'value'=>['anyOf'=>[['type'=>'boolean'],['type'=>'string','maxLength'=>32],['type'=>'array','maxItems'=>100,'uniqueItems'=>true,'items'=>['type'=>'string','maxLength'=>100]]]]],['expected_revision','reason','name','value']),true],
 'list-content'=>['Find translated post types or taxonomies with their current language and translation links. Returns metadata only. Use get-content for a writable revision.',object_schema(['kind'=>$kind,'type'=>['type'=>'string','maxLength'=>100],'language'=>$slug,'search'=>['type'=>'string','maxLength'=>200]]+$page,['kind','type']),false],
 'get-content'=>['Read language, translation group and revision for one post or taxonomy term.',object_schema($content,['kind','id']),false],
 'set-content-language'=>['Assign a language to an existing editable object. A language change of a linked object requires unlink-translation first. No content text is changed.',object_schema($write+$content+['language'=>$slug],['expected_revision','reason','kind','id','language']),true],
 'link-translation'=>['Link an existing same-type object in another language to the source group. Preserves other translations; rejects occupied language slots and foreign groups. Requires fresh revisions for both objects.',object_schema($write+$content+['translation_id'=>$id,'translation_revision'=>$revision],['expected_revision','reason','kind','id','translation_id','translation_revision']),true],
 'unlink-translation'=>['Detach only the selected object from its translation group without deleting content or removing its language.',object_schema($write+$content,['expected_revision','reason','kind','id']),true],
 'list-strings'=>['List strings registered in this request and their native translation for a language. Admin-only third-party registrations may not be available over REST. Includes whole-catalog revision.',object_schema(['language'=>$slug,'search'=>['type'=>'string','maxLength'=>200]]+$page,['language']),false],
 'translate-string'=>['Translate one registered string by its ID from list-strings. Preserves other translations and applies native sanitization. Empty translation removes this translation and restores source fallback.',object_schema($write+['language'=>$slug,'string_id'=>['type'=>'string','pattern'=>'^[a-f0-9]{64}$'],'translation'=>['type'=>'string','maxLength'=>20000]],['expected_revision','reason','language','string_id','translation']),true],
 'get-language-links'=>['Read native home URLs, or available translation URLs for a post. Supplies switcher link data without editing menus or widgets.',object_schema(['post_id'=>$id]),false],
];
ACFW_Pack::category('polylang',['label'=>'Polylang','description'=>'Native language and translation management.']);
foreach($specs as $operation=>[$description,$schema,$mutation]) {
    ACFW_Pack::ability([
        'name'=>'polylang/'.$operation,'label'=>ucwords(str_replace('-',' ',$operation)),
        'description'=>$description,'category'=>'polylang','input_schema'=>$schema,
        'output_schema'=>['type'=>'object','properties'=>['result'=>['type'=>'object']],'required'=>['result'],'additionalProperties'=>false],
        'annotations'=>['readonly'=>!$mutation,'destructive'=>$mutation,'idempotent'=>$operation!=='create-language'],
        'execute_callback'=>static function(array $args)use($operation,$schema,$mutation) {
            // Validate direct callbacks too; the host owns authentication, domain lock and audit.
            if(is_wp_error(rest_validate_value_from_schema($args,$schema)))return Adapter::error('input','Input does not match the ability schema.');
            try {$result=Adapter::run($operation,$args,$mutation);return is_wp_error($result)?$result:['result'=>$result];}
            catch(\Throwable $e){return Adapter::error('native','Native operation failed. Re-read state before retrying; hooks may already have run.');}
        },
        // Do not place content, translations or reasons in the audit summary.
        'summarize'=>static fn(array $args):array=>['id'=>$args['id']??null,'kind'=>$args['kind']??null,'dry_run'=>$args['dry_run']??true],
    ]);
}
