<?php
/** Export public schemas without a WordPress installation or native target. */
define('ABSPATH', __DIR__ . '/');
/** Ignore registration hooks while exporting declarative definitions. */
function add_action($hook,$callback): void {}
require dirname(__DIR__) . '/wrd-code-snippets-ability-pack.php';
$out=[];
foreach (\WeRock\CodeSnippetsAbilities\definitions() as $name=>$definition) {
    unset($definition['execute_callback'],$definition['summarize']);
    if($definition['input_schema']['properties']===[])$definition['input_schema']['properties']=(object)[];
    $out[$name]=$definition;
}
echo json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) . "\n";
