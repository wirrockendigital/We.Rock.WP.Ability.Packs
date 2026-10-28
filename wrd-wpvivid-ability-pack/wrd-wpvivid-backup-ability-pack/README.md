# WPvivid Backup Ability Pack for WordPress MCP

Backup settings, schedules, catalog and task diagnostics, plus a controlled local-backup start.

## Compatibility

Pack version: **0.1.0**. Reviewed target: **WPvivid Backup 0.9.136** (`wpvivid-backuprestore/wpvivid-backuprestore.php`). Requires WordPress 7.0+, PHP 8.1+, the named target plugin, and [Agent Connector for WP](https://github.com/soflyy/agent-connector-for-wp). The target version is informative; native capability checks determine which operations are available.

## Install

Follow the [AI-agent installation guide](../../INSTALL_FOR_AI_AGENTS.md) to check the target plugin, package this pack as a ZIP, activate it, and verify MCP discovery.

## Use

Read status, settings, and catalog state first. Starting a backup requires a UUID request ID, an explicit retention acknowledgement, and follow-up checks of the completed archive.

Available abilities:

- `wpvivid-backup/get-status`
- `wpvivid-backup/get-settings`
- `wpvivid-backup/get-settings-schema`
- `wpvivid-backup/update-settings`
- `wpvivid-backup/list-cron-events`
- `wpvivid-backup/list-backups`
- `wpvivid-backup/list-tasks`
- `wpvivid-backup/list-remote-storage`
- `wpvivid-backup/start-local-backup`
