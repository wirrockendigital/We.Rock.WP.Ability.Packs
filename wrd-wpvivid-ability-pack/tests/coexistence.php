<?php
/** Verify independent loading, hook order and namespace coexistence with an absent target. */
declare(strict_types=1);
define('ABSPATH', __DIR__ . '/');
$hooks = [];
$abilities = [];
$categories = [];
$withHost = ($argv[1] ?? '') !== 'no-host';
/** Model the inactive-target error without loading WordPress. */
class WP_Error
{
    public function __construct(public string $code, public string $message, public array $data = []) {}
}
/** Capture WordPress hook registrations without bootstrapping a website. */
function add_action(string $hook, callable $callback): void { $GLOBALS['hooks'][$hook][] = $callback; }
function is_multisite(): bool { return false; }
function wp_register_ability_category(string $name, array $args): void { $GLOBALS['categories'][$name] = $args; }
if ($withHost) {
    /** Reject duplicate names and registration before category creation. */
    function agent_connector_for_wp_register_ability(string $name, array $args): void
    {
        if (isset($GLOBALS['abilities'][$name])) throw new RuntimeException('Duplicate ability name');
        if (!isset($GLOBALS['categories'][$args['category']])) throw new RuntimeException('Category hook order broken');
        $GLOBALS['abilities'][$name] = $args;
    }
}
foreach (glob(dirname(__DIR__) . '/*-ability-pack/*-ability-pack.php') as $main) require $main;
foreach ($hooks['wp_abilities_api_categories_init'] as $callback) $callback();
foreach ($hooks['wp_abilities_api_init'] as $callback) $callback();
if (count($categories) !== 4 || count($abilities) !== ($withHost ? 35 : 0)) throw new RuntimeException('Unexpected pack registration count');
foreach (['Backup','Pro','Staging','ImgoptimFree'] as $module) {
    $class = 'WeRock\\WPvivid' . $module . 'Abilities\\Adapter';
    if ($class::status()['active']) throw new RuntimeException('Missing target incorrectly reported active');
    if ($class::status()['writes_supported']) throw new RuntimeException('Missing target incorrectly writable');
    if (!$class::guard() instanceof WP_Error) throw new RuntimeException('Missing target not rejected');
}
echo 'PASS: four independent packs, ' . count($abilities) . " abilities, absent targets handled\n";
