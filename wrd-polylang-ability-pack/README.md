# Polylang Free Ability Pack for WordPress MCP

Language settings, content language assignments, translation links, and registered string translations.

## Compatibility

Pack version: **0.1.0**. Reviewed target: **Polylang Free 3.9.1** (`polylang/polylang.php`). Requires WordPress 7.0+, PHP 8.1+, the named target plugin, and [Agent Connector for WP](https://github.com/soflyy/agent-connector-for-wp). The target version is informative; native capability checks determine which operations are available.

## Install

Follow the [AI-agent installation guide](../INSTALL_FOR_AI_AGENTS.md) to check the target plugin, package this pack as a ZIP, activate it, and verify MCP discovery.

## Use

Start with `polylang/get-status`, then read languages or settings. Use fresh object and configuration revisions for previews and authorized changes.

Available abilities:

- `polylang/get-status`
- `polylang/list-languages`
- `polylang/create-language`
- `polylang/update-language`
- `polylang/get-settings`
- `polylang/update-setting`
- `polylang/list-content`
- `polylang/get-content`
- `polylang/set-content-language`
- `polylang/link-translation`
- `polylang/unlink-translation`
- `polylang/list-strings`
- `polylang/translate-string`
- `polylang/get-language-links`
