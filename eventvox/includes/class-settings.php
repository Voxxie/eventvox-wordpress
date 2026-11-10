<?php
/**
 * Instellingen > EventVOX: the organisation ID (tenant UUID, from EventVOX: Webshop > Delen), the addresses of EventVOX
 * (only to change for a test environment) and the pages of this site that hold the webshop, the volunteer page and the
 * Pick-Up form (the widgets link there; without one they link to EventVOX itself). Kept in one option, eventvox_settings.
 */

if (!defined('ABSPATH')) {
    exit;
}

class EventVOX_Settings
{
    const OPTION = 'eventvox_settings';
    const DEFAULTS = [
        'tenant_uuid' => '',
        'app_url' => 'https://eventvox.nl',
        'api_url' => 'https://api.eventvox.nl',
        'shop_page' => 0,
        'volunteer_page' => 0,
        'pickup_page' => 0,
    ];
    // Former default addresses: saved by versions before 0.2 (they stored the default), cleared so the default above applies
    const OLD_DEFAULTS = [
        'app_url' => ['https://frontend.ingestel.nl'],
        'api_url' => ['https://backend.ingestel.nl'],
    ];
    // Which address migration has run (option eventvox_settings_version)
    const SETTINGS_VERSION = 2;

    public static function init()
    {
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_init', [__CLASS__, 'register']);
        add_action('admin_notices', [__CLASS__, 'notice']);
        add_action('init', [__CLASS__, 'migrate']);
    }

    /** The settings as saved (an address left empty: the default) */
    public static function saved()
    {
        $saved = get_option(self::OPTION, []);
        return array_merge(self::DEFAULTS, ['app_url' => '', 'api_url' => ''], is_array($saved) ? $saved : []);
    }

    /** All settings, with the defaults for what is not set */
    public static function all()
    {
        $all = self::saved();
        foreach (['app_url', 'api_url'] as $key) {
            if ((string) $all[$key] === '') {
                $all[$key] = self::DEFAULTS[$key];
            }
        }
        return $all;
    }

    /**
     * Once after an update: an address that is a former default (EventVOX moved to eventvox.nl) is cleared, so the
     * current default applies. An address someone entered on purpose (a test environment) stays.
     */
    public static function migrate()
    {
        if ((int) get_option('eventvox_settings_version', 0) >= self::SETTINGS_VERSION) {
            return;
        }
        $saved = get_option(self::OPTION, null);
        if (is_array($saved)) {
            $changed = false;
            foreach (self::OLD_DEFAULTS as $key => $olds) {
                $value = untrailingslashit(strtolower(trim((string) ($saved[$key] ?? ''))));
                if ($value !== '' && (in_array($value, $olds, true) || $value === self::DEFAULTS[$key])) {
                    $saved[$key] = '';
                    $changed = true;
                }
            }
            if ($changed) {
                update_option(self::OPTION, $saved);
                EventVOX_API::flush();
            }
        }
        update_option('eventvox_settings_version', self::SETTINGS_VERSION);
    }

    public static function get($key)
    {
        return self::all()[$key] ?? null;
    }

    public static function is_uuid($value)
    {
        return is_string($value) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value);
    }

    /** The organisation ID, or '' when it is not (properly) set */
    public static function tenant()
    {
        $tenant = strtolower((string) self::get('tenant_uuid'));
        return self::is_uuid($tenant) ? $tenant : '';
    }

    /** An address of the EventVOX app (shop/, shop/volunteer, pickup-embed/, pickup-signup), with the organisation in it */
    public static function app_link($path, array $query = [])
    {
        return add_query_arg(array_map('rawurlencode', $query), untrailingslashit(self::get('app_url')) . '/' . ltrim($path, '/'));
    }

    /** The page of this site with the given shortcode (shop_page, volunteer_page, pickup_page), or null */
    public static function page_link($key)
    {
        $id = (int) self::get($key);
        if ($id <= 0 || get_post_status($id) !== 'publish') {
            return null;
        }
        return get_permalink($id) ?: null;
    }

    public static function menu()
    {
        add_options_page(__('EventVOX', 'eventvox'), __('EventVOX', 'eventvox'), 'manage_options', 'eventvox', [__CLASS__, 'page']);
    }

    public static function register()
    {
        register_setting('eventvox', self::OPTION, ['type' => 'array', 'sanitize_callback' => [__CLASS__, 'sanitize'], 'default' => self::DEFAULTS]);
    }

    public static function sanitize($input)
    {
        $input = is_array($input) ? $input : [];
        $old = self::all();
        $out = self::saved();

        $tenant = strtolower(trim((string) ($input['tenant_uuid'] ?? '')));
        if ($tenant === '' || self::is_uuid($tenant)) {
            $out['tenant_uuid'] = $tenant;
        } else {
            add_settings_error(self::OPTION, 'tenant', __('Het organisatie-ID klopt niet. Kopieer het uit EventVOX: Webshop > Delen.', 'eventvox'));
        }

        foreach (['app_url', 'api_url'] as $key) {
            $url = untrailingslashit(esc_url_raw(trim((string) ($input[$key] ?? '')), ['https', 'http']));
            // The default (or nothing) is not stored, so a new default reaches this site with an update
            if ($url === '' || strtolower($url) === self::DEFAULTS[$key]) {
                $out[$key] = '';
            } elseif (wp_parse_url($url, PHP_URL_SCHEME) === 'https' || self::is_local($url)) {
                $out[$key] = $url;
            } else {
                add_settings_error(self::OPTION, $key, __('Een adres van EventVOX moet met https:// beginnen.', 'eventvox'));
            }
        }

        foreach (['shop_page', 'volunteer_page', 'pickup_page'] as $key) {
            $out[$key] = absint($input[$key] ?? 0);
        }

        // Other organisation or address: what is cached belongs to the old one
        if ($out['tenant_uuid'] !== $old['tenant_uuid'] || ($out['api_url'] ?: self::DEFAULTS['api_url']) !== $old['api_url']) {
            EventVOX_API::flush();
        }
        return $out;
    }

    /** http:// only for a test installation on this computer */
    private static function is_local($url)
    {
        return in_array(wp_parse_url($url, PHP_URL_HOST), ['localhost', '127.0.0.1'], true);
    }

    /** Until the organisation ID is set: a note on the dashboard and the plugin list */
    public static function notice()
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!current_user_can('manage_options') || self::tenant() !== '' || !$screen || !in_array($screen->id, ['dashboard', 'plugins'], true)) {
            return;
        }
        printf('<div class="notice notice-info"><p>%s <a href="%s">%s</a></p></div>',
            esc_html__('EventVOX is bijna klaar: vul het organisatie-ID in.', 'eventvox'),
            esc_url(admin_url('options-general.php?page=eventvox')),
            esc_html__('Naar de instellingen', 'eventvox'));
    }

    public static function page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        $s = self::all();
        $raw = self::saved();
        $name = self::OPTION;
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('EventVOX', 'eventvox'); ?></h1>
            <?php settings_errors(self::OPTION); ?>
            <?php self::status(); ?>
            <form method="post" action="options.php">
                <?php settings_fields('eventvox'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="eventvox-tenant"><?php esc_html_e('Organisatie-ID', 'eventvox'); ?></label></th>
                        <td>
                            <input type="text" class="regular-text code" id="eventvox-tenant" name="<?php echo esc_attr($name); ?>[tenant_uuid]" value="<?php echo esc_attr($s['tenant_uuid']); ?>"
                                placeholder="00000000-0000-0000-0000-000000000000" autocomplete="off" spellcheck="false">
                            <p class="description"><?php esc_html_e('In EventVOX bij Webshop > Delen, onder WordPress-plugin.', 'eventvox'); ?></p>
                        </td>
                    </tr>
                    <?php foreach (['shop_page' => [__('Pagina met de webshop', 'eventvox'), '[eventvox_webshop]'],
                        'volunteer_page' => [__('Pagina voor vrijwilligers', 'eventvox'), '[eventvox_vrijwilligers]'],
                        'pickup_page' => [__('Pagina met het Pick-Up formulier', 'eventvox'), '[eventvox_pickup]']] as $key => [$label, $code]) : ?>
                        <tr>
                            <th scope="row"><label for="eventvox-<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th>
                            <td>
                                <?php wp_dropdown_pages(['name' => $name . '[' . $key . ']', 'id' => 'eventvox-' . $key, 'selected' => (int) $s[$key],
                                    'show_option_none' => __('— Geen: naar EventVOX zelf —', 'eventvox'), 'option_none_value' => '0']); ?>
                                <p class="description"><?php
                                    /* translators: %s: a shortcode */
                                    printf(esc_html__('De pagina waar %s op staat. De widgets linken hiernaartoe.', 'eventvox'), '<code>' . esc_html($code) . '</code>'); ?></p>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
                <details>
                    <summary><?php esc_html_e('Geavanceerd', 'eventvox'); ?></summary>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="eventvox-app"><?php esc_html_e('Adres van EventVOX', 'eventvox'); ?></label></th>
                            <td><input type="url" class="regular-text code" id="eventvox-app" name="<?php echo esc_attr($name); ?>[app_url]" value="<?php echo esc_attr($raw['app_url']); ?>" placeholder="<?php echo esc_attr(self::DEFAULTS['app_url']); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="eventvox-api"><?php esc_html_e('Adres van de EventVOX API', 'eventvox'); ?></label></th>
                            <td>
                                <input type="url" class="regular-text code" id="eventvox-api" name="<?php echo esc_attr($name); ?>[api_url]" value="<?php echo esc_attr($raw['api_url']); ?>" placeholder="<?php echo esc_attr(self::DEFAULTS['api_url']); ?>">
                                <p class="description"><?php esc_html_e('Alleen aanpassen als EventVOX je dat vraagt. Leeg: het standaardadres.', 'eventvox'); ?></p>
                            </td>
                        </tr>
                    </table>
                </details>
                <?php submit_button(); ?>
            </form>

            <h2><?php esc_html_e('Gebruiken', 'eventvox'); ?></h2>
            <p><?php esc_html_e('Zet een blok (zoek op "EventVOX") of een shortcode op een pagina, of kies een widget bij Weergave > Widgets.', 'eventvox'); ?></p>
            <table class="widefat striped" style="max-width: 60rem">
                <thead><tr><th><?php esc_html_e('Shortcode', 'eventvox'); ?></th><th><?php esc_html_e('Wat', 'eventvox'); ?></th></tr></thead>
                <tbody>
                    <?php foreach ([
                        '[eventvox_webshop]' => __('De webshop in een iframe die meegroeit met de inhoud.', 'eventvox'),
                        '[eventvox_vrijwilligers]' => __('De aanmeldpagina voor vrijwilligers (iframe).', 'eventvox'),
                        '[eventvox_pickup]' => __('Het Pick-Up aanmeldformulier (iframe).', 'eventvox'),
                        '[eventvox_events aantal="5"]' => __('Lijst met komende events, met een link naar de webshop.', 'eventvox'),
                        '[eventvox_pickup_rondes aantal="5"]' => __('De Pick-Up rondes die nog plaats hebben, met een aanmeldknop.', 'eventvox'),
                        '[eventvox_vrijwilliger_worden]' => __('Je tekst voor nieuwe vrijwilligers met een aanmeldknop.', 'eventvox'),
                    ] as $code => $what) : ?>
                        <tr><td><code><?php echo esc_html($code); ?></code></td><td><?php echo esc_html($what); ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p class="description"><?php
                printf(esc_html__('Versie %s. Nieuwe versies verschijnen bij Plugins zoals bij andere plugins.', 'eventvox'), esc_html(EVENTVOX_VERSION)); ?></p>
        </div>
        <?php
    }

    /** Whether EventVOX answers for this organisation, and which parts are available */
    private static function status()
    {
        $tenant = self::tenant();
        if ($tenant === '') {
            echo '<div class="notice notice-info inline"><p>' . esc_html__('Vul het organisatie-ID in om te beginnen.', 'eventvox') . '</p></div>';
            return;
        }
        $shop = EventVOX_API::get('/public/shop/' . $tenant, 60);
        $pickups = EventVOX_API::get('/public/pickups/' . $tenant, 60);
        $name = !is_wp_error($shop) ? ($shop['name'] ?? '') : (!is_wp_error($pickups) ? ($pickups['tenant_name'] ?? '') : '');
        if ($name === '') {
            $error = is_wp_error($shop) ? $shop : $pickups;
            $text = EventVOX_API::is_not_found($error)
                ? __('EventVOX kent deze organisatie niet, of de webshop en Pick-Up staan uit. Controleer het organisatie-ID.', 'eventvox')
                /* translators: %s: error message */
                : sprintf(__('EventVOX is niet bereikbaar: %s', 'eventvox'), $error->get_error_message());
            echo '<div class="notice notice-warning inline"><p>' . esc_html($text) . '</p></div>';
            return;
        }
        $parts = [
            __('Webshop', 'eventvox') => !is_wp_error($shop),
            __('Vrijwilligers', 'eventvox') => !is_wp_error($shop) && !empty($shop['volunteer_signup']),
            __('Pick-Up', 'eventvox') => !is_wp_error($pickups),
        ];
        echo '<div class="notice notice-success inline"><p>';
        /* translators: %s: organisation name */
        printf(esc_html__('Verbonden met %s.', 'eventvox'), '<strong>' . esc_html($name) . '</strong>');
        foreach ($parts as $label => $on) {
            echo ' &nbsp;<span>' . ($on ? '✓ ' : '✗ ') . esc_html($label) . '</span>';
        }
        echo '</p></div>';
    }
}
