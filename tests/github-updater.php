<?php
/** Exercise the GitHub updater without a WordPress site or live HTTP requests. */
declare(strict_types=1);

const ABSPATH = '/test/';
const HOUR_IN_SECONDS = 3600;
const MINUTE_IN_SECONDS = 60;
$GLOBALS['filters'] = [];
$GLOBALS['transients'] = [];
$GLOBALS['replies'] = [];
$GLOBALS['requests'] = [];

/** Keep plugin filenames stable in the WordPress filter simulation. */
function plugin_basename(string $file): string { return $file; }
/** Record filter registration without loading WordPress. */
function add_filter(string $hook, $callback, int $priority, int $arguments): void { $GLOBALS['filters'][$hook] = $callback; }
/** Report whether the updater already registered its single filter. */
function has_filter(string $hook, $callback): bool { return ($GLOBALS['filters'][$hook] ?? null) === $callback; }
/** Model the shared site transient store. */
function get_site_transient(string $key) { return $GLOBALS['transients'][$key] ?? false; }
/** Record cached results for repeat update checks. */
function set_site_transient(string $key, $value, int $expiration): void { $GLOBALS['transients'][$key] = $value; }
/** Return a deterministic response for one GitHub API URL. */
function wp_remote_get(string $url, array $args) { $GLOBALS['requests'][] = [$url, $args]; return $GLOBALS['replies'][$url] ?? ['code' => 404, 'body' => '']; }
/** Simulate WordPress HTTP error classification. */
function is_wp_error($response): bool { return false; }
/** Read a stubbed HTTP status. */
function wp_remote_retrieve_response_code(array $response): int { return $response['code']; }
/** Read a stubbed HTTP body. */
function wp_remote_retrieve_body(array $response): string { return $response['body']; }

require __DIR__ . '/../wrd-ninjafirewall-ability-pack/includes/github-updater.php';

/** Build a JSON GitHub API response. */
function reply($value): array { return ['code' => 200, 'body' => json_encode($value)]; }
/** Fail with a focused assertion message. */
function check(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }

$class = \WeRock\AbilityPackUpdater\Updater::class;
$slug = 'unofficial-abilities-for-ninjafirewall';
$repo = 'https://github.com/wirrockendigital/We.Rock.WP.Ability.Packs';
$api = 'https://api.github.com/repos/wirrockendigital/We.Rock.WP.Ability.Packs';
$file = $slug . '/unofficial-abilities-for-ninjafirewall.php';
$uri = $repo . '/releases?q=' . $slug;
$class::register($file, $slug);
check(isset($GLOBALS['filters']['update_plugins_github.com']), 'WordPress update filter missing');

// An unrelated plugin or a mismatched update URI must never receive this pack's update.
check($class::check('other', ['UpdateURI' => $uri], 'other/main.php', []) === 'other', 'Unrelated plugin changed');
check($class::check('other', ['UpdateURI' => 'https://github.com/elsewhere'], $file, []) === 'other', 'Mismatched URI changed');
check(count($GLOBALS['requests']) === 0, 'Unrelated plugin made a network request');

// The highest complete stable release supplies the exact ZIP, while an incomplete tag is ignored.
$tag1 = $slug . '-v0.1.1';
$tag2 = $slug . '-v0.1.2';
$GLOBALS['replies'][$api . '/git/matching-refs/tags/' . $slug . '-v'] = reply([
    ['ref' => 'refs/tags/' . $tag1],
    ['ref' => 'refs/tags/' . $tag2],
    ['ref' => 'refs/tags/another-pack-v9.9.9'],
]);
$GLOBALS['replies'][$api . '/releases/tags/' . $tag2] = reply([
    'tag_name' => $tag2, 'draft' => false, 'prerelease' => true, 'assets' => [],
]);
$asset = $slug . '-0.1.1.zip';
$package = $repo . '/releases/download/' . $tag1 . '/' . $asset;
$GLOBALS['replies'][$api . '/releases/tags/' . $tag1] = reply([
    'tag_name' => $tag1, 'draft' => false, 'prerelease' => false,
    'assets' => [['name' => $asset, 'state' => 'uploaded', 'size' => 1024, 'browser_download_url' => $package]],
]);
$found = $class::check(false, ['UpdateURI' => $uri], $file, []);
check(is_array($found) && $found['version'] === '0.1.1', 'Stable pack version not selected');
check($found['package'] === $package && $found['slug'] === $slug, 'Wrong ZIP or pack identity');
check($found['id'] === $uri, 'Update URI changed');
$requestCount = count($GLOBALS['requests']);
$class::check(false, ['UpdateURI' => $uri], $file, []);
check(count($GLOBALS['requests']) === $requestCount, 'Release result was not cached');

// A foreign download URL must fail closed even if the release tag and asset name match.
$badSlug = 'wrd-code-snippets-ability-pack';
$badFile = $badSlug . '/main.php';
$badUri = $repo . '/releases?q=' . $badSlug;
$badTag = $badSlug . '-v0.2.0';
$class::register($badFile, $badSlug);
$GLOBALS['replies'][$api . '/git/matching-refs/tags/' . $badSlug . '-v'] = reply([['ref' => 'refs/tags/' . $badTag]]);
$GLOBALS['replies'][$api . '/releases/tags/' . $badTag] = reply([
    'tag_name' => $badTag, 'draft' => false, 'prerelease' => false,
    'assets' => [['name' => $badSlug . '-0.2.0.zip', 'state' => 'uploaded', 'size' => 1024, 'browser_download_url' => 'https://invalid.example/pack.zip']],
]);
check($class::check(false, ['UpdateURI' => $badUri], $badFile, []) === false, 'Foreign ZIP accepted');
check(count($GLOBALS['filters']) === 1, 'Updater installed duplicate filters');

echo "PASS: isolated pack updates, stable release selection, cache and ZIP validation\n";
