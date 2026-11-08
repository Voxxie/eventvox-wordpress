<?php
/**
 * The public EventVOX API (no login), called from this server, not from the visitor's browser. Answers are kept a few
 * minutes in a transient, so a busy page does not ask EventVOX for every visitor; a failure is kept one minute.
 */

if (!defined('ABSPATH')) {
    exit;
}

class EventVOX_API
{
    const PREFIX = 'eventvox_api_';

    /**
     * GET a path of the API, e.g. /public/pickups/{tenant}. Returns the decoded JSON array or a WP_Error (code
     * eventvox_http with the status in its data, or the transport error).
     */
    public static function get($path, $ttl = 300)
    {
        $url = untrailingslashit(EventVOX_Settings::get('api_url')) . $path;
        $key = self::PREFIX . md5($url);
        $cached = get_transient($key);
        if (is_array($cached)) {
            return isset($cached['error']) ? new WP_Error('eventvox_http', $cached['error'], ['status' => $cached['status']]) : $cached['body'];
        }

        $response = wp_remote_get($url, [
            'timeout' => 8,
            'headers' => ['Accept' => 'application/json'],
            'user-agent' => 'EventVOX-WordPress/' . EVENTVOX_VERSION . '; ' . home_url('/'),
        ]);
        if (is_wp_error($response)) {
            set_transient($key, ['error' => $response->get_error_message(), 'status' => 0], MINUTE_IN_SECONDS);
            return $response;
        }
        $status = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if ($status !== 200 || !is_array($body)) {
            $message = is_array($body) && isset($body['error']) ? (string) $body['error'] : sprintf('HTTP %d', $status);
            set_transient($key, ['error' => $message, 'status' => $status], MINUTE_IN_SECONDS);
            return new WP_Error('eventvox_http', $message, ['status' => $status]);
        }
        set_transient($key, ['body' => $body], $ttl);
        return $body;
    }

    public static function is_not_found($error)
    {
        return is_wp_error($error) && (int) (($error->get_error_data() ?: [])['status'] ?? 0) === 404;
    }

    /** Forget every cached answer (after changing the settings, and on uninstall) */
    public static function flush()
    {
        global $wpdb;
        $like = $wpdb->esc_like('_transient_' . self::PREFIX) . '%';
        $timeouts = $wpdb->esc_like('_transient_timeout_' . self::PREFIX) . '%';
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $like, $timeouts));
        // With an object cache the transients are not in the options table: they run out by themselves within minutes
    }
}
