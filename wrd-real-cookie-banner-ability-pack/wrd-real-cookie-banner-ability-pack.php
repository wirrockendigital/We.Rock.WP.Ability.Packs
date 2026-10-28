<?php
/**
 * Plugin Name: WRD Real Cookie Banner Ability Pack
 * Description: Governed service and content blocker draft management for Real Cookie Banner.
 * Version: 0.1.0
 * Tested Target Plugin Version: 5.3.0
 * Requires at least: 7.0
 * Requires PHP: 8.1
 * Requires Plugins: agent-connector-for-wp, real-cookie-banner
 * Author: We.Rock
 * License: GPL-3.0-or-later
 * Agent Connector: Ability Pack
 * Agent Connector Target: real-cookie-banner/index.php
 */
declare(strict_types=1);
namespace WeRock\RealCookieBannerAbilities;
defined('ABSPATH') || exit;
// Load strict public contracts before WordPress initializes its ability registry.
require_once __DIR__ . '/src/Adapter.php';
require_once __DIR__ . '/abilities/register.php';
