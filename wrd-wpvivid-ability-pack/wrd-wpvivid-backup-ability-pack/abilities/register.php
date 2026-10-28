<?php
/** Register wpvivid-backup abilities through the native governed host API. */
declare(strict_types=1);
namespace WeRock\WPvividBackupAbilities;
defined('ABSPATH') || exit;

/** Build a strict object schema for each public operation. */
function objectSchema(array $properties = [], array $required = []): array
{
    return ['type' => 'object', 'properties' => $properties, 'required' => $required, 'additionalProperties' => false];
}

/** Enumerate the complete public surface for registration and coverage validation. */
function definitions(): array
{
    $page = ['limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 25, 'description' => 'Maximum returned records.'],
        'offset' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100000, 'default' => 0, 'description' => 'Records skipped in deterministic order.']];
    $write = ['expected_revision' => ['type' => 'string', 'pattern' => '^[a-f0-9]{64}$', 'description' => 'Revision from a fresh get-settings response.'],
        'reason' => ['type' => 'string', 'minLength' => 3, 'maxLength' => 300, 'description' => 'Operator rationale; never include secrets. Not written to the audit summary.'],
        'dry_run' => ['type' => 'boolean', 'default' => true, 'description' => 'Preview unless explicitly false.'],
        'changes' => Adapter::settingsSchema()['changes_schema']];
    $schemas = ['empty' => objectSchema(), 'page' => objectSchema($page),
        'write' => objectSchema($write, ['expected_revision','reason','changes']),
        'image' => objectSchema(['attachment_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'An existing WordPress image attachment ID.']], ['attachment_id']),
        'backup' => objectSchema([
            'content' => ['type' => 'string', 'enum' => ['files+db','db','files'], 'default' => 'files+db', 'description' => 'Native backup content selection.'],
            'request_id' => ['type' => 'string', 'pattern' => '^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$', 'description' => 'UUID v4 unique to this logical request; reuse on retries.'],
            'dry_run' => $write['dry_run'], 'reason' => $write['reason'],
            'acknowledge_retention' => ['type' => 'boolean', 'default' => false, 'description' => 'Explicit acknowledgment that native retention can delete old unlocked backups. Required true for execution.']
        ], ['request_id','reason'])];
    $specs = [['get-status', 'Inspect target status', 'Read target identity, reviewed version, multisite and cron state. Does not initialize target defaults.', 'status', 'empty'], ['get-settings', 'Read supported settings', 'Read stored supported settings and a revision over the complete native option. Missing fields are listed; no defaults are persisted.', 'settings', 'empty'], ['get-settings-schema', 'Discover writable settings', 'Return exact types and limits accepted by update-settings.', 'settingsSchema', 'empty'], ['update-settings', 'Patch supported settings', 'Preview a minimal settings delta. Explicit dry_run false writes only this site using a revision check and InnoDB row lock. Unknown and credential-bearing settings are rejected. Current site backup runtime settings; no retention changes.', 'updateSettings', 'write'], ['list-cron-events', 'Inspect WPvivid cron events', 'List shared WPvivid cron timestamps without arguments or executing callbacks.', 'cron', 'page'], ['list-backups', 'List backup catalog', 'Read backup IDs, timestamps, result metadata and archive-part counts. Does not verify archives or restore integrity.', 'backups', 'page'], ['list-tasks', 'Inspect backup tasks', 'Read native backup task states and progress; no options, filenames or raw errors are returned.', 'tasks', 'page'], ['list-remote-storage', 'List configured remote storage', 'Read configured remote destination IDs only. Does not authenticate to providers or test connectivity.', 'remotes', 'page'], ['start-local-backup', 'Start a local backup', 'Preview by default. With dry_run false and acknowledge_retention true, prepare and queue a native manual local backup. Native retention may delete old unlocked backups. Requires a UUID v4 request_id reused across retries. A preparing or schedule_failed receipt requires reconciliation, not a new request. Poll list-tasks and list-backups for completion.', 'startBackup', 'backup']];
    $out = [];
    foreach ($specs as [$slug,$label,$description,$method,$schema]) {
        $writeAbility = in_array($method, ['updateSettings','startBackup'], true);
        $out['wpvivid-backup/' . $slug] = [
            'label' => $label, 'description' => $description, 'category' => 'wpvivid-backup',
            'input_schema' => $schemas[$schema],
            'output_schema' => ['type' => 'object', 'required' => ['result'], 'properties' => ['result' => ['type' => 'object']], 'additionalProperties' => false],
            'annotations' => ['readonly' => !$writeAbility, 'destructive' => $writeAbility, 'idempotent' => $method !== 'startBackup'],
            'execute_callback' => static function(array $args) use ($method, $schema, $schemas) {
                // The host validates inputs; repeat validation for direct registered-callback use.
                $validation = rest_validate_value_from_schema($args, $schemas[$schema]);
                if (is_wp_error($validation)) return Adapter::error('input', 'Input does not match the ability schema.');
                if (!in_array($method, ['status','settingsSchema'], true) && ($error = Adapter::guard())) return $error;
                try {
                    $result = Adapter::$method($args);
                    return is_wp_error($result) ? $result : ['result' => $result];
                } catch (\Throwable $exception) {
                    // Native exceptions can contain paths or credentials; never forward their text.
                    return Adapter::error('native_error', 'Native operation failed; inspect current state before retrying.', 503);
                }
            },
            'summarize' => static function(array $args): array {
                // Exclude free text, request identifiers and all option values from the audit summary.
                return ['dry_run' => $args['dry_run'] ?? true, 'fields' => array_keys($args['changes'] ?? [])];
            },
        ];
    }
    return $out;
}

// Register categories before abilities; the host owns authorization, domain checks and auditing.
add_action('wp_abilities_api_categories_init', static function(): void {
    if (function_exists('wp_register_ability_category')) wp_register_ability_category('wpvivid-backup', [
        'label' => 'WPvivid backup', 'description' => 'WPvivid backup diagnostics and scoped administration.']);
});
add_action('wp_abilities_api_init', static function(): void {
    if (!function_exists('agent_connector_for_wp_register_ability')) return;
    foreach (definitions() as $name => $args) agent_connector_for_wp_register_ability($name, $args);
});
