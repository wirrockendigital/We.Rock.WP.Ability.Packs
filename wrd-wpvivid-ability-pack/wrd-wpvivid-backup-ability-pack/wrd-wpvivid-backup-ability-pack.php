<?php
/**
 * Plugin Name: WRD WPvivid backup Ability Pack
 * Description: WPvivid backup diagnostics and scoped administration through Agent Connector for WP.
 * Version: 0.1.0
 * Tested Target Plugin Version: 0.9.136
 * Requires at least: 7.0
 * Requires PHP: 8.1
 * Requires Plugins: agent-connector-for-wp, wpvivid-backuprestore
 * Author: We.Rock
 * License: GPL-3.0-or-later
 * Agent Connector: Ability Pack
 * Agent Connector Target: wpvivid-backuprestore/wpvivid-backuprestore.php
 */
declare(strict_types=1);
namespace WeRock\WPvividBackupAbilities;
defined('ABSPATH') || exit;

// Each companion is independently installable without a shared loader plugin.
require_once __DIR__ . '/src/Runtime.php';
require_once __DIR__ . '/src/Adapter.php';
require_once __DIR__ . '/abilities/register.php';
