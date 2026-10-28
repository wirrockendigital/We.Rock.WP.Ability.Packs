<?php
/** Register namespaced abilities through the host's governed public API. */
declare(strict_types=1);
namespace WeRock\NinjaAbilities;
defined('ABSPATH') || exit;

/** Build an input schema that rejects unknown properties. */
function objectSchema(array $properties = [], array $required = []): array
{
    return ['type'=>'object','properties'=>$properties,
        'required'=>$required,'additionalProperties'=>false];
}

/** Enumerate definitions for registration and the machine-readable coverage catalog. */
function definitions(): array
{
    $month=['type'=>'string','pattern'=>'^\d{4}-(0[1-9]|1[0-2])$','description'=>'Log month YYYY-MM; defaults to the server current month.'];
    $pagination=['limit'=>['type'=>'integer','minimum'=>1,'maximum'=>200,'default'=>50,'description'=>'Maximum records returned.'],
        'offset'=>['type'=>'integer','minimum'=>0,'maximum'=>100000,'default'=>0,'description'=>'Records skipped within the filtered result.']];
    $write=['expected_revision'=>['type'=>'string','pattern'=>'^[a-f0-9]{64}$','description'=>'Fresh revision from get-settings or list-rules.'],
        'dry_run'=>['type'=>'boolean','default'=>true,'description'=>'True previews only; explicitly set false to persist the change.'],
        'reason'=>['type'=>'string','minLength'=>3,'maxLength'=>300,'description'=>'Operator rationale. Never include secrets. The audit records affected fields, not this free text.']];
    $defs=[
        'get-status'=>['Read protection status','Read NinjaFirewall version, configured protection, debug state and the authenticated request runtime status. Does not simulate an attack.','status',objectSchema()],
        'get-settings'=>['Read non-secret settings','Read source-verified non-secret NinjaFirewall scalar settings and the complete configuration revision. Writable fields are marked; secret/custom-string fields are omitted.','settings',objectSchema()],
        'get-settings-schema'=>['Discover writable settings','Return the exact boolean switches and blocked-request HTTP status accepted by update-settings. Global disable/debug, REST/app-password blocking and coupled settings are excluded.','schema',objectSchema()],
        'update-settings'=>['Patch selected settings','Apply a minimal settings delta using the native NinjaFirewall writer and a locked transaction. Dry run defaults to true. Use only for an explicitly requested firewall change; read the revision first. Global disabling, credentials and coupled settings are unsupported.','updateSettings',objectSchema(array_merge($write,['changes'=>Adapter::settingsSchema()]),['expected_revision','reason','changes'])],
        'list-rules'=>['Find firewall rules','List native rule IDs, descriptions, enabled state, severity and editability, with a revision and pagination. Filter by a known incident rule ID to diagnose blocks. Reserved and policy-owned rules cannot be changed by set-rule-enabled.','rules',objectSchema(array_merge($pagination,[
            'rule_id'=>['type'=>'integer','minimum'=>1,'description'=>'Exact NinjaFirewall rule ID.'],
            'enabled'=>['type'=>'boolean','description'=>'Optional enabled-state filter.']]))],
        'set-rule-enabled'=>['Set one rule state','Enable or disable one native editable security rule, preserving its matching logic and all other rules. Dry run defaults to true. Disabling reduces protection and needs a concrete operator request. Rules 2, 500-599 and 999 are reserved.','setRule',objectSchema(array_merge($write,[
            'rule_id'=>['type'=>'integer','minimum'=>1,'description'=>'Editable native rule ID discovered with list-rules.'],
            'enabled'=>['type'=>'boolean','description'=>'Requested rule state.']]),['expected_revision','reason','rule_id','enabled'])],
        'list-log-months'=>['List available log months','List recognized monthly firewall and statistics files with sizes. Does not return arbitrary files.','logMonths',objectSchema()],
        'search-log'=>['Search redacted incidents','Search the most recent 2 MiB of a monthly firewall log, newest first. Filter by incident ID, rule ID or severity. Returns timestamp, incident, rule, severity, HTTP status and method; IPs, URLs, messages and payloads are omitted. tail_truncated signals incomplete historical coverage.','logs',objectSchema(array_merge($pagination,[
            'month'=>$month,'incident_id'=>['type'=>'string','pattern'=>'^[0-9]{7}$','description'=>'Seven-digit incident ID shown on a NinjaFirewall block page, without #.'],
            'rule_id'=>['type'=>'integer','minimum'=>0,'description'=>'Exact rule ID, including 0 for non-rule events.'],
            'severity'=>['type'=>'integer','minimum'=>1,'maximum'=>7,'description'=>'1 medium, 2 high, 3 critical, 4 post-detection, 5 upload, 6 info, 7 debug.']]))],
        'get-statistics'=>['Read monthly statistics','Read the native monthly counters. Blocked threats are medium+high+critical; informational and debug events are separate. Missing month files are reported explicitly.','statistics',objectSchema(['month'=>$month])],
        'get-login-protection'=>['Read login protection','Read the numeric brute-force and CAPTCHA protection configuration without executing its PHP file. Passwords, authentication usernames, random seeds and custom text are never returned.','loginProtection',objectSchema()],
        'get-schedules'=>['Read NinjaFirewall schedules','Read planned rule updates, file checks, daily reports and garbage collection; does not run or reschedule jobs.','schedules',objectSchema()],
        'inspect-htninja'=>['Inspect .htninja','Inspect document-root and parent .htninja existence, reviewed engine precedence, bounded readability and PHP syntax. Never executes, writes or discloses file contents. A detected file does not prove a particular directive matched a request.','htninja',objectSchema()],
        'preview-htninja-rule'=>['Preview temporary .htninja rule','Generate, but do not install, an expiring exact-host, exact-IP, exact-URL-path ALLOW/BLOCK PHP example. ALLOW bypasses firewall and brute-force filtering and logging. Existing files need reviewed merging; proxy forwarding headers are not trusted.','previewHtninja',objectSchema([
            'ip'=>['type'=>'string','minLength'=>2,'maxLength'=>45,'description'=>'Single exact source IPv4 or IPv6 address, no CIDR.'],
            'path'=>['type'=>'string','minLength'=>1,'maxLength'=>200,'description'=>'Literal URL path starting with /, without query string or wildcards.'],
            'action'=>['type'=>'string','enum'=>['ALLOW','BLOCK'],'description'=>'Early firewall decision.'],
            'ttl_minutes'=>['type'=>'integer','minimum'=>1,'maximum'=>60,'default'=>15,'description'=>'Expiry in minutes from generation; default 15.']
        ],['ip','path','action'])],
    ];
    $out=[];
    foreach($defs as $name=>[$label,$description,$method,$input]){
        $writeAbility=in_array($name,['update-settings','set-rule-enabled'],true);
        $out['ninjafirewall/'.$name]=[
            'label'=>$label,'description'=>$description,'category'=>'ninjafirewall',
            'input_schema'=>$input,
            // The envelope is stable; individual results contain documented native metadata.
            'output_schema'=>['type'=>'object','required'=>['result'],'properties'=>['result'=>['type'=>'object']],'additionalProperties'=>false],
            'annotations'=>['readonly'=>!$writeAbility,'destructive'=>$writeAbility,'idempotent'=>$name!=='preview-htninja-rule'],
            'execute_callback'=>static function(array $args)use($method){
                $r=$method==='schema'?['changes_schema'=>Adapter::settingsSchema()]:Adapter::$method($args);
                return is_wp_error($r)?$r:['result'=>$r];
            },
            'summarize'=>static function(array $args):array{
                // Free text and supplied IPs never enter the host audit summary.
                return array_intersect_key($args,array_flip(['rule_id','enabled','dry_run','month','limit','offset']))+
                    (isset($args['changes'])?['fields'=>array_keys($args['changes'])]:[]);
            },
        ];
    }
    return $out;
}

// Follow the scaffold hook order; the host supplies authorization, domain checks and auditing.
add_action('wp_abilities_api_categories_init',static function():void{
    if(function_exists('wp_register_ability_category'))wp_register_ability_category('ninjafirewall',[
        'label'=>'NinjaFirewall','description'=>'NinjaFirewall diagnostics and scoped administration.']);
});
add_action('wp_abilities_api_init',static function():void{
    if(!function_exists('agent_connector_for_wp_register_ability')||!defined('NFW_ENGINE_VERSION'))return;
    foreach(definitions() as $name=>$args)agent_connector_for_wp_register_ability($name,$args);
});
