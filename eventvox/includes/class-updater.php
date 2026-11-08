<?php
/**
 * Updates from the GitHub releases of Voxxie/eventvox-wordpress, the way WordPress shows them for wordpress.org plugins:
 * "Er is een nieuwe versie beschikbaar" at Plugins, Dashboard > Updates, and automatic updates when switched on.
 *
 * - The plugin header has "Update URI: https://github.com/Voxxie/eventvox-wordpress"; WordPress (5.8+) then asks the
 *   filter update_plugins_github.com for this plugin instead of wordpress.org.
 * - The answer comes from https://api.github.com/repos/Voxxie/eventvox-wordpress/releases/latest (the newest release
 *   that is not a draft or pre-release), kept 6 hours; "Opnieuw controleren" at Dashboard > Updates asks again.
 * - The release must carry the asset eventvox.zip (made by .github/workflows/release.yml) with the folder eventvox/
 *   in it; a release without it is not offered. The version is the tag without a leading v (v1.2.0 -> 1.2.0).
 * - "Details bekijken" shows the release notes (plugins_api).
 *
 * The repository must be public: GitHub answers 404 to anyone else.
 */

if (!defined('ABSPATH')) {
    exit;
}

class EventVOX_Updater
{
    const REPO = 'Voxxie/eventvox-wordpress';
    const ASSET = 'eventvox.zip';
    const SLUG = 'eventvox';
    const CACHE = 'eventvox_release';

    public static function init()
    {
        add_filter('update_plugins_github.com', [__CLASS__, 'check'], 10, 3);
        add_filter('plugins_api', [__CLASS__, 'details'], 20, 3);
        // After an update: ask GitHub again next time instead of offering the version just installed
        add_action('upgrader_process_complete', function () {
            delete_site_transient(self::CACHE);
        });
    }

    /** The newest release as [version, package, url, notes, published], or null (none, no zip, GitHub not reachable) */
    public static function release($fresh = false)
    {
        $cached = get_site_transient(self::CACHE);
        if (!$fresh && is_array($cached)) {
            return $cached['release'];
        }
        $response = wp_remote_get('https://api.github.com/repos/' . self::REPO . '/releases/latest', [
            'timeout' => 10,
            'headers' => ['Accept' => 'application/vnd.github+json'],
            'user-agent' => 'EventVOX-WordPress/' . EVENTVOX_VERSION,
        ]);
        $release = null;
        if (!is_wp_error($response) && (int) wp_remote_retrieve_response_code($response) === 200) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            $version = is_array($body) ? ltrim((string) ($body['tag_name'] ?? ''), 'vV') : '';
            $package = null;
            foreach ((is_array($body) ? ($body['assets'] ?? []) : []) as $asset) {
                if (($asset['name'] ?? '') === self::ASSET && !empty($asset['browser_download_url'])) {
                    $package = $asset['browser_download_url'];
                }
            }
            if (preg_match('/^\d+(\.\d+){0,3}([-.][0-9A-Za-z.]+)?$/', $version) && $package) {
                $release = [
                    'version' => $version,
                    'package' => $package,
                    'url' => (string) ($body['html_url'] ?? 'https://github.com/' . self::REPO),
                    'notes' => (string) ($body['body'] ?? ''),
                    'published' => (string) ($body['published_at'] ?? ''),
                ];
            }
        }
        // Not reachable or nothing usable: try again in an hour instead of at every page
        set_site_transient(self::CACHE, ['release' => $release], $release ? 6 * HOUR_IN_SECONDS : HOUR_IN_SECONDS);
        return $release;
    }

    /** update_plugins_github.com: what WordPress needs to offer (or not offer) an update for this plugin */
    public static function check($update, $plugin_data, $plugin_file)
    {
        if ($plugin_file !== plugin_basename(EVENTVOX_FILE)) {
            return $update;
        }
        // Dashboard > Updates > Opnieuw controleren
        $fresh = is_admin() && isset($_GET['force-check']) && current_user_can('update_plugins');
        $release = self::release($fresh);
        if (!$release) {
            return $update;
        }
        // WordPress itself compares the version with the installed one and offers the update only when it is newer
        return [
            'id' => 'github.com/' . self::REPO,
            'slug' => self::SLUG,
            'plugin' => $plugin_file,
            'version' => $release['version'],
            'url' => 'https://github.com/' . self::REPO,
            'package' => $release['package'],
            'requires_php' => '7.4',
            'icons' => [],
            'banners' => [],
        ];
    }

    /** plugins_api: the "Details bekijken" window */
    public static function details($result, $action, $args)
    {
        if ($action !== 'plugin_information' || ($args->slug ?? '') !== self::SLUG) {
            return $result;
        }
        $release = self::release();
        $data = get_plugin_data(EVENTVOX_FILE, false, false);
        return (object) [
            'name' => $data['Name'],
            'slug' => self::SLUG,
            'version' => $release['version'] ?? EVENTVOX_VERSION,
            'author' => $data['Author'],
            'homepage' => 'https://github.com/' . self::REPO,
            'requires' => '5.8',
            'requires_php' => '7.4',
            'last_updated' => $release['published'] ?? '',
            'download_link' => $release['package'] ?? '',
            'sections' => [
                'description' => esc_html($data['Description']),
                'changelog' => $release
                    ? '<h4>' . esc_html($release['version']) . '</h4>' . wpautop(esc_html($release['notes'] ?: __('Zie de release op GitHub.', 'eventvox')))
                        . '<p><a href="' . esc_url($release['url']) . '" target="_blank" rel="noopener">GitHub</a></p>'
                    : esc_html__('Geen release gevonden.', 'eventvox'),
            ],
        ];
    }
}
