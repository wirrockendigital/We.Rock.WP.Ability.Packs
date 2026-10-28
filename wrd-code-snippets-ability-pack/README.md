# Code Snippets Pro Ability Pack for WordPress MCP

Local snippet discovery, validation, creation, editing, activation, and lifecycle control.

## Compatibility

Pack version: **0.1.0**. Reviewed target: **Code Snippets Pro 4.0.0** (`code-snippets-pro/code-snippets.php`). Requires WordPress 7.0+, PHP 8.1+, the named target plugin, and [Agent Connector for WP](https://github.com/soflyy/agent-connector-for-wp). The target version is informative; native capability checks determine which operations are available.

## Install

Follow the [AI-agent installation guide](../INSTALL_FOR_AI_AGENTS.md) to check the target plugin, package this pack as a ZIP, activate it, and verify MCP discovery.

## Use

Start with `code-snippets/get-status` and `code-snippets/list-snippets`. Create or edit an inactive snippet through a preview; activation requires `acknowledge_execution: true` because it enables code execution.

Available abilities:

- `code-snippets/get-status`
- `code-snippets/list-snippets`
- `code-snippets/get-snippet`
- `code-snippets/validate-code`
- `code-snippets/create-snippet`
- `code-snippets/update-snippet`
- `code-snippets/activate-snippet`
- `code-snippets/deactivate-snippet`
- `code-snippets/trash-snippet`
- `code-snippets/restore-snippet`
