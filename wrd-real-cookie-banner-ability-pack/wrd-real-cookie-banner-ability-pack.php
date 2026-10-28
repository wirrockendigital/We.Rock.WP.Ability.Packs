<?php
/**
 * Plugin Name: We.Rock.RealCookieBanner.Ability.Pack
 * Description: Governed service and content blocker draft management for Real Cookie Banner.
 * Version: 0.1.1
 * Update URI: https://github.com/wirrockendigital/We.Rock.WP.Ability.Packs/releases?q=wrd-real-cookie-banner-ability-pack
 * Tested Target Plugin Version: 5.4.0
 * Requires at least: 7.0
 * Requires PHP: 8.1
 * Requires Plugins: agent-connector-for-wp, real-cookie-banner
 * Author: wir.rocken.digital
 * Author URI: https://wir.rocken.digital
 * License: GPL-3.0-or-later
 * Agent Connector: Ability Pack
 * Agent Connector Target: real-cookie-banner/index.php
 */
declare(strict_types=1);
namespace WeRock\RealCookieBannerAbilities;
defined('ABSPATH') || exit;
// Register this pack with WordPress core plugin updates.
require_once __DIR__ . '/includes/github-updater.php';
\WeRock\AbilityPackUpdater\Updater::register(__FILE__, 'wrd-real-cookie-banner-ability-pack');
// Load strict public contracts before WordPress initializes its ability registry.
require_once __DIR__ . '/src/Adapter.php';
require_once __DIR__ . '/abilities/register.php';
