<?php
/** Exercise adapters against independent native API doubles with controlled missing capabilities. */
declare(strict_types=1);
namespace {
    if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
    define('ABSPATH', __DIR__ . '/'); define('ARRAY_A', 'ARRAY_A');
    $pack=$argv[1]; $scenario=$argv[2];
    $versions=['ninja'=>['NFW_ENGINE_VERSION','4.9.2'],'snippets'=>['CODE_SNIPPETS_VERSION','4.0.0'],'media'=>['RML_VERSION','4.23.7'],'cookie'=>['RCB_VERSION','5.3.0']];
    if($scenario!=='inactive') define($versions[$pack][0],$scenario==='current'?$versions[$pack][1]:'99.0.0');
    /** Keep all test failures and permission outcomes independent from WordPress. */
    class WP_Error { public function __construct(public string $code,public string $message,public array $data=[]){} }
    function is_wp_error(mixed $value):bool{return $value instanceof WP_Error;}
    function is_multisite():bool{return $GLOBALS['scenario']==='multisite';}
    function current_user_can(...$args):bool{return $GLOBALS['scenario']!=='denied';}
    function home_url():string{return 'https://fixture.invalid';}
    function wp_json_encode(mixed $value):string{return json_encode($value);}
    function get_option(string $key,mixed $default=false):mixed{return $default;}
    function post_type_exists(string $type):bool{return $GLOBALS['scenario']!=='missing-read';}
    function taxonomy_exists(string $type):bool{return true;}
    function _wp_rml_root():int{return -1;}
    // Simulate a live multilingual plugin without changing its native media tables.
    if($scenario==='multilingual') { function pll_current_language():string{return 'en';} }
    /** A native REST controller can disappear without breaking ordinary catalog reads. */
    class Controller {public function create_item():void{} public function update_item():void{}}
    function get_post_type_object(string $type):object{return new class {
        public bool $show_in_rest=true;
        public function get_rest_controller():object{return $GLOBALS['scenario']==='missing-write'?new \stdClass():new Controller();}
    };}
    class Database {
        public string $posts='wp_posts';
        public function prepare(string $sql,mixed ...$args):string{return $sql;}
        public function get_results(...$args):array{return [];}
        public function get_col(...$args):array{return [];}
    }
    $GLOBALS['wpdb']=new Database();
    if($scenario!=='missing-read') {
        function nfw_get_option(string $key):array{return ['enabled'=>1];}
        function nfw_validate_option(array $options):bool{return true;}
    }
    if($scenario!=='missing-write') {
        function nfw_update_option(...$args):bool{throw new \RuntimeException('Unexpected native mutation');}
        function wp_rml_create(...$args):int{throw new \RuntimeException('Unexpected native mutation');}
    }
    function wp_rml_structure_reset():void{}
    function wp_rml_get_object_by_id(int $id):object{return new \stdClass();}
}
namespace Code_Snippets\Model {class Snippet {}}
namespace Code_Snippets\Utils {
    /** The validator has no execution side effect. */
    class Validator {public function __construct(string $code){} public function validate():bool{return false;}}
}
namespace Code_Snippets {
    if($GLOBALS['scenario']!=='missing-read') {
        function code_snippets():object{return new class {
            public object $db;
            public function __construct(){$this->db=new class {public function get_table_name(bool $network):string{return 'wp_snippets';}};}
            public function current_user_can():bool{return \current_user_can('manage_options');}
        };}
    }
    function clean_snippets_cache(...$args):void{}
    function is_snippet_locked(...$args):bool{return false;}
    function get_snippets(...$args):array{return [];}
    function get_snippet(...$args):mixed{return null;}
    if($GLOBALS['scenario']!=='missing-write') {function save_snippet(...$args):mixed{throw new \RuntimeException('Unexpected native mutation');}}
}
namespace MatthiasWeb\RealMediaLibrary {
    if($GLOBALS['scenario']!=='missing-read') {
        class Core {
            public static function getInstance():self{return new self();}
            public function isPro():bool{return true;}
            public function getTableName(string $suffix):string{return 'wp_realmedialibrary'.$suffix;}
        }
    }
}
namespace MatthiasWeb\RealMediaLibrary\rest {
    class Service {public static function permit():mixed{return \current_user_can('upload_files')?true:new \WP_Error('permission','Denied');}}
}
namespace {
    /** Exercise actual adapter dispatch, rather than reimplementing its availability logic. */
    $paths=['ninja'=>'wrd-ninjafirewall-ability-pack','snippets'=>'wrd-code-snippets-ability-pack','media'=>'wrd-real-media-library-ability-pack','cookie'=>'wrd-real-cookie-banner-ability-pack'];
    $classes=['ninja'=>'WeRock\\NinjaAbilities\\Adapter','snippets'=>'WeRock\\CodeSnippetsAbilities\\Adapter','media'=>'WeRock\\RealMediaLibraryAbilities\\Adapter','cookie'=>'WeRock\\RealCookieBannerAbilities\\Adapter'];
    require dirname(__DIR__).'/'.$paths[$pack].'/src/Adapter.php';$adapter=$classes[$pack];
    $reads=['snippets'=>'list-snippets','media'=>'list-folders','cookie'=>'list-items'];
    $writes=['snippets'=>'create-snippet','media'=>'create-folder','cookie'=>'create-draft'];
    $args=$pack==='cookie'?['kind'=>'service']:[];
    $result=$pack==='ninja'?$adapter::settings([]):$adapter::run($reads[$pack],$args);
    $blocked=in_array($scenario,['inactive','missing-read','multisite'],true)||($scenario==='denied'&&$pack!=='ninja');
    if(is_wp_error($result)!==$blocked)throw new \RuntimeException('Unexpected read availability: '.$pack.'/'.$scenario);
    $guard=$pack==='ninja'?$adapter::ready(true):($pack==='cookie'?$adapter::guard($writes[$pack],'service'):$adapter::guard($writes[$pack]));
    if(is_wp_error($guard)!==($blocked||$scenario==='missing-write'||($pack==='media'&&$scenario==='multilingual')))throw new \RuntimeException('Unexpected write availability: '.$pack.'/'.$scenario);
    $status=$pack==='ninja'?$adapter::status([]):$adapter::run('get-status',[]);
    if(!is_wp_error($status)){
        if(($status['version_policy']??null)!=='informational')throw new \RuntimeException('Version is not informational');
        if($status['source_version_matches']!==($scenario==='current'))throw new \RuntimeException('Wrong source metadata');
        if($pack==='media'&&$scenario==='multilingual'&&(!$status['supported']||$status['writes_supported']))throw new \RuntimeException('Multilingual media status is wrong');
    }
    echo 'PASS: '.$pack.'/'.$scenario."\n";
}
