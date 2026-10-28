<?php
/** Export the real registry without requiring WordPress or touching a website. */
define('ABSPATH',__DIR__);
function add_action(...$args) {}
require dirname(__DIR__).'/wrd-polylang-ability-pack.php';
$definitions=\WeRock\PolylangAbilities\ACFW_Pack::definitions();
foreach($definitions as &$definition){unset($definition['execute_callback'],$definition['summarize']);}
unset($definition);
echo json_encode($definitions,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
