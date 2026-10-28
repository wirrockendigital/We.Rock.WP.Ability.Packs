# We.Rock WordPress MCP Ability Packs

Independent WordPress Ability API plugins that give AI agents focused tools for backup management, multilingual content, category trees, media folders, code snippets, firewall diagnostics, and cookie-banner drafts. Each pack registers structured abilities through [Agent Connector for WP](https://github.com/soflyy/agent-connector-for-wp), the underlying WordPress MCP project from Soflyy.

The reference development site uses [Oxygen 6](https://oxygenbuilder.com/) as its WordPress page builder. These packs extend the named WordPress plugins through Agent Connector; install the packs that match the plugins on your site.

## Ability packs

Each pack has its **own version**, starting at `0.1.0`. The target version below identifies the WordPress plugin source reviewed for that pack. Runtime availability also depends on the native functions and permissions required by each operation.

| Pack | WordPress plugin and reviewed version | Abilities |
|---|---|---:|
| [NinjaFirewall](wrd-ninjafirewall-ability-pack/) | NinjaFirewall 4.9.2 | 13 |
| [Code Snippets Pro](wrd-code-snippets-ability-pack/) | Code Snippets Pro 4.0.0 | 10 |
| [Polylang Free](wrd-polylang-ability-pack/) | Polylang Free 3.9.1 | 14 |
| [Real Category Management](wrd-real-category-management-ability-pack/) | Real Category Management 4.3.7 | 11 |
| [Real Cookie Banner](wrd-real-cookie-banner-ability-pack/) | Real Cookie Banner 5.3.0 | 10 |
| [Real Media Library](wrd-real-media-library-ability-pack/) | Real Media Library 4.23.7 | 8 |
| [WPvivid Backup](wrd-wpvivid-ability-pack/wrd-wpvivid-backup-ability-pack/) | WPvivid Backup 0.9.136 | 9 |
| [WPvivid Pro](wrd-wpvivid-ability-pack/wrd-wpvivid-pro-ability-pack/) | WPvivid Pro 2.2.53 | 9 |
| [WPvivid Staging](wrd-wpvivid-ability-pack/wrd-wpvivid-staging-ability-pack/) | WPvivid Staging 2.0.25 | 8 |
| [WPvivid Imgoptim Free](wrd-wpvivid-ability-pack/wrd-wpvivid-imgoptim-free-ability-pack/) | WPvivid Imgoptim Free 0.9.25 | 9 |

The WordPress plugin header and `pack.json` inside each pack carry its independent version, target plugin slug, and reviewed target version.

## Install

Use WordPress 7.0+, PHP 8.1+, the target plugin, and [Agent Connector for WP](https://github.com/soflyy/agent-connector-for-wp/releases). Package and activate one pack at a time. The [installation guide for AI agents](INSTALL_FOR_AI_AGENTS.md) covers prerequisite checks, ZIP creation, MCP discovery, and verification.

## Use

Connect an authorized AI agent to Agent Connector for WP, discover the pack's ability namespace, and start with its `get-status` ability. Read the relevant state before a change, pass its `expected_revision` and a reason, inspect the default `dry_run` preview, then explicitly set `dry_run: false` for an authorized write. The exact input and output contracts are defined in each pack's `abilities/register.php`.

## License and responsibility

The repository and each pack are licensed under [GPL-3.0-or-later](LICENSE); see the [source notice](NOTICE.md). The software is provided **as is**, without warranty. Use it at your own risk. To the extent permitted by applicable law, the maintainers accept no liability for damage or loss arising from its use; the license contains the full legal terms. Back up the site and verify the exact target plugin before installing or allowing an agent to write.
