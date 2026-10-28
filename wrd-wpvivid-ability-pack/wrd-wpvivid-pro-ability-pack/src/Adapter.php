<?php
/** Source-specific adapter for WPvivid pro 2.2.53. */
declare(strict_types=1);
namespace WeRock\WPvividProAbilities;
defined('ABSPATH') || exit;

/** Map reviewed native state to a bounded, redacted ability surface. */
final class Adapter extends Runtime
{
    public const TARGET = 'wpvivid-backup-pro/wpvivid-backup-pro.php';
    public const VERSION = '2.2.53';
    public const VERSION_CONSTANT = 'WPVIVID_BACKUP_PRO_VERSION';
    public const OPTION = 'wpvivid_common_setting';
    public const SETTINGS_SCOPE = 'Current site Pro settings; retain_local controls retention after future remote uploads.';
    public const FIELDS = ['retain_local' => ['type' => 'boolean', 'description' => 'Retain a local copy after a future remote backup upload.'], 'hide_admin_update_notice' => ['type' => 'boolean', 'description' => 'Hide the WPvivid Pro update notice.']];

    /** Separate Pro schedule types while omitting embedded storage and database credentials. */
    public static function schedules(array $args): array
    {
        $rows = [];
        foreach (['general' => 'wpvivid_schedule_addon_setting', 'incremental' => 'wpvivid_incremental_schedules'] as $kind => $option) {
            foreach (static::option($option) as $id => $schedule) {
                if (!is_array($schedule)) continue;
                $rows[] = ['id' => static::id($id), 'kind' => $kind] + static::project($schedule, [
                    'status' => ['type' => 'string', 'enum' => ['Active','Inactive']],
                    'backup_files' => ['type' => 'string', 'enum' => ['files+db','db','files']],
                    'next_start' => ['type' => 'integer']]);
            }
        }
        return static::page($rows, $args) + ['incremental_enabled' => (bool) get_option('wpvivid_enable_incremental_schedules', false)];
    }

    /** Expose retention counts without invoking cleanup or changing policy. */
    public static function retention(array $args): array
    {
        $fields = [];
        foreach (['max_backup_count','max_backup_db_count','max_remote_backup_count','max_remote_backup_db_count'] as $key) $fields[$key] = ['type' => 'integer'];
        $fields['retain_local'] = ['type' => 'boolean'];
        return ['values' => (object) static::project(static::option('wpvivid_common_setting'), $fields),
            'cleanup_executed' => false, 'missing_values' => 'Absent fields use native defaults; this pack does not infer them.'];
    }

    /** Report loaded modules by class presence rather than trusting marketing or a license token. */
    public static function modules(array $args): array
    {
        return ['backup_engine_loaded' => class_exists('WPvivid_Backup_2'),
            'pro_option_store_loaded' => class_exists('WPvivid_Pro_Option'),
            'staging_active' => defined('WPVIVID_STAGING_VERSION'),
            'imgoptim_free_active' => defined('WPVIVID_IMGOPTIM_VERSION'),
            'license_validity_verified' => false];
    }

    /** Read the source-native custom-table last-job summary without free-form error messages. */
    public static function lastBackup(array $args): array
    {
        $data = static::customOption('wpvivid_last_msg');
        return ['available' => !empty($data), 'id' => static::id($data['id'] ?? 'unknown'),
            'metadata' => (object) static::project($data, [
                'status' => ['path' => 'status.str', 'type' => 'string', 'enum' => ['ready','running','no_responds','completed','error','cancelled']],
                'start_time' => ['path' => 'status.start_time', 'type' => 'integer'],
                'run_time' => ['path' => 'status.run_time', 'type' => 'integer']])];
    }

}
