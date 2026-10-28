<?php
/** Native backup adapter reviewed against WPvivid backup 0.9.136. */
declare(strict_types=1);
namespace WeRock\WPvividBackupAbilities;
defined('ABSPATH') || exit;

/** Map reviewed native state to a bounded, redacted ability surface. */
final class Adapter extends Runtime
{
    public const TARGET = 'wpvivid-backuprestore/wpvivid-backuprestore.php';
    public const VERSION = '0.9.136';
    public const VERSION_CONSTANT = 'WPVIVID_PLUGIN_VERSION';
    public const OPTION = 'wpvivid_common_setting';
    public const SETTINGS_SCOPE = 'Current site backup runtime settings; no retention changes.';
    public const FIELDS = ['max_execution_time' => ['type' => 'integer', 'description' => 'Maximum execution time in seconds for future backup tasks.', 'minimum' => 30, 'maximum' => 900], 'max_resume_count' => ['type' => 'integer', 'description' => 'Maximum retries for future backup tasks.', 'minimum' => 0, 'maximum' => 12], 'compress_file_count' => ['type' => 'integer', 'description' => 'Files per compression batch for future tasks.', 'minimum' => 10, 'maximum' => 5000], 'max_file_size' => ['type' => 'integer', 'description' => 'Archive split size in MiB for future backups.', 'minimum' => 10, 'maximum' => 1024], 'max_sql_file_size' => ['type' => 'integer', 'description' => 'SQL split size in MiB for future backups.', 'minimum' => 10, 'maximum' => 1024], 'show_admin_bar' => ['type' => 'boolean', 'description' => 'Show the WPvivid shortcut in the administration toolbar.']];

    /** List backup catalog records without revealing filenames, paths or remote credentials. */
    public static function backups(array $args): array
    {
        $rows = [];
        foreach (static::option('wpvivid_backup_list') as $id => $backup) {
            if (!is_array($backup)) continue;
            $rows[] = ['id' => static::id($id)] + static::project($backup, [
                'create_time' => ['type' => 'integer'], 'lock' => ['type' => 'boolean'],
                'type' => ['type' => 'string', 'enum' => ['Manual', 'Cron', 'Auto', 'Rollback']],
                'result' => ['path' => 'backup.result', 'type' => 'string', 'enum' => ['success', 'failed']]]) +
                ['archive_parts' => count(is_array($backup['backup']['files'] ?? null) ? $backup['backup']['files'] : []),
                 'remote_locations' => count(is_array($backup['remote'] ?? null) ? $backup['remote'] : [])];
        }
        usort($rows, static fn($a, $b) => ($b['create_time'] ?? 0) <=> ($a['create_time'] ?? 0));
        return static::page($rows, $args) + ['verification' => 'Catalog only; archive readability and restore integrity are not verified.'];
    }

    /** Inspect task progress without returning options, file lists or error messages. */
    public static function tasks(array $args): array
    {
        $rows = [];
        foreach (static::option('wpvivid_task_list') as $id => $task) {
            if (!is_array($task)) continue;
            $rows[] = ['id' => static::id($id)] + static::project($task, [
                'status' => ['path' => 'status.str', 'type' => 'string', 'enum' => ['ready','running','no_responds','completed','error','cancel','cancelled','timeout']],
                'started_at' => ['path' => 'status.task_start_time', 'type' => 'integer'],
                'ended_at' => ['path' => 'status.task_end_time', 'type' => 'integer'],
                'retries' => ['path' => 'status.resume_count', 'type' => 'integer'],
                'progress' => ['path' => 'data.backup.progress', 'type' => 'integer']]);
        }
        return static::page($rows, $args);
    }

    /** Return counts of configured destinations without calling storage providers. */
    public static function remotes(array $args): array
    {
        $rows = [];
        foreach (static::option('wpvivid_upload_setting') as $id => $remote) {
            if (!is_array($remote)) continue;
            $rows[] = ['id' => static::id($id), 'configured' => true];
        }
        return static::page($rows, $args) + ['connectivity_tested' => false];
    }

    /** Inspect the actual native entry points without starting or preparing a backup. */
    public static function backupAvailability(): array
    {
        global $wpvivid_plugin;
        $engine = $wpvivid_plugin->backup2 ?? null;
        $missing = [];
        foreach (['pre_new_backup', 'backup_schedule'] as $method) {
            if (!is_object($engine) || !is_callable([$engine, $method])) $missing[] = $method;
        }
        if (!is_object($engine) || has_action('wpvivid_backup_2_schedule_event', [$engine, 'backup_schedule']) === false) {
            $missing[] = 'wpvivid_backup_2_schedule_event';
        }
        return ['available' => !$missing, 'missing_requirements' => $missing];
    }

    /** Expose backup readiness separately from informational source-version metadata. */
    public static function status(array $args = []): array
    {
        $status = parent::status($args);
        $backup = static::backupAvailability();
        return $status + ['backup_available' => $status['active'] && !$status['multisite'] && $backup['available'],
            'backup_missing_requirements' => $backup['missing_requirements']];
    }

    /** Queue the source-native local backup engine; a queued job is not a completed backup. */
    public static function startBackup(array $args): array|\WP_Error
    {
        if ($error = static::guard()) return $error;
        $content = $args['content'] ?? 'files+db';
        if (!in_array($content, ['files+db','db','files'], true)) return static::error('content', 'Unsupported backup content.');
        if (isset($args['dry_run']) && !is_bool($args['dry_run'])) return static::error('dry_run', 'dry_run must be a boolean.');
        $request = $args['request_id'] ?? '';
        if (!is_string($request) || !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D', $request)) return static::error('request_id', 'Supply a UUID v4 and reuse it for this logical request.');
        if (!isset($args['reason']) || !is_string($args['reason']) || strlen(trim($args['reason'])) < 3 || strlen($args['reason']) > 300) return static::error('reason', 'A reason of 3 to 300 bytes is required.');
        // Validate the same entry points for preview and execution, irrespective of plugin versions.
        if (!static::backupAvailability()['available']) return static::error('engine', 'Native backup preparation, worker or registered cron callback unavailable.', 409);
        $preview = ['dry_run' => $args['dry_run'] ?? true, 'content' => $content, 'destination' => 'local',
            'native_retention_may_delete_old_backups' => true, 'completion' => 'Poll list-tasks and list-backups; queue acceptance does not prove backup completion.'];
        if ($preview['dry_run']) return $preview;
        if (($args['acknowledge_retention'] ?? false) !== true) return static::error('retention', 'Explicitly acknowledge native backup retention before starting.');
        global $wpvivid_plugin;
        $engine = $wpvivid_plugin->backup2 ?? null;
        $receiptKey = 'wrd_wpvivid_backup_request_' . $request;
        $lockKey = 'wrd_wpvivid_backup_start_lock';
        if (!add_option($lockKey, ['request_id' => $request, 'created_at' => time()], '', false)) return static::error('busy', 'A backup start is in progress or needs reconciliation. Do not force-retry.', 409);
        try {
            $receipt = get_option($receiptKey, false);
            if (is_array($receipt)) {
                if (($receipt['content'] ?? null) !== $content) return static::error('request_conflict', 'This request ID was used for different content.', 409);
                return $preview + ['replayed' => true, 'receipt' => $receipt];
            }
            foreach (static::option('wpvivid_task_list') as $task) {
                if (!is_array($task) || !in_array($task['status']['str'] ?? '', ['completed','error','cancelled','cancel'], true)) return static::error('busy', 'An unfinished native task exists; inspect it before starting another.', 409);
            }
            // Persist intent before any native side effects to prevent unsafe automatic retries.
            $receipt = ['content' => $content, 'state' => 'preparing', 'created_at' => time()];
            if (!add_option($receiptKey, $receipt, '', false)) return static::error('receipt', 'Cannot record backup intent.', 503);
            // Prevent native diagnostic output from escaping the structured redacted result.
            ob_start();
            try {
                // Pro's native preparation filter also reads the action of this local job.
                $native = $engine->pre_new_backup(['type' => 'Manual', 'action' => 'backup', 'backup_files' => $content, 'local' => 1, 'remote' => 0, 'lock' => 1]);
            } finally {
                ob_end_clean();
            }
            if (($native['result'] ?? '') !== 'success' || empty($native['task_id'])) return static::error('prepare_unknown', 'Native preparation did not succeed. Inspect tasks; reuse the request ID.', 503);
            $taskId = static::id($native['task_id']);
            $receipt['task_id'] = $taskId;
            $receipt['state'] = 'prepared';
            if (!update_option($receiptKey, $receipt, false)) return static::error('receipt_unknown', 'Task prepared but receipt update failed; inspect native tasks.', 503);
            $scheduled = wp_schedule_single_event(time() + 5, 'wpvivid_backup_2_schedule_event', [$taskId], true);
            $receipt['state'] = is_wp_error($scheduled) || !$scheduled ? 'schedule_failed' : 'queued';
            if (!update_option($receiptKey, $receipt, false)) return static::error('receipt_unknown', 'Queue outcome requires task and cron inspection; do not create another request.', 503);
            return $preview + ['replayed' => false, 'receipt' => $receipt];
        } finally {
            // A hard process crash intentionally leaves the lock for explicit reconciliation.
            delete_option($lockKey);
        }
    }

}
