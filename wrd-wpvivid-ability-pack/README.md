# Unofficial WPvivid Ability Packs for WordPress AI Agents

These packs add WPvivid tools to [Agent Connector for WP](https://github.com/soflyy/agent-connector-for-wp), so an authorized AI agent can help you inspect backups, staging sites, and image optimization from your WordPress site. They are unofficial third-party integrations, not WPvivid products.

| Pack | Required WPvivid plugin | What your agent can do |
|---|---|---|
| [Backup](wrd-wpvivid-backup-ability-pack/) | WPvivid Backup | Read backup settings, schedules, tasks, and archives; request a local backup with explicit confirmation. |
| [Pro](wrd-wpvivid-pro-ability-pack/) | WPvivid Pro | Review Pro settings, schedules, retention information, and backup diagnostics. |
| [Staging](wrd-wpvivid-staging-ability-pack/) | WPvivid Staging | Inspect staging sites, tasks, access defaults, and selected settings. |
| [Imgoptim Free](wrd-wpvivid-imgoptim-free-ability-pack/) | WPvivid Imgoptim Free | Review optimization tasks, image status, sizes, lazy-loading settings, and supported options. |

## Install

Install the matching WPvivid plugin and Agent Connector for WP, then package and activate the pack you want. The [main installation instructions](../README.md#install-on-wordpress) walk through the WordPress upload. The [AI-agent installation guide](../INSTALL_FOR_AI_AGENTS.md) covers automated installation and verification.

## Use

Ask your agent to discover the installed WPvivid pack's tools and start by showing the current status. For example: “Show my recent backup tasks and available archives. Do not change anything.” For changes, review the agent's preview before approving it. A backup request needs an explicit acknowledgement; confirm completion by checking the resulting task and archive. Each pack page lists its available tools.
