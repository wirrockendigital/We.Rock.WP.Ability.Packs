# LiteSpeed Cache Ability Pack for WordPress AI Agents

We.Rock.LiteSpeedCache.Ability.Pack connects [LiteSpeed Cache for WordPress](https://wordpress.org/plugins/litespeed-cache/) to AI agents through Agent Connector for WP. It provides 13 tools for cache administration, 153 reviewed settings, image optimization, crawler management, QUIC.cloud service maintenance and targeted database cleanup.

## Install

Install and activate LiteSpeed Cache and [Agent Connector for WP](https://github.com/soflyy/agent-connector-for-wp/releases) on WordPress 7.0 or later with PHP 8.1 or later. Make a verified full backup, then upload `wrd-litespeed-cache-ability-pack-0.1.1.zip` from the repository's GitHub releases under **Plugins → Add New → Upload Plugin** and activate it. Each pack has its own version and GitHub updater.

Database cleanup and batch image switching additionally require an executable native WP-CLI installation and PHP process support. Availability is reported by the tools. Single-site WordPress is supported. Reviewed LiteSpeed version: **7.9.2**; runtime compatibility is checked per operation.

## Discover agent tools

All tools use the `litespeed-cache/` namespace. They require `manage_options` in addition to Agent Connector authentication and policy checks.

| Tool | Use |
|---|---|
| `get-status` | Read plugin version, request-local cache flags and native availability. |
| `get-settings-schema` | Discover supported setting names, exact types and bounds. |
| `get-settings` | Read effective settings and their revision. |
| `update-settings` | Preview or apply a minimal native configuration change. |
| `purge-cache` | Preview or request page-cache invalidation for local URLs, editable posts or the site. |
| `get-maintenance-status` | Discover 33 maintenance actions, their effects and prerequisites. |
| `get-component-status` | Read image, crawler, cloud, cache or optimization-queue status and revision. |
| `run-maintenance` | Preview or request one discovered maintenance action. |
| `list-crawler-map` | Read paginated crawler-map or blocklist IDs and URL paths. |
| `set-crawler-active` | Preview or set one native crawler's active flag. |
| `get-database-status` | Read cleanup candidate counts and their revision. |
| `cleanup-database` | Preview or apply one of ten native database cleanup categories. |
| `get-operation-receipt` | Reconcile a previous maintenance request by its UUID. |

## Change settings

Read `get-settings` and `get-settings-schema`. Pass a minimal `changes` object, the returned `expected_revision` and a meaningful `reason`. `dry_run` defaults to `true`. Review the delta, then apply with `dry_run: false` and read the state again.

The schema covers cache and purge rules, ESI, CSS/JavaScript/HTML optimization, media and image optimization, existing object/CDN integration switches, crawler scheduling, heartbeat and selected debug settings. High-impact changes require `acknowledge_high_impact: true`; inspect their effects before applying, especially automatic original-image backup removal, image rescaling, public cache forcing, logging and automatic jobs.

Native saves can invalidate caches, update rewrite files and synchronize existing CDN integrations. Verify layout, navigation, consent handling and scripts after applying. Guest optimization, delayed JavaScript and duplicate lazy loading need particular attention with page builders.

## Run maintenance

1. Call `get-maintenance-status` and choose one named action.
2. Read its component through `get-component-status`, using the catalog's `component` value.
3. Preview with the action, `expected_revision`, a fresh UUID v4 `request_id` and `reason`.
4. Review the effects and prerequisites. Apply using `dry_run: false` and `confirm_action` exactly matching the preview's action. Shared cache operations also require `acknowledge_shared_cache: true`; image batch switching requires `backup_reference` naming a verified backup.
5. Read the receipt and component state. Verify the actual background job, remote service or HTTP cache outcome separately.

Reuse the same `request_id` when retrying the same request. An existing receipt is replayed without repeating the operation. `started` or `needs_reconciliation` means the result is uncertain; inspect actual state before creating another request. `native_returned` confirms a native call returned, while asynchronous completion still needs verification.

Supported maintenance includes image requests/pulls and retained-image switching, crawler start/reset/map operations, cloud usage/node maintenance, CCSS/UCSS/LQIP/VPI/Optimax queue processing and clearing, and selected cache flushes. Cloud work can send URLs or page/image data to QUIC.cloud and consume quota. Native activation, throttling and server limits remain effective.

## Clean the database

Read `get-database-status`, choose one returned `kind`, and preview `cleanup-database` with its revision, a UUID and reason. For execution, supply `dry_run: false`, a verified `backup_reference`, and `confirm_action: "database:<kind>"`.

Cleanup permanently deletes matching revisions, unused metadata, old auto-drafts, trash, selected comments or transients, depending on the chosen category. Table optimization is a separate category. LiteSpeed's revision retention settings apply. Re-read counts and reconcile the receipt afterwards.

## Invalidate page cache

For `purge-cache`, use `scope: "urls"` with up to 50 absolute same-origin URLs, `scope: "posts"` with up to 50 editable post IDs, or `scope: "site"`. Include a reason and review the default preview. Apply with `dry_run: false` and `acknowledge_cache_rebuild: true`.

URL targets use paths without query strings, fragments or wildcards. Post invalidation follows native related-page rules. Verify eviction and cache warming through subsequent HTTP responses.

## Coverage and license

See the [coverage contract](https://github.com/wirrockendigital/We.Rock.WP.Ability.Packs/blob/main/wrd-litespeed-cache-ability-pack/COVERAGE.md) for native mappings, operational boundaries and validation details. Licensed GPL-3.0-or-later; see [LICENSE](LICENSE).
