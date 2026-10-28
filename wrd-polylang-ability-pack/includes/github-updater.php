<?php
/** GitHub release updates for independently installed We.Rock ability packs. */
declare(strict_types=1);
namespace WeRock\AbilityPackUpdater;

defined('ABSPATH') || exit;

if (!class_exists(__NAMESPACE__ . '\\Updater', false)) {
    final class Updater
    {
        private const REPOSITORY = 'wirrockendigital/We.Rock.WP.Ability.Packs';
        private const API = 'https://api.github.com/repos/' . self::REPOSITORY;
        private const WEB = 'https://github.com/' . self::REPOSITORY;
        private static array $plugins = [];

        /** Register one installed pack without affecting updates for other plugins. */
        public static function register(string $mainFile, string $slug): void
        {
            // Schema exporters load plugin entry points without WordPress bootstrap.
            if (!function_exists('plugin_basename') || !function_exists('add_filter')) {
                return;
            }
            $pluginFile = plugin_basename($mainFile);
            self::$plugins[$pluginFile] = $slug;
            if (!has_filter('update_plugins_github.com', [self::class, 'check'])) {
                add_filter('update_plugins_github.com', [self::class, 'check'], 10, 4);
            }
        }

        /** Return a WordPress update only for this repository and an exact pack identity. */
        public static function check($update, array $pluginData, string $pluginFile, array $locales)
        {
            $slug = self::$plugins[$pluginFile] ?? null;
            if ($slug === null || ($pluginData['UpdateURI'] ?? '') !== self::updateUri($slug)) {
                return $update;
            }

            $release = self::latestRelease($slug);
            if ($release === null) {
                return false;
            }

            return [
                'id' => self::updateUri($slug),
                'slug' => $slug,
                'version' => $release['version'],
                'url' => $release['url'],
                'package' => $release['package'],
                'requires_php' => '8.1',
            ];
        }

        /** Keep the update identifier stable for the lifetime of a pack. */
        private static function updateUri(string $slug): string
        {
            return self::WEB . '/releases?q=' . $slug;
        }

        /** Discover the highest published, stable release with the expected ZIP asset. */
        private static function latestRelease(string $slug): ?array
        {
            $key = 'wrd_pack_update_' . md5($slug);
            $cached = get_site_transient($key);
            if (is_array($cached)) {
                return $cached['release'] ?? null;
            }

            $refs = self::fetch(self::API . '/git/matching-refs/tags/' . $slug . '-v');
            if (!is_array($refs)) {
                return null;
            }

            $versions = [];
            foreach ($refs as $ref) {
                $name = is_array($ref) ? ($ref['ref'] ?? '') : '';
                if (is_string($name) && preg_match('~^refs/tags/' . preg_quote($slug, '~') . '-v(\d+\.\d+\.\d+)$~', $name, $matches)) {
                    $versions[] = $matches[1];
                }
            }
            usort($versions, static fn(string $a, string $b): int => version_compare($b, $a));

            foreach ($versions as $version) {
                $tag = $slug . '-v' . $version;
                $release = self::fetch(self::API . '/releases/tags/' . $tag);
                if (!is_array($release) || ($release['tag_name'] ?? '') !== $tag || !empty($release['draft']) || !empty($release['prerelease'])) {
                    continue;
                }
                $assetName = $slug . '-' . $version . '.zip';
                foreach (($release['assets'] ?? []) as $asset) {
                    $package = is_array($asset) ? ($asset['browser_download_url'] ?? '') : '';
                    $expected = self::WEB . '/releases/download/' . $tag . '/' . $assetName;
                    if (($asset['name'] ?? '') === $assetName && ($asset['state'] ?? '') === 'uploaded' && ($asset['size'] ?? 0) > 0 && $package === $expected) {
                        $result = ['version' => $version, 'url' => self::WEB . '/releases/tag/' . $tag, 'package' => $package];
                        set_site_transient($key, ['release' => $result], 6 * HOUR_IN_SECONDS);
                        return $result;
                    }
                }
            }

            set_site_transient($key, ['release' => null], 15 * MINUTE_IN_SECONDS);
            return null;
        }

        /** Accept only successful JSON responses from the fixed public GitHub API. */
        private static function fetch(string $url): ?array
        {
            $response = wp_remote_get($url, [
                'timeout' => 8,
                'redirection' => 0,
                'headers' => ['Accept' => 'application/vnd.github+json', 'User-Agent' => 'WeRock-Ability-Pack-Updater'],
            ]);
            if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
                return null;
            }
            $body = json_decode(wp_remote_retrieve_body($response), true);
            return is_array($body) ? $body : null;
        }
    }
}
