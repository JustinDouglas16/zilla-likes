<?php

/**
 * Plugin Name: ZillaLikes
 * Description: A simple, modern like button for WordPress posts and pages.
 * Version: 2.0.0
 * Requires PHP: 7.4
 * Author: Starter
 * Text Domain: zilla-likes
 * Domain Path: /languages
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

define('ZILLA_LIKES_VERSION', '2.0.0');
define('ZILLA_LIKES_FILE', __FILE__);
define('ZILLA_LIKES_DIR', plugin_dir_path(__FILE__));
define('ZILLA_LIKES_URL', plugin_dir_url(__FILE__));

require_once ZILLA_LIKES_DIR . 'src/Plugin.php';
require_once ZILLA_LIKES_DIR . 'src/Settings.php';
require_once ZILLA_LIKES_DIR . 'src/LikeHandler.php';
require_once ZILLA_LIKES_DIR . 'src/RestApi.php';
require_once ZILLA_LIKES_DIR . 'src/Widget.php';

register_activation_hook(__FILE__, [ZillaLikes\Plugin::class, 'activate']);
register_uninstall_hook(__FILE__, [ZillaLikes\Plugin::class, 'uninstall']);

add_action('plugins_loaded', static function (): void {
    ZillaLikes\Plugin::instance()->init();
});

/**
 * Template tag – use in theme files: <?php zilla_likes(); ?>
 */
function zilla_likes(int $post_id = 0): void
{
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo ZillaLikes\Plugin::instance()->like_handler()->render($post_id);
}
