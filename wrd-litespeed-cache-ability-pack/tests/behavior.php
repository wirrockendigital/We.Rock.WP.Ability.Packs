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
        public static function cls(): self {return new self();}
        public function purge_url($url): bool {self::$calls[]=['url',$url];return true;}
        public function purge_post($id): void {self::$calls[]=['post',$id];}
        public static function purge_all_lscache(): void {self::$calls[]=['site'];}
    }
}
namespace {
    define('ABSPATH',__DIR__);define('ARRAY_A','ARRAY_A');define('DB_NAME','test');
    $scenario=$argv[1]??'current';
    if ($scenario!=='inactive') define('LSCWP_V',$scenario==='future'?'99.0.0':'7.9.2');
    $allowed=true;$multisite=false;
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
        public string $options='wp_options';public string $prefix='wp_';public string $last_error='';public bool $busy=false;
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
    check(count(ACFW_Pack::definitions())===5,'registration');
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
    echo "PASS $scenario: permission, validation, revision, preview, native writes and purges\n";
}
