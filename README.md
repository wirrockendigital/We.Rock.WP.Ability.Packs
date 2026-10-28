# Unofficial We.Rock WordPress MCP Ability Packs for AI Agents

These **unofficial WordPress ability packs** let an AI agent work with selected WordPress plugins through [Agent Connector for WP](https://github.com/soflyy/agent-connector-for-wp). Each pack adds a focused set of tools for one plugin, such as reading backup status, finding media folders, reviewing firewall incidents, or preparing a draft. You install only the packs for plugins you already use.

Agent Connector for WP provides the connection between an AI agent and the WordPress Abilities API. The packs add plugin-specific actions to that connection. They are useful when you want an agent to help with a real WordPress task while seeing the relevant plugin state, permissions, and a preview before supported changes. The project was developed for WordPress sites using [Oxygen 6](https://oxygenbuilder.com/) as the page builder.

These are third-party integrations. They are not official products of Soflyy, Oxygen, or the vendors of the supported plugins.

## Choose a pack

| Pack | What your AI agent can do |
|---|---|
| [NinjaFirewall](wrd-ninjafirewall-ability-pack/) | Check protection status, review redacted incidents and statistics, inspect selected settings and rules, and preview supported changes. |
| [Code Snippets Pro](wrd-code-snippets-ability-pack/) | Find and review snippets, validate code, prepare inactive snippets, and manage their activation and lifecycle. |
| [Polylang Free](wrd-polylang-ability-pack/) | Inspect languages and settings, manage content language assignments and translation links, and work with registered string translations. |
| [Real Category Management](wrd-real-category-management-ability-pack/) | Explore category trees, manage terms and ordering, inspect content assignments, and adjust selected settings. |
| [Real Cookie Banner](wrd-real-cookie-banner-ability-pack/) | Inspect consent groups and manage service or content-blocker drafts. |
| [Real Media Library](wrd-real-media-library-ability-pack/) | Browse media folders, manage folders, and assign attachments. |
| [WPvivid Backup](wrd-wpvivid-ability-pack/wrd-wpvivid-backup-ability-pack/) | Review backup settings, schedules, tasks, and archives; request a local backup with an explicit confirmation. |
| [WPvivid Pro](wrd-wpvivid-ability-pack/wrd-wpvivid-pro-ability-pack/) | Inspect Pro settings, schedules, retention information, module status, and backup diagnostics. |
| [WPvivid Staging](wrd-wpvivid-ability-pack/wrd-wpvivid-staging-ability-pack/) | Review staging settings, sites, tasks, access defaults, and scheduled events. |
| [WPvivid Imgoptim Free](wrd-wpvivid-ability-pack/wrd-wpvivid-imgoptim-free-ability-pack/) | Inspect image optimization tasks, image status, sizes, lazy-loading settings, and supported options. |

Open a pack's page for its available agent tools and usage notes.

## Install on WordPress

1. Install and activate the WordPress plugin you want the agent to use. For example, the Real Media Library pack requires Real Media Library.
2. Install and activate the [official Agent Connector for WP release](https://github.com/soflyy/agent-connector-for-wp/releases). Follow its setup instructions to connect your AI agent.
3. Download or clone this repository and create the installable ZIP for your chosen pack. This example packages Real Media Library:

   ```sh
   git clone https://github.com/wirrockendigital/We.Rock.WP.Ability.Packs.git
   cd We.Rock.WP.Ability.Packs
   python3 scripts/package_pack.py wrd-real-media-library-ability-pack
   ```

4. In WordPress, open **Plugins → Add New → Upload Plugin**, select `dist/wrd-real-media-library-ability-pack-0.1.0.zip`, and activate it. Replace the example pack path and ZIP name with the pack you chose.

The pack appears as its own entry under **Plugins → Installed Plugins**, alongside Agent Connector and the target plugin. Check the pack page for any plugin-specific requirements. An [installation guide for AI agents](INSTALL_FOR_AI_AGENTS.md) gives the full verification workflow for automated installation.

## Use with an AI agent

Connect your agent through Agent Connector for WP and ask it to discover the tools from the installed pack. Start with a read-only request, for example: “Show me the latest WPvivid backup status and available backup tools. Do not change anything.”

For a change, ask the agent to read the current state and show you the preview first. Review the proposed action before asking it to apply the change. Some actions, including enabling executable snippets or starting a backup, require an additional explicit acknowledgement. Each pack's page lists its tools and the information needed for its actions.

## License and responsibility

The packs are licensed under [GPL-3.0-or-later](LICENSE). They are provided **as is**, without warranty. Use them at your own risk. To the extent permitted by applicable law, the maintainers accept no liability for damage or loss arising from their use; the license contains the full legal terms. Make a verified backup before installation or changes.
