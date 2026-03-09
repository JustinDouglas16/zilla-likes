<?php

declare(strict_types=1);

namespace ZillaLikes;

final class Plugin
{
    private static ?self $instance = null;

    private Settings $settings;
    private LikeHandler $like_handler;
    private RestApi $rest_api;

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->settings = new Settings();
        $this->like_handler = new LikeHandler();
        $this->rest_api = new RestApi($this->like_handler);
    }

    public function like_handler(): LikeHandler
    {
        return $this->like_handler;
    }

    public function init(): void
    {
        load_plugin_textdomain(
            'zilla-likes',
            false,
            dirname(plugin_basename(ZILLA_LIKES_FILE)) . '/languages'
        );

        $this->settings->register();
        $this->rest_api->register();

        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_filter('the_content', [$this, 'append_to_content']);
        add_filter('the_excerpt', [$this, 'append_to_excerpt']);
        add_action(
            'widgets_init',
            static fn() => register_widget(Widget::class)
        );
        add_shortcode('zilla_likes', [$this, 'shortcode']);
    }

    public function enqueue_assets(): void
    {
        $options = $this->settings->get_options();

        if (empty($options['disable_css'])) {
            wp_enqueue_style(
                'zilla-likes',
                ZILLA_LIKES_URL . 'assets/css/zilla-likes.css',
                [],
                ZILLA_LIKES_VERSION
            );
        }

        wp_enqueue_script(
            'zilla-likes',
            ZILLA_LIKES_URL . 'assets/js/zilla-likes.js',
            [],
            ZILLA_LIKES_VERSION,
            true
        );

        wp_localize_script('zilla-likes', 'ZillaLikesConfig', [
            'restUrl' => esc_url_raw(rest_url('zilla-likes/v1')),
            'nonce'   => wp_create_nonce('wp_rest'),
        ]);
    }

    public function append_to_content(string $content): string
    {
        if ($this->should_append()) {
            $content .= $this->like_handler->render();
        }
        return $content;
    }

    public function append_to_excerpt(string $excerpt): string
    {
        $options = $this->settings->get_options();
        if (
            !empty($options['add_to_other']) &&
            !$this->is_excluded()
        ) {
            $excerpt .= $this->like_handler->render();
        }
        return $excerpt;
    }

    /**
     * @param array<string, mixed> $atts
     */
    public function shortcode(array $atts): string
    {
        $atts = shortcode_atts(
            ['id' => 0],
            $atts,
            'zilla_likes'
        );

        return $this->like_handler->render((int) $atts['id']);
    }

    private function should_append(): bool
    {
        if ($this->is_excluded()) {
            return false;
        }

        $options = $this->settings->get_options();

        if (is_singular('post') && !empty($options['add_to_posts'])) {
            return true;
        }

        if (is_page() && !empty($options['add_to_pages'])) {
            return true;
        }

        if (
            !empty($options['add_to_other']) &&
            (is_archive() || is_home() || is_search() || is_front_page())
        ) {
            return true;
        }

        return false;
    }

    private function is_excluded(): bool
    {
        $options = $this->settings->get_options();
        $excluded = array_filter(
            array_map(
                'intval',
                explode(',', $options['exclude_from'] ?? '')
            )
        );

        return in_array(get_the_ID(), $excluded, true);
    }

    // ---- Lifecycle hooks ----

    public static function activate(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'zilla_likes';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED DEFAULT NULL,
            ip_address VARCHAR(45) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_like (post_id, user_id, ip_address),
            KEY idx_post_id (post_id)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        update_option('zilla_likes_db_version', '2.0.0');
    }

    public static function uninstall(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'zilla_likes';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query("DROP TABLE IF EXISTS {$table}");

        delete_option('zilla_likes_settings');
        delete_option('zilla_likes_db_version');
        delete_post_meta_by_key('_zilla_likes');
    }
}
