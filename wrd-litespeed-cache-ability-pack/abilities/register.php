<?php
/** Closed request schemas and Agent Connector registration for LiteSpeed Cache. */
declare(strict_types=1);
namespace WeRock\LiteSpeedAbilities;
defined('ABSPATH') || exit;
/** Define schemas centrally for both host validation and direct adapter invocations. */
function input_schemas(): array {
    $object = static fn(array $properties=[],array $required=[]):array => ['type'=>'object','additionalProperties'=>false] + ($properties ? ['properties'=>$properties] : []) + ($required ? ['required'=>$required] : []);
    $write = ['reason'=>['type'=>'string','minLength'=>3,'maxLength'=>300],'dry_run'=>['type'=>'boolean','default'=>true]];
    return [
        'get-status'=>$object(),
        'get-settings-schema'=>$object(),
        'get-settings'=>$object(),
        'update-settings'=>$object($write+['expected_revision'=>['type'=>'string','pattern'=>'^[a-f0-9]{64}$'],'changes'=>Adapter::settings_schema()],['reason','expected_revision','changes']),
        'purge-cache'=>$object($write+[
            'scope'=>['type'=>'string','enum'=>['urls','posts','site']],
            'urls'=>['type'=>'array','minItems'=>1,'maxItems'=>50,'uniqueItems'=>true,'items'=>['type'=>'string','maxLength'=>2048]],
            'post_ids'=>['type'=>'array','minItems'=>1,'maxItems'=>50,'uniqueItems'=>true,'items'=>['type'=>'integer','minimum'=>1]],
            'acknowledge_cache_rebuild'=>['type'=>'boolean','default'=>false],
        ],['reason','scope']),
    ];
}
ACFW_Pack::category('litespeed-cache',['label'=>'LiteSpeed Cache','description'=>'Native cache diagnostics, settings and scoped page invalidation.']);
$descriptions = [
    'get-status'=>'Read source version and operation-specific native availability. Version differences alone never block operations.',
    'get-settings-schema'=>'Read the explicit allowlist and value types for supported settings.',
    'get-settings'=>'Read selected effective settings and the current revision for update-settings. Credentials and account details are excluded.',
    'update-settings'=>'Preview a settings delta by default. Apply with dry_run=false and the expected_revision from get-settings. Uses the native save lifecycle, including cache purges, file updates and existing CDN synchronization; validate the frontend afterwards.',
    'purge-cache'=>'Preview native page-cache invalidation for same-origin URLs, editable posts, or this site. Applying requires dry_run=false and acknowledge_cache_rebuild=true. Reports a request, not completed cache eviction.',
];
foreach (input_schemas() as $op=>$schema) {
    $write = in_array($op,['update-settings','purge-cache'],true);
    ACFW_Pack::ability([
        'name'=>'litespeed-cache/'.$op,'label'=>ucwords(str_replace('-',' ',$op)),
        'description'=>$descriptions[$op],'category'=>'litespeed-cache','input_schema'=>$schema,
        'output_schema'=>['type'=>'object','properties'=>['result'=>['type'=>'object']],'required'=>['result'],'additionalProperties'=>false],
        'annotations'=>['readonly'=>!$write,'destructive'=>$write,'idempotent'=>$op!=='purge-cache'],
        'execute_callback'=>static function(array $args) use ($op) {
            try {
                $result = Adapter::run($op,$args);
                return is_wp_error($result)?$result:['result'=>$result];
            } catch (\Throwable $e) {
                return Adapter::error('native','Native execution failed. Re-read state before retrying; changes may already have occurred.');
            }
        },
        // Exclude free text, option values and URLs from audit summaries.
        'summarize'=>static fn(array $args):array=>['dry_run'=>$args['dry_run']??true,'scope'=>$args['scope']??null,'fields'=>array_keys($args['changes']??[])],
    ]);
}
