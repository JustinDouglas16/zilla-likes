<?php

declare(strict_types=1);

namespace ZillaLikes;

final class LikeHandler
{
    private const META_KEY = '_zilla_likes';
    private const HEART_SVG = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="zilla-likes-icon" aria-hidden="true"><path d="M2 9.5a5.5 5.5 0 0 1 9.591-3.676.56.56 0 0 0 .818 0A5.49 5.49 0 0 1 22 9.5c0 2.29-1.5 4-3 5.5l-5.492 5.313a2 2 0 0 1-3 .019L5 15c-1.5-1.5-3-3.2-3-5.5"/></svg>';

    /**
     * Render the like button HTML for a given post.
     */
    public function render(int $post_id = 0): string
    {
        if ($post_id <= 0) {
            $post_id = (int) get_the_ID();
        }

        if ($post_id <= 0) {
            return '';
        }

        $count = $this->get_count($post_id);
        $liked = $this->has_liked($post_id);
        $postfix = $this->get_postfix($count);

        $classes = 'zilla-likes';
        if ($liked) {
            $classes .= ' is-liked';
        }

        $title = $liked
            ? esc_attr__('You already like this', 'zilla-likes')
            : esc_attr__('Like this', 'zilla-likes');

        return sprintf(
            '<button type="button" class="%s" data-post-id="%d" title="%s" aria-label="%s">%s<span class="zilla-likes-count">%s</span> <span class="zilla-likes-postfix">%s</span></button>',
            esc_attr($classes),
            $post_id,
            $title,
            $title,
            self::HEART_SVG,
            esc_html((string) $count),
            esc_html($postfix)
        );
    }

    /**
     * Toggle a like for a post. Returns the new state.
     *
     * @return array{count: int, liked: bool, postfix: string}
     */
    public function toggle(int $post_id): array
    {
        if ($this->has_liked($post_id)) {
            return $this->remove_like($post_id);
        }

        return $this->add_like($post_id);
    }

    /**
     * @return array{count: int, liked: bool, postfix: string}
     */
    public function get_state(int $post_id): array
    {
        $count = $this->get_count($post_id);

        return [
            'count'   => $count,
            'liked'   => $this->has_liked($post_id),
            'postfix' => $this->get_postfix($count),
        ];
    }

    /**
     * @param int[] $post_ids
     * @return array<int, array{count: int, liked: bool, postfix: string}>
     */
    public function get_states(array $post_ids): array
    {
        $result = [];
        foreach ($post_ids as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $result[$id] = $this->get_state($id);
            }
        }
        return $result;
    }

    public function get_count(int $post_id): int
    {
        $count = get_post_meta($post_id, self::META_KEY, true);
        return $count !== '' ? (int) $count : 0;
    }

    public function has_liked(int $post_id): bool
    {
        $user_id = get_current_user_id();

        if ($user_id > 0) {
            $liked = (array) get_user_meta(
                $user_id,
                '_zilla_liked_posts',
                true
            );
            return in_array($post_id, array_map('intval', $liked), true);
        }

        return $this->has_liked_by_ip($post_id);
    }

    /**
     * @return array{count: int, liked: bool, postfix: string}
     */
    private function add_like(int $post_id): array
    {
        global $wpdb;

        $user_id = get_current_user_id();
        $ip = $this->get_ip();
        $table = $wpdb->prefix . 'zilla_likes';

        // Insert into tracking table (UNIQUE KEY prevents duplicates)
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $inserted = $wpdb->insert(
            $table,
            [
                'post_id'    => $post_id,
                'user_id'    => $user_id > 0 ? $user_id : null,
                'ip_address' => $user_id > 0 ? '' : $ip,
            ],
            ['%d', '%d', '%s']
        );

        if ($inserted) {
            // Increment cached count
            $count = $this->get_count($post_id) + 1;
            update_post_meta($post_id, self::META_KEY, $count);

            // Track in user meta for logged-in users
            if ($user_id > 0) {
                $liked = (array) get_user_meta(
                    $user_id,
                    '_zilla_liked_posts',
                    true
                );
                $liked[] = $post_id;
                update_user_meta(
                    $user_id,
                    '_zilla_liked_posts',
                    array_unique(array_map('intval', $liked))
                );
            }
        } else {
            $count = $this->get_count($post_id);
        }

        return [
            'count'   => $count,
            'liked'   => true,
            'postfix' => $this->get_postfix($count),
        ];
    }

    /**
     * @return array{count: int, liked: bool, postfix: string}
     */
    private function remove_like(int $post_id): array
    {
        global $wpdb;

        $user_id = get_current_user_id();
        $ip = $this->get_ip();
        $table = $wpdb->prefix . 'zilla_likes';

        if ($user_id > 0) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $deleted = $wpdb->delete(
                $table,
                [
                    'post_id' => $post_id,
                    'user_id' => $user_id,
                ],
                ['%d', '%d']
            );
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $deleted = $wpdb->delete(
                $table,
                [
                    'post_id'    => $post_id,
                    'ip_address' => $ip,
                    'user_id'    => null,
                ],
                ['%d', '%s', null]
            );
        }

        if ($deleted) {
            $count = max(0, $this->get_count($post_id) - 1);
            update_post_meta($post_id, self::META_KEY, $count);

            if ($user_id > 0) {
                $liked = (array) get_user_meta(
                    $user_id,
                    '_zilla_liked_posts',
                    true
                );
                $liked = array_diff(
                    array_map('intval', $liked),
                    [$post_id]
                );
                update_user_meta(
                    $user_id,
                    '_zilla_liked_posts',
                    array_values($liked)
                );
            }
        } else {
            $count = $this->get_count($post_id);
        }

        return [
            'count'   => $count,
            'liked'   => false,
            'postfix' => $this->get_postfix($count),
        ];
    }

    private function has_liked_by_ip(int $post_id): bool
    {
        global $wpdb;

        $table = $wpdb->prefix . 'zilla_likes';
        $ip = $this->get_ip();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $row = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table}
                 WHERE post_id = %d
                   AND user_id IS NULL
                   AND ip_address = %s
                 LIMIT 1",
                $post_id,
                $ip
            )
        );

        return $row !== null;
    }

    private function get_postfix(int $count): string
    {
        $options = (new Settings())->get_options();

        if ($count === 0) {
            return $options['zero_postfix'] ?? __('Likes', 'zilla-likes');
        }

        if ($count === 1) {
            return $options['one_postfix'] ?? __('Like', 'zilla-likes');
        }

        return $options['more_postfix'] ?? __('Likes', 'zilla-likes');
    }

    private function get_ip(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        return filter_var($ip, FILTER_VALIDATE_IP) ?: '0.0.0.0';
    }
}
