<?php

declare(strict_types=1);

namespace ZillaLikes;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class RestApi
{
    private const NAMESPACE = 'zilla-likes/v1';
    private LikeHandler $handler;

    public function __construct(LikeHandler $handler)
    {
        $this->handler = $handler;
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void
    {
        register_rest_route(self::NAMESPACE, '/like/(?P<id>\d+)', [
            'methods'             => 'POST',
            'callback'            => [$this, 'toggle_like'],
            'permission_callback' => '__return_true',
            'args'                => [
                'id' => [
                    'validate_callback' => [$this, 'validate_post_id'],
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/likes/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_like'],
            'permission_callback' => '__return_true',
            'args'                => [
                'id' => [
                    'validate_callback' => [$this, 'validate_post_id'],
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/likes', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_likes_batch'],
            'permission_callback' => '__return_true',
            'args'                => [
                'ids' => [
                    'required'          => true,
                    'sanitize_callback' => static function ($value): array {
                        return array_map('absint', explode(',', (string) $value));
                    },
                ],
            ],
        ]);
    }

    public function toggle_like(WP_REST_Request $request): WP_REST_Response
    {
        $post_id = (int) $request->get_param('id');
        $result = $this->handler->toggle($post_id);

        return new WP_REST_Response($result, 200);
    }

    public function get_like(WP_REST_Request $request): WP_REST_Response
    {
        $post_id = (int) $request->get_param('id');
        $result = $this->handler->get_state($post_id);

        return new WP_REST_Response($result, 200);
    }

    public function get_likes_batch(
        WP_REST_Request $request
    ): WP_REST_Response {
        /** @var int[] $ids */
        $ids = $request->get_param('ids');

        // Cap batch size to prevent abuse
        $ids = array_slice($ids, 0, 100);

        $result = $this->handler->get_states($ids);

        return new WP_REST_Response($result, 200);
    }

    /**
     * @param mixed $value
     */
    public function validate_post_id($value): bool
    {
        $id = (int) $value;
        return $id > 0 && get_post_status($id) !== false;
    }
}
