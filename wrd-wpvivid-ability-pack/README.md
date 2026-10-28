# WPvivid Ability Packs for WordPress MCP

Four independently installable WordPress Ability API plugins expose selected WPvivid backup, Pro, staging, and image-optimization operations to AI agents through [Agent Connector for WP](https://github.com/soflyy/agent-connector-for-wp).

## Packs

| Pack | Version | Reviewed WPvivid target |
|---|---:|---:|
| [Backup](wrd-wpvivid-backup-ability-pack/) | 0.1.0 | Backup 0.9.136 |
| [Pro](wrd-wpvivid-pro-ability-pack/) | 0.1.0 | Pro 2.2.53 |
| [Staging](wrd-wpvivid-staging-ability-pack/) | 0.1.0 | Staging 2.0.25 |
| [Imgoptim Free](wrd-wpvivid-imgoptim-free-ability-pack/) | 0.1.0 | Imgoptim Free 0.9.25 |

Each pack carries its own version, target plugin slug, and reviewed source version in its WordPress plugin header and `pack.json`.

## Install

Choose only the pack whose target WPvivid plugin is installed. Follow the repository's [AI-agent installation guide](../INSTALL_FOR_AI_AGENTS.md) to verify prerequisites, install Agent Connector for WP when needed, package the selected pack, activate it, and confirm MCP discovery.

## Use

Start with the pack's `get-status` ability, then read settings and a fresh revision before any update preview. WPvivid Backup can request a local backup after an explicit retention acknowledgement; verify the resulting task and archive independently. Each pack's README lists its ability names and intended workflow.
