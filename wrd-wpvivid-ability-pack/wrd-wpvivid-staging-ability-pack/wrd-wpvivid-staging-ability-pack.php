<?php
/**
 * Plugin Name: WRD WPvivid staging Ability Pack
 * Description: WPvivid staging diagnostics and scoped administration through Agent Connector for WP.
 * Version: 0.1.0
 * Tested Target Plugin Version: 2.0.25
 * Requires at least: 7.0
 * Requires PHP: 8.1
 * Requires Plugins: agent-connector-for-wp, wpvivid-staging
 * Author: We.Rock
 * License: GPL-3.0-or-later
 * Agent Connector: Ability Pack
 * Agent Connector Target: wpvivid-staging/wpvivid-staging.php
 */
declare(strict_types=1);
namespace WeRock\WPvividStagingAbilities;
defined('ABSPATH') || exit;

// Each companion is independently installable without a shared loader plugin.
require_once __DIR__ . '/src/Runtime.php';
require_once __DIR__ . '/src/Adapter.php';
require_once __DIR__ . '/abilities/register.php';
