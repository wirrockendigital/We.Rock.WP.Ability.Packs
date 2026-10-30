# LiteSpeed Cache Ability Pack for WordPress AI Agents

We.Rock.LiteSpeedCache.Ability.Pack connects [LiteSpeed Cache for WordPress](https://wordpress.org/plugins/litespeed-cache/) to AI agents through Agent Connector for WP. It provides native cache diagnostics, selected configuration changes and scoped page-cache invalidation.

## Install

Install and activate LiteSpeed Cache and [Agent Connector for WP](https://github.com/soflyy/agent-connector-for-wp/releases) on WordPress 7.0 or later with PHP 8.1 or later. Make a verified full backup, then upload `wrd-litespeed-cache-ability-pack-0.1.0.zip` from the repository's GitHub releases under **Plugins → Add New → Upload Plugin** and activate it. The pack has its own version and GitHub updater.

## Agent tools

All tools use the `litespeed-cache/` namespace and require `manage_options`, in addition to Agent Connector authentication and policy checks.

| Tool | Usage |
|---|---|
| `get-status` | Read plugin version, request-local cache flags and native operation availability. |
| `get-settings-schema` | Discover the explicit setting allowlist, types and bounds. |
| `get-settings` | Read effective settings and a revision for a subsequent update. |
| `update-settings` | Preview or apply a minimal native settings change. |
| `purge-cache` | Preview or request page-cache invalidation for local URLs, editable posts, or the site. |

The reviewed source version is **7.9.2**. Availability depends on native methods for each operation; a different version alone does not prevent execution. Operations target single-site WordPress installations.

## Change settings

Read `get-settings` and `get-settings-schema` first. Pass the returned revision as `expected_revision`, a meaningful `reason`, and a `changes` object. `dry_run` defaults to `true`. After reviewing the delta, repeat with `dry_run: false` and then read the settings again.

Supported groups include page caching, cache TTLs, cache exclusions, guest mode, CSS/JS/HTML minimization and combination, JavaScript defer/delay, optimization exclusions, image/iframe lazy loading and missing image sizes. The schema is the authoritative list. Values are validated before native calls; omitted settings remain unchanged.

Native saving can invalidate caches, update rewrite files, and synchronize existing CDN integrations. Check frontend rendering, navigation, consent handling and scripts after applying. JavaScript delay, guest optimization and duplicate lazy loading need particular care with page builders. Revisions detect changed configuration; an advisory lock serializes pack writers, while simultaneous native administrator changes remain an optimistic-concurrency limitation. A failure after a native save may leave partial changes: re-read state before deciding whether to retry or restore.

## Invalidate page cache

Pass `scope: "urls"` with up to 50 absolute same-origin `urls`, `scope: "posts"` with up to 50 editable `post_ids`, or `scope: "site"`. Include `reason`; review the default preview. To apply, set `dry_run: false` and `acknowledge_cache_rebuild: true`.

URL targets use exact paths without queries, fragments, credentials or wildcards. Post invalidation follows LiteSpeed's native related-page rules. Site scope uses `Purge::purge_all_lscache`. The response confirms that native invalidation was requested; independently inspect HTTP cache responses to verify eviction and subsequent warming.

## Adapter coverage

The adapter exposes 37 reviewed local settings and page-cache operations. See the [coverage contract](https://github.com/wirrockendigital/We.Rock.WP.Ability.Packs/blob/main/wrd-litespeed-cache-ability-pack/COVERAGE.md) for the reviewed boundaries and validation methods.

Source review: [LiteSpeed API](https://docs.litespeedtech.com/lscache/lscwp/api/), [cache settings](https://docs.litespeedtech.com/lscache/lscwp/cache/), and the installed 7.9.2 implementations of `Conf`, `Root`, `Base`, `API`, and `Purge`.

## License

GPL-3.0-or-later. This is an unofficial integration provided as is; see [LICENSE](LICENSE).
