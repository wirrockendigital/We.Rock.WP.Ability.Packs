<?php
/** Focused regression tests for privacy, file bounds, concurrency and delta semantics. */
declare(strict_types=1);
// This standalone fixture runner must never be reachable over HTTP.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('ABSPATH', sys_get_temp_dir().'/wrd-ninja-test-'.getmypid().'/');
define('WP_CONTENT_DIR',ABSPATH.'wp-content');
// Run the same mutation and privacy regressions with a future vendor version.
define('NFW_ENGINE_VERSION',getenv('WRD_TEST_FUTURE_VERSION') ?: '4.9.2');
define('NFW_STATUS',20);
mkdir(WP_CONTENT_DIR.'/nfwlog/cache',0700,true);
$_SERVER['DOCUMENT_ROOT']=rtrim(ABSPATH,'/');

/** Minimal WordPress boundary doubles; the live suite separately exercises the real MCP API. */
class WP_Error { public function __construct(public string $code, public string $message){} }
function is_wp_error($x):bool{return $x instanceof WP_Error;}
function is_multisite():bool{return false;}
function home_url():string{return 'https://example.test';}
function wp_parse_url(string $url,int $component):mixed{return parse_url($url,$component);}
function sanitize_text_field(string $s):string{return strip_tags($s);}
function add_action(...$args):void{}
function wp_cache_delete(...$args):void{}
function wp_get_scheduled_event($hook):bool{return false;}
function nfw_get_option(string $key):mixed{return $GLOBALS['stores'][$key]??false;}
function nfw_validate_option(array $o):bool{return isset($o['enabled']);}
function nfw_update_option(string $key,array $value):bool{
    if($GLOBALS['fail_write']??false)return false;
    $GLOBALS['stores'][$key]=$value;return true;
}

/** Simulate transactional persistence so rollback and stale writes have observable outcomes. */
class TestDatabase {
    public string $options='wp_options';private array $snapshot=[];
    public function prepare(string $sql,...$args):array{return [$sql,$args];}
    public function get_var(array $q):?string{
        if(str_contains($q[0],'SELECT ENGINE'))return 'InnoDB';
        return isset($GLOBALS['stores'][$q[1][0]])?serialize($GLOBALS['stores'][$q[1][0]]):null;
    }
    public function query(string $sql):int{
        if($sql==='START TRANSACTION')$this->snapshot=$GLOBALS['stores'];
        if($sql==='ROLLBACK')$GLOBALS['stores']=$this->snapshot;
        return 1;
    }
}
$GLOBALS['wpdb']=new TestDatabase();
$GLOBALS['stores']=['nfw_options'=>['enabled'=>1,'anon_ip'=>0,'ret_code'=>'403','get_scan'=>1,'private_field'=>'private-fixture'],
 'nfw_rules'=>[1=>['ena'=>1,'why'=>'Traversal','lev'=>3,'cha'=>['fixture']],2=>['ena'=>1],999=>['ena'=>1]]];
require dirname(__DIR__).'/src/Adapter.php';
require dirname(__DIR__).'/abilities/register.php';
use WeRock\NinjaAbilities\Adapter;

/** Fail immediately with a readable case name. */
function check(bool $condition,string $case):void{
    if(!$condition)throw new RuntimeException('FAIL: '.$case);
    echo 'PASS: '.$case."\n";
}

try{
    $before=$GLOBALS['stores'];$rev=Adapter::revision($before['nfw_options']);
    $base=['expected_revision'=>$rev,'reason'=>'Fixture test','changes'=>['anon_ip'=>true]];
    $r=Adapter::updateSettings($base);
    check($r['would_change']&&!$r['changed']&&$GLOBALS['stores']===$before,'default dry run does not write');
    $r=Adapter::updateSettings($base+['dry_run'=>false]);
    check($r['changed']&&$GLOBALS['stores']['nfw_options']['anon_ip']===1,'minimal delta persists');
    check($GLOBALS['stores']['nfw_options']['private_field']==='private-fixture'&&$GLOBALS['stores']['nfw_options']['ret_code']==='403','unrelated values and native types survive');
    $after=$GLOBALS['stores'];
    check(is_wp_error(Adapter::updateSettings($base+['dry_run'=>false]))&&$GLOBALS['stores']===$after,'stale revision cannot overwrite');
    $current=Adapter::revision($after['nfw_options']);
    check(is_wp_error(Adapter::updateSettings(['expected_revision'=>$current,'changes'=>['enabled'=>false],'dry_run'=>false])),'global disable excluded');
    check(is_wp_error(Adapter::updateSettings(['expected_revision'=>$current,'changes'=>['anon_ip'=>true,'ret_code'=>999],'dry_run'=>false]))&&$GLOBALS['stores']===$after,'mixed invalid delta is atomic');
    $GLOBALS['fail_write']=true;
    check(is_wp_error(Adapter::updateSettings(['expected_revision'=>$current,'changes'=>['anon_ip'=>false],'dry_run'=>false]))&&$GLOBALS['stores']===$after,'persistence failure rolls back');
    $GLOBALS['fail_write']=false;
    $rr=Adapter::revision($GLOBALS['stores']['nfw_rules']);
    check(is_wp_error(Adapter::setRule(['rule_id'=>2,'enabled'=>false,'expected_revision'=>$rr,'dry_run'=>false])),'policy-owned rule rejected');
    check(is_wp_error(Adapter::setRule(['rule_id'=>999,'enabled'=>false,'expected_revision'=>$rr,'dry_run'=>false])),'reserved metadata rule rejected');
    $r=Adapter::setRule(['rule_id'=>1,'enabled'=>false,'expected_revision'=>$rr,'dry_run'=>false]);
    check($r['changed']&&$GLOBALS['stores']['nfw_rules'][1]['cha']===['fixture'],'rule mutation preserves matching logic');
    $rr=Adapter::revision($GLOBALS['stores']['nfw_rules']);
    $r=Adapter::setRule(['rule_id'=>1,'enabled'=>true,'expected_revision'=>$rr,'dry_run'=>false]);
    check($r['changed']&&$GLOBALS['stores']['nfw_rules']===$before['nfw_rules'],'rule state round trip');
    $settings=json_encode(Adapter::settings([]));
    check(!str_contains($settings,'private-fixture')&&!str_contains($settings,'private_field'),'unknown settings are not disclosed');
    $log='[1788860000] [0.003] [example.com] [#1234567] [42] [3] [192.0.2.9] [403] [POST] [/login?token=private-fixture] [blocked] [hex:private-fixture]';
    $row=Adapter::parseLogLine($log);
    check($row['rule_id']===42&&$row['http_status']===403&&!str_contains(json_encode($row),'private-fixture')&&!str_contains(json_encode($row),'192.0.2.9'),'log parser drops all request data');
    file_put_contents(WP_CONTENT_DIR.'/nfwlog/firewall_2026-09.php',"<?php exit; ?>\n$log\n");
    check(count(Adapter::logs(['month'=>'2026-09','incident_id'=>'1234567'])['entries'])===1,'exact incident search');
    check(count(Adapter::logs(['month'=>'2026-09','rule_id'=>99])['entries'])===0,'exact rule filter');
    check(is_wp_error(Adapter::logs(['month'=>'../../wp-config'])),'path traversal rejected');
    file_put_contents(WP_CONTENT_DIR.'/nfwlog/stats_2026-09.php','<?php exit; ?>0:2:3:4:0:1:5:6:0:0');
    check(Adapter::statistics(['month'=>'2026-09'])['blocked_threats']===9,'native severity index mapping');
    file_put_contents(WP_CONTENT_DIR.'/nfwlog/cache/bf_conf.php',"<?php\n\$bf_enable=2;\n\$bf_type=1;\n\$auth_pass='private-fixture';\nfile_put_contents('".ABSPATH."executed','bad');");
    $login=Adapter::loginProtection([]);
    check($login['settings'][0]['value']===2&&!str_contains(json_encode($login),'private-fixture')&&!file_exists(ABSPATH.'executed'),'login configuration parsed without execution or secrets');
    file_put_contents(ABSPATH.'.htninja',"<?php file_put_contents('".ABSPATH."executed', 'private-fixture');");
    $ht=Adapter::htninja([]);
    check($ht['files'][0]['selected']&&$ht['files'][0]['syntax_valid']&&!file_exists(ABSPATH.'executed')&&!str_contains(json_encode($ht),'private-fixture'),'htninja syntax check never executes or discloses code');
    file_put_contents(ABSPATH.'.htninja','<?php if (');
    check(Adapter::htninja([])['files'][0]['syntax_valid']===false,'invalid htninja syntax detected');
    $preview=Adapter::previewHtninja(['ip'=>'2001:db8::1','path'=>'/wp-admin/','action'=>'ALLOW']);
    check($preview['installed']===false&&str_contains($preview['php'],'HTTP_HOST')&&str_contains($preview['php'],'time() <')&&str_contains($preview['php'],'REMOTE_ADDR'),'preview has exact host, IP, path and expiry');
    token_get_all($preview['php'],TOKEN_PARSE);
    check(is_wp_error(Adapter::previewHtninja(['ip'=>"';exit;",'path'=>'/','action'=>'ALLOW'])),'preview rejects PHP injection via IP');
    check(is_wp_error(Adapter::previewHtninja(['ip'=>'192.0.2.1','path'=>'/../secret','action'=>'BLOCK'])),'preview rejects traversal');
    unlink(WP_CONTENT_DIR.'/nfwlog/stats_2026-09.php');
    symlink(ABSPATH.'.htninja',WP_CONTENT_DIR.'/nfwlog/stats_2026-09.php');
    check(is_wp_error(Adapter::statistics(['month'=>'2026-09'])),'symlink log target rejected');
    $defs=WeRock\NinjaAbilities\definitions();
    check(count($defs)===13,'all thirteen ability definitions present');
    foreach($defs as $def)check($def['input_schema']['additionalProperties']===false&&!isset($def['permission_callback']),'strict schema and host governance: '.$def['label']);
    echo "ALL BEHAVIOR TESTS PASSED\n";
} finally {
    // Remove only this process's synthetic test files.
    $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator(ABSPATH,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($it as $p){if($p->isDir()&&!$p->isLink())rmdir($p->getPathname());else unlink($p->getPathname());}
    rmdir(ABSPATH);
}
