<?php
/**
 * Plugin Name: We.Rock.Polylang.Ability.Pack
 * Description: Native Polylang Free language, translation and settings abilities for Agent Connector.
 * Version: 0.1.0
 * Update URI: https://github.com/wirrockendigital/We.Rock.WP.Ability.Packs/releases?q=wrd-polylang-ability-pack
 * Tested Target Plugin Version: 3.9.1
 * Requires at least: 7.0
 * Requires PHP: 8.1
 * Requires Plugins: agent-connector-for-wp, polylang
 * Author: wir.rocken.digital
 * Author URI: https://wir.rocken.digital
 * License: GPL-3.0-or-later
 * Agent Connector: Ability Pack
 * Agent Connector Target: polylang/polylang.php
 */
declare(strict_types=1);
namespace WeRock\PolylangAbilities;
defined('ABSPATH') || exit;
// Register this pack with WordPress core plugin updates.
require_once __DIR__ . '/includes/github-updater.php';
\WeRock\AbilityPackUpdater\Updater::register(__FILE__, 'wrd-polylang-ability-pack');
// Use the generator scaffold with a private registry for companion-pack coexistence.
require_once __DIR__.'/bootstrap.php';
require_once __DIR__.'/src/Adapter.php';
require_once __DIR__.'/abilities/register.php';
ACFW_Pack::boot();
