<?php
/**
 * Classic widgets (Weergave > Widgets, also in the block widget editor under "Verouderde widget"): Komende events,
 * Pick-Up rondes and Vrijwilliger worden. Each shows a title and the list of EventVOX_Lists.
 */

if (!defined('ABSPATH')) {
    exit;
}

class EventVOX_Widgets
{
    public static function init()
    {
        add_action('widgets_init', function () {
            register_widget('EventVOX_Widget_Events');
            register_widget('EventVOX_Widget_Pickups');
            register_widget('EventVOX_Widget_Volunteer');
        });
    }
}

abstract class EventVOX_Widget extends WP_Widget
{
    /** [field => [label, default, type]]; type number, text or textarea */
    abstract protected function fields();

    abstract protected function content(array $instance);

    public function widget($args, $instance)
    {
        $instance = $this->with_defaults($instance);
        $content = $this->content($instance);
        if ($content === '') {
            return;
        }
        echo $args['before_widget'];
        $title = apply_filters('widget_title', $instance['title'], $instance, $this->id_base);
        if ($title !== '') {
            echo $args['before_title'] . esc_html($title) . $args['after_title'];
        }
        echo $content; // Escaped in EventVOX_Lists
        echo $args['after_widget'];
    }

    public function form($instance)
    {
        $instance = $this->with_defaults($instance);
        foreach ($this->fields() as $key => [$label, $default, $type]) {
            $id = $this->get_field_id($key);
            $name = $this->get_field_name($key);
            echo '<p><label for="' . esc_attr($id) . '">' . esc_html($label) . '</label>';
            if ($type === 'textarea') {
                echo '<textarea class="widefat" rows="4" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '">' . esc_textarea($instance[$key]) . '</textarea>';
            } elseif ($type === 'number') {
                echo ' <input class="tiny-text" type="number" min="1" max="20" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="' . esc_attr($instance[$key]) . '">';
            } else {
                echo '<input class="widefat" type="text" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="' . esc_attr($instance[$key]) . '">';
            }
            echo '</p>';
        }
        if (EventVOX_Settings::tenant() === '') {
            echo '<p class="description"><a href="' . esc_url(admin_url('options-general.php?page=eventvox')) . '">' . esc_html__('Vul eerst het organisatie-ID in.', 'eventvox') . '</a></p>';
        }
        return '';
    }

    public function update($new, $old)
    {
        $out = [];
        foreach ($this->fields() as $key => [, $default, $type]) {
            $value = $new[$key] ?? $default;
            $out[$key] = $type === 'number' ? max(1, min(20, (int) $value)) : ($type === 'textarea' ? sanitize_textarea_field($value) : sanitize_text_field($value));
        }
        return $out;
    }

    private function with_defaults($instance)
    {
        $out = [];
        foreach ($this->fields() as $key => [, $default]) {
            $out[$key] = isset($instance[$key]) ? $instance[$key] : $default;
        }
        return $out;
    }
}

class EventVOX_Widget_Events extends EventVOX_Widget
{
    public function __construct()
    {
        parent::__construct('eventvox_events', __('EventVOX: Komende events', 'eventvox'),
            ['description' => __('De komende events uit je EventVOX-webshop.', 'eventvox'), 'show_instance_in_rest' => true]);
    }

    protected function fields()
    {
        return [
            'title' => [__('Titel', 'eventvox'), __('Komende events', 'eventvox'), 'text'],
            'limit' => [__('Aantal events', 'eventvox'), 5, 'number'],
            'button' => [__('Tekst van de knop', 'eventvox'), __('Naar de webshop', 'eventvox'), 'text'],
        ];
    }

    protected function content(array $instance)
    {
        return EventVOX_Lists::events($instance);
    }
}

class EventVOX_Widget_Pickups extends EventVOX_Widget
{
    public function __construct()
    {
        parent::__construct('eventvox_pickups', __('EventVOX: Pick-Up rondes', 'eventvox'),
            ['description' => __('De Pick-Up rondes die nog plaats hebben, met een aanmeldknop.', 'eventvox'), 'show_instance_in_rest' => true]);
    }

    protected function fields()
    {
        return [
            'title' => [__('Titel', 'eventvox'), __('Pick-Up', 'eventvox'), 'text'],
            'limit' => [__('Aantal rondes', 'eventvox'), 5, 'number'],
            'button' => [__('Tekst van de knop', 'eventvox'), __('Aanmelden', 'eventvox'), 'text'],
        ];
    }

    protected function content(array $instance)
    {
        return EventVOX_Lists::pickups($instance);
    }
}

class EventVOX_Widget_Volunteer extends EventVOX_Widget
{
    public function __construct()
    {
        parent::__construct('eventvox_volunteer', __('EventVOX: Vrijwilliger worden', 'eventvox'),
            ['description' => __('Een oproep om vrijwilliger te worden, met een aanmeldknop.', 'eventvox'), 'show_instance_in_rest' => true]);
    }

    protected function fields()
    {
        return [
            'title' => [__('Titel', 'eventvox'), __('Vrijwilliger worden', 'eventvox'), 'text'],
            'text' => [__('Tekst (leeg: de tekst uit EventVOX)', 'eventvox'), '', 'textarea'],
            'button' => [__('Tekst van de knop', 'eventvox'), __('Word vrijwilliger', 'eventvox'), 'text'],
        ];
    }

    protected function content(array $instance)
    {
        return EventVOX_Lists::volunteer($instance);
    }
}
