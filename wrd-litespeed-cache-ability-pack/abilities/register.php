<?php
/** Closed request schemas and Agent Connector registration for LiteSpeed Cache. */
declare(strict_types=1);
namespace WeRock\LiteSpeedAbilities;
defined('ABSPATH') || exit;
/** Define schemas centrally for both host validation and direct adapter invocations. */
function input_schemas(): array {
    $object = static fn(array $properties=[],array $required=[]):array => ['type'=>'object','additionalProperties'=>false] + ($properties ? ['properties'=>$properties] : []) + ($required ? ['required'=>$required] : []);
    $write = ['reason'=>['type'=>'string','minLength'=>3,'maxLength'=>300],'dry_run'=>['type'=>'boolean','default'=>true]];
    $extended = [
        'expected_revision'=>['type'=>'string','pattern'=>'^[a-f0-9]{64}$'],
        'request_id'=>['type'=>'string','pattern'=>'^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$'],
        'confirm_action'=>['type'=>'string','maxLength'=>80],
        'backup_reference'=>['type'=>'string','minLength'=>3,'maxLength'=>300],
        'acknowledge_shared_cache'=>['type'=>'boolean','default'=>false],
    ];
    $required=['reason','expected_revision','request_id'];
    return [
        'get-maintenance-status'=>$object(),
        'get-component-status'=>$object(['component'=>['type'=>'string','enum'=>['image','crawler','cloud','ccss','ucss','lqip','vpi','optimax','cache']]],['component']),
        'get-database-status'=>$object(),
        'get-operation-receipt'=>$object(['request_id'=>$extended['request_id']],['request_id']),
        'list-crawler-map'=>$object(['kind'=>['type'=>'string','enum'=>['map','blocked']],'limit'=>['type'=>'integer','minimum'=>1,'maximum'=>100],'offset'=>['type'=>'integer','minimum'=>0,'maximum'=>100000]],['kind']),
        'run-maintenance'=>$object($write+$extended+['action'=>['type'=>'string','enum'=>array_keys(Operations::catalog())]],array_merge($required,['action'])),
        'cleanup-database'=>$object($write+$extended+['kind'=>['type'=>'string','enum'=>Operations::DB_TYPES]],array_merge($required,['kind'])),
        'set-crawler-active'=>$object($write+$extended+['crawler_id'=>['type'=>'integer','minimum'=>0,'maximum'=>10000],'active'=>['type'=>'boolean']],array_merge($required,['crawler_id','active'])),
        'get-status'=>$object(),
        'get-settings-schema'=>$object(),
        'get-settings'=>$object(),
        'update-settings'=>$object($write+['acknowledge_high_impact'=>['type'=>'boolean','default'=>false]]+['expected_revision'=>['type'=>'string','pattern'=>'^[a-f0-9]{64}$'],'changes'=>Adapter::settings_schema()],['reason','expected_revision','changes']),
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
    'get-maintenance-status'=>'Discover reviewed native maintenance actions, availability, effects and database cleanup types.',
    'get-component-status'=>'Read redacted image, crawler, cloud or optimization-queue state and a revision. Does not contact remote services.',
    'get-database-status'=>'Read native cleanup counts and a revision covering relevant candidate rows and settings. Does not clean data.',
    'get-operation-receipt'=>'Read a maintenance request receipt. Reuse request_id across retries; a started or needs_reconciliation state requires inspecting actual state.',
    'list-crawler-map'=>'Read bounded native crawler or blocklist rows with IDs and URL paths; credentials, cookies and query strings are omitted.',
    'run-maintenance'=>'Preview one action from get-maintenance-status. Use the component revision and a UUID v4 request_id. Applying needs dry_run=false and confirm_action exactly matching the action. Cloud actions can transmit data and consume quota; shared caches require acknowledgement. Inspect receipt and state afterwards.',
    'cleanup-database'=>'Preview one native cleanup category using get-database-status. Applying permanently deletes matching data or optimizes tables; requires verified backup_reference, dry_run=false, and confirm_action=database:<kind>. Native WP-CLI is required. Reuse request_id on retry.',
    'set-crawler-active'=>'Preview an active-flag change for a zero-based crawler ID from get-component-status. Applying requires confirm_action=set-crawler-active, dry_run=false, fresh crawler revision and a UUID v4 request_id.',
    'get-status'=>'Read source version and operation-specific native availability. Version differences alone never block operations.',
    'get-settings-schema'=>'Read the explicit allowlist and value types for supported settings.',
    'get-settings'=>'Read selected effective settings and the current revision for update-settings. Credentials and account details are excluded.',
    'update-settings'=>'Preview a settings delta by default. Apply with dry_run=false and the expected_revision from get-settings. Uses the native save lifecycle, including cache purges, file updates and existing CDN synchronization; validate the frontend afterwards.',
    'purge-cache'=>'Preview native page-cache invalidation for same-origin URLs, editable posts, or this site. Applying requires dry_run=false and acknowledge_cache_rebuild=true. Reports a request, not completed cache eviction.',
];
foreach (input_schemas() as $op=>$schema) {
    $write = in_array($op,['update-settings','purge-cache','run-maintenance','cleanup-database','set-crawler-active'],true);
    ACFW_Pack::ability([
        'name'=>'litespeed-cache/'.$op,'label'=>ucwords(str_replace('-',' ',$op)),
        'description'=>$descriptions[$op],'category'=>'litespeed-cache','input_schema'=>$schema,
        'output_schema'=>['type'=>'object','properties'=>['result'=>['type'=>'object']],'required'=>['result'],'additionalProperties'=>false],
        'annotations'=>['readonly'=>!$write,'destructive'=>$write,'idempotent'=>!in_array($op,['purge-cache','run-maintenance','cleanup-database'],true)],
        'execute_callback'=>static function(array $args) use ($op) {
            try {
                $result = Adapter::run($op,$args);
                return is_wp_error($result)?$result:['result'=>$result];
            } catch (\Throwable $e) {
                return Adapter::error('native','Native execution failed. Re-read state before retrying; changes may already have occurred.');
            }
        },
        // Exclude free text, option values and URLs from audit summaries.
        'summarize'=>static fn(array $args):array=>['dry_run'=>$args['dry_run']??true,'scope'=>$args['scope']??null,'action'=>$args['action']??null,'kind'=>$args['kind']??null,'fields'=>array_keys($args['changes']??[])],
    ]);
}
