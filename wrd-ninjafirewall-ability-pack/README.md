# NinjaFirewall Ability Pack for WordPress MCP

Firewall status, redacted incidents, settings, rules, and safe configuration previews.

## Compatibility

Pack version: **0.1.0**. Reviewed target: **NinjaFirewall 4.9.2** (`ninjafirewall/ninjafirewall.php`). Requires WordPress 7.0+, PHP 8.1+, the named target plugin, and [Agent Connector for WP](https://github.com/soflyy/agent-connector-for-wp). The target version is informative; native capability checks determine which operations are available.

## Install

Follow the [AI-agent installation guide](../INSTALL_FOR_AI_AGENTS.md) to check the target plugin, package this pack as a ZIP, activate it, and verify MCP discovery.

## Use

Inspect the firewall state with `ninjafirewall/get-status`, then read settings, rules, or incidents. Setting and rule changes require a fresh revision and a dry-run preview.

Available abilities:

- `ninjafirewall/get-status`
- `ninjafirewall/get-settings`
- `ninjafirewall/get-settings-schema`
- `ninjafirewall/update-settings`
- `ninjafirewall/list-rules`
- `ninjafirewall/set-rule-enabled`
- `ninjafirewall/list-log-months`
- `ninjafirewall/search-log`
- `ninjafirewall/get-statistics`
- `ninjafirewall/get-login-protection`
- `ninjafirewall/get-schedules`
- `ninjafirewall/inspect-htninja`
- `ninjafirewall/preview-htninja-rule`
