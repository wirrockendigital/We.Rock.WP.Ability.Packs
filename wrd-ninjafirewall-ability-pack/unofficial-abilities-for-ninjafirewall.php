<?php
/**
 * Plugin Name: Unofficial Abilities for NinjaFirewall
 * Description: NinjaFirewall diagnostics, scoped settings and rule management through Agent Connector for WP.
 * Version: 0.1.0
 * Tested Target Plugin Version: 4.9.2
 * Requires at least: 7.0
 * Requires PHP: 8.1
 * Requires Plugins: agent-connector-for-wp, ninjafirewall
 * Author: We.Rock
 * License: GPL-3.0-or-later
 * Agent Connector: Ability Pack
 * Agent Connector Target: ninjafirewall/ninjafirewall.php
 */
declare(strict_types=1);
namespace WeRock\NinjaAbilities;
defined('ABSPATH') || exit;

// Use a private namespace so multiple generated packs can coexist.
require_once __DIR__ . '/src/Adapter.php';
require_once __DIR__ . '/abilities/register.php';
