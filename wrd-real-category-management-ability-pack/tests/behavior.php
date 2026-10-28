<?php
/** Verify operation guards without loading WordPress or any remote state. */
namespace {
    define('ABSPATH',__DIR__);define('RCL_VERSION','99.9.9');
    class WP_Error {public function __construct(public string $code,public string $message){}}
    function add_action(...$args){}
    function is_wp_error($v){return $v instanceof WP_Error;}
    function current_user_can($cap){return $GLOBALS['allowed'];}
    function is_multisite(){return false;}
    function wp_rcl_active(){return true;}
    $GLOBALS['allowed']=true;
}
namespace DevOwl\RealCategoryLibrary {
    class TaxTree {public static function getAvailablePostTypes(){return [];}public function getCats(){return [];}}
}
namespace DevOwl\RealCategoryLibrary\rest {
    class Service {public static function permit($cap='manage_categories'){return null;}}
}
namespace {
    require dirname(__DIR__).'/wrd-real-category-management-ability-pack.php';
    use WeRock\RealCategoryManagementAbilities\Adapter;
    function check($ok){if(!$ok)throw new RuntimeException('Behavior assertion failed');}
    $status=Adapter::run('get-status',[],false);
    check($status['source_version']==='99.9.9');
    check($status['operations']['get-tree']===true);
    check($status['operations']['create-term']===false);
    check(Adapter::run('create-term',[],true)->code==='wrd_rcm_unavailable');
    check(Adapter::run('list-contexts',[],false)===['contexts'=>[]]);
    $GLOBALS['allowed']=false;
    check(Adapter::run('get-status',[],false)->code==='wrd_rcm_permission');
    echo "6 native availability and permission checks passed\n";
}
