# Real Media Library Ability Pack for WordPress MCP

Media-folder discovery, folder management, and attachment assignments.

## Compatibility

Pack version: **0.1.0**. Reviewed target: **Real Media Library 4.23.7** (`real-media-library/index.php`). Requires WordPress 7.0+, PHP 8.1+, the named target plugin, and [Agent Connector for WP](https://github.com/soflyy/agent-connector-for-wp). The target version is informative; native capability checks determine which operations are available.

## Install

Follow the [AI-agent installation guide](../INSTALL_FOR_AI_AGENTS.md) to check the target plugin, package this pack as a ZIP, activate it, and verify MCP discovery.

## Use

Start with `real-media-library/get-status` and list folders. With a multilingual plugin active, reads remain available while folder and attachment writes require a separate native integration review.

Available abilities:

- `real-media-library/get-status`
- `real-media-library/list-folders`
- `real-media-library/get-folder`
- `real-media-library/list-attachments`
- `real-media-library/create-folder`
- `real-media-library/rename-folder`
- `real-media-library/move-attachments`
- `real-media-library/delete-empty-folder`
