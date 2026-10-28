<?php
/** Source-specific adapter for WPvivid staging 2.0.25. */
declare(strict_types=1);
namespace WeRock\WPvividStagingAbilities;
defined('ABSPATH') || exit;

/** Map reviewed native state to a bounded, redacted ability surface. */
final class Adapter extends Runtime
{
    public const TARGET = 'wpvivid-staging/wpvivid-staging.php';
    public const VERSION = '2.0.25';
    public const VERSION_CONSTANT = 'WPVIVID_STAGING_VERSION';
    public const OPTION = 'wpvivid_staging_options';
    public const SETTINGS_SCOPE = 'Only this site and its future staging jobs. Existing child sites are not synchronized.';
    public const FIELDS = ['staging_db_insert_count' => ['type' => 'integer', 'description' => 'Database insert batch size.', 'minimum' => 100, 'maximum' => 50000], 'staging_db_replace_count' => ['type' => 'integer', 'description' => 'Database replacement batch size.', 'minimum' => 100, 'maximum' => 50000], 'staging_file_copy_count' => ['type' => 'integer', 'description' => 'Files copied per batch.', 'minimum' => 10, 'maximum' => 5000], 'staging_max_execution_time' => ['type' => 'integer', 'description' => 'Maximum batch execution time in seconds.', 'minimum' => 30, 'maximum' => 900], 'staging_resume_count' => ['type' => 'integer', 'description' => 'Maximum retries per staging job.', 'minimum' => 0, 'maximum' => 12], 'staging_request_timeout' => ['type' => 'integer', 'description' => 'Delay between requests in milliseconds.', 'minimum' => 500, 'maximum' => 10000]];

    /** List staging catalog metadata without login URLs, database fields or local paths. */
    public static function sites(array $args): array
    {
        $rows = [];
        foreach (static::customOption('staging_site_data') as $id => $site) {
            if (!is_array($site)) continue;
            $rows[] = ['id' => static::id($id)] + static::project($site, [
                'create_time' => ['type' => 'integer'], 'fresh_install' => ['type' => 'boolean'],
                'mu_single' => ['type' => 'boolean'], 'is_create_subdomain' => ['type' => 'boolean'],
                'additional_database' => ['path' => 'db_connect.use_additional_db', 'type' => 'boolean']]);
        }
        return static::page($rows, $args) + ['scope' => 'Native catalog only; reachability and synchronization are not verified.'];
    }

    /** Inspect create, copy and push task records without running their constructors. */
    public static function tasks(array $args): array
    {
        $rows = [];
        foreach (['create' => 'wpvivid_staging_task_ex','copy' => 'wpvivid_staging_copy_task_ex','push' => 'wpvivid_staging_push_task_ex'] as $kind => $option) {
            $task = static::customOption($option);
            if (!$task) continue;
            $jobs = is_array($task['jobs'] ?? null) ? $task['jobs'] : [];
            $rows[] = ['kind' => $kind, 'id' => static::id($task['id'] ?? 'unknown'),
                'jobs' => count($jobs), 'finished_jobs' => count(array_filter($jobs, static fn($job) => is_array($job) && (int) ($job['finished'] ?? 0) === 1))] +
                static::project($task, ['status' => ['path' => 'status.str', 'type' => 'string', 'enum' => ['ready','running','completed','error','timeout','finished','cancelled']],
                    'run_time' => ['path' => 'status.run_time', 'type' => 'integer']]);
        }
        return static::page($rows, $args);
    }

    /** Inspect access-related options without weakening them or changing child sites. */
    public static function access(array $args): array
    {
        return ['stored' => (object) static::project(static::option(self::OPTION), [
            'not_need_login' => ['type' => 'boolean'], 'staging_show_push' => ['type' => 'boolean'],
            'staging_keep_setting' => ['type' => 'boolean'], 'staging_overwrite_permalink' => ['type' => 'boolean']]),
            'interpretation' => 'Stored creation defaults, not a live authentication or search-indexing test of child sites.'];
    }

}
