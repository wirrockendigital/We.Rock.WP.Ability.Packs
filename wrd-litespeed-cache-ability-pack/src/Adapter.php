<?php
/** Native LiteSpeed adapter with bounded settings, permissions and optimistic revisions. */
declare(strict_types=1);
namespace WeRock\LiteSpeedAbilities;
use LiteSpeed\Conf;
use LiteSpeed\Purge;
defined('ABSPATH') || exit;
final class Adapter {
    /** Return sanitized errors; native exceptions may contain private server information. */
    public static function error(string $code, string $message): \WP_Error {
        return new \WP_Error('wrd_litespeed_' . $code, $message);
    }
    /** Explicitly enumerate reviewed, credential-free settings and their accepted types. */
    public static function settings_schema(): array {
        $properties = [];
        foreach (['cache','cache-priv','cache-commenter','cache-rest','cache-page_login','cache-mobile','cache-browser','guest','guest_optm','optm-css_min','optm-css_comb','optm-js_min','optm-js_comb','optm-html_min','optm-qs_rm','optm-emoji_rm','optm-ggfonts_rm','media-lazy','media-iframe_lazy','media-add_missing_sizes','purge-upgrade'] as $key) {
            $properties[$key] = ['type'=>'boolean'];
        }
        foreach (['cache-ttl_pub','cache-ttl_frontpage','cache-ttl_feed','cache-ttl_rest','cache-ttl_browser'] as $key) {
            $properties[$key] = ['type'=>'integer','minimum'=>30,'maximum'=>31557600];
        }
        $properties['cache-ttl_priv'] = ['type'=>'integer','minimum'=>60,'maximum'=>3600];
        $properties['optm-js_defer'] = ['type'=>'integer','enum'=>[0,1,2],'description'=>'0: off; 1: deferred; 2: delayed until interaction.'];
        foreach (['cache-exc','cache-exc_qs','optm-css_exc','optm-js_exc','optm-js_defer_exc','optm-exc','media-lazy_exc','media-lazy_cls_exc','media-lazy_uri_exc'] as $key) {
            $properties[$key] = ['type'=>'array','maxItems'=>100,'uniqueItems'=>true,'items'=>['type'=>'string','minLength'=>1,'maxLength'=>512,'pattern'=>'^[^\\x00-\\x1F\\x7F<>]+$']];
        }
        return ['type'=>'object','properties'=>$properties,'additionalProperties'=>false,'minProperties'=>1];
    }
    /** Inspect public native methods without treating a version difference as incompatibility. */
    public static function available(string $op): bool {
        if (!defined('LSCWP_V')) return false;
        $methods = match ($op) {
            'get-settings' => [[Conf::class,'cls'],[Conf::class,'conf']],
            'update-settings' => [[Conf::class,'cls'],[Conf::class,'conf'],[Conf::class,'get_option'],[Conf::class,'update_confs']],
            'purge-cache' => [[Purge::class,'cls'],[Purge::class,'purge_url'],[Purge::class,'purge_post'],[Purge::class,'purge_all_lscache']],
            default => [],
        };
        foreach ($methods as [$class,$method]) {
            if (!method_exists($class,$method) || !(new \ReflectionMethod($class,$method))->isPublic()) return false;
        }
        return true;
    }
    /** Read only allowlisted values; revision includes all persisted config without exposing it. */
    private static function settings(): array {
        $conf = Conf::cls(); $values = []; $original = [];
        foreach (self::settings_schema()['properties'] as $key=>$schema) {
            $value = $conf->conf($key);
            if ($value === null) continue;
            $values[$key] = $value;
            $original[$key] = $conf->conf($key, true);
        }
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare("SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_name", $wpdb->esc_like('litespeed.conf.') . '%'), ARRAY_A);
        if ($wpdb->last_error || !is_array($rows)) throw new \RuntimeException('Configuration read failed');
        return ['settings'=>$values,'revision'=>hash_hmac('sha256',wp_json_encode([get_current_blog_id(),home_url('/'),$values,$original,$rows]),wp_salt('auth'))];
    }
    /** WordPress stores scalar options as strings across requests; normalize by reviewed type. */
    private static function persisted_value(string $key): mixed {
        $type = self::settings_schema()['properties'][$key]['type'];
        $value = Conf::get_option($key, $type === 'array' ? [] : null);
        if ($value === null) return null;
        return match ($type) {
            'boolean' => (bool)$value,
            'integer' => (int)$value,
            default => $value,
        };
    }
    /** Validate schema even when the adapter is called directly outside the registered wrapper. */
    public static function run(string $op, array $args): array|\WP_Error {
        if (!current_user_can('manage_options')) return self::error('permission','manage_options is required.');
        $schemas = input_schemas();
        if (!isset($schemas[$op]) || is_wp_error(rest_validate_value_from_schema($args,$schemas[$op]))) return self::error('input','Input does not match the operation schema.');
        if ($op === 'get-status') {
            $operations = [];
            foreach (['get-settings','update-settings','purge-cache'] as $name) $operations[$name] = !is_multisite() && self::available($name);
            return ['source_version'=>defined('LSCWP_V')?LSCWP_V:null,'reviewed_source_version'=>'7.9.2','multisite'=>is_multisite(),'operations'=>$operations,'cache_enabled_for_request'=>defined('LITESPEED_ON') && (bool)LITESPEED_ON,'cache_allowed_for_request'=>defined('LITESPEED_ALLOWED') && (bool)LITESPEED_ALLOWED,'cache_signal_note'=>'Request-local flags, not a frontend cache hit test; CLI and HTTP contexts can differ.'];
        }
        if ($op === 'get-settings-schema') return ['schema'=>self::settings_schema()];
        if (is_multisite()) return self::error('scope','Multisite operations have not been validated.');
        if (!self::available($op)) return self::error('unavailable','Required native methods are unavailable.');
        if ($op === 'get-settings') return self::settings();
        if (trim($args['reason']) === '') return self::error('reason','A meaningful reason is required.');
        if ($op === 'purge-cache') return self::purge($args);
        // Serialize pack writers and always recheck persistent state under the advisory lock.
        global $wpdb;
        $lock = 'wrd_lsc_' . substr(hash('sha256',DB_NAME . ':' . $wpdb->prefix),0,40);
        if ((string)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)',$lock)) !== '1') return self::error('busy','Another settings operation is running.');
        try {
            $before = self::settings();
            if (!hash_equals($before['revision'],$args['expected_revision'])) return self::error('revision','Settings changed. Read fresh settings and preview again.');
            $delta = [];
            foreach ($args['changes'] as $key=>$value) {
                if (!array_key_exists($key,$before['settings'])) return self::error('unavailable','A requested setting is not available in this plugin.');
                if ($before['settings'][$key] !== $value) $delta[$key] = ['before'=>$before['settings'][$key],'after'=>$value];
            }
            $preview = ['dry_run'=>$args['dry_run']??true,'changes'=>(object)$delta,'revision'=>$before['revision'],'effects'=>['Native saving can invalidate caches, update rewrite files, and synchronize existing CDN integrations.','Optimization flags can affect rendering and scripts; verify the frontend after applying.']];
            if (($args['dry_run']??true) || !$delta) return $preview + ['applied'=>false];
            // Use the native save lifecycle, including file updates and cache invalidation.
            Conf::cls()->update_confs(array_intersect_key($args['changes'],$delta));
            foreach ($delta as $key=>$change) {
                if (self::persisted_value($key) !== $change['after'] || Conf::cls()->conf($key) !== $change['after']) return self::error('verification','Native save did not match the request. Changes may be partial; re-read before retrying.');
            }
            return $preview + ['applied'=>true,'after'=>self::settings()];
        } finally {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));
        }
    }
    /** Validate exact local targets before dispatch; success means invalidation was requested. */
    private static function purge(array $args): array|\WP_Error {
        $scope = $args['scope']; $targets = [];
        if ($scope === 'urls') {
            if (empty($args['urls']) || isset($args['post_ids'])) return self::error('input','Supply urls only for URL scope.');
            $origin = wp_parse_url(home_url('/'));
            foreach ($args['urls'] as $url) {
                $parsed = wp_parse_url($url);
                if (!$parsed || !isset($parsed['scheme'],$parsed['host']) || strtolower($parsed['host']) !== strtolower($origin['host']) || $parsed['scheme'] !== $origin['scheme'] || ($parsed['port']??null) !== ($origin['port']??null) || isset($parsed['user']) || isset($parsed['pass']) || isset($parsed['query']) || isset($parsed['fragment']) || preg_match('/[\x00-\x20\x7f<>*\\\\]/',$url)) return self::error('url','Use absolute same-origin URLs without credentials, queries, fragments or wildcards.');
                $targets[] = $url;
            }
        } elseif ($scope === 'posts') {
            if (empty($args['post_ids']) || isset($args['urls'])) return self::error('input','Supply post_ids only for post scope.');
            foreach ($args['post_ids'] as $id) {
                if (!get_post($id) || !current_user_can('edit_post',$id)) return self::error('permission','Every post must exist and be editable.');
                $targets[] = $id;
            }
        } elseif (isset($args['urls']) || isset($args['post_ids'])) return self::error('input','Site scope does not accept individual targets.');
        $result = ['dry_run'=>$args['dry_run']??true,'scope'=>$scope,'targets'=>$targets,'effects'=>['Invalidates cached pages; it does not delete WordPress content.','Post purges may invalidate related archive pages through native publish rules.','Cache rebuilds can temporarily increase server load.']];
        if ($args['dry_run']??true) return $result + ['requested'=>false];
        if (!($args['acknowledge_cache_rebuild']??false)) return self::error('acknowledgement','acknowledge_cache_rebuild=true is required.');
        if ($scope === 'site') Purge::purge_all_lscache();
        else foreach ($targets as $target) {
            if ($scope === 'posts') Purge::cls()->purge_post($target);
            elseif (Purge::cls()->purge_url($target) === false) return self::error('native','Native URL invalidation rejected a target; earlier targets may have been requested.');
        }
        return $result + ['requested'=>true,'verification'=>'Native invalidation requested. Check subsequent HTTP cache responses independently; this is not proof of completed server-side eviction.'];
    }
}
