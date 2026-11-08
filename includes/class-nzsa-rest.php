<?php
/**
 * REST API: audit a post by ID (editors+) and list low-scoring posts.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class NZSA_REST {

    public static function init() {
        add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
    }

    public static function routes() {
        register_rest_route(
            'nzsa/v1',
            '/audit/(?P<id>\d+)',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( __CLASS__, 'audit' ),
                'permission_callback' => function () {
                    return current_user_can( 'edit_posts' );
                },
                'args'                => array(
                    'id' => array( 'sanitize_callback' => 'absint' ),
                ),
            )
        );
        register_rest_route(
            'nzsa/v1',
            '/lowest',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( __CLASS__, 'lowest' ),
                'permission_callback' => function () {
                    return current_user_can( 'edit_posts' );
                },
            )
        );
    }

    public static function audit( WP_REST_Request $request ) {
        $post = get_post( (int) $request->get_param( 'id' ) );
        if ( ! $post ) {
            return new WP_Error( 'nzsa_not_found', __( 'Post not found.', 'nz-seo-audit' ), array( 'status' => 404 ) );
        }
        $result = NZSA_Admin::audit_post( $post );
        return rest_ensure_response(
            array(
                'id'    => $post->ID,
                'title' => get_the_title( $post ),
                'score' => $result['score'],
                'grade' => $result['grade'],
                'checks' => $result['checks'],
            )
        );
    }

    public static function lowest( WP_REST_Request $request ) {
        $posts = get_posts(
            array(
                'post_type'      => NZSA_Admin::post_types(),
                'post_status'    => 'publish',
                'posts_per_page' => 10,
                'meta_key'       => NZSA_META_SCORE,
                'orderby'        => 'meta_value_num',
                'order'          => 'ASC',
            )
        );
        $out = array();
        foreach ( $posts as $post ) {
            $out[] = array(
                'id'    => $post->ID,
                'title' => get_the_title( $post ),
                'score' => (int) get_post_meta( $post->ID, NZSA_META_SCORE, true ),
            );
        }
        return rest_ensure_response( $out );
    }
}
