<?php
/**
 * Plugin Name: We.Rock.RealCategoryManagement.Ability.Pack
 * Description: Native category trees, ordering, assignments and settings abilities for Agent Connector.
 * Version: 0.1.0
 * Tested Target Plugin Version: 4.3.7
 * Requires at least: 7.0
 * Requires PHP: 8.1
 * Requires Plugins: agent-connector-for-wp, real-category-library
 * Author: wir.rocken.digital
 * Author URI: https://wir.rocken.digital
 * License: GPL-3.0-or-later
 * Agent Connector: Ability Pack
 * Agent Connector Target: real-category-library/index.php
 */
declare(strict_types=1);
namespace WeRock\RealCategoryManagementAbilities;
defined('ABSPATH') || exit;
// Use the generator scaffold with a private registry for companion-pack coexistence.
require_once __DIR__.'/bootstrap.php';
require_once __DIR__.'/src/Adapter.php';
require_once __DIR__.'/abilities/register.php';
ACFW_Pack::boot();
