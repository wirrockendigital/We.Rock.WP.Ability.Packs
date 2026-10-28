<?php
/**
 * Plugin Name: We.Rock.Polylang.Ability.Pack
 * Description: Native Polylang Free language, translation and settings abilities for Agent Connector.
 * Version: 0.1.0
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
// Use the generator scaffold with a private registry for companion-pack coexistence.
require_once __DIR__.'/bootstrap.php';
require_once __DIR__.'/src/Adapter.php';
require_once __DIR__.'/abilities/register.php';
ACFW_Pack::boot();
