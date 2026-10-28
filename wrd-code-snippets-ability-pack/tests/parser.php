<?php
/** Verify parsing without evaluating supplied code against the reviewed vendor validator. */
define('ABSPATH', __DIR__ . '/');
/** Local translation shim for the native validator. */
function __($text,$domain=null) { return $text; }
require $argv[1] . '/php/Utils/Validator.php';
require dirname(__DIR__) . '/src/Adapter.php';
use WeRock\CodeSnippetsAbilities\Adapter;
$cases=[['echo "hello";',true],['echo ;',false],['file_put_contents("/tmp/wrd-parser-must-not-execute", "bad");',true],['function strlen() {}',false]];
foreach($cases as [$code,$valid]) {
    $result=Adapter::validate($code,'global');
    if($result['valid']!==$valid || $result['executed']!==false)throw new RuntimeException('Parser contract failed.');
}
if(file_exists('/tmp/wrd-parser-must-not-execute'))throw new RuntimeException('Parser evaluated code.');
if(Adapter::validate('body { color: red; }','site-css')['valid']!==null)throw new RuntimeException('Non-PHP validation overstated.');
echo "PASS: syntax, duplicate declaration, no evaluation, non-PHP boundary\n";
