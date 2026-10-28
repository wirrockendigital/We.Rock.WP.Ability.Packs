# Installing WordPress MCP Ability Packs with an AI Agent

This guide is for an AI agent working on a WordPress site with the site owner's authorization. Install only the pack for a target plugin that is present on that site.

## 1. Check the site and make a backup

Identify the WordPress root and list the installed plugins and versions with WP-CLI or the WordPress administration screen. Confirm WordPress 7.0+ and PHP 8.1+. Before changing plugins, create a complete files-and-database backup and verify that the resulting archive can be read.

Read the selected pack's `pack.json`. Compare `target_plugin` and `tested_target_plugin_version` with the installed target plugin. The tested version is a reviewed source reference, not an automatic version lock. If versions differ, review the native APIs used by the pack and test them before installation.

## 2. Install Agent Connector for WP when needed

Check whether `agent-connector-for-wp` is already active. If it is missing, install the [official packaged release](https://github.com/soflyy/agent-connector-for-wp/releases) and activate it. The release ZIP includes its Composer dependencies. Follow the upstream [connection and protection instructions](https://github.com/soflyy/agent-connector-for-wp/blob/master/plugin/README.md) to connect the agent to this site. Treat the site's MCP credentials like an administrator credential.

Agent Connector is the shared MCP prerequisite. The target plugin named in `pack.json` is a separate dependency. Obtain that plugin from its official vendor or licensed source before installing its pack.

## 3. Copy and activate one pack

Clone this repository on a trusted machine. Package one selected pack with the included script. It takes the `slug` from `pack.json`, so NinjaFirewall keeps its installed identity `unofficial-abilities-for-ninjafirewall`. The ZIP contains runtime files and user documentation; development tests and historical site evidence stay outside the plugin installation.

```sh
git clone https://github.com/wirrockendigital/We.Rock.WP.Ability.Packs.git
cd We.Rock.WP.Ability.Packs

# Example: Code Snippets Pro. Replace the path with the site you administer.
WP_PATH=/path/to/wordpress
python3 scripts/package_pack.py wrd-code-snippets-ability-pack
wp plugin install dist/wrd-code-snippets-ability-pack-0.1.0.zip --activate --path="$WP_PATH"
wp plugin get wrd-code-snippets-ability-pack --field=version --path="$WP_PATH"
```

For an existing installation, compare the current files, back them up, and update the same plugin directory instead of creating a second copy. Use `wp plugin install <zip> --force --activate` only after confirming the slug and backup. The other packs are optional and remain independently installable.

## 4. Verify registration and behavior

Reconnect the MCP client if it caches its tool list. Discover abilities through Agent Connector and verify the selected namespace, for example `code-snippets/`. Execute `<namespace>/get-status` and one read-only ability. Confirm that the status reports the installed target version and the reviewed source version. Test a write preview with `dry_run: true` only after reading its input schema and revision source.

Verify the site's frontend and relevant WordPress administration page after activation. Keep the backup until the installation has passed these checks. If an operation reports a version mismatch but its native capability checks pass, the mismatch is informational; if a native capability is missing, stop that operation and review the pack against the installed target source.

## 5. Use write abilities deliberately

For changes, read fresh state, supply the returned `expected_revision`, include a meaningful `reason`, inspect the default preview, and set `dry_run: false` only for the intended action. Some operations have extra acknowledgements, such as enabling executable snippets or starting a backup. After a write, read the state again. If a response is uncertain, reconcile the current state before retrying.
