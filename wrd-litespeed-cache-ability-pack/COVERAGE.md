# LiteSpeed Cache adapter coverage

Version 0.1.1 provides 13 abilities, 153 explicitly reviewed configuration fields, 33 maintenance actions, and ten database cleanup categories. The native source reviewed on the installation reports LiteSpeed Cache 7.9.2. Version metadata is informative; methods and runtime prerequisites decide availability.

| Area | Native coverage | Preconditions and boundaries |
|---|---|---|
| Settings | `Conf::conf`, `Conf::get_option`, `Conf::update_confs`; fixed scalar/list allowlist across cache, TTL, exclusions, purge rules, ESI, optimization, media, image optimization, crawler, heartbeat, debug and existing object/CDN integrations | Strict schema, default preview, fresh configuration revision and native save/readback. High-impact settings require acknowledgement. Native saves can update rewrite files, purge shared caches and synchronize existing CDN integrations. |
| Page cache | Same-origin URLs, editable post IDs, site page cache, private cache and feeds | Explicit cache-rebuild confirmation; native invalidation is a request, not completed eviction. |
| Other caches | Native combined purge, object cache and opcode cache | Explicit shared-backend acknowledgement. Combined purge includes CSS/JS, local resources and configured Cloudflare invalidation. Native dedicated private purge handlers are not invoked through reflection. |
| Image optimization | Native counts, redacted summary, request, async pull, unfinished-queue cleanup, switch to retained originals/optimized files | QUIC.cloud activation and native settings are prerequisites for requests; quota and native throttling still apply. Batch switching uses the native CLI context and a verified-backup reference. |
| Optimization queues | CCSS, UCSS, LQIP, VPI and Optimax queue counts, one native processing pass, queue clearing | Remote processing requires existing cloud activation and can consume quota. Queue URLs and provider payloads are not returned. |
| Crawler | Progress, zero-based crawler IDs/active flags, paginated map/blocklist paths, async start, reset, map refresh/empty and blocklist clearing | Native limits and server support still apply. Sitemap refresh requires a local HTTPS sitemap. Cookie values and simulated-user data are not exposed. |
| QUIC.cloud | Redacted activation/usage state, usage sync, node cache clearing and six service-node redetection operations | Existing native account connection; activation, linking, DNS, certificate and account-reset flows remain in the native administrator UI. |
| Database | Native counts for revisions, orphaned postmeta, old auto-drafts, trashed posts, spam/trash comments, trackbacks/pingbacks, expired/all transients and optimizable tables; targeted native cleanup | Fresh candidate-state revision, exact category confirmation, UUID request ID and verified-backup reference. Only the enumerated native routine executes in a scoped WP-CLI subprocess. No broad `optimize_all` shortcut. |
| Receipts | Durable per-site fingerprints, started/native-returned/needs-reconciliation states and receipt lookup | Reusing an ID replays its receipt, never the mutation. Changed requests cannot reuse an ID. A started/uncertain receipt requires reconciliation. Receipts contain no secrets or free-text reasons. |

## Deliberate interface boundaries

Secret values, raw logs, signed cloud callbacks, arbitrary PHP/shell/file access, raw configuration import, preset application/reset, CDN mapping structures, role/cookie simulation configuration, object-cache connection credentials, network-wide operations and server-wide cache-emptying are not exposed. These require separate contracts or interactive/native administration. Some raw markup and nested settings are also excluded from the fixed schema; `get-settings-schema` is authoritative.

Native bulk deletion of original image backups and optimization history is excluded because the reviewed methods use redirect-driven batches and cannot provide a reliable bounded JSON completion contract. The native automatic backup-removal setting is available only with explicit high-impact acknowledgement; enabling it affects subsequent native jobs, not this installation acceptance.

Public native methods are used directly; private methods are never made callable. WP-CLI is used only for a fixed child program handling database cleanup and image batch switching. The caller cannot supply a command, program, script path or arbitrary PHP. Child execution verifies the site and user, reads parameters from stdin, captures provider diagnostics without returning them, and times out after 90 seconds. Long operations can end in a partial/uncertain state; inspect the receipt and native state before issuing another request.

Revisions are optimistic checks. Pack maintenance writers serialize through an advisory lock; native UI, cron, filesystem changes and other plugins do not participate in that lock. Receipts are stored without autoload and retained for reconciliation. `native_returned` does not prove completed background work, cache eviction, successful remote quota allocation or every file transformation.

## Verification

Local tests cover all 13 registered schemas, typed settings, permission denial, default previews, stale revisions, missing native methods, future/inactive plugin versions, failed persistence, URL/post scope, shared-cache and high-impact confirmations, cloud redaction, request collisions, receipt replay and uncertain outcomes. Database cleanup preview and backup gates are exercised without executing deletion.

Live acceptance reads all component families, validates native effective settings against the schema, previews maintenance and database actions, compares preserved content/configuration hashes, verifies installed files and checks authenticated MCP discovery. Production cleanup, quota-consuming submissions, shared-cache flushing and image replacement are not executed merely to test installation.

Sources: installed `Base`, `Conf`, `Root`, `Purge`, `DB_Optm`, `Img_Optm` and its traits, `Crawler`, `Crawler_Map`, `Cloud` and its traits, `Cloud_Queue_Svc`, `CSS`, `UCSS`, `Placeholder`, `VPI`, `Optimax`, and the native CLI classes; [official API reference](https://docs.litespeedtech.com/lscache/lscwp/api/) and [WP-CLI reference](https://docs.litespeedtech.com/lscache/lscwp/cli/).
