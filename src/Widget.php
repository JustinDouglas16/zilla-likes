<?php

declare(strict_types=1);

namespace ZillaLikes;

use WP_Widget;

final class Widget extends WP_Widget
{
    public function __construct()
    {
        parent::__construct(
            'zilla_likes_widget',
            __('ZillaLikes – Most Liked', 'zilla-likes'),
            [
                'description' => __(
                    'Displays the most liked posts.',
                    'zilla-likes'
                ),
            ]
        );
    }

    /**
     * @param array<string, string> $args
     * @param array<string, mixed>  $instance
     */
    public function widget($args, $instance): void
    {
        $title = apply_filters(
            'widget_title',
            $instance['title'] ?? __('Most Liked', 'zilla-likes')
        );
        $number = (int) ($instance['number'] ?? 5);
        $show_count = !empty($instance['show_count']);

        echo $args['before_widget'];

        if ($title) {
            echo $args['before_title']
                . esc_html($title)
                . $args['after_title'];
        }

        if (!empty($instance['description'])) {
            printf(
                '<p class="zilla-likes-widget-desc">%s</p>',
                esc_html($instance['description'])
            );
        }

        $posts = get_posts([
            'post_type'      => 'post',
            'posts_per_page' => $number,
            'meta_key'       => '_zilla_likes',
            'orderby'        => 'meta_value_num',
            'order'          => 'DESC',
            'meta_query'     => [
                [
                    'key'     => '_zilla_likes',
                    'value'   => '0',
                    'compare' => '>',
                    'type'    => 'NUMERIC',
                ],
            ],
        ]);

        if ($posts) {
            echo '<ul class="zilla-likes-widget-list">';
            foreach ($posts as $post) {
                $count = (int) get_post_meta(
                    $post->ID,
                    '_zilla_likes',
                    true
                );
                printf(
                    '<li><a href="%s">%s</a>%s</li>',
                    esc_url(get_permalink($post)),
                    esc_html(get_the_title($post)),
                    $show_count
                        ? sprintf(
                            ' <span class="zilla-likes-widget-count">(%d)</span>',
                            $count
                        )
                        : ''
                );
            }
            echo '</ul>';
        } else {
            printf(
                '<p>%s</p>',
                esc_html__('No liked posts yet.', 'zilla-likes')
            );
        }

        echo $args['after_widget'];
    }

    /**
     * @param array<string, mixed> $new_instance
     * @param array<string, mixed> $old_instance
     * @return array<string, mixed>
     */
    public function update($new_instance, $old_instance): array
    {
        return [
            'title'       => sanitize_text_field(
                $new_instance['title'] ?? ''
            ),
            'description' => sanitize_text_field(
                $new_instance['description'] ?? ''
            ),
            'number'      => min(
                20,
                max(1, (int) ($new_instance['number'] ?? 5))
            ),
            'show_count'  => !empty($new_instance['show_count']),
        ];
    }

    /**
     * @param array<string, mixed> $instance
     */
    public function form($instance): void
    {
        $title = $instance['title'] ?? __('Most Liked', 'zilla-likes');
        $description = $instance['description'] ?? '';
        $number = (int) ($instance['number'] ?? 5);
        $show_count = !empty($instance['show_count']);

        printf(
            '<p>
                <label for="%1$s">%2$s</label>
                <input type="text" class="widefat"
                       id="%1$s" name="%3$s" value="%4$s" />
            </p>',
            esc_attr($this->get_field_id('title')),
            esc_html__('Title:', 'zilla-likes'),
            esc_attr($this->get_field_name('title')),
            esc_attr($title)
        );

        printf(
            '<p>
                <label for="%1$s">%2$s</label>
                <input type="text" class="widefat"
                       id="%1$s" name="%3$s" value="%4$s" />
            </p>',
            esc_attr($this->get_field_id('description')),
            esc_html__('Description:', 'zilla-likes'),
            esc_attr($this->get_field_name('description')),
            esc_attr($description)
        );

        printf(
            '<p>
                <label for="%1$s">%2$s</label>
                <input type="number" class="tiny-text"
                       id="%1$s" name="%3$s" value="%4$d"
                       min="1" max="20" />
            </p>',
            esc_attr($this->get_field_id('number')),
            esc_html__('Number of posts:', 'zilla-likes'),
            esc_attr($this->get_field_name('number')),
            $number
        );

        printf(
            '<p>
                <input type="checkbox"
                       id="%1$s" name="%2$s" value="1" %3$s />
                <label for="%1$s">%4$s</label>
            </p>',
            esc_attr($this->get_field_id('show_count')),
            esc_attr($this->get_field_name('show_count')),
            checked($show_count, true, false),
            esc_html__('Show like count', 'zilla-likes')
        );
    }
}
