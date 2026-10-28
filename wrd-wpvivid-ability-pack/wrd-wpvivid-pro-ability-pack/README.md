# WPvivid Pro Ability Pack for WordPress MCP

Pro settings, schedules, retention metadata, module status, and last-backup diagnostics.

## Compatibility

Pack version: **0.1.0**. Reviewed target: **WPvivid Pro 2.2.53** (`wpvivid-backup-pro/wpvivid-backup-pro.php`). Requires WordPress 7.0+, PHP 8.1+, the named target plugin, and [Agent Connector for WP](https://github.com/soflyy/agent-connector-for-wp). The target version is informative; native capability checks determine which operations are available.

## Install

Follow the [AI-agent installation guide](../../INSTALL_FOR_AI_AGENTS.md) to check the target plugin, package this pack as a ZIP, activate it, and verify MCP discovery.

## Use

Start with `wpvivid-pro/get-status`. Read the current settings revision before previewing or applying a supported setting delta.

Available abilities:

- `wpvivid-pro/get-status`
- `wpvivid-pro/get-settings`
- `wpvivid-pro/get-settings-schema`
- `wpvivid-pro/update-settings`
- `wpvivid-pro/list-cron-events`
- `wpvivid-pro/list-schedules`
- `wpvivid-pro/get-retention`
- `wpvivid-pro/get-modules`
- `wpvivid-pro/get-last-backup`
