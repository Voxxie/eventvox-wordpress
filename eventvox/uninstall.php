<?php
/** Removing the plugin (Plugins > Verwijderen): its settings and cached answers go too. */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('eventvox_settings');
delete_site_transient('eventvox_release');
delete_option('widget_eventvox_events');
delete_option('widget_eventvox_pickups');
delete_option('widget_eventvox_volunteer');

global $wpdb;
$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
    $wpdb->esc_like('_transient_eventvox_api_') . '%', $wpdb->esc_like('_transient_timeout_eventvox_api_') . '%'));
