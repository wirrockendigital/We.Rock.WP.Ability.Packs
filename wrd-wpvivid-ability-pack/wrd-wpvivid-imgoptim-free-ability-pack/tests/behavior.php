<?php
/** Exercise real adapter logic against isolated WordPress and native-engine test doubles. */
declare(strict_types=1);
error_reporting(E_ALL);
set_error_handler(static function(int $level, string $message): never { throw new RuntimeException($message); });
define('ABSPATH', __DIR__ . '/');
define('WPVIVID_PLUGIN_VERSION', getenv('WPVIVID_TEST_FUTURE_VERSIONS') ? '99.0.0' : '0.9.136');
define('WPVIVID_BACKUP_PRO_VERSION', getenv('WPVIVID_TEST_FUTURE_VERSIONS') ? '99.0.0' : '2.2.53');
define('WPVIVID_STAGING_VERSION', getenv('WPVIVID_TEST_FUTURE_VERSIONS') ? '99.0.0' : '2.0.25');
define('WPVIVID_IMGOPTIM_VERSION', getenv('WPVIVID_TEST_FUTURE_VERSIONS') ? '99.0.0' : '0.9.25');
$GLOBALS['options'] = [];
$GLOBALS['writes'] = 0;
$GLOBALS['multisite'] = false;
$GLOBALS['scheduled'] = [];
$GLOBALS['schedule_fail'] = false;

/** Represent errors without depending on a WordPress installation. */
class WP_Error
{
    public function __construct(public string $code, public string $message, public array $data = []) {}
    public function get_error_code(): string { return $this->code; }
}
/** Test-only WordPress primitives retain state so unintended mutations are observable. */
function is_wp_error(mixed $value): bool { return $value instanceof WP_Error; }
function is_multisite(): bool { return $GLOBALS['multisite']; }
function get_option(string $key, mixed $default = false): mixed { return $GLOBALS['options'][$key] ?? $default; }
function update_option(string $key, mixed $value, mixed $autoload = null): bool
{
    if (($GLOBALS['options'][$key] ?? null) === $value) return false;
    $GLOBALS['options'][$key] = $value;
    $GLOBALS['writes']++;
    return true;
}
function add_option(string $key, mixed $value, string $deprecated = '', mixed $autoload = null): bool
{
    if (array_key_exists($key, $GLOBALS['options'])) return false;
    return update_option($key, $value);
}
function delete_option(string $key): bool { unset($GLOBALS['options'][$key]); $GLOBALS['writes']++; return true; }
function wp_salt(string $scheme): string { return 'isolated-test-fixture-not-a-credential'; }
function wp_cache_delete(string $key, string $group): void {}
function maybe_serialize(mixed $value): string { return is_array($value) || is_object($value) ? serialize($value) : (string) $value; }
function is_serialized(mixed $value): bool { return is_string($value) && preg_match('/^[aObis]:/', $value) === 1; }
function add_action(string $hook, mixed $callback): void { $GLOBALS['hooks'][$hook][] = $callback; }
function has_action(string $hook, mixed $callback = false): int|false { return ($GLOBALS['callback_available'] ?? true) ? 0 : false; }
function _get_cron_array(): array { return [123 => ['wpvivid_test_event' => [['schedule' => false, 'args' => ['private-payload']]], 'unrelated_hook' => [[]]]]; }
function get_post_type(int $id): string { return $id === 123 ? 'attachment' : 'post'; }
function wp_attachment_is_image(int $id): bool { return $id === 123; }
function get_post_meta(int $id, string $key, bool $single): mixed { return $GLOBALS['meta'][$key] ?? []; }
function wp_get_registered_image_subsizes(): array { return ['thumbnail' => ['width' => 150, 'height' => 150, 'crop' => true]]; }
function wp_schedule_single_event(int $time, string $hook, array $args, bool $wpError): bool|WP_Error
{
    if ($GLOBALS['schedule_fail']) return new WP_Error('fixture', 'schedule failed');
    $GLOBALS['scheduled'][] = [$hook,$args];
    return true;
}

/** Model row locks, storage-engine checks and rollback to test failure paths. */
class TestDatabase
{
    public string $options = 'wp_options';
    public string $base_prefix = 'wp_';
    public string $engine = 'InnoDB';
    public array $custom = [];
    public bool $conflict = false;
    public bool $verifyFail = false;
    public bool $commitFail = false;
    private array $snapshot = [];
    public function esc_like(string $s): string { return $s; }
    public function prepare(string $sql, mixed ...$args): array { return [$sql,$args]; }
    public function get_var(array $query): mixed
    {
        [$sql,$args] = $query;
        if (str_contains($sql, 'SELECT ENGINE')) return $this->engine;
        if (str_contains($sql, 'SHOW TABLES')) return 'wp_wpvivid_options';
        if (str_contains($sql, '`wp_wpvivid_options`')) return isset($this->custom[$args[0]]) ? serialize($this->custom[$args[0]]) : null;
        if ($this->conflict && str_contains($sql, 'FOR UPDATE')) return serialize(['concurrent' => true]);
        if ($this->verifyFail && !str_contains($sql, 'FOR UPDATE')) return serialize(['unexpected' => true]);
        return isset($GLOBALS['options'][$args[0]]) ? maybe_serialize($GLOBALS['options'][$args[0]]) : null;
    }
    public function query(string $sql): int|false
    {
        if ($sql === 'START TRANSACTION') $this->snapshot = $GLOBALS['options'];
        if ($sql === 'ROLLBACK') $GLOBALS['options'] = $this->snapshot;
        if ($sql === 'COMMIT' && $this->commitFail) return false;
        return 1;
    }
}
$GLOBALS['wpdb'] = new TestDatabase();

/** Stub only the external backup engine; the adapter's retry and queue logic is real. */
class WPvivid_Backup_2
{
    /** Model the callable worker registered by the native backup engine. */
    public function backup_schedule(string $taskId): void {}
    public int $calls = 0;
    public bool $fail = false;
    public function pre_new_backup(array $args): array
    {
        $this->calls++;
        if ($this->fail) return ['result' => 'failed'];
        $GLOBALS['options']['wpvivid_task_list'] = ['wpvivid-fixture' => ['status' => ['str' => 'ready']]];
        return ['result' => 'success','task_id' => 'wpvivid-fixture'];
    }
}
$GLOBALS['wpvivid_plugin'] = (object) ['backup2' => new WPvivid_Backup_2()];

/** Load only this independently installable pack. */
require dirname(__DIR__) . '/src/Runtime.php';
require dirname(__DIR__) . '/src/Adapter.php';
require dirname(__DIR__) . '/abilities/register.php';
$adapter = 'WeRock\WPvividImgoptimFreeAbilities\\Adapter';
$definitions = ('WeRock\WPvividImgoptimFreeAbilities\\definitions')();
$assertions = 0;
/** Fail at the first violated behavior rather than relying on disabled PHP assertions. */
function check(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
}

// Future vendor versions must keep all supported read and write paths usable.
check($adapter::guard() === null, 'Plugin version alone blocked operations');
check($adapter::status()['version_policy'] === 'informational', 'Version policy missing');
check($adapter::status()['source_version_matches'] === !((bool) getenv('WPVIVID_TEST_FUTURE_VERSIONS')), 'Source version metadata incorrect');
$key = array_key_first($adapter::FIELDS);
$field = $adapter::FIELDS[$key];
$sample = match ($field['type']) { 'boolean' => true, 'integer' => $field['minimum'], default => $field['enum'][0] };
$original = ['preserve_me' => ['nested' => 'sentinel-private-value'], $key => $sample];
$GLOBALS['options'][$adapter::OPTION] = $original;
$before = $GLOBALS['writes'];
$settings = $adapter::settings();
check($GLOBALS['writes'] === $before, 'A settings read mutated state');
check(!str_contains(json_encode($settings), 'sentinel-private-value'), 'Settings leaked hidden state');
check($settings['revision'] === $adapter::revision($original), 'Revision not based on full option');
$changed = match ($field['type']) { 'boolean' => false, 'integer' => $field['minimum'] + 1, default => $field['enum'][1] };
$args = ['expected_revision' => $settings['revision'], 'reason' => 'Fixture operation', 'changes' => [$key => $changed]];
$result = $adapter::updateSettings($args);
check(!is_wp_error($result) && $result['dry_run'] === true, 'Default did not preview');
check($GLOBALS['writes'] === $before && $GLOBALS['options'][$adapter::OPTION] === $original, 'Preview wrote native data');
check(is_wp_error($adapter::updateSettings($args + ['dry_run' => 'false'])), 'String false accepted');
$invalid = $args; $invalid['expected_revision'] = str_repeat('0',64);
check(is_wp_error($adapter::updateSettings($invalid)), 'Stale revision accepted');
$invalid = $args; $invalid['changes'] = ['encrypt_db_password' => 'fixture'];
check(is_wp_error($adapter::updateSettings($invalid)), 'Credential-bearing field accepted');
$invalid['changes'] = [];
check(is_wp_error($adapter::updateSettings($invalid)), 'Empty delta accepted');
foreach ($adapter::FIELDS as $name => $definition) {
    $invalid = $args; $invalid['changes'] = [$name => []];
    check(is_wp_error($adapter::updateSettings($invalid)), 'Invalid type accepted: ' . $name);
    if ($definition['type'] === 'integer') {
        $invalid['changes'] = [$name => $definition['maximum'] + 1];
        check(is_wp_error($adapter::updateSettings($invalid)), 'Out-of-range integer accepted');
    }
}
$GLOBALS['wpdb']->engine = 'MyISAM';
check(is_wp_error($adapter::updateSettings($args + ['dry_run' => false])), 'Nontransactional storage accepted');
$GLOBALS['wpdb']->engine = 'InnoDB';
$GLOBALS['wpdb']->conflict = true;
check(is_wp_error($adapter::updateSettings($args + ['dry_run' => false])), 'Concurrent update accepted');
check($GLOBALS['options'][$adapter::OPTION] === $original, 'Conflict altered existing state');
$GLOBALS['wpdb']->conflict = false;
$GLOBALS['wpdb']->verifyFail = true;
check(is_wp_error($adapter::updateSettings($args + ['dry_run' => false])), 'Readback mismatch accepted');
check($GLOBALS['options'][$adapter::OPTION] === $original, 'Readback failure was not rolled back');
$GLOBALS['wpdb']->verifyFail = false;
$GLOBALS['wpdb']->commitFail = true;
check(is_wp_error($adapter::updateSettings($args + ['dry_run' => false])), 'Failed commit reported success');
$GLOBALS['wpdb']->commitFail = false;
$result = $adapter::updateSettings($args + ['dry_run' => false]);
check(!is_wp_error($result) && $result['dry_run'] === false, 'Valid delta failed');
check($GLOBALS['options'][$adapter::OPTION]['preserve_me'] === $original['preserve_me'], 'Unknown fields lost');
check($GLOBALS['options'][$adapter::OPTION][$key] === $changed, 'Requested field not persisted');
check(!str_contains(json_encode($result), 'sentinel-private-value'), 'Write result leaked hidden state');
$GLOBALS['multisite'] = true;
check(is_wp_error($adapter::updateSettings($args)), 'Unsupported multisite accepted');
$GLOBALS['multisite'] = false;
$cron = $adapter::cron([]);
check(count($cron['items']) === 1 && !str_contains(json_encode($cron), 'private-payload'), 'Cron arguments leaked');
check($adapter::page(range(1,105), ['limit' => 100])['has_more'], 'Pagination lost remaining records');
foreach ($definitions as $name => $definition) {
    check($definition['input_schema']['additionalProperties'] === false, 'Unknown input properties allowed');
    check(isset($definition['annotations']['readonly']), 'Missing behavior annotation');
    check(!str_contains(json_encode($definition['summarize'](['reason' => 'private-rationale', 'changes' => [$key => 'private-value']])), 'private-'), 'Audit summary leaked values');
}

if (method_exists($adapter, 'startBackup')) {
    $request = ['request_id' => '12345678-1234-4123-8123-123456789abc', 'reason' => 'Fixture backup'];
    check($adapter::startBackup($request)['dry_run'] === true, 'Backup default started work');
    check($GLOBALS['wpvivid_plugin']->backup2->calls === 0, 'Dry run touched native engine');
    check(is_wp_error($adapter::startBackup($request + ['dry_run' => false])), 'Retention acknowledgment omitted');
    $run = $request + ['dry_run' => false,'acknowledge_retention' => true];
    $GLOBALS['options']['wpvivid_task_list'] = ['busy' => ['status' => ['str' => 'running']]];
    check(is_wp_error($adapter::startBackup($run)), 'Running backup overwritten');
    $GLOBALS['options']['wpvivid_task_list'] = [];
    $started = $adapter::startBackup($run);
    check($started['receipt']['state'] === 'queued', 'Backup was not queued');
    check($GLOBALS['scheduled'][0][0] === 'wpvivid_backup_2_schedule_event', 'Wrong native event');
    $again = $adapter::startBackup($run);
    check($again['replayed'] && $GLOBALS['wpvivid_plugin']->backup2->calls === 1, 'Retry created duplicate backup');
    check(is_wp_error($adapter::startBackup($run + ['content' => 'db'])), 'Request-content mismatch accepted');
    $GLOBALS['options']['wpvivid_task_list'] = [];
    $GLOBALS['schedule_fail'] = true;
    $run['request_id'] = '12345678-1234-4123-8123-123456789abd';
    check($adapter::startBackup($run)['receipt']['state'] === 'schedule_failed', 'Queue failure reported acceptance');
    check($adapter::startBackup($run)['replayed'], 'Unknown queue outcome not retained');
    $GLOBALS['options']['wpvivid_task_list'] = [];
    $GLOBALS['wpvivid_plugin']->backup2->fail = true;
    $run['request_id'] = '12345678-1234-4123-8123-123456789abe';
    check(is_wp_error($adapter::startBackup($run)), 'Preparation failure reported success');
    check($adapter::startBackup($run)['receipt']['state'] === 'preparing', 'Preparation retry lost uncertain state');
}
if (method_exists($adapter, 'sites')) {
    $GLOBALS['wpdb']->custom['staging_site_data'] = ['site1' => ['create_time' => 1, 'login_url' => 'private-login', 'db_connect' => ['dbpassword' => 'private-db']]];
    check(!str_contains(json_encode($adapter::sites([])), 'private'), 'Staging credentials leaked');
}
if (method_exists($adapter, 'image')) {
    $GLOBALS['meta']['wpvivid_image_optimize_meta'] = ['size' => ['original' => ['opt_status' => 1,'path' => 'private-path']]];
    check(is_wp_error($adapter::image(['attachment_id' => 999])), 'Non-image attachment accepted');
    check(!str_contains(json_encode($adapter::image(['attachment_id' => 123])), 'private'), 'Image path leaked');
    check($adapter::image(['attachment_id' => 123])['sizes'][0]['opt_status'] === 1, 'Native image status lost');
}
echo 'PASS: ' . $assertions . " behavior assertions\n";
