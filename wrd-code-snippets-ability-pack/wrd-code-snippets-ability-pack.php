<?php
/**
 * Plugin Name: We.Rock.CodeSnippetsPro.Ability.Pack
 * Description: Governed local snippet administration for Code Snippets Pro.
 * Version: 0.1.0
 * Tested Target Plugin Version: 4.0.0
 * Requires at least: 7.0
 * Requires PHP: 8.1
 * Requires Plugins: agent-connector-for-wp, code-snippets-pro
 * Author: wir.rocken.digital
 * Author URI: https://wir.rocken.digital
 * License: GPL-3.0-or-later
 * Agent Connector: Ability Pack
 * Agent Connector Target: code-snippets-pro/code-snippets.php
 */
declare(strict_types=1);
namespace WeRock\CodeSnippetsAbilities;
defined('ABSPATH') || exit;
// Load the native adapter before registering its governed public surface.
require_once __DIR__ . '/src/Adapter.php';
require_once __DIR__ . '/abilities/register.php';
