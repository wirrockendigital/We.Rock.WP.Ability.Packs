<?php
/**
 * Plugin Name: We.Rock.LiteSpeedCache.Ability.Pack
 * Description: Native LiteSpeed Cache diagnostics, settings previews and scoped cache invalidation for Agent Connector.
 * Version: 0.1.1
 * Update URI: https://github.com/wirrockendigital/We.Rock.WP.Ability.Packs/releases?q=wrd-litespeed-cache-ability-pack
 * Tested Target Plugin Version: 7.9.2
 * Requires at least: 7.0
 * Requires PHP: 8.1
 * Requires Plugins: agent-connector-for-wp, litespeed-cache
 * Author: wir.rocken.digital
 * Author URI: https://wir.rocken.digital
 * License: GPL-3.0-or-later
 * Agent Connector: Ability Pack
 * Agent Connector Target: litespeed-cache/litespeed-cache.php
 */
declare(strict_types=1);
namespace WeRock\LiteSpeedAbilities;
defined('ABSPATH') || exit;
// Keep update discovery independent from the other installed ability packs.
require_once __DIR__ . '/includes/github-updater.php';
\WeRock\AbilityPackUpdater\Updater::register(__FILE__, 'wrd-litespeed-cache-ability-pack');
// Register native operations only through the existing Agent Connector host.
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/src/Settings.php';
require_once __DIR__ . '/src/NativeProcess.php';
require_once __DIR__ . '/src/Operations.php';
require_once __DIR__ . '/src/Adapter.php';
require_once __DIR__ . '/abilities/register.php';
ACFW_Pack::boot();
