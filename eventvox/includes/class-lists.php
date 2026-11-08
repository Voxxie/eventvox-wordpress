<?php
/**
 * The small blocks of EventVOX information for a page or a sidebar, drawn on this server from the public API:
 *
 * [eventvox_events aantal="5" knop="Bekijk alle events"]  coming events on sale, each linking to the shop
 * [eventvox_pickup_rondes aantal="5"]                     Pick-Up rounds with places left, with a sign-up button
 * [eventvox_vrijwilliger_worden tekst="..."]              the organisation's text for new volunteers, with a button
 *
 * The same output in the widgets (class-widgets.php) and the blocks (class-blocks.php). Links go to the pages of this
 * site set in Instellingen > EventVOX, or else to EventVOX itself. Nothing to show: the shortcode shows nothing (for a
 * site manager a short note why).
 */

if (!defined('ABSPATH')) {
    exit;
}

class EventVOX_Lists
{
    // EventVOX keeps its dates and times in Dutch time
    const TIMEZONE = 'Europe/Amsterdam';

    public static function init()
    {
        add_shortcode('eventvox_events', function ($atts) {
            $atts = shortcode_atts(['aantal' => 5, 'knop' => ''], $atts, 'eventvox_events');
            return self::events(['limit' => $atts['aantal'], 'button' => $atts['knop']]);
        });
        add_shortcode('eventvox_pickup_rondes', function ($atts) {
            $atts = shortcode_atts(['aantal' => 5, 'knop' => ''], $atts, 'eventvox_pickup_rondes');
            return self::pickups(['limit' => $atts['aantal'], 'button' => $atts['knop']]);
        });
        add_shortcode('eventvox_vrijwilliger_worden', function ($atts) {
            $atts = shortcode_atts(['tekst' => '', 'knop' => ''], $atts, 'eventvox_vrijwilliger_worden');
            return self::volunteer(['text' => $atts['tekst'], 'button' => $atts['knop']]);
        });
    }

    private static function limit($value)
    {
        return max(1, min(20, (int) $value ?: 5));
    }

    /** "za 14 nov 2026, 20:30" or with an end on the same day "za 14 nov 2026, 20:30–23:30" (in the site's language) */
    public static function when($start, $end = null, $with_time = true)
    {
        $tz = new DateTimeZone(self::TIMEZONE);
        try {
            $from = new DateTimeImmutable((string) $start, $tz);
        } catch (Exception $e) {
            return '';
        }
        $text = wp_date('D j M Y', $from->getTimestamp(), $tz);
        if ($with_time) {
            $text .= ', ' . wp_date('H:i', $from->getTimestamp(), $tz);
        }
        if ($end) {
            try {
                $to = new DateTimeImmutable((string) $end, $tz);
                if ($to->format('Y-m-d') === $from->format('Y-m-d')) {
                    $text .= $with_time ? '–' . wp_date('H:i', $to->getTimestamp(), $tz) : '';
                } else {
                    $text .= ' – ' . wp_date('D j M Y', $to->getTimestamp(), $tz);
                }
            } catch (Exception $e) {
                // Only the start then
            }
        }
        return $text;
    }

    /** For a site manager only: why there is nothing (visitors see nothing) */
    private static function problem($error)
    {
        if (!current_user_can('manage_options')) {
            return '';
        }
        $text = EventVOX_API::is_not_found($error)
            ? __('EventVOX: dit onderdeel staat uit bij je organisatie, of het organisatie-ID klopt niet.', 'eventvox')
            /* translators: %s: error message */
            : sprintf(__('EventVOX is niet bereikbaar: %s', 'eventvox'), $error->get_error_message());
        return '<p class="eventvox-notice">' . esc_html($text) . '</p>';
    }

    private static function button($url, $label)
    {
        return sprintf('<p class="eventvox-actions"><a class="eventvox-button wp-element-button" href="%s">%s</a></p>', esc_url($url), esc_html($label));
    }

    private static function wrap($kind, $content)
    {
        wp_enqueue_style('eventvox');
        return '<div class="eventvox eventvox-' . esc_attr($kind) . '">' . $content . '</div>';
    }

    public static function events(array $args = [])
    {
        $tenant = EventVOX_Settings::tenant();
        if ($tenant === '') {
            return EventVOX_Embeds::setup_hint();
        }
        $answer = EventVOX_API::get('/public/shop/' . $tenant . '/events?limit=' . self::limit($args['limit'] ?? 5));
        if (is_wp_error($answer)) {
            return self::problem($answer);
        }
        $events = $answer['data'] ?? [];
        $page = EventVOX_Settings::page_link('shop_page');
        if (!$events) {
            return self::wrap('events', '<p class="eventvox-empty">' . esc_html__('Er staan op dit moment geen events in de verkoop.', 'eventvox') . '</p>');
        }
        $items = '';
        foreach ($events as $event) {
            $uuid = (string) ($event['event_uuid'] ?? '');
            // To the shop page of this site with the event, or to the EventVOX shop itself
            $link = $page ? add_query_arg('evx_event', rawurlencode($uuid), $page)
                : EventVOX_Settings::app_link('shop/buy', ['t' => $tenant, 'event' => $uuid]);
            $start = $event['startdatetime'] ?? null;
            $items .= '<li class="eventvox-item">'
                . (!empty($event['image_url']) && wp_parse_url($event['image_url'], PHP_URL_SCHEME) === 'https'
                    ? '<img class="eventvox-image" src="' . esc_url($event['image_url']) . '" alt="" loading="lazy">' : '')
                . '<div class="eventvox-text">'
                . '<a class="eventvox-title" href="' . esc_url($link) . '">' . esc_html($event['name'] ?? '') . '</a>'
                . ($start ? '<span class="eventvox-meta">' . esc_html(self::when($start, $event['enddatetime'] ?? null)) . '</span>' : '')
                . (!empty($event['location']) ? '<span class="eventvox-meta">' . esc_html($event['location']) . '</span>' : '')
                . '</div></li>';
        }
        $label = trim((string) ($args['button'] ?? '')) ?: __('Naar de webshop', 'eventvox');
        return self::wrap('events', '<ul class="eventvox-list">' . $items . '</ul>'
            . self::button($page ?: EventVOX_Settings::app_link('shop/', ['t' => $tenant]), $label));
    }

    public static function pickups(array $args = [])
    {
        $tenant = EventVOX_Settings::tenant();
        if ($tenant === '') {
            return EventVOX_Embeds::setup_hint();
        }
        $answer = EventVOX_API::get('/public/pickups/' . $tenant, 120);
        if (is_wp_error($answer)) {
            return self::problem($answer);
        }
        $rounds = array_slice($answer['rounds'] ?? [], 0, self::limit($args['limit'] ?? 5));
        $link = EventVOX_Settings::page_link('pickup_page') ?: EventVOX_Settings::app_link('pickup-signup', ['tenant' => $tenant]);
        if (!$rounds) {
            return self::wrap('pickup', '<p class="eventvox-empty">' . esc_html__('Er zijn op dit moment geen ophaalrondes met plaats. Kijk later nog eens.', 'eventvox') . '</p>');
        }
        $items = '';
        foreach ($rounds as $round) {
            $left = (int) ($round['places_left'] ?? 0);
            $items .= '<li class="eventvox-item"><div class="eventvox-text">'
                . '<span class="eventvox-title">' . esc_html(self::when($round['starts_at'] ?? '', $round['ends_at'] ?? null)) . '</span>'
                . (!empty($round['note']) ? '<span class="eventvox-meta">' . esc_html($round['note']) . '</span>' : '')
                /* translators: %d: number of places */
                . '<span class="eventvox-meta">' . esc_html(sprintf(_n('Nog %d plaats', 'Nog %d plaatsen', $left, 'eventvox'), $left)) . '</span>'
                . '</div></li>';
        }
        $label = trim((string) ($args['button'] ?? '')) ?: __('Aanmelden', 'eventvox');
        return self::wrap('pickup', '<ul class="eventvox-list">' . $items . '</ul>' . self::button($link, $label));
    }

    public static function volunteer(array $args = [])
    {
        $tenant = EventVOX_Settings::tenant();
        if ($tenant === '') {
            return EventVOX_Embeds::setup_hint();
        }
        $answer = EventVOX_API::get('/public/shop/' . $tenant);
        if (is_wp_error($answer)) {
            return self::problem($answer);
        }
        if (empty($answer['volunteer_signup'])) {
            // Signing up as a volunteer is off in EventVOX: no call to action that leads nowhere
            return current_user_can('manage_options')
                ? '<p class="eventvox-notice">' . esc_html__('EventVOX: aanmelden als vrijwilliger staat uit (Webshop > Extra).', 'eventvox') . '</p>'
                : '';
        }
        $text = trim((string) ($args['text'] ?? '')) ?: (string) ($answer['volunteer_intro'] ?? '');
        if ($text === '') {
            /* translators: %s: organisation name */
            $text = sprintf(__('Help je mee bij %s? Meld je aan als vrijwilliger.', 'eventvox'), $answer['name'] ?? '');
        }
        $link = EventVOX_Settings::page_link('volunteer_page') ?: EventVOX_Settings::app_link('shop/volunteer', ['t' => $tenant]);
        $label = trim((string) ($args['button'] ?? '')) ?: __('Word vrijwilliger', 'eventvox');
        return self::wrap('volunteer', '<div class="eventvox-intro">' . wpautop(esc_html($text)) . '</div>' . self::button($link, $label));
    }
}
