<?php
/** Shared implementation copied into each independent pack under its private namespace. */
declare(strict_types=1);
namespace WeRock\WPvividImgoptimFreeAbilities;
defined('ABSPATH') || exit;

/** Provide bounded reads, explicit projections and revision-protected option deltas. */
abstract class Runtime
{
    /** Return a consistent error without forwarding native logs or database details. */
    public static function error(string $code, string $message, int $status = 400): \WP_Error
    {
        return new \WP_Error('wrd_wpvivid_' . $code, $message, ['status' => $status]);
    }

    /** Identify the active target without initializing any WPvivid configuration. */
    public static function status(array $args = []): array
    {
        $version = defined(static::VERSION_CONSTANT) ? (string) constant(static::VERSION_CONSTANT) : null;
        return ['target_plugin' => static::TARGET, 'version' => $version,
            'source_version' => static::VERSION, 'active' => $version !== null,
            'source_version_matches' => $version === static::VERSION, 'version_policy' => 'informational',
            'multisite' => is_multisite(),
            'writes_supported' => $version !== null && !is_multisite(),
            'cron_disabled' => defined('DISABLE_WP_CRON') && DISABLE_WP_CRON];
    }

    /** Require the active target and supported site topology, never an exact version. */
    public static function guard(): ?\WP_Error
    {
        $status = static::status();
        if (!$status['active']) return static::error('inactive', 'The target plugin is not active.', 409);
        if ($status['multisite']) return static::error('multisite', 'This pack supports single-site WordPress only.', 409);
        return null;
    }

    /** Read only existing WordPress options; WPvivid getters can create defaults. */
    public static function option(string $name): array
    {
        $value = get_option($name, []);
        return is_array($value) ? $value : [];
    }

    /** Read the native custom table without calling its table-creating constructor. */
    public static function customOption(string $name): array
    {
        global $wpdb;
        $table = $wpdb->base_prefix . 'wpvivid_options';
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));
        if ($exists !== $table) return [];
        $value = $wpdb->get_var($wpdb->prepare("SELECT option_value FROM `{$table}` WHERE option_name = %s", $name));
        // Disallow serialized PHP objects even if another plugin wrote malformed data.
        $decoded = is_string($value) && is_serialized($value) ? unserialize($value, ['allowed_classes' => false]) : $value;
        return is_array($decoded) ? $decoded : [];
    }

    /** Read a fixed nested field, never a caller-supplied option or path. */
    public static function at(array $data, string $path): mixed
    {
        $value = $data;
        foreach (explode('.', $path) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) return null;
            $value = $value[$part];
        }
        return $value;
    }

    /** Project typed public values; unknown strings and nested payloads are omitted. */
    public static function project(array $data, array $fields): array
    {
        $out = [];
        foreach ($fields as $name => $schema) {
            $value = static::at($data, $schema['path'] ?? $name);
            if ($value === null) continue;
            if ($schema['type'] === 'boolean' && in_array($value, [true, false, 0, 1, '0', '1'], true)) $out[$name] = (bool) $value;
            elseif ($schema['type'] === 'integer' && (is_int($value) || (is_string($value) && preg_match('/^\d{1,12}$/D', $value)))) $out[$name] = (int) $value;
            elseif ($schema['type'] === 'string' && is_string($value) && isset($schema['enum']) && in_array($value, $schema['enum'], true)) $out[$name] = $value;
        }
        return $out;
    }

    /** Bind revisions to the full native value and site salt without disclosing it. */
    public static function revision(array $value): string
    {
        return hash_hmac('sha256', serialize($value), wp_salt('auth'));
    }

    /** Describe the exact supported delta without mutating default options. */
    public static function settingsSchema(array $args = []): array
    {
        $properties = [];
        foreach (static::FIELDS as $name => $field) {
            unset($field['path']);
            $properties[$name] = $field;
        }
        return ['changes_schema' => ['type' => 'object', 'properties' => $properties,
            'minProperties' => 1, 'additionalProperties' => false], 'scope' => static::SETTINGS_SCOPE];
    }

    /** Report stored values; absent fields remain absent instead of inventing defaults. */
    public static function settings(array $args = []): array
    {
        $data = static::option(static::OPTION);
        return ['values' => (object) static::project($data, static::FIELDS),
            'revision' => static::revision($data), 'scope' => static::SETTINGS_SCOPE,
            'missing_fields' => array_values(array_diff(array_keys(static::FIELDS), array_keys(static::project($data, static::FIELDS))))];
    }

    /** Validate deltas again at the adapter boundary, including direct callback use. */
    public static function validateChanges(array $args): array|\WP_Error
    {
        if (!isset($args['expected_revision']) || !is_string($args['expected_revision']) || !preg_match('/^[a-f0-9]{64}$/D', $args['expected_revision'])) return static::error('revision_required', 'Read get-settings and supply its revision.');
        if (isset($args['dry_run']) && !is_bool($args['dry_run'])) return static::error('dry_run', 'dry_run must be a boolean.');
        if (!isset($args['reason']) || !is_string($args['reason']) || strlen(trim($args['reason'])) < 3 || strlen($args['reason']) > 300) return static::error('reason', 'A reason of 3 to 300 bytes is required.');
        $changes = $args['changes'] ?? null;
        if (!is_array($changes) || !$changes) return static::error('changes', 'Provide at least one supported setting.');
        foreach ($changes as $key => $value) {
            $field = static::FIELDS[$key] ?? null;
            if (!$field) return static::error('field', 'An unsupported setting was supplied.');
            if ($field['type'] === 'boolean' && !is_bool($value)) return static::error('type', 'Boolean settings require JSON booleans.');
            if ($field['type'] === 'integer' && (!is_int($value) || $value < $field['minimum'] || $value > $field['maximum'])) return static::error('range', 'Integer setting outside the supported range.');
            if ($field['type'] === 'string' && (!is_string($value) || !in_array($value, $field['enum'], true))) return static::error('enum', 'Unsupported setting value.');
        }
        return $changes;
    }

    /** Apply a minimal nested delta while retaining all unexposed native fields. */
    public static function merge(array $current, array $changes): array
    {
        foreach ($changes as $key => $value) {
            $parts = explode('.', static::FIELDS[$key]['path'] ?? $key);
            $cursor =& $current;
            foreach ($parts as $part) {
                if (!isset($cursor[$part]) || !is_array($cursor[$part])) $cursor[$part] = [];
                $cursor =& $cursor[$part];
            }
            $cursor = $value;
            unset($cursor);
        }
        return $current;
    }

    /** Invalidate both individual and autoload option caches after commit or rollback. */
    public static function clearOptionCache(): void
    {
        wp_cache_delete(static::OPTION, 'options');
        wp_cache_delete('alloptions', 'options');
        wp_cache_delete('notoptions', 'options');
    }

    /** Serialize concurrent option writers with a native row lock and preserve unknown keys. */
    public static function updateSettings(array $args): array|\WP_Error
    {
        if ($error = static::guard()) return $error;
        $changes = static::validateChanges($args);
        if (is_wp_error($changes)) return $changes;
        $current = static::option(static::OPTION);
        if (!hash_equals(static::revision($current), $args['expected_revision'])) return static::error('conflict', 'Settings changed; read a fresh revision.', 409);
        $next = static::merge($current, $changes);
        $preview = ['dry_run' => $args['dry_run'] ?? true, 'changed' => $next !== $current,
            'before' => (object) static::project($current, static::FIELDS),
            'after' => (object) static::project($next, static::FIELDS), 'scope' => static::SETTINGS_SCOPE];
        if ($preview['dry_run']) return $preview + ['revision' => static::revision($current)];
        global $wpdb;
        $engine = $wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $wpdb->options));
        if (strtoupper((string) $engine) !== 'INNODB') return static::error('storage_engine', 'Transactional InnoDB options are required.', 409);
        if ($wpdb->query('START TRANSACTION') === false) return static::error('transaction', 'Cannot start settings transaction.', 503);
        $committed = false;
        try {
            $raw = $wpdb->get_var($wpdb->prepare("SELECT option_value FROM `{$wpdb->options}` WHERE option_name=%s FOR UPDATE", static::OPTION));
            if ($raw === null) return static::error('uninitialized', 'Save the target settings once in WPvivid before editing them.', 409);
            $locked = is_serialized($raw) ? unserialize($raw, ['allowed_classes' => false]) : $raw;
            if (!is_array($locked) || !hash_equals(static::revision($locked), $args['expected_revision'])) return static::error('conflict', 'Concurrent settings change; read again.', 409);
            $next = static::merge($locked, $changes);
            static::clearOptionCache();
            if ($next !== $locked) {
                // WordPress is the native persistence API used by these settings screens.
                if (!update_option(static::OPTION, $next)) return static::error('write_failed', 'The settings update failed.', 503);
                $saved = $wpdb->get_var($wpdb->prepare("SELECT option_value FROM `{$wpdb->options}` WHERE option_name=%s", static::OPTION));
                if ($saved !== maybe_serialize($next)) return static::error('verification', 'The stored settings differ from the requested delta.', 409);
            }
            if ($wpdb->query('COMMIT') === false) return static::error('commit_unknown', 'Commit outcome unknown; reread before taking further action.', 503);
            $committed = true;
            return $preview + ['revision' => static::revision($next)];
        } finally {
            if (!$committed) $wpdb->query('ROLLBACK');
            static::clearOptionCache();
        }
    }

    /** Return deterministic pagination over metadata already held by WordPress. */
    public static function page(array $rows, array $args): array
    {
        $offset = max(0, min(100000, (int) ($args['offset'] ?? 0)));
        $limit = max(1, min(100, (int) ($args['limit'] ?? 25)));
        return ['items' => array_values(array_slice($rows, $offset, $limit)), 'total' => count($rows),
            'offset' => $offset, 'has_more' => count($rows) > $offset + $limit];
    }

    /** Redact arbitrary native identifiers while keeping supported job IDs actionable. */
    public static function id(mixed $value): string
    {
        return is_scalar($value) && preg_match('/^[A-Za-z0-9_-]{1,100}$/D', (string) $value) ? (string) $value : 'redacted';
    }

    /** Enumerate only known WPvivid cron metadata, never callback arguments. */
    public static function cron(array $args = []): array
    {
        $rows = [];
        foreach (_get_cron_array() ?: [] as $timestamp => $hooks) {
            foreach ($hooks as $hook => $events) {
                if (!str_starts_with($hook, 'wpvivid')) continue;
                foreach ($events as $event) $rows[] = ['hook' => static::id($hook), 'timestamp' => (int) $timestamp,
                    'recurrence' => static::id($event['schedule'] ?: 'single'), 'interval' => (int) ($event['interval'] ?? 0)];
            }
        }
        return static::page($rows, $args) + ['scope' => 'All WPvivid hooks on this site; shared engines may serve several modules.'];
    }
}
