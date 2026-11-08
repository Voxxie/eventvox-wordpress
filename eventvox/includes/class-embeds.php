<?php
/**
 * The pages of EventVOX itself in an iframe: the webshop, the volunteer page and the Pick-Up form. They stay EventVOX
 * pages (logging in, paying and signing up happen there); assets/embed.js lets the frame grow with what is in it, as
 * EventVOX's own embed code does (messages { source: 'eventvox-shop', type: 'height' | 'top' } from the EventVOX
 * address only).
 *
 * [eventvox_webshop hoogte="900"]       the shop; with ?evx_event=<event uuid> in the page address it opens that event
 * [eventvox_vrijwilligers hoogte="700"] the volunteer sign-up page of the shop
 * [eventvox_pickup hoogte="800"]        the Pick-Up sign-up form (/pickup-embed/)
 */

if (!defined('ABSPATH')) {
    exit;
}

class EventVOX_Embeds
{
    const KINDS = [
        'webshop' => ['height' => 900],
        'vrijwilligers' => ['height' => 700],
        'pickup' => ['height' => 800],
    ];

    public static function init()
    {
        add_action('wp_enqueue_scripts', [__CLASS__, 'register_assets']);
        foreach (array_keys(self::KINDS) as $kind) {
            add_shortcode('eventvox_' . $kind, function ($atts) use ($kind) {
                $atts = shortcode_atts(['hoogte' => self::KINDS[$kind]['height'], 'titel' => ''], $atts, 'eventvox_' . $kind);
                return self::render($kind, ['height' => $atts['hoogte'], 'title' => $atts['titel']]);
            });
        }
    }

    public static function register_assets()
    {
        wp_register_script('eventvox-embed', EVENTVOX_URL . 'assets/embed.js', [], EVENTVOX_VERSION, true);
        wp_register_style('eventvox', EVENTVOX_URL . 'assets/eventvox.css', [], EVENTVOX_VERSION);
    }

    /** The address in the frame, or null without an organisation ID */
    public static function src($kind)
    {
        $tenant = EventVOX_Settings::tenant();
        if ($tenant === '') {
            return null;
        }
        if ($kind === 'pickup') {
            return EventVOX_Settings::app_link('pickup-embed/', ['tenant' => $tenant]);
        }
        if ($kind === 'vrijwilligers') {
            return EventVOX_Settings::app_link('shop/volunteer', ['t' => $tenant]);
        }
        // A widget's link to one event: open the shop at that event
        $event = isset($_GET['evx_event']) ? sanitize_text_field(wp_unslash($_GET['evx_event'])) : '';
        if (EventVOX_Settings::is_uuid($event)) {
            return EventVOX_Settings::app_link('shop/buy', ['t' => $tenant, 'event' => strtolower($event)]);
        }
        return EventVOX_Settings::app_link('shop/', ['t' => $tenant]);
    }

    public static function render($kind, array $args = [])
    {
        $src = self::src($kind);
        if ($src === null) {
            return self::setup_hint();
        }
        $titles = [
            'webshop' => __('Webshop', 'eventvox'),
            'vrijwilligers' => __('Vrijwilliger worden', 'eventvox'),
            'pickup' => __('Aanmelden Pick-Up', 'eventvox'),
        ];
        $height = max(200, min(5000, (int) ($args['height'] ?? self::KINDS[$kind]['height']) ?: self::KINDS[$kind]['height']));
        $title = trim((string) ($args['title'] ?? '')) ?: $titles[$kind];
        $origin = self::origin(EventVOX_Settings::get('app_url'));

        wp_enqueue_script('eventvox-embed');
        return sprintf(
            '<iframe class="eventvox-frame eventvox-frame-%1$s" src="%2$s" title="%3$s" data-eventvox-origin="%4$s" style="width: 100%%; min-height: %5$dpx; border: 0; display: block"></iframe>',
            esc_attr($kind), esc_url($src), esc_attr($title), esc_attr($origin), $height
        );
    }

    /** scheme://host[:port] of an address: where the frame's messages come from */
    public static function origin($url)
    {
        $parts = wp_parse_url($url);
        if (empty($parts['scheme']) || empty($parts['host'])) {
            return '';
        }
        return $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
    }

    /** Only for whoever can fix it: the organisation ID is missing. Visitors see nothing. */
    public static function setup_hint()
    {
        if (!current_user_can('manage_options')) {
            return '';
        }
        return sprintf('<p class="eventvox-notice">%s <a href="%s">%s</a></p>',
            esc_html__('EventVOX: vul eerst het organisatie-ID in.', 'eventvox'),
            esc_url(admin_url('options-general.php?page=eventvox')),
            esc_html__('Instellingen', 'eventvox'));
    }
}
