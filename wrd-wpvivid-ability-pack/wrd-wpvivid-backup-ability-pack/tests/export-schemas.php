<?php
/** Export public schemas without loading WordPress or executing a native operation. */
declare(strict_types=1);
define('ABSPATH', __DIR__ . '/');
/** Ignore lifecycle callbacks during schema-only inspection. */
function add_action(string $hook, callable $callback): void {}
require dirname(__DIR__) . '/src/Runtime.php';
require dirname(__DIR__) . '/src/Adapter.php';
require dirname(__DIR__) . '/abilities/register.php';
/** Normalize an empty JSON Schema properties map to a JSON object for external validators. */
function normalizeSchema(array $value): array
{
    foreach ($value as $key => $entry) {
        if ($key === 'properties' && $entry === []) $value[$key] = (object) [];
        elseif (is_array($entry)) $value[$key] = normalizeSchema($entry);
    }
    return $value;
}
$out = [];
foreach (\WeRock\WPvividBackupAbilities\definitions() as $name => $definition) {
    unset($definition['execute_callback'], $definition['summarize']);
    $out[$name] = normalizeSchema($definition);
}
echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
