<?php
/**
 * Plugin Name: We.Rock.WPvividPro.Ability.Pack
 * Description: WPvivid pro diagnostics and scoped administration through Agent Connector for WP.
 * Version: 0.1.1
 * Update URI: https://github.com/wirrockendigital/We.Rock.WP.Ability.Packs/releases?q=wrd-wpvivid-pro-ability-pack
 * Tested Target Plugin Version: 2.2.53
 * Requires at least: 7.0
 * Requires PHP: 8.1
 * Requires Plugins: agent-connector-for-wp, wpvivid-backup-pro
 * Author: wir.rocken.digital
 * Author URI: https://wir.rocken.digital
 * License: GPL-3.0-or-later
 * Agent Connector: Ability Pack
 * Agent Connector Target: wpvivid-backup-pro/wpvivid-backup-pro.php
 */
declare(strict_types=1);
namespace WeRock\WPvividProAbilities;
defined('ABSPATH') || exit;
// Register this pack with WordPress core plugin updates.
require_once __DIR__ . '/includes/github-updater.php';
\WeRock\AbilityPackUpdater\Updater::register(__FILE__, 'wrd-wpvivid-pro-ability-pack');

// Each companion is independently installable without a shared loader plugin.
require_once __DIR__ . '/src/Runtime.php';
require_once __DIR__ . '/src/Adapter.php';
require_once __DIR__ . '/abilities/register.php';
