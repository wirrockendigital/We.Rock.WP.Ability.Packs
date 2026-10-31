<?php
/** Exercise permissions, previews, revisions, native boundaries and failure reporting in isolation. */
declare(strict_types=1);
namespace LiteSpeed {
    /** Model WordPress scalar persistence separately from effective typed settings. */
    class Conf {
        public static array $values = ['cache'=>true,'optm-css_min'=>false,'optm-js_defer'=>0];
        public static array $stored = ['cache'=>'1','optm-css_min'=>'','optm-js_defer'=>'0'];
        public static int $writes = 0;
        public static bool $fail = false;
        public static function cls(): self {return new self();}
        public function conf($key,$ori=false): mixed {return self::$values[$key]??null;}
        public static function get_option($key,$default=null): mixed {return self::$stored[$key]??$default;}
        public function update_confs(array $values): void {
            self::$writes++;
            if (self::$fail) return;
            foreach ($values as $key=>$value) {self::$values[$key]=$value;self::$stored[$key]=(string)$value;}
        }
    }
    /** Record exact native dispatch so previews and rejected inputs cannot silently mutate. */
    class Purge {
        public static array $calls=[];
        public static function get_summary(): array {return [];}
        public static function purge_all(): void {self::$calls[]=['all'];}
        public static function cls(): self {return new self();}
        public function purge_url($url): bool {self::$calls[]=['url',$url];return true;}
        public function purge_post($id): void {self::$calls[]=['post',$id];}
        public static function purge_all_lscache(): void {self::$calls[]=['site'];}
    }
}
namespace LiteSpeed {
    /** Cloud fixture deliberately includes secrets that must never reach a tool response. */
    class DB_Optm {
        /** Deterministic candidate counts let the database preview exercise its revision path. */
        public static function cls(): self {return new self();}
        public function db_count($type): int {return $type==='revision'?4:0;}
        public function handler_clean_db_cli($type): bool {throw new \RuntimeException('Cleanup must not run in these preview tests');}
    }
    class Cloud {
        public static int $calls=0;
        public static bool $fail=false;
        public static function cls(): self {return new self();}
        public static function get_summary(): array {return ['sk_b64'=>'private-secret','qc_activated'=>1,'access_token'=>'private-secret','last_request'=>123,'usage.ccss'=>['quota'=>100,'token'=>'private-secret']];}
        public function sync_usage(): mixed {self::$calls++;return self::$fail?false:['private'=>'private-secret'];}
        public function clear_cloud(): void {self::$calls++;}
    }
}
namespace {
    define('ABSPATH',__DIR__);define('ARRAY_A','ARRAY_A');define('DB_NAME','test');
    $scenario=$argv[1]??'current';
    if ($scenario!=='inactive') define('LSCWP_V',$scenario==='future'?'99.0.0':'7.9.2');
    $allowed=true;$multisite=false;$receipts=[];
    function get_current_user_id(): int {return 1;}
    function get_option($key,$default=false): mixed {return $GLOBALS['receipts'][$key]??$default;}
    function add_option($key,$value,$deprecated='',$autoload=false): bool {if(isset($GLOBALS['receipts'][$key]))return false;$GLOBALS['receipts'][$key]=$value;return true;}
    function update_option($key,$value,$autoload=false): bool {$GLOBALS['receipts'][$key]=$value;return true;}
    /** Minimal host error type, without native exception text. */
    class WP_Error {public function __construct(public string $code,public string $message) {}}
    function is_wp_error($v): bool {return $v instanceof WP_Error;}
    function current_user_can($cap,$id=null): bool {return $GLOBALS['allowed'] && ($id===null||$id===7);}
    function is_multisite(): bool {return $GLOBALS['multisite'];}
    function home_url($path): string {return 'https://example.test'.$path;}
    function get_current_blog_id(): int {return 1;}
    function wp_salt($type): string {return 'isolated-test-only';}
    function wp_json_encode($v): string {return json_encode($v,JSON_THROW_ON_ERROR);}
    function wp_parse_url($v): array|false {return parse_url($v);}
    function get_post($id): ?object {return $id===7?(object)['ID'=>7]:null;}
    function add_action(...$args): void {}
    /** Schema fixtures are also validated independently with Python jsonschema. */
    function rest_validate_value_from_schema($value,$schema) {
        $type=$schema['type']??null;
        $valid=match($type){'object'=>is_array($value),'array'=>is_array($value)&&array_is_list($value),'integer'=>is_int($value),'boolean'=>is_bool($value),'string'=>is_string($value),default=>true};
        if (!$valid) return new WP_Error('schema','type');
        if (isset($schema['enum'])&&!in_array($value,$schema['enum'],true)) return new WP_Error('schema','enum');
        if ($type==='object') {
            foreach ($schema['required']??[] as $key) if (!array_key_exists($key,$value)) return new WP_Error('schema','required');
            if (isset($schema['minProperties'])&&count($value)<$schema['minProperties']) return new WP_Error('schema','empty');
            foreach ($value as $key=>$item) {
                if (!isset($schema['properties'][$key])) return new WP_Error('schema','extra');
                if (is_wp_error(rest_validate_value_from_schema($item,$schema['properties'][$key]))) return new WP_Error('schema','child');
            }
        }
        if ($type==='integer'&&($value<($schema['minimum']??PHP_INT_MIN)||$value>($schema['maximum']??PHP_INT_MAX))) return new WP_Error('schema','bounds');
        if ($type==='string'&&(strlen($value)<($schema['minLength']??0)||strlen($value)>($schema['maxLength']??PHP_INT_MAX))) return new WP_Error('schema','length');
        if ($type==='string'&&isset($schema['pattern'])&&!preg_match('~'.$schema['pattern'].'~u',$value)) return new WP_Error('schema','pattern');
        if ($type==='array') {
            if (count($value)<($schema['minItems']??0)||count($value)>($schema['maxItems']??PHP_INT_MAX)) return new WP_Error('schema','count');
            if (($schema['uniqueItems']??false)&&count(array_unique($value))!==count($value)) return new WP_Error('schema','duplicates');
            foreach ($value as $item) if (is_wp_error(rest_validate_value_from_schema($item,$schema['items']))) return new WP_Error('schema','item');
        }
        return true;
    }
    /** Represent persistent rows and the advisory-lock result independently. */
    class Database {
        public string $posts='wp_posts';public string $postmeta='wp_postmeta';public string $comments='wp_comments';public string $commentmeta='wp_commentmeta';public string $options='wp_options';public string $prefix='wp_';public string $last_error='';public bool $busy=false;
        public array $extra=[];
        public function prepare($sql,...$args): string {return $sql;}
        public function esc_like($v): string {return $v;}
        public function get_results($sql,$mode): array {return [\LiteSpeed\Conf::$stored,$this->extra];}
        public function get_var($sql): string {return $this->busy?'0':'1';}
    }
    $wpdb=new Database();
    require __DIR__.'/../wrd-litespeed-cache-ability-pack.php';
    use WeRock\LiteSpeedAbilities\Adapter;
    use WeRock\LiteSpeedAbilities\ACFW_Pack;
    /** Make regressions fail regardless of the interpreter assertion configuration. */
    function check($condition,$message): void {if (!$condition) throw new \RuntimeException($message);}
    function run($op,$args=[]): mixed {return Adapter::run($op,$args);}
    if ($scenario==='export') {
        $definitions=ACFW_Pack::definitions();
        foreach ($definitions as &$def) {unset($def['execute_callback'],$def['summarize']);}unset($def);
        echo json_encode($definitions);exit;
    }
    check(count(ACFW_Pack::definitions())===13,'registration');
    $allowed=false;check(is_wp_error(run('get-status')),'permission gate');$allowed=true;
    $status=run('get-status');
    if ($scenario==='inactive') {check(!$status['operations']['get-settings'],'inactive availability');check(is_wp_error(run('get-settings')),'inactive read blocked');echo "PASS inactive\n";exit;}
    check($status['operations']['update-settings'],'native availability despite version');
    $multisite=true;check(is_wp_error(run('get-settings')),'multisite blocked');$multisite=false;
    $state=run('get-settings');$revision=$state['revision'];
    $update=['reason'=>'test update','expected_revision'=>$revision,'changes'=>['optm-css_min'=>true]];
    check(run('update-settings',$update)['dry_run']===true&&\LiteSpeed\Conf::$writes===0,'default preview');
    check(is_wp_error(run('update-settings',$update+['unexpected'=>true])),'closed input');
    $bad=$update;$bad['changes']=['api_key'=>'secret'];check(is_wp_error(run('update-settings',$bad)),'secret rejected');
    $bad=$update;$bad['changes']=['cache'=>'false'];check(is_wp_error(run('update-settings',$bad)),'strict boolean');
    $bad=$update;$bad['changes']=['optm-js_defer'=>3];check(is_wp_error(run('update-settings',$bad)),'enum bounds');
    $wpdb->extra=['private_key'=>'changed'];check(is_wp_error(run('update-settings',$update)),'hidden config affects revision');$wpdb->extra=[];
    $wpdb->busy=true;check(is_wp_error(run('update-settings',$update)),'busy lock');$wpdb->busy=false;
    $applied=run('update-settings',$update+['dry_run'=>false]);check($applied['applied']&&\LiteSpeed\Conf::$writes===1,'native typed persistence');
    check(is_wp_error(run('update-settings',$update+['dry_run'=>false])),'stale revision');
    $update['expected_revision']=run('get-settings')['revision'];check(!run('update-settings',$update+['dry_run'=>false])['applied'],'no-op avoids native side effects');
    $update['changes']=['optm-css_min'=>false];\LiteSpeed\Conf::$fail=true;check(is_wp_error(run('update-settings',$update+['dry_run'=>false])),'failed native persistence reported');
    $purge=['reason'=>'test invalidation','scope'=>'urls','urls'=>['https://example.test/example/']];
    check(!run('purge-cache',$purge)['requested']&&\LiteSpeed\Purge::$calls===[],'purge preview');
    check(is_wp_error(run('purge-cache',$purge+['dry_run'=>false])),'purge acknowledgement');
    foreach (['https://evil.test/','https://example.test/x?q=1','https://example.test/#fragment','https://user@example.test/','https://example.test/*'] as $url) {
        $bad=$purge;$bad['urls']=[$url];check(is_wp_error(run('purge-cache',$bad)),'invalid URL rejected');
    }
    check(run('purge-cache',$purge+['dry_run'=>false,'acknowledge_cache_rebuild'=>true])['requested'],'native URL purge');
    check(\LiteSpeed\Purge::$calls===[['url','https://example.test/example/']],'exact native target');
    check(is_wp_error(run('purge-cache',['reason'=>'bad post','scope'=>'posts','post_ids'=>[8]])),'post permission');
    check(is_wp_error(run('purge-cache',['reason'=>'extra target','scope'=>'site','post_ids'=>[7]])),'ambiguous site scope');
    run('purge-cache',['reason'=>'site test','scope'=>'site','dry_run'=>false,'acknowledge_cache_rebuild'=>true]);
    check(end(\LiteSpeed\Purge::$calls)===['site'],'site native dispatch');
    check(\WeRock\LiteSpeedAbilities\Operations::sitemap_urls_valid("https://example.test/sitemap.xml\r\nhttps://example.test/posts.xml"),'native multiline sitemap');
    check(!\WeRock\LiteSpeedAbilities\Operations::sitemap_urls_valid("https://example.test/sitemap.xml\nhttps://other.test/sitemap.xml"),'sitemap origin boundary');
    // Exercise native maintenance replay protection, redaction and uncertain outcomes.
    $cloud=run('get-component-status',['component'=>'cloud']);
    check(!str_contains(json_encode($cloud),'private-secret')&&$cloud['activated'],'redacted cloud status');
    $maintenance=['reason'=>'refresh cloud status','action'=>'sync-cloud-usage','expected_revision'=>$cloud['revision'],'request_id'=>'12345678-1234-4234-8234-123456789abc'];
    check(run('run-maintenance',$maintenance)['requested']===false&&count($receipts)===0,'maintenance preview has no receipt or write');
    check(is_wp_error(run('run-maintenance',$maintenance+['dry_run'=>false])),'exact action acknowledgement');
    $applied=run('run-maintenance',$maintenance+['dry_run'=>false,'confirm_action'=>'sync-cloud-usage']);
    check($applied['receipt']['state']==='native_returned'&&\LiteSpeed\Cloud::$calls===1,'native maintenance call');
    check(!str_contains(json_encode($applied),'private-secret'),'native return redacted');
    $replay=run('run-maintenance',$maintenance+['dry_run'=>false,'confirm_action'=>'sync-cloud-usage']);
    check($replay['replayed']&&\LiteSpeed\Cloud::$calls===1,'idempotent receipt replay');
    $other=$maintenance;$other['action']='clear-cloud-nodes';check(is_wp_error(run('run-maintenance',$other)),'request ID collision');
    $other=$maintenance;$other['request_id']='22345678-1234-4234-8234-123456789abc';$other['expected_revision']=str_repeat('0',64);check(is_wp_error(run('run-maintenance',$other)),'maintenance stale revision');
    $other=$maintenance;$other['request_id']='32345678-1234-4234-8234-123456789abc';\LiteSpeed\Cloud::$fail=true;
    $failed=run('run-maintenance',$other+['dry_run'=>false,'confirm_action'=>'sync-cloud-usage']);
    check($failed['receipt']['state']==='needs_reconciliation','native uncertainty receipt');
    $receipt=run('get-operation-receipt',['request_id'=>$other['request_id']]);check($receipt['found'],'read receipt');
    $cache=run('get-component-status',['component'=>'cache']);
    $flush=['reason'=>'flush test','action'=>'purge-all','expected_revision'=>$cache['revision'],'request_id'=>'42345678-1234-4234-8234-123456789abc','dry_run'=>false,'confirm_action'=>'purge-all'];
    check(is_wp_error(run('run-maintenance',$flush)),'shared backend acknowledgement');
    $db=run('get-database-status');check($db['counts']['revision']===4,'native candidate count');
    $cleanup=['reason'=>'preview database cleanup','kind'=>'revision','expected_revision'=>$db['revision'],'request_id'=>'52345678-1234-4234-8234-123456789abc'];
    $preview=\WeRock\LiteSpeedAbilities\Operations::run('cleanup-database',$cleanup);
    check(!$preview['requested']&&$preview['required_confirmation']==='database:revision','database preview');
    $rejected=\WeRock\LiteSpeedAbilities\Operations::run('cleanup-database',$cleanup+['dry_run'=>false,'confirm_action'=>'database:revision']);
    check(is_wp_error($rejected)&&$rejected->code==='wrd_litespeed_backup','backup gate before native deletion');
    check(is_wp_error(\WeRock\LiteSpeedAbilities\NativeProcess::run('arbitrary-shell',[])),'closed process dispatcher');
    $high=['reason'=>'high impact test','changes'=>['img_optm-rm_bkup'=>true],'expected_revision'=>run('get-settings')['revision'],'dry_run'=>false];
    check(is_wp_error(run('update-settings',$high)),'high impact setting acknowledgement');
    echo "PASS $scenario: permission, validation, revision, preview, native writes and purges\n";
}
