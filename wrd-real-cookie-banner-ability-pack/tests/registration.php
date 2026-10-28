<?php
/** Verify independent registration and safe loading without an active target plugin. */
define('ABSPATH',__DIR__.'/');
$GLOBALS['wrd_test_hooks']=[];$GLOBALS['wrd_test_abilities']=[];$GLOBALS['wrd_test_categories']=[];
/** Capture registrations without starting WordPress. */
function add_action($hook,$callback):void {$GLOBALS['wrd_test_hooks'][$hook][]=$callback;}
/** Capture category ownership. */
function wp_register_ability_category($name,$args):void {$GLOBALS['wrd_test_categories'][$name]=$args;}
if(($argv[1]??'')!=='no-host') {
    /** Reject duplicate names or incorrect initialization order. */
    function agent_connector_for_wp_register_ability($name,$args):void {
        if(isset($GLOBALS['wrd_test_abilities'][$name]) || !isset($GLOBALS['wrd_test_categories'][$args['category']]))throw new RuntimeException('Invalid registration order');
        $GLOBALS['wrd_test_abilities'][$name]=$args;
    }
}
require dirname(__DIR__).'/wrd-real-cookie-banner-ability-pack.php';
foreach(['wp_abilities_api_categories_init','wp_abilities_api_init'] as $hook)foreach($GLOBALS['wrd_test_hooks'][$hook] as $callback)$callback();
if(count($GLOBALS['wrd_test_abilities'])!==(($argv[1]??'')==='no-host'?0:10))throw new RuntimeException('Unexpected registration count');
echo "PASS: independent registration, absent target, optional host\n";
