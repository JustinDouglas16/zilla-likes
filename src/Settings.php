<?php

declare(strict_types=1);

namespace ZillaLikes;

final class Settings
{
    private const OPTION_KEY = 'zilla_likes_settings';
    private const SLUG = 'zilla-likes';

    /** @var array<string, mixed>|null */
    private ?array $cache = null;

    /** @return array<string, mixed> */
    public function get_options(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $defaults = [
            'add_to_posts'  => '1',
            'add_to_pages'  => '1',
            'add_to_other'  => '',
            'exclude_from'  => '',
            'disable_css'   => '',
            'zero_postfix'  => __('Likes', 'zilla-likes'),
            'one_postfix'   => __('Like', 'zilla-likes'),
            'more_postfix'  => __('Likes', 'zilla-likes'),
        ];

        $stored = get_option(self::OPTION_KEY, []);
        $this->cache = wp_parse_args(
            is_array($stored) ? $stored : [],
            $defaults
        );

        return $this->cache;
    }

    public function register(): void
    {
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_menu', [$this, 'add_menu']);
    }

    public function add_menu(): void
    {
        add_options_page(
            __('ZillaLikes Settings', 'zilla-likes'),
            __('ZillaLikes', 'zilla-likes'),
            'manage_options',
            self::SLUG,
            [$this, 'render_page']
        );
    }

    public function register_settings(): void
    {
        register_setting(
            self::SLUG,
            self::OPTION_KEY,
            [
                'type'              => 'array',
                'sanitize_callback' => [$this, 'sanitize'],
            ]
        );

        // -- Display section --
        add_settings_section(
            'zilla_likes_display',
            __('Display Settings', 'zilla-likes'),
            static fn() => printf(
                '<p>%s</p>',
                esc_html__(
                    'Control where the like button appears automatically.',
                    'zilla-likes'
                )
            ),
            self::SLUG
        );

        $checkboxes = [
            'add_to_posts' => __('Add to posts', 'zilla-likes'),
            'add_to_pages' => __('Add to pages', 'zilla-likes'),
            'add_to_other' => __(
                'Add to archive/home/search/excerpts',
                'zilla-likes'
            ),
            'disable_css'  => __('Disable built-in CSS', 'zilla-likes'),
        ];

        foreach ($checkboxes as $key => $label) {
            add_settings_field(
                $key,
                $label,
                fn() => $this->render_checkbox($key),
                self::SLUG,
                'zilla_likes_display'
            );
        }

        add_settings_field(
            'exclude_from',
            __('Exclude from (comma-separated IDs)', 'zilla-likes'),
            fn() => $this->render_text('exclude_from'),
            self::SLUG,
            'zilla_likes_display'
        );

        // -- Postfix section --
        add_settings_section(
            'zilla_likes_postfix',
            __('Label Settings', 'zilla-likes'),
            static fn() => printf(
                '<p>%s</p>',
                esc_html__(
                    'Customize the text shown after the like count.',
                    'zilla-likes'
                )
            ),
            self::SLUG
        );

        $postfixes = [
            'zero_postfix' => __('Zero likes text', 'zilla-likes'),
            'one_postfix'  => __('One like text', 'zilla-likes'),
            'more_postfix' => __('Multiple likes text', 'zilla-likes'),
        ];

        foreach ($postfixes as $key => $label) {
            add_settings_field(
                $key,
                $label,
                fn() => $this->render_text($key),
                self::SLUG,
                'zilla_likes_postfix'
            );
        }

        // -- Usage section --
        add_settings_section(
            'zilla_likes_usage',
            __('Usage', 'zilla-likes'),
            static function (): void {
                echo '<p>';
                echo esc_html__(
                    'Template tag:',
                    'zilla-likes'
                );
                echo ' <code>&lt;?php zilla_likes(); ?&gt;</code><br>';
                echo esc_html__(
                    'Shortcode:',
                    'zilla-likes'
                );
                echo ' <code>[zilla_likes]</code> ';
                echo esc_html__(
                    'or',
                    'zilla-likes'
                );
                echo ' <code>[zilla_likes id="123"]</code>';
                echo '</p>';
            },
            self::SLUG
        );
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function sanitize(array $input): array
    {
        $clean = [];

        $booleans = [
            'add_to_posts',
            'add_to_pages',
            'add_to_other',
            'disable_css',
        ];

        foreach ($booleans as $key) {
            $clean[$key] = !empty($input[$key]) ? '1' : '';
        }

        $clean['exclude_from'] = sanitize_text_field(
            $input['exclude_from'] ?? ''
        );
        $clean['zero_postfix'] = sanitize_text_field(
            $input['zero_postfix'] ?? ''
        );
        $clean['one_postfix'] = sanitize_text_field(
            $input['one_postfix'] ?? ''
        );
        $clean['more_postfix'] = sanitize_text_field(
            $input['more_postfix'] ?? ''
        );

        $this->cache = null;

        return $clean;
    }

    public function render_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        echo '<div class="wrap">';
        printf(
            '<h1>%s</h1>',
            esc_html(get_admin_page_title())
        );
        echo '<form method="post" action="options.php">';
        settings_fields(self::SLUG);
        do_settings_sections(self::SLUG);
        submit_button();
        echo '</form></div>';
    }

    private function render_checkbox(string $key): void
    {
        $options = $this->get_options();
        printf(
            '<input type="checkbox" name="%s[%s]" value="1" %s />',
            esc_attr(self::OPTION_KEY),
            esc_attr($key),
            checked(!empty($options[$key]), true, false)
        );
    }

    private function render_text(string $key): void
    {
        $options = $this->get_options();
        printf(
            '<input type="text" name="%s[%s]" value="%s" class="regular-text" />',
            esc_attr(self::OPTION_KEY),
            esc_attr($key),
            esc_attr($options[$key] ?? '')
        );
    }
}
