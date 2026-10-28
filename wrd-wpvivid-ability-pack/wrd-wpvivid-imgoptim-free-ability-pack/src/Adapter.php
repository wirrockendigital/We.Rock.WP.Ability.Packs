<?php
/** Source-specific adapter for WPvivid imgoptim-free 0.9.25. */
declare(strict_types=1);
namespace WeRock\WPvividImgoptimFreeAbilities;
defined('ABSPATH') || exit;

/** Map reviewed native state to a bounded, redacted ability surface. */
final class Adapter extends Runtime
{
    public const TARGET = 'wpvivid-imgoptim/wpvivid-imgoptim.php';
    public const VERSION = '0.9.25';
    public const VERSION_CONSTANT = 'WPVIVID_IMGOPTIM_VERSION';
    public const OPTION = 'wpvivid_optimization_options';
    public const SETTINGS_SCOPE = 'Current site optimization settings for future work; saving does not optimize existing images.';
    public const FIELDS = ['quality' => ['type' => 'string', 'description' => 'Compression mode used for future optimization.', 'enum' => ['lossless', 'lossy']], 'keep_exif' => ['type' => 'boolean', 'description' => 'Keep EXIF metadata during future image optimization.'], 'backup' => ['type' => 'boolean', 'description' => 'Keep original image backups during future optimization.'], 'only_resize' => ['type' => 'boolean', 'description' => 'Resize images without compression during future operations.']];

    /** Read optimization progress without sending images or contacting the remote API. */
    public static function task(array $args): array
    {
        $task = static::option('wpvivid_image_opt_task');
        $images = is_array($task['images'] ?? null) ? $task['images'] : [];
        return ['available' => !empty($task), 'images' => count($images),
            'finished_images' => count(array_filter($images, static fn($image) => is_array($image) && (int) ($image['finished'] ?? 0) === 1)),
            'metadata' => (object) static::project($task, [
                'status' => ['type' => 'string', 'enum' => ['running','finished','completed','error','timeout']],
                'last_update_time' => ['type' => 'integer'], 'retry' => ['type' => 'integer']])];
    }

    /** Show lazyload booleans without disclosing custom selectors or injected strings. */
    public static function lazyload(array $args): array
    {
        $fields = [];
        foreach (['enable','content','thumbnails','noscript'] as $key) $fields[$key] = ['type' => 'boolean'];
        foreach (['jpg|jpeg|jpe','png','gif','svg'] as $key) $fields[$key] = ['path' => 'extensions.' . $key, 'type' => 'boolean'];
        return ['stored' => (object) static::project(static::option(self::OPTION)['lazyload'] ?? [], $fields)];
    }

    /** Report native optimization metadata for one known media attachment. */
    public static function image(array $args): array|\WP_Error
    {
        $id = $args['attachment_id'] ?? 0;
        if (!is_int($id) || $id < 1 || get_post_type($id) !== 'attachment' || !wp_attachment_is_image($id)) return static::error('attachment', 'A valid image attachment ID is required.', 404);
        $meta = get_post_meta($id, 'wpvivid_image_optimize_meta', true);
        $backup = get_post_meta($id, 'wpvivid_backup_image_meta', true);
        $sizes = is_array($meta) && is_array($meta['size'] ?? null) ? $meta['size'] : [];
        $rows = [];
        foreach ($sizes as $name => $size) {
            if (!is_array($size)) continue;
            $rows[] = ['size' => static::id($name)] + static::project($size, ['opt_status' => ['type' => 'integer']]);
        }
        return ['attachment_id' => $id, 'has_optimization_metadata' => !empty($meta),
            'has_backup_metadata' => !empty($backup), 'sizes' => $rows,
            'verification' => 'Stored metadata; original backup files and rendered image quality are not verified.'];
    }

    /** Expose configured image dimensions without URLs or filesystem access. */
    public static function sizes(array $args): array
    {
        $rows = [];
        foreach (wp_get_registered_image_subsizes() as $name => $size) $rows[] = ['name' => static::id($name),
            'width' => (int) $size['width'], 'height' => (int) $size['height'], 'crop' => (bool) $size['crop']];
        return static::page($rows, $args);
    }

}
