# WPvivid Staging Ability Pack for WordPress MCP

Staging settings, site and task catalogs, access defaults, and cron-event diagnostics.

## Compatibility

Pack version: **0.1.0**. Reviewed target: **WPvivid Staging 2.0.25** (`wpvivid-staging/wpvivid-staging.php`). Requires WordPress 7.0+, PHP 8.1+, the named target plugin, and [Agent Connector for WP](https://github.com/soflyy/agent-connector-for-wp). The target version is informative; native capability checks determine which operations are available.

## Install

Follow the [AI-agent installation guide](../../INSTALL_FOR_AI_AGENTS.md) to check the target plugin, package this pack as a ZIP, activate it, and verify MCP discovery.

## Use

Start with `wpvivid-staging/get-status`. Read the current settings revision before previewing or applying a supported setting delta.

Available abilities:

- `wpvivid-staging/get-status`
- `wpvivid-staging/get-settings`
- `wpvivid-staging/get-settings-schema`
- `wpvivid-staging/update-settings`
- `wpvivid-staging/list-cron-events`
- `wpvivid-staging/list-sites`
- `wpvivid-staging/list-tasks`
- `wpvivid-staging/get-access-settings`
