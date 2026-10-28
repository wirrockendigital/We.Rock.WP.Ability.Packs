<?php
/** NinjaFirewall 4.9.2 adapter; never load firewall.php or execute configuration files. */
declare(strict_types=1);
namespace WeRock\NinjaAbilities;
defined('ABSPATH') || exit;

final class Adapter
{
    /** Independent scalar switches whose native save path only updates nfw_options. */
    public const SWITCHES = [
        'anon_ip', 'get_scan', 'get_sanitise', 'post_scan', 'post_b64',
        'cookies_scan', 'cookies_sanitise', 'ua_scan', 'ua_sanitise',
        'referer_scan', 'referer_sanitise', 'referer_post', 'no_host_ip',
        'php_superglobals', 'php_errors', 'php_self', 'php_path_t', 'php_path_i',
        'enum_archives', 'enum_sitemap', 'enum_login', 'enum_restapi', 'enum_feed',
        'disable_error_handler', 'disallow_creation', 'disallow_deletion',
        'disallow_settings', 'disallow_privesc', 'disallow_publish',
    ];

    /** Return a typed error without including credentials or arbitrary file contents. */
    public static function error(string $code, string $message): \WP_Error
    {
        return new \WP_Error('ninja_' . $code, $message);
    }

    /** Fail closed if the target plugin is unavailable or its stored options are invalid. */
    public static function ready(bool $write = false): array|\WP_Error
    {
        if (!defined('NFW_ENGINE_VERSION') || !function_exists('nfw_get_option')) {
            return self::error('inactive', 'NinjaFirewall must be active.');
        }
        if (is_multisite()) {
            return self::error('multisite', 'This pack supports single-site WordPress only.');
        }
        // Reads remain available when only the native writer is unavailable.
        foreach ($write ? ['nfw_validate_option', 'nfw_update_option'] : ['nfw_validate_option'] as $function) {
            if (!is_callable($function)) return self::error('native_api', 'Required native function unavailable: ' . $function);
        }
        $options = nfw_get_option('nfw_options');
        if (!is_array($options) || !nfw_validate_option($options)) {
            return self::error('config', 'NinjaFirewall configuration is missing or invalid.');
        }
        return $options;
    }

    /** Hash the complete store so callers can detect stale reads without disclosing it. */
    public static function revision(array $store): string
    {
        return hash('sha256', serialize($store));
    }

    /** Report configured protection separately from this request's runtime status. */
    public static function status(array $input): array|\WP_Error
    {
        $o = self::ready();
        if (is_wp_error($o)) return $o;
        return [
            'site_url' => home_url(), 'engine_version' => (string) NFW_ENGINE_VERSION,
            'source_version' => '4.9.2', 'source_version_matches' => (string) NFW_ENGINE_VERSION === '4.9.2',
            'version_policy' => 'informational', 'writes_supported' => !is_wp_error(self::ready(true)),
            'rules_version' => (string) ($o['rules_version'] ?? ''),
            'enabled' => !empty($o['enabled']), 'debug' => !empty($o['debug']),
            'runtime_status' => defined('NFW_STATUS') ? (int) NFW_STATUS : null,
            'request_whitelisted' => defined('NFW_UWL') && (bool) NFW_UWL,
            'full_waf' => !empty(ini_get('auto_prepend_file')) && str_contains((string) ini_get('auto_prepend_file'), 'ninjafirewall'),
            'configuration_revision' => self::revision($o),
            'note' => 'Runtime status describes this authenticated request, not a public attack simulation.',
        ];
    }

    /** Publish only known non-secret scalar configuration fields. */
    public static function settings(array $input): array|\WP_Error
    {
        $o = self::ready();
        if (is_wp_error($o)) return $o;
        $keys = array_merge(self::SWITCHES, [
            'enabled','debug','ret_code','scan_protocol','uploads','sanitise_fn',
            'allow_local_ip','no_post_themes','force_ssl','disallow_edit','disallow_mods',
            'wl_admin','no_restapi','restapi_loggedin','no_appswd','no_xmlrpc',
            'no_xmlrpc_multi','no_xmlrpc_pingback','fg_enable','fg_mtime',
            'enable_updates','sched_updates','notify_updates','sched_scan','report_scan',
            'auto_del_log','clogs_enable','secupdates','alert_sa_only',
            'a_0','a_11','a_12','a_13','a_14','a_15','a_16','a_21','a_22','a_23',
            'a_24','a_25','a_31','a_41','a_51','a_52','a_61',
        ]);
        $rows = [];
        foreach (array_unique($keys) as $key) {
            if (!array_key_exists($key, $o)) continue;
            // Some native toggles store a path; expose their enabled state instead.
            $value = $key === 'no_post_themes' ? !empty($o[$key]) : $o[$key];
            if (!is_scalar($value)) continue;
            $rows[] = ['key'=>$key, 'value'=>$value, 'writable'=>in_array($key,self::SWITCHES,true)||$key==='ret_code'];
        }
        return ['revision'=>self::revision($o), 'settings'=>$rows,
            'omitted'=>'Credentials, email addresses, CSP/custom headers, block HTML, path exclusions and unknown fields are omitted.'];
    }

    /** Minimal deltas and exact field schemas are the public configuration contract. */
    public static function settingsSchema(): array
    {
        $fields = [];
        foreach (self::SWITCHES as $key) $fields[$key] = ['type'=>'boolean','description'=>'Native NinjaFirewall '.$key.' switch.'];
        $fields['ret_code'] = ['type'=>'integer','enum'=>[400,403,404,406,418,500,503], 'description'=>'HTTP status returned for blocked requests.'];
        return ['type'=>'object','properties'=>$fields,'minProperties'=>1,'additionalProperties'=>false];
    }

    /** List rule metadata without returning regular-expression payloads. */
    public static function rules(array $input): array|\WP_Error
    {
        $o = self::ready();
        if (is_wp_error($o)) return $o;
        $rules = nfw_get_option('nfw_rules');
        if (!is_array($rules)) return self::error('rules', 'Rules are unavailable.');
        $rows = [];
        foreach ($rules as $id=>$rule) {
            if (!is_array($rule) || (int)$id===999) continue;
            if (isset($input['rule_id']) && (int)$input['rule_id'] !== (int)$id) continue;
            if (isset($input['enabled']) && $input['enabled'] !== !empty($rule['ena'])) continue;
            $rows[] = ['id'=>(int)$id,'enabled'=>!empty($rule['ena']),
                'description'=>sanitize_text_field((string)($rule['why']??'')),
                'severity'=>(int)($rule['lev']??0),'editable'=>self::editableRule((int)$id)];
        }
        usort($rows, static fn($a,$b)=>$a['id']<=>$b['id']);
        $offset = $input['offset']??0; $limit=$input['limit']??50;
        return ['revision'=>self::revision($rules),'total'=>count($rows),
            'rules'=>array_slice($rows,$offset,$limit),'has_more'=>count($rows)>$offset+$limit];
    }

    /** Match the exclusions enforced by the native Security Rules Editor. */
    public static function editableRule(int $id): bool
    {
        return $id>0 && $id!==2 && $id!==999 && !($id>=500 && $id<600);
    }

    /** Hold the native options row lock while persisting through NinjaFirewall's writer. */
    private static function mutate(string $option, string $expected, bool $dryRun, callable $change): array|\WP_Error
    {
        $ready=self::ready(true);
        if (is_wp_error($ready)) return $ready;
        global $wpdb;
        // InnoDB is required: otherwise a row lock would give false concurrency guarantees.
        $engine=$wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s',$wpdb->options));
        if (strtolower((string)$engine)!=='innodb') return self::error('engine','Transactional InnoDB options storage is required.');
        if ($wpdb->query('START TRANSACTION')===false) return self::error('transaction','Cannot start a transaction.');
        try {
            $raw=$wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name=%s FOR UPDATE",$option));
            if (!is_string($raw)) throw new \RuntimeException('Native option is missing.');
            $old=unserialize($raw,['allowed_classes'=>false]);
            if (!is_array($old)) throw new \RuntimeException('Native option has an invalid shape.');
            if (!hash_equals(self::revision($old),$expected)) {
                $wpdb->query('ROLLBACK');
                return self::error('stale','Configuration changed. Read its revision again before retrying.');
            }
            $new=$change($old);
            if (is_wp_error($new)) { $wpdb->query('ROLLBACK'); return $new; }
            $changed=$old!==$new;
            if (!$dryRun && $changed) {
                // Clear cached values so the native writer sees the row just locked.
                wp_cache_delete($option,'options'); wp_cache_delete('alloptions','options');
                if (!nfw_update_option($option,$new)) throw new \RuntimeException('Native persistence failed.');
                $saved=$wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name=%s",$option));
                if ($saved!==serialize($new)) throw new \RuntimeException('Persisted configuration did not match.');
            }
            if ($wpdb->query('COMMIT')===false) throw new \RuntimeException('Commit failed.');
            return ['dry_run'=>$dryRun,'would_change'=>$changed,'changed'=>!$dryRun&&$changed,
                'revision'=>self::revision($dryRun?$old:$new),'proposed_revision'=>self::revision($new)];
        } catch (\Throwable $e) {
            $wpdb->query('ROLLBACK');
            wp_cache_delete($option,'options'); wp_cache_delete('alloptions','options');
            return self::error('write','Change failed; transaction rolled back.');
        }
    }

    /** Apply only source-verified independent fields, preserving the remainder byte-for-byte. */
    public static function updateSettings(array $input): array|\WP_Error
    {
        return self::mutate('nfw_options',$input['expected_revision'],$input['dry_run']??true,
            static function(array $o) use ($input) {
                foreach ($input['changes'] as $key=>$value) {
                    if (!array_key_exists($key,$o)) return self::error('field','Setting is absent in this installation.');
                    if ($key==='ret_code') {
                        if (!is_int($value)||!in_array($value,[400,403,404,406,418,500,503],true)) return self::error('value','Invalid HTTP status.');
                    } elseif (!in_array($key,self::SWITCHES,true)||!is_bool($value)) {
                        return self::error('field','Unknown field or non-boolean switch.');
                    }
                    // Preserve native representation when the requested value is unchanged.
                    if ($key==='ret_code' ? (int)$o[$key]===$value : (bool)$o[$key]===$value) continue;
                    $o[$key]=(int)$value;
                }
                return $o;
            });
    }

    /** Enable or disable one editable native rule; do not edit policy-owned rules or regexes. */
    public static function setRule(array $input): array|\WP_Error
    {
        if (!self::editableRule($input['rule_id'])) return self::error('reserved','This rule is policy-owned or reserved.');
        return self::mutate('nfw_rules',$input['expected_revision'],$input['dry_run']??true,
            static function(array $rules) use ($input) {
                $id=$input['rule_id'];
                if (!isset($rules[$id])||!is_array($rules[$id])) return self::error('missing_rule','Rule does not exist.');
                if ((bool)($rules[$id]['ena']??false)!==$input['enabled']) $rules[$id]['ena']=(int)$input['enabled'];
                return $rules;
            });
    }

    /** Resolve only plugin-owned log paths; callers cannot provide filesystem paths. */
    public static function logRoot(): string
    {
        return (defined('NFW_LOG_DIR')?NFW_LOG_DIR:WP_CONTENT_DIR).'/nfwlog';
    }

    /** Read bounded regular files, rejecting symlinks and unexpected oversized input. */
    private static function readBounded(string $path, int $max): string|\WP_Error
    {
        if (is_link($path)) return self::error('symlink','Symbolic-link log/configuration files are not supported.');
        if (!is_file($path)) return self::error('missing_file','Requested file does not exist.');
        $f=@fopen($path,'rb');
        if (!$f) return self::error('unreadable','Requested file is not readable.');
        $text=stream_get_contents($f,$max+1); fclose($f);
        if ($text===false||strlen($text)>$max) return self::error('size','File exceeds the bounded read limit.');
        return $text;
    }

    /** Enumerate only recognized monthly log and statistics files. */
    public static function logMonths(array $input): array|\WP_Error
    {
        $o=self::ready(); if(is_wp_error($o))return $o;
        $rows=[];
        foreach (glob(self::logRoot().'/{firewall,stats}_????-??.php',GLOB_BRACE)?:[] as $path) {
            if(is_link($path)||!preg_match('/^(firewall|stats)_(\d{4}-(?:0[1-9]|1[0-2]))\.php$/D',basename($path),$m))continue;
            $rows[]=['kind'=>$m[1],'month'=>$m[2],'bytes'=>filesize($path)];
        }
        return ['files'=>$rows];
    }

    /** Parse fixed log columns; never expose IPs, request URLs or attack payloads. */
    public static function parseLogLine(string $line): ?array
    {
        if(!preg_match('/^\[(\d{10})\] \[([^\]]*)\] \[([^\]]*)\] \[#(\d+)\] \[(\d+)\] \[(\d)\] \[([^\]]*)\] \[(\d{3})\] \[([^\]]*)\] /',$line,$m))return null;
        return ['timestamp'=>(int)$m[1],'incident_id'=>$m[4],'rule_id'=>(int)$m[5],
            'severity'=>(int)$m[6],'http_status'=>(int)$m[8],
            'method'=>in_array($m[9],['GET','POST','HEAD','PUT','PATCH','DELETE','OPTIONS','N/A'],true)?$m[9]:'OTHER'];
    }

    /** Search the bounded tail of a selected month with exact incident/rule filters. */
    public static function logs(array $input): array|\WP_Error
    {
        $o=self::ready(); if(is_wp_error($o))return $o;
        $month=$input['month']??date('Y-m');
        if(!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D',$month))return self::error('month','Use YYYY-MM.');
        $path=self::logRoot().'/firewall_'.$month.'.php';
        if(!is_file($path))return ['month'=>$month,'entries'=>[],'exists'=>false,'tail_truncated'=>false,'has_more'=>false];
        if(is_link($path))return self::error('symlink','Symbolic-link logs are not supported.');
        $f=@fopen($path,'rb');if(!$f)return self::error('unreadable','Log cannot be read.');
        $size=fstat($f)['size'];$start=max(0,$size-2097152);
        if(fseek($f,$start)!==0){fclose($f);return self::error('seek','Cannot seek the log.');}
        if($start>0)fgets($f,65537);
        $text=stream_get_contents($f,2097152);fclose($f);
        $rows=[];
        foreach(array_reverse(explode("\n",(string)$text)) as $line){
            $row=self::parseLogLine($line);if(!$row)continue;
            if(isset($input['incident_id'])&&$input['incident_id']!==$row['incident_id'])continue;
            if(isset($input['rule_id'])&&$input['rule_id']!==$row['rule_id'])continue;
            if(isset($input['severity'])&&$input['severity']!==$row['severity'])continue;
            $rows[]=$row;
        }
        $limit=$input['limit']??50;$offset=$input['offset']??0;
        return ['month'=>$month,'exists'=>true,'entries'=>array_slice($rows,$offset,$limit),
            'tail_truncated'=>$start>0,'has_more'=>count($rows)>$offset+$limit,
            'redacted_fields'=>['source_ip','host','request_uri','message','payload']];
    }

    /** Read native severity counters using the same indices as the dashboard. */
    public static function statistics(array $input): array|\WP_Error
    {
        $o=self::ready();if(is_wp_error($o))return $o;
        $month=$input['month']??date('Y-m');
        if(!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D',$month))return self::error('month','Use YYYY-MM.');
        $p=self::logRoot().'/stats_'.$month.'.php';$exists=is_file($p);
        $data=$exists?self::readBounded($p,4096):'0:0:0:0:0:0:0:0:0:0';
        if(is_wp_error($data))return $data;
        $data=trim(str_replace('<?php exit; ?>','',$data));
        if(!preg_match('/^\d+(?::\d+){9}:?$/D',$data))return self::error('stats','Unrecognized statistics format.');
        $v=array_map('intval',explode(':',$data));
        return ['month'=>$month,'exists'=>$exists,'medium'=>$v[1],'high'=>$v[2],
            'critical'=>$v[3],'blocked_threats'=>$v[1]+$v[2]+$v[3],
            'uploads'=>$v[5],'info'=>$v[6],'debug'=>$v[7]];
    }

    /** Parse allowlisted numeric login settings without executing the PHP configuration. */
    public static function loginProtection(array $input): array|\WP_Error
    {
        $o=self::ready();if(is_wp_error($o))return $o;
        $p=self::logRoot().'/cache/bf_conf.php';$active=is_file($p);
        if(!$active)$p=self::logRoot().'/cache/bf_conf_off.php';
        if(!is_file($p))return ['configured'=>false,'configuration_active'=>false,'settings'=>[]];
        $s=self::readBounded($p,65536);if(is_wp_error($s))return $s;
        $rows=[];
        foreach(['bf_enable','bf_type','bf_bantime','bf_attempt','bf_maxtime','bf_xmlrpc','bf_allow_bot','bf_authlog','bf_nosig'] as $key){
            if(preg_match('/\$'.preg_quote($key,'/').'\s*=\s*[\'"]?(\d+)[\'"]?\s*;/',$s,$m))$rows[]=['key'=>$key,'value'=>(int)$m[1]];
        }
        return ['configured'=>true,'configuration_active'=>$active,'settings'=>$rows,
            'credential_fields_omitted'=>true];
    }

    /** Return configured scheduling without running cron jobs or exposing their arguments. */
    public static function schedules(array $input): array|\WP_Error
    {
        $o=self::ready();if(is_wp_error($o))return $o;
        $rows=[];
        foreach(['nfwgccron','nfsecupdates','nfscanevent','nfdailyreport'] as $hook){
            $e=wp_get_scheduled_event($hook);
            $rows[]=['hook'=>$hook,'scheduled'=>$e!==false,'next_timestamp'=>$e?(int)$e->timestamp:null,'schedule'=>$e?(string)$e->schedule:null];
        }
        return ['events'=>$rows];
    }

    /** Inspect precedence and syntax, never include or return arbitrary .htninja source. */
    public static function htninja(array $input): array|\WP_Error
    {
        $o=self::ready();if(is_wp_error($o))return $o;
        $root=rtrim((string)($_SERVER['DOCUMENT_ROOT']??''),'/');
        if($root===''||!is_dir($root))return self::error('docroot','Web request DOCUMENT_ROOT is unavailable.');
        $rows=[];$selected=false;
        foreach(['document_root'=>$root.'/.htninja','parent'=>dirname($root).'/.htninja'] as $location=>$p){
            $exists=@is_file($p);$row=['location'=>$location,'exists'=>$exists,'selected'=>$exists&&!$selected];
            if($exists){
                $selected=true;$s=self::readBounded($p,262144);
                if(is_wp_error($s))$row['readable']=false;
                else{
                    $row['readable']=true;$row['sha256']=hash('sha256',$s);$row['bytes']=strlen($s);
                    try{token_get_all($s,TOKEN_PARSE);$row['syntax_valid']=true;}catch(\ParseError $e){$row['syntax_valid']=false;}
                    $row['source_omitted']=true;
                }
            }
            $rows[]=$row;
        }
        return ['files'=>$rows,'precedence'=>'document_root_then_parent',
            'notes'=>['The reviewed engine checks the document root first; the PDF recommends the parent for file protection.',
                'ALLOW bypasses filtering, brute-force protection and normal firewall logging. BLOCK returns 403 before logging.',
                'This ability neither executes nor changes .htninja.']];
    }

    /** Generate an expiring exact-IP/exact-path example for review; never install it. */
    public static function previewHtninja(array $input): array|\WP_Error
    {
        $ip=$input['ip'];$path=$input['path'];$action=$input['action'];
        if(filter_var($ip,FILTER_VALIDATE_IP)===false)return self::error('ip','A single valid IPv4 or IPv6 address is required.');
        if(!preg_match('~^/[A-Za-z0-9/_\-.]*$~D',$path)||str_contains($path,'..')||str_contains($path,'//'))return self::error('path','Use a literal absolute URL path without a query or traversal.');
        if(!in_array($action,['ALLOW','BLOCK'],true))return self::error('action','Use ALLOW or BLOCK.');
        $expires=time()+($input['ttl_minutes']??15)*60;
        $host=(string)wp_parse_url(home_url(),PHP_URL_HOST);
        $code="<?php\n// Reviewed example only: exact host, source IP and URL path; expires automatically.\n";
        $code.="if (time() < ".$expires."\n    && strtolower((string) (\$_SERVER['HTTP_HOST'] ?? '')) === ".var_export(strtolower($host),true)."\n    && (\$_SERVER['REMOTE_ADDR'] ?? '') === ".var_export($ip,true)."\n    && parse_url((string) (\$_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) === ".var_export($path,true).") {\n";
        if($action==='ALLOW')$code.="    // Explicitly mark this request as whitelisted before returning.\n    if (!defined('NFW_UWL')) { define('NFW_UWL', true); }\n";
        $code.="    return ".var_export($action,true).";\n}\n";
        return ['installed'=>false,'php'=>$code,'expires_at'=>gmdate('c',$expires),
            'warning'=>'Review and merge manually with existing .htninja. ALLOW skips all firewall filtering and logging. Never trust proxy headers without trusted-proxy validation.'];
    }
}
