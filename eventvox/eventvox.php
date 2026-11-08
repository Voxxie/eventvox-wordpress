<?php
/**
 * Plugin Name:       EventVOX
 * Plugin URI:        https://github.com/Voxxie/eventvox-wordpress
 * Description:       Zet de EventVOX-webshop, de aanmeldpagina voor vrijwilligers en het Pick-Up formulier op je site, met widgets voor komende events, Pick-Up rondes en vrijwilligers.
 * Version:           0.1.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            EventVOX
 * Author URI:        https://www.eventvox.nl
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       eventvox
 * Update URI:        https://github.com/Voxxie/eventvox-wordpress
 *
 * Updates come from the GitHub releases of Voxxie/eventvox-wordpress (includes/class-updater.php), not from
 * wordpress.org: the Update URI keeps wordpress.org from offering another plugin called "eventvox" in its place.
 * The release workflow (.github/workflows/release.yml) sets the version above from the release tag.
 */

if (!defined('ABSPATH')) {
    exit;
}

define('EVENTVOX_VERSION', '0.1.0');
define('EVENTVOX_FILE', __FILE__);
define('EVENTVOX_DIR', plugin_dir_path(__FILE__));
define('EVENTVOX_URL', plugin_dir_url(__FILE__));

require_once EVENTVOX_DIR . 'includes/class-settings.php';
require_once EVENTVOX_DIR . 'includes/class-api.php';
require_once EVENTVOX_DIR . 'includes/class-embeds.php';
require_once EVENTVOX_DIR . 'includes/class-lists.php';
require_once EVENTVOX_DIR . 'includes/class-widgets.php';
require_once EVENTVOX_DIR . 'includes/class-blocks.php';
require_once EVENTVOX_DIR . 'includes/class-updater.php';

EventVOX_Settings::init();
EventVOX_Embeds::init();
EventVOX_Lists::init();
EventVOX_Widgets::init();
EventVOX_Blocks::init();
EventVOX_Updater::init();

// A link to the settings in the list of plugins
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
    array_unshift($links, '<a href="' . esc_url(admin_url('options-general.php?page=eventvox')) . '">' . esc_html__('Instellingen', 'eventvox') . '</a>');
    return $links;
});
