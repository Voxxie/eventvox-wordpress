<?php
/**
 * Blocks for the block editor (search for "EventVOX"), drawn on the server like the shortcodes, so there is no build
 * step: assets/blocks.js only gives the editor a preview and the settings in the side panel. The iframes show a
 * placeholder in the editor; the lists their real content.
 */

if (!defined('ABSPATH')) {
    exit;
}

class EventVOX_Blocks
{
    /** name => [title, description, icon, attributes, render] */
    private static function blocks()
    {
        $height = function ($default) {
            return ['height' => ['type' => 'number', 'default' => $default, 'label' => __('Starthoogte (px)', 'eventvox')]];
        };
        $limit = ['limit' => ['type' => 'number', 'default' => 5, 'label' => __('Aantal', 'eventvox')]];
        $button = ['button' => ['type' => 'string', 'default' => '', 'label' => __('Tekst van de knop (leeg: standaard)', 'eventvox')]];
        return [
            'webshop' => [__('EventVOX webshop', 'eventvox'), __('De webshop in een iframe die meegroeit met de inhoud.', 'eventvox'), 'cart',
                $height(900), function ($a) { return EventVOX_Embeds::render('webshop', $a); }],
            'vrijwilligers' => [__('EventVOX vrijwilligers', 'eventvox'), __('De aanmeldpagina voor vrijwilligers in een iframe.', 'eventvox'), 'groups',
                $height(700), function ($a) { return EventVOX_Embeds::render('vrijwilligers', $a); }],
            'pickup' => [__('EventVOX Pick-Up formulier', 'eventvox'), __('Het Pick-Up aanmeldformulier in een iframe.', 'eventvox'), 'car',
                $height(800), function ($a) { return EventVOX_Embeds::render('pickup', $a); }],
            'events' => [__('EventVOX komende events', 'eventvox'), __('De komende events uit de webshop.', 'eventvox'), 'calendar-alt',
                $limit + $button, function ($a) { return EventVOX_Lists::events($a); }],
            'pickup-rondes' => [__('EventVOX Pick-Up rondes', 'eventvox'), __('De Pick-Up rondes met plaats, met een aanmeldknop.', 'eventvox'), 'clock',
                $limit + $button, function ($a) { return EventVOX_Lists::pickups($a); }],
            'vrijwilliger-worden' => [__('EventVOX vrijwilliger worden', 'eventvox'), __('Een oproep om vrijwilliger te worden.', 'eventvox'), 'heart',
                ['text' => ['type' => 'string', 'default' => '', 'label' => __('Tekst (leeg: de tekst uit EventVOX)', 'eventvox'), 'multiline' => true]] + $button,
                function ($a) { return EventVOX_Lists::volunteer($a); }],
        ];
    }

    public static function init()
    {
        add_action('init', [__CLASS__, 'register']);
    }

    public static function register()
    {
        if (!function_exists('register_block_type')) {
            return;
        }
        wp_register_script('eventvox-blocks', EVENTVOX_URL . 'assets/blocks.js',
            ['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render', 'wp-i18n'], EVENTVOX_VERSION, true);
        wp_register_style('eventvox', EVENTVOX_URL . 'assets/eventvox.css', [], EVENTVOX_VERSION);

        $client = [];
        foreach (self::blocks() as $name => [$title, $description, $icon, $attributes, $render]) {
            $schema = [];
            foreach ($attributes as $key => $attribute) {
                $schema[$key] = ['type' => $attribute['type'], 'default' => $attribute['default']];
            }
            register_block_type('eventvox/' . $name, [
                'api_version' => 2,
                'title' => $title,
                'description' => $description,
                'category' => 'widgets',
                'icon' => $icon,
                'keywords' => ['eventvox'],
                'attributes' => $schema,
                'supports' => ['html' => false],
                'editor_script' => 'eventvox-blocks',
                'editor_style' => 'eventvox',
                'render_callback' => $render,
            ]);
            $client[] = ['name' => 'eventvox/' . $name, 'title' => $title, 'description' => $description, 'icon' => $icon,
                'attributes' => $attributes, 'frame' => in_array($name, ['webshop', 'vrijwilligers', 'pickup'], true)];
        }
        wp_add_inline_script('eventvox-blocks', 'window.eventvoxBlocks = ' . wp_json_encode([
            'blocks' => $client,
            'configured' => EventVOX_Settings::tenant() !== '',
            'settingsUrl' => admin_url('options-general.php?page=eventvox'),
            'frameNote' => __('Op de pagina staat hier de EventVOX-pagina zelf; die groeit mee met de inhoud.', 'eventvox'),
            'setupNote' => __('Vul eerst het organisatie-ID in bij Instellingen > EventVOX.', 'eventvox'),
            'settingsTitle' => __('Instellingen', 'eventvox'),
        ]) . ';', 'before');
    }
}
