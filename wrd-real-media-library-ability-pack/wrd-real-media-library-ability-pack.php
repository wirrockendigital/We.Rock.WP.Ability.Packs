<?php
/**
 * Plugin Name: We.Rock.RealMediaLibrary.Ability.Pack
 * Description: Governed folder and media assignment operations for Real Media Library.
 * Version: 0.1.1
 * Update URI: https://github.com/wirrockendigital/We.Rock.WP.Ability.Packs/releases?q=wrd-real-media-library-ability-pack
 * Tested Target Plugin Version: 4.23.7
 * Requires at least: 7.0
 * Requires PHP: 8.1
 * Requires Plugins: agent-connector-for-wp, real-media-library
 * Author: wir.rocken.digital
 * Author URI: https://wir.rocken.digital
 * License: GPL-3.0-or-later
 * Agent Connector: Ability Pack
 * Agent Connector Target: real-media-library/index.php
 */
declare(strict_types=1);
namespace WeRock\RealMediaLibraryAbilities;
defined('ABSPATH') || exit;
// Register this pack with WordPress core plugin updates.
require_once __DIR__ . '/includes/github-updater.php';
\WeRock\AbilityPackUpdater\Updater::register(__FILE__, 'wrd-real-media-library-ability-pack');
// Load the native adapter and isolated ability definitions.
require_once __DIR__.'/src/Adapter.php';
require_once __DIR__.'/abilities/register.php';
