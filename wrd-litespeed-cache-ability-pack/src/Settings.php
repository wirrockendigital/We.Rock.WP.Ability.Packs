<?php
/** Frozen credential-free setting schemas reviewed against LiteSpeed Cache 7.9.2. */
declare(strict_types=1);
namespace WeRock\LiteSpeedAbilities;
defined('ABSPATH') || exit;
final class Settings {
    /** Return a fixed allowlist; future native options are never exposed automatically. */
    public static function properties(): array {
        return json_decode(<<<'JSON'
{
  "guest": {
    "type": "boolean"
  },
  "guest_optm": {
    "type": "boolean"
  },
  "cache": {
    "type": "boolean"
  },
  "cache-priv": {
    "type": "boolean"
  },
  "cache-commenter": {
    "type": "boolean"
  },
  "cache-rest": {
    "type": "boolean"
  },
  "cache-page_login": {
    "type": "boolean"
  },
  "cache-mobile": {
    "type": "boolean"
  },
  "cache-mobile_rules": {
    "type": "array",
    "maxItems": 100,
    "uniqueItems": true,
    "items": {
      "type": "string",
      "minLength": 1,
      "maxLength": 512,
      "pattern": "^[^\\x00-\\x1F\\x7F<>]+$"
    }
  },
  "cache-browser": {
    "type": "boolean"
  },
  "cache-exc_useragents": {
    "type": "array",
    "maxItems": 100,
    "uniqueItems": true,
    "items": {
      "type": "string",
      "minLength": 1,
      "maxLength": 512,
      "pattern": "^[^\\x00-\\x1F\\x7F<>]+$"
    }
  },
  "cache-exc_cookies": {
    "type": "array",
    "maxItems": 100,
    "uniqueItems": true,
    "items": {
      "type": "string",
      "minLength": 1,
      "maxLength": 512,
      "pattern": "^[^\\x00-\\x1F\\x7F<>]+$"
    }
  },
  "cache-exc_qs": {
    "type": "array",
    "maxItems": 100,
    "uniqueItems": true,
    "items": {
      "type": "string",
      "minLength": 1,
      "maxLength": 512,
      "pattern": "^[^\\x00-\\x1F\\x7F<>]+$"
    }
  },
  "cache-force_uri": {
    "type": "array",
    "maxItems": 100,
    "uniqueItems": true,
    "items": {
      "type": "string",
      "minLength": 1,
      "maxLength": 512,
      "pattern": "^[^\\x00-\\x1F\\x7F<>]+$"
    }
  },
  "cache-force_pub_uri": {
    "type": "array",
    "maxItems": 100,
    "uniqueItems": true,
    "items": {
      "type": "string",
      "minLength": 1,
      "maxLength": 512,
      "pattern": "^[^\\x00-\\x1F\\x7F<>]+$"
    }
  },
  "cache-priv_uri": {
    "type": "array",
    "maxItems": 100,
    "uniqueItems": true,
    "items": {
      "type": "string",
      "minLength": 1,
      "maxLength": 512,
      "pattern": "^[^\\x00-\\x1F\\x7F<>]+$"
    }
  },
  "cache-exc": {
    "type": "array",
    "maxItems": 100,
    "uniqueItems": true,
    "items": {
      "type": "string",
      "minLength": 1,
      "maxLength": 512,
      "pattern": "^[^\\x00-\\x1F\\x7F<>]+$"
    }
  },
  "cache-exc_roles": {
    "type": "array",
    "maxItems": 100,
    "uniqueItems": true,
    "items": {
      "type": "string",
      "minLength": 1,
      "maxLength": 512,
      "pattern": "^[^\\x00-\\x1F\\x7F<>]+$"
    }
  },
  "cache-drop_qs": {
    "type": "array",
    "maxItems": 100,
    "uniqueItems": true,
    "items": {
      "type": "string",
      "minLength": 1,
      "maxLength": 512,
      "pattern": "^[^\\x00-\\x1F\\x7F<>]+$"
    }
  },
  "cache-ttl_pub": {
    "type": "integer",
    "minimum": 0,
    "maximum": 31557600
  },
  "cache-ttl_priv": {
    "type": "integer",
    "minimum": 0,
    "maximum": 31557600
  },
  "cache-ttl_frontpage": {
    "type": "integer",
    "minimum": 0,
    "maximum": 31557600
  },
  "cache-ttl_feed": {
    "type": "integer",
    "minimum": 0,
    "maximum": 31557600
  },
  "cache-ttl_rest": {
    "type": "integer",
    "minimum": 0,
    "maximum": 31557600
  },
  "cache-ttl_browser": {
    "type": "integer",
    "minimum": 0,
    "maximum": 31557600
  },
  "cache-vary_cookies": {
    "type": "array",
    "maxItems": 100,
    "uniqueItems": true,
    "items": {
      "type": "string",
      "minLength": 1,
      "maxLength": 512,
      "pattern": "^[^\\x00-\\x1F\\x7F<>]+$"
    }
  },
  "purge-upgrade": {
    "type": "boolean"
  },
  "purge-stale": {
    "type": "boolean"
  },
  "purge-post_all": {
    "type": "boolean"
  },
  "purge-post_f": {
    "type": "boolean"
  },
  "purge-post_h": {
    "type": "boolean"
  },
  "purge-post_p": {
    "type": "boolean"
  },
  "purge-post_pwrp": {
    "type": "boolean"
  },
  "purge-post_a": {
    "type": "boolean"
  },
  "purge-post_y": {
    "type": "boolean"
  },
  "purge-post_m": {
    "type": "boolean"
  },
  "purge-post_d": {
    "type": "boolean"
  },
  "purge-post_t": {
    "type": "boolean"
  },
  "purge-post_pt": {
    "type": "boolean"
  },
  "purge-timed_urls": {
    "type": "array",
    "maxItems": 100,
    "uniqueItems": true,
    "items": {
      "type": "string",
      "minLength": 1,
      "maxLength": 512,
      "pattern": "^[^\\x00-\\x1F\\x7F<>]+$"
    }
  },
  "purge-hook_all": {
    "type": "array",
    "maxItems": 100,
    "uniqueItems": true,
    "items": {
      "type": "string",
      "minLength": 1,
      "maxLength": 512,
      "pattern": "^[^\\x00-\\x1F\\x7F<>]+$"
    }
  },
  "esi": {
    "type": "boolean"
  },
  "esi-cache_admbar": {
    "type": "boolean"
  },
  "esi-cache_commform": {
    "type": "boolean"
  },
  "esi-nonce": {
    "type": "array",
    "maxItems": 100,
    "uniqueItems": true,
    "items": {
      "type": "string",
      "minLength": 1,
      "maxLength": 512,
      "pattern": "^[^\\x00-\\x1F\\x7F<>]+$"
    }
  },
  "util-instant_click": {
    "type": "boolean"
  },
  "util-no_https_vary": {
    "type": "boolean"
  },
  "debug-disable_all": {
    "type": "boolean"
  },
  "debug": {
    "type": "integer",
    "enum": [
      0,
      1,
      2
    ]
  },
  "debug-level": {
    "type": "boolean"
  },
  "debug-filesize": {
    "type": "integer",
    "minimum": 1,
    "maximum": 3000
  },
  "debug-collapse_qs": {
    "type": "boolean"
  },
  "db_optm-revisions_max": {
    "type": "integer",
    "minimum": 0,
    "maximum": 10000
  },
  "db_optm-revisions_age": {
    "type": "integer",
    "minimum": 0,
    "maximum": 36500
  },
  "optm-css_min": {
    "type": "boolean"
  },
  "optm-css_comb": {
    "type": "boolean"
  },
  "optm-css_comb_ext_inl": {
    "type": "boolean"
  },
  "optm-ucss": {
    "type": "boolean"
  },
  "optm-ucss_inline": {
    "type": "boolean"
  },
  "optm-ucss_whitelist": {
    "type": "array",
    "maxItems": 100,
    "uniqueItems": true,
    "items": {
      "type": "string",
      "minLength": 1,
      "maxLength": 512,
      "pattern": "^[^\\x00-\\x1F\\x7F<>]+$"
    }
  },
  "optm-ucss_file_exc_inline": {
    "type": "array",
    "maxItems": 100,
    "uniqueItems": true,
    "items": {
      "type": "string",
      "minLength": 1,
      "maxLength": 512,
      "pattern": "^[^\\x00-\\x1F\\x7F<>]+$"
    }
  },
  "optm-ucss_exc": {
    "type": "array",
    "maxItems": 100,
    "uniqueItems": true,
    "items": {
      "type": "string",
      "minLength": 1,
      "maxLength": 512,
      "pattern": "^[^\\x00-\\x1F\\x7F<>]+$"
    }
  },
  "optm-css_exc": {
    "type": "array",
    "maxItems": 100,
    "uniqueItems": true,
    "items": {
      "type": "string",
      "minLength": 1,
      "maxLength": 512,
      "pattern": "^[^\\x00-\\x1F\\x7F<>]+$"
    }
  },
  "optm-js_min": {
    "type": "boolean"
  },
  "optm-js_comb": {
    "type": "boolean"
  },
  "optm-js_comb_ext_inl": {
    "type": "boolean"
  },
  "optm-js_delay_inc": {
    "type": "array",
    "maxItems": 100,
    "uniqueItems": true,
    "items": {
      "type": "string",
      "minLength": 1,
      "maxLength": 512,
      "pattern": "^[^\\x00-\\x1F\\x7F<>]+$"
    }
  },
  "optm-js_exc": {
    "type": "array",
    "maxItems": 100,
    "uniqueItems": true,
    "items": {
      "type": "string",
      "minLength": 1,
      "maxLength": 512,
      "pattern": "^[^\\x00-\\x1F\\x7F<>]+$"
    }
  },
  "optm-html_min": {
    "type": "boolean"
  },
  "optm-html_lazy": {
    "type": "array",
    "maxItems": 100,
    "uniqueItems": true,
    "items": {
      "type": "string",
      "minLength": 1,
      "maxLength": 512,
      "pattern": "^[^\\x00-\\x1F\\x7F<>]+$"
    }
  },
  "optm-html_skip_comment": {
    "type": "array",
    "maxItems": 100,
    "uniqueItems": true,
    "items": {
      "type": "string",
      "minLength": 1,
      "maxLength": 512,
      "pattern": "^[^\\x00-\\x1F\\x7F<>]+$"
    }
  },
  "optm-qs_rm": {
    "type": "boolean"
  },
  "optm-ggfonts_rm": {
    "type": "boolean"
  },
  "optm-css_async": {
    "type": "boolean"
  },
  "optm-ccss_per_url": {
    "type": "boolean"
  },
  "optm-ccss_sep_posttype": {
    "type": "array",
    "maxItems": 100,
    "uniqueItems": true,
    "items": {
      "type": "string",
      "minLength": 1,
      "maxLength": 512,
      "pattern": "^[^\\x00-\\x1F\\x7F<>]+$"
    }
  },
  "optm-ccss_sep_uri": {
    "type": "array",
    "maxItems": 100,
    "uniqueItems": true,
    "items": {
      "type": "string",
      "minLength": 1,
      "maxLength": 512,
      "pattern": "^[^\\x00-\\x1F\\x7F<>]+$"
    }
  },
  "optm-ccss_whitelist": {
    "type": "array",
    "maxItems": 100,
    "uniqueItems": true,
    "items": {
      "type": "string",
      "minLength": 1,
      "maxLength": 512,
      "pattern": "^[^\\x00-\\x1F\\x7F<>]+$"
    }
  },
  "optm-css_async_inline": {
    "type": "boolean"
  },
  "optm-css_font_display": {
    "type": "boolean"
  },
  "optm-js_defer": {
    "type": "integer",
    "enum": [
      0,
      1,
      2
    ]
  },
  "optm-emoji_rm": {
    "type": "boolean"
  },
  "optm-noscript_rm": {
    "type": "boolean"
  },
  "optm-ggfonts_async": {
    "type": "boolean"
  },
  "optm-exc_roles": {
    "type": "array",
    "maxItems": 100,
    "uniqueItems": true,
    "items": {
      "type": "string",
      "minLength": 1,
      "maxLength": 512,
      "pattern": "^[^\\x00-\\x1F\\x7F<>]+$"
    }
  },
  "optm-js_defer_exc": {
    "type": "array",
    "maxItems": 100,
    "uniqueItems": true,
    "items": {
      "type": "string",
      "minLength": 1,
      "maxLength": 512,
      "pattern": "^[^\\x00-\\x1F\\x7F<>]+$"
    }
  },
  "optm-gm_js_exc": {
    "type": "array",
    "maxItems": 100,
    "uniqueItems": true,
    "items": {
      "type": "string",
      "minLength": 1,
      "maxLength": 512,
      "pattern": "^[^\\x00-\\x1F\\x7F<>]+$"
    }
  },
  "optm-dns_prefetch": {
    "type": "array",
    "maxItems": 100,
    "uniqueItems": true,
    "items": {
      "type": "string",
      "minLength": 1,
      "maxLength": 512,
      "pattern": "^[^\\x00-\\x1F\\x7F<>]+$"
    }
  },
  "optm-dns_prefetch_ctrl": {
    "type": "boolean"
  },
  "optm-dns_preconnect": {
    "type": "array",
    "maxItems": 100,
    "uniqueItems": true,
    "items": {
      "type": "string",
      "minLength": 1,
      "maxLength": 512,
      "pattern": "^[^\\x00-\\x1F\\x7F<>]+$"
    }
  },
  "optm-exc": {
    "type": "array",
    "maxItems": 100,
    "uniqueItems": true,
    "items": {
      "type": "string",
      "minLength": 1,
      "maxLength": 512,
      "pattern": "^[^\\x00-\\x1F\\x7F<>]+$"
    }
  },
  "optm-guest_only": {
    "type": "boolean"
  },
  "object": {
    "type": "boolean"
  },
  "object-kind": {
    "type": "boolean"
  },
  "object-life": {
    "type": "integer",
    "minimum": 0,
    "maximum": 31557600
  },
  "object-persistent": {
    "type": "boolean"
  },
  "object-admin": {
    "type": "boolean"
  },
  "object-global_groups": {
    "type": "array",
    "maxItems": 100,
    "uniqueItems": true,
    "items": {
      "type": "string",
      "minLength": 1,
      "maxLength": 512,
      "pattern": "^[^\\x00-\\x1F\\x7F<>]+$"
    }
  },
  "object-non_persistent_groups": {
    "type": "array",
    "maxItems": 100,
    "uniqueItems": true,
    "items": {
      "type": "string",
      "minLength": 1,
      "maxLength": 512,
      "pattern": "^[^\\x00-\\x1F\\x7F<>]+$"
    }
  },
  "discuss-avatar_cache": {
    "type": "boolean"
  },
  "discuss-avatar_cron": {
    "type": "boolean"
  },
  "discuss-avatar_cache_ttl": {
    "type": "integer",
    "minimum": 0,
    "maximum": 31557600
  },
  "optm-localize": {
    "type": "boolean"
  },
  "optm-localize_domains": {
    "type": "array",
    "maxItems": 100,
    "uniqueItems": true,
    "items": {
      "type": "string",
      "minLength": 1,
      "maxLength": 512,
      "pattern": "^[^\\x00-\\x1F\\x7F<>]+$"
    }
  },
  "media-lazy": {
    "type": "boolean"
  },
  "media-placeholder_resp": {
    "type": "boolean"
  },
  "media-lqip": {
    "type": "boolean"
  },
  "media-lqip_qual": {
    "type": "integer",
    "minimum": 1,
    "maximum": 20
  },
  "media-lqip_min_w": {
    "type": "integer",
    "minimum": 0,
    "maximum": 10000
  },
  "media-lqip_min_h": {
    "type": "integer",
    "minimum": 0,
    "maximum": 10000
  },
  "media-placeholder_resp_async": {
    "type": "boolean"
  },
  "media-iframe_lazy": {
    "type": "boolean"
  },
  "media-add_missing_sizes": {
    "type": "boolean"
  },
  "media-lazy_exc": {
    "type": "array",
    "maxItems": 100,
    "uniqueItems": true,
    "items": {
      "type": "string",
      "minLength": 1,
      "maxLength": 512,
      "pattern": "^[^\\x00-\\x1F\\x7F<>]+$"
    }
  },
  "media-lazy_cls_exc": {
    "type": "array",
    "maxItems": 100,
    "uniqueItems": true,
    "items": {
      "type": "string",
      "minLength": 1,
      "maxLength": 512,
      "pattern": "^[^\\x00-\\x1F\\x7F<>]+$"
    }
  },
  "media-lazy_parent_cls_exc": {
    "type": "array",
    "maxItems": 100,
    "uniqueItems": true,
    "items": {
      "type": "string",
      "minLength": 1,
      "maxLength": 512,
      "pattern": "^[^\\x00-\\x1F\\x7F<>]+$"
    }
  },
  "media-iframe_lazy_cls_exc": {
    "type": "array",
    "maxItems": 100,
    "uniqueItems": true,
    "items": {
      "type": "string",
      "minLength": 1,
      "maxLength": 512,
      "pattern": "^[^\\x00-\\x1F\\x7F<>]+$"
    }
  },
  "media-iframe_lazy_parent_cls_exc": {
    "type": "array",
    "maxItems": 100,
    "uniqueItems": true,
    "items": {
      "type": "string",
      "minLength": 1,
      "maxLength": 512,
      "pattern": "^[^\\x00-\\x1F\\x7F<>]+$"
    }
  },
  "media-lazy_uri_exc": {
    "type": "array",
    "maxItems": 100,
    "uniqueItems": true,
    "items": {
      "type": "string",
      "minLength": 1,
      "maxLength": 512,
      "pattern": "^[^\\x00-\\x1F\\x7F<>]+$"
    }
  },
  "media-lqip_exc": {
    "type": "array",
    "maxItems": 100,
    "uniqueItems": true,
    "items": {
      "type": "string",
      "minLength": 1,
      "maxLength": 512,
      "pattern": "^[^\\x00-\\x1F\\x7F<>]+$"
    }
  },
  "media-vpi": {
    "type": "boolean"
  },
  "media-vpi_cron": {
    "type": "boolean"
  },
  "media-auto_rescale_ori": {
    "type": "boolean"
  },
  "img_optm-auto": {
    "type": "boolean"
  },
  "img_optm-ori": {
    "type": "boolean"
  },
  "img_optm-rm_bkup": {
    "type": "boolean"
  },
  "img_optm-webp": {
    "type": "integer",
    "enum": [
      0,
      1,
      2
    ]
  },
  "img_optm-lossless": {
    "type": "boolean"
  },
  "img_optm-sizes_skipped": {
    "type": "array",
    "maxItems": 100,
    "uniqueItems": true,
    "items": {
      "type": "string",
      "minLength": 1,
      "maxLength": 512,
      "pattern": "^[^\\x00-\\x1F\\x7F<>]+$"
    }
  },
  "img_optm-exif": {
    "type": "boolean"
  },
  "img_optm-webp_attr": {
    "type": "array",
    "maxItems": 100,
    "uniqueItems": true,
    "items": {
      "type": "string",
      "minLength": 1,
      "maxLength": 512,
      "pattern": "^[^\\x00-\\x1F\\x7F<>]+$"
    }
  },
  "img_optm-webp_replace_srcset": {
    "type": "boolean"
  },
  "img_optm-jpg_quality": {
    "type": "integer",
    "minimum": 1,
    "maximum": 100
  },
  "crawler": {
    "type": "boolean"
  },
  "crawler-crawl_interval": {
    "type": "integer",
    "minimum": 60,
    "maximum": 31557600
  },
  "crawler-load_limit": {
    "type": "integer",
    "minimum": 1,
    "maximum": 100
  },
  "misc-heartbeat_front": {
    "type": "boolean"
  },
  "misc-heartbeat_front_ttl": {
    "type": "integer",
    "minimum": 0,
    "maximum": 31557600
  },
  "misc-heartbeat_back": {
    "type": "boolean"
  },
  "misc-heartbeat_back_ttl": {
    "type": "integer",
    "minimum": 0,
    "maximum": 31557600
  },
  "misc-heartbeat_editor": {
    "type": "boolean"
  },
  "misc-heartbeat_editor_ttl": {
    "type": "integer",
    "minimum": 0,
    "maximum": 31557600
  },
  "cdn": {
    "type": "boolean"
  },
  "cdn-ori": {
    "type": "array",
    "maxItems": 100,
    "uniqueItems": true,
    "items": {
      "type": "string",
      "minLength": 1,
      "maxLength": 512,
      "pattern": "^[^\\x00-\\x1F\\x7F<>]+$"
    }
  },
  "cdn-ori_dir": {
    "type": "array",
    "maxItems": 100,
    "uniqueItems": true,
    "items": {
      "type": "string",
      "minLength": 1,
      "maxLength": 512,
      "pattern": "^[^\\x00-\\x1F\\x7F<>]+$"
    }
  },
  "cdn-exc": {
    "type": "array",
    "maxItems": 100,
    "uniqueItems": true,
    "items": {
      "type": "string",
      "minLength": 1,
      "maxLength": 512,
      "pattern": "^[^\\x00-\\x1F\\x7F<>]+$"
    }
  },
  "cdn-quic": {
    "type": "boolean"
  },
  "cdn-cloudflare": {
    "type": "boolean"
  },
  "cdn-cloudflare_clear": {
    "type": "boolean"
  },
  "cdn-attr": {
    "type": "array",
    "maxItems": 100,
    "uniqueItems": true,
    "items": {
      "type": "string",
      "minLength": 1,
      "maxLength": 512,
      "pattern": "^[^\\x00-\\x1F\\x7F<>]+$"
    }
  },
  "purge-timed_urls_time": {
    "type": "string",
    "pattern": "^$|^([01][0-9]|2[0-3]):[0-5][0-9]$"
  },
  "media-placeholder_resp_color": {
    "type": "string",
    "pattern": "^#[a-fA-F0-9]{6}$"
  },
  "crawler-sitemap": {
    "type": "string",
    "maxLength": 8192,
    "description": "Up to 20 newline-separated HTTPS sitemaps on this WordPress origin, or empty to clear."
  }
}
JSON, true, 512, JSON_THROW_ON_ERROR);
    }
}
