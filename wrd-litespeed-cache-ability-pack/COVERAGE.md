# LiteSpeed Cache adapter coverage

The adapter exposes reviewed local settings and page-cache operations. Database cleanup, media deletion, object/opcode cache flushing, server-wide cache emptying, raw configuration import, file editing, credentials, account management and QUIC.cloud job submission are outside this interface. Native save hooks may still synchronize an already configured CDN.

Reviewed against installed LiteSpeed Cache 7.9.2: `API::save_conf`, `Conf::update_confs`, `Conf::conf`, `Conf::get_option`, `Purge::purge_url`, `Purge::purge_post`, `Purge::purge_all_lscache`. Version metadata does not gate execution.

Tests cover authorization, input schema, default previews, stale and hidden-setting revisions, no-op saves, scalar persistence, failed native saves, URL scope, post permissions, missing write methods, an inactive plugin and future versions. Live acceptance uses authenticated reads and previews and verifies preserved configuration; it does not intentionally alter production optimization settings or empty the site cache.
