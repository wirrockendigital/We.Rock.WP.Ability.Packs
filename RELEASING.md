# Releasing one ability pack

Each pack has an independent SemVer version in its WordPress plugin header and `pack.json`. The GitHub release tag for one pack is `<pack-slug>-v<version>`. The release asset is `<pack-slug>-<version>.zip`, with the same plugin directory as the existing installation. NinjaFirewall uses `unofficial-abilities-for-ninjafirewall` as its pack slug and plugin directory.

When a pack is ready for release:

1. Update only that pack's plugin header and `pack.json` to the new version.
2. Run the repository's local validation checks and publish the reviewed commit.
3. Create and push the exact pack tag, for example `wrd-code-snippets-ability-pack-v0.1.1`.
4. Confirm that the **Release one ability pack** workflow passes and that its GitHub release contains `wrd-code-snippets-ability-pack-0.1.1.zip`.

The release workflow checks all packs, verifies that the tag matches the selected pack's header and manifest, packages only that pack, and publishes its ZIP. WordPress then discovers the new release for sites with the updater-enabled pack installed. Site owners control manual and automatic installation independently for each pack in the WordPress Plugins screen.
