<?php
/**
 * Admin: post-editor metabox (score + checklist + keyword), site-wide
 * dashboard, dashboard widget, settings, and score caching.
 *
 * Scores are computed when a post is saved and stored in post meta, so
 * the dashboard never re-parses every post on each page load. The metabox
 * always audits the current (unsaved) editor content via AJAX-free
 * re-audit on save, and shows the cached score otherwise.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class NZSA_Admin {

    public static function init() {
        add_action( 'add_meta_boxes', array( __CLASS__, 'metabox' ) );
        add_action( 'save_post', array( __CLASS__, 'save_post' ), 10, 2 );
        add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
        add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
        add_action( 'wp_dashboard_setup', array( __CLASS__, 'dashboard_widget' ) );
        add_filter( 'manage_posts_columns', array( __CLASS__, 'score_column' ) );
        add_filter( 'manage_pages_columns', array( __CLASS__, 'score_column' ) );
        add_action( 'manage_posts_custom_column', array( __CLASS__, 'score_column_value' ), 10, 2 );
        add_action( 'manage_pages_custom_column', array( __CLASS__, 'score_column_value' ), 10, 2 );
    }

    public static function post_types() {
        $types = get_option( 'nzsa_post_types', array( 'post', 'page' ) );
        return is_array( $types ) ? $types : array( 'post', 'page' );
    }

    /** Gather a post's auditable data from WordPress. */
    public static function post_data( $post ) {
        $keyword = get_post_meta( $post->ID, NZSA_META_KEYWORD, true );
        $meta    = '';
        // Prefer a real SEO plugin's description when one is in use.
        if ( function_exists( 'get_post_meta' ) ) {
            $meta = get_post_meta( $post->ID, '_yoast_wpseo_metadesc', true );
            if ( ! $meta ) {
                $meta = get_post_meta( $post->ID, 'rank_math_description', true );
            }
        }
        if ( ! $meta ) {
            $meta = get_the_excerpt( $post );
        }
        return array(
            'title'            => get_the_title( $post ),
            'content'          => $post->post_content,
            'meta_description' => $meta,
            'keyword'          => $keyword,
            'slug'             => $post->post_name,
            'site_url'         => home_url( '/' ),
            'min_word_count'   => (int) get_option( 'nzsa_min_word_count', 300 ),
            'max_sentence_words' => (int) get_option( 'nzsa_target_readability', 20 ),
        );
    }

    public static function audit_post( $post ) {
        $result = NZSA_Audit::run( self::post_data( $post ) );
        update_post_meta( $post->ID, NZSA_META_SCORE, $result['score'] );
        return $result;
    }

    public static function metabox() {
        foreach ( self::post_types() as $type ) {
            add_meta_box(
                'nzsa-metabox',
                __( 'SEO Audit', 'nz-seo-audit' ),
                array( __CLASS__, 'render_metabox' ),
                $type,
                'side',
                'high'
            );
        }
    }

    public static function render_metabox( $post ) {
        wp_nonce_field( 'nzsa_save', 'nzsa_nonce' );
        $keyword = get_post_meta( $post->ID, NZSA_META_KEYWORD, true );
        $cached  = get_post_meta( $post->ID, NZSA_META_SCORE, true );
        $result  = self::audit_post( $post );
        ?>
        <p>
            <label for="nzsa-keyword"><strong><?php esc_html_e( 'Target keyword', 'nz-seo-audit' ); ?></strong></label><br />
            <input id="nzsa-keyword" name="nzsa_keyword" type="text" value="<?php echo esc_attr( $keyword ); ?>" class="widefat" placeholder="<?php esc_attr_e( 'e.g. wordpress developer', 'nz-seo-audit' ); ?>" />
        </p>
        <div class="nzsa-score nzsa-<?php echo esc_attr( $result['grade'] ); ?>">
            <span class="nzsa-score-num"><?php echo (int) $result['score']; ?></span>
            <span class="nzsa-score-label">/ 100 — <?php echo esc_html( NZSA_Audit::grade_label( $result['grade'] ) ); ?></span>
        </div>
        <?php if ( '' !== $cached && (int) $cached !== (int) $result['score'] ) : ?>
            <p class="description"><?php printf( esc_html__( 'Previous saved score: %d.', 'nz-seo-audit' ), (int) $cached ); ?></p>
        <?php endif; ?>
        <ul class="nzsa-checks">
            <?php foreach ( $result['checks'] as $check ) : ?>
                <li class="nzsa-<?php echo esc_attr( $check['status'] ); ?>">
                    <span class="nzsa-dot" aria-hidden="true"></span>
                    <strong><?php echo esc_html( $check['label'] ); ?></strong><br />
                    <span class="description"><?php echo esc_html( $check['detail'] ); ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
        <p class="description"><?php esc_html_e( 'Score updates when you save the post. Keyword checks need a target keyword.', 'nz-seo-audit' ); ?></p>
        <?php
    }

    public static function save_post( $post_id, $post ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( ! isset( $_POST['nzsa_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['nzsa_nonce'] ) ), 'nzsa_save' ) ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }
        if ( ! in_array( $post->post_type, self::post_types(), true ) ) {
            return;
        }
        $keyword = isset( $_POST['nzsa_keyword'] ) ? sanitize_text_field( wp_unslash( $_POST['nzsa_keyword'] ) ) : '';
        update_post_meta( $post_id, NZSA_META_KEYWORD, $keyword );
        // Re-audit with the saved content so the stored score is current.
        $post->post_content = isset( $_POST['content'] ) ? wp_unslash( $_POST['content'] ) : $post->post_content;
        self::audit_post( $post );
    }

    public static function menu() {
        add_menu_page(
            __( 'SEO Audit', 'nz-seo-audit' ),
            __( 'SEO Audit', 'nz-seo-audit' ),
            'edit_posts',
            'nzsa-dashboard',
            array( __CLASS__, 'render_dashboard' ),
            'dashicons-chart-line',
            30
        );
        add_submenu_page(
            'nzsa-dashboard',
            __( 'SEO Audit Settings', 'nz-seo-audit' ),
            __( 'Settings', 'nz-seo-audit' ),
            'manage_options',
            'nzsa-settings',
            array( __CLASS__, 'render_settings' )
        );
    }

    public static function register_settings() {
        register_setting( 'nzsa_settings', 'nzsa_min_word_count', array( 'sanitize_callback' => 'absint' ) );
        register_setting( 'nzsa_settings', 'nzsa_target_readability', array( 'sanitize_callback' => 'absint' ) );
    }

    /** Site-wide list: latest 50 posts/pages with cached (or fresh) scores. */
    public static function render_dashboard() {
        $posts = get_posts(
            array(
                'post_type'      => self::post_types(),
                'post_status'    => 'publish',
                'posts_per_page' => 50,
                'orderby'        => 'modified',
                'order'          => 'DESC',
            )
        );
        $buckets = array( 'good' => 0, 'needs-work' => 0, 'poor' => 0, 'unscored' => 0 );
        $rows    = array();
        foreach ( $posts as $post ) {
            $score = get_post_meta( $post->ID, NZSA_META_SCORE, true );
            if ( '' === $score ) {
                $result = self::audit_post( $post );
                $score  = $result['score'];
                $grade  = $result['grade'];
            } else {
                $grade = NZSA_Audit::grade( (int) $score );
            }
            $buckets[ $grade ]++;
            $rows[] = array( 'post' => $post, 'score' => (int) $score, 'grade' => $grade );
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'SEO Audit', 'nz-seo-audit' ); ?></h1>
            <p><?php printf(
                esc_html__( 'Good: %1$d · Needs work: %2$d · Poor: %3$d · %4$d pages checked.', 'nz-seo-audit' ),
                $buckets['good'], $buckets['needs-work'], $buckets['poor'], count( $rows )
            ); ?></p>
            <table class="wp-list-table widefat fixed striped">
                <thead><tr>
                    <th><?php esc_html_e( 'Title', 'nz-seo-audit' ); ?></th>
                    <th><?php esc_html_e( 'Type', 'nz-seo-audit' ); ?></th>
                    <th><?php esc_html_e( 'Score', 'nz-seo-audit' ); ?></th>
                    <th><?php esc_html_e( 'Grade', 'nz-seo-audit' ); ?></th>
                    <th><?php esc_html_e( 'Last modified', 'nz-seo-audit' ); ?></th>
                </tr></thead>
                <tbody>
                <?php if ( empty( $rows ) ) : ?>
                    <tr><td colspan="5"><?php esc_html_e( 'No published posts or pages yet.', 'nz-seo-audit' ); ?></td></tr>
                <?php endif; ?>
                <?php foreach ( $rows as $row ) : ?>
                    <tr>
                        <td><a href="<?php echo esc_url( get_edit_post_link( $row['post']->ID ) ); ?>"><?php echo esc_html( get_the_title( $row['post'] ) ); ?></a></td>
                        <td><?php echo esc_html( $row['post']->post_type ); ?></td>
                        <td><strong><?php echo (int) $row['score']; ?> / 100</strong></td>
                        <td><?php echo esc_html( NZSA_Audit::grade_label( $row['grade'] ) ); ?></td>
                        <td><?php echo esc_html( $row['post']->post_modified ); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public static function render_settings() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'SEO Audit Settings', 'nz-seo-audit' ); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields( 'nzsa_settings' ); ?>
                <table class="form-table">
                    <tr><th scope="row"><label for="nzsa_min_word_count"><?php esc_html_e( 'Minimum word count', 'nz-seo-audit' ); ?></label></th>
                        <td><input id="nzsa_min_word_count" name="nzsa_min_word_count" type="number" min="0" value="<?php echo esc_attr( get_option( 'nzsa_min_word_count', 300 ) ); ?>" class="small-text" /></td></tr>
                    <tr><th scope="row"><label for="nzsa_target_readability"><?php esc_html_e( 'Max average words per sentence', 'nz-seo-audit' ); ?></label></th>
                        <td><input id="nzsa_target_readability" name="nzsa_target_readability" type="number" min="5" value="<?php echo esc_attr( get_option( 'nzsa_target_readability', 20 ) ); ?>" class="small-text" /></td></tr>
                </table>
                <?php submit_button(); ?>
            </form>
            <h2><?php esc_html_e( 'How scoring works', 'nz-seo-audit' ); ?></h2>
            <p><?php esc_html_e( '10 checks, 100 points in total. 80+ is Good, 50–79 Needs work, below 50 is Poor. Set a target keyword in the SEO Audit box on each post to enable the two keyword checks.', 'nz-seo-audit' ); ?></p>
        </div>
        <?php
    }

    public static function dashboard_widget() {
        wp_add_dashboard_widget(
            'nzsa-widget',
            __( 'SEO Audit — lowest scores', 'nz-seo-audit' ),
            array( __CLASS__, 'render_widget' )
        );
    }

    public static function render_widget() {
        $posts = get_posts(
            array(
                'post_type'      => self::post_types(),
                'post_status'    => 'publish',
                'posts_per_page' => 5,
                'meta_key'       => NZSA_META_SCORE,
                'orderby'        => 'meta_value_num',
                'order'          => 'ASC',
            )
        );
        if ( empty( $posts ) ) {
            echo '<p>' . esc_html__( 'No scored posts yet — open any post to see its audit.', 'nz-seo-audit' ) . '</p>';
            return;
        }
        echo '<ul>';
        foreach ( $posts as $post ) {
            $score = (int) get_post_meta( $post->ID, NZSA_META_SCORE, true );
            printf(
                '<li><a href="%s">%s</a> — <strong>%d / 100</strong></li>',
                esc_url( get_edit_post_link( $post->ID ) ),
                esc_html( get_the_title( $post ) ),
                $score
            );
        }
        echo '</ul>';
    }

    public static function score_column( $columns ) {
        $columns['nzsa_score'] = __( 'SEO Score', 'nz-seo-audit' );
        return $columns;
    }

    public static function score_column_value( $column, $post_id ) {
        if ( 'nzsa_score' !== $column ) {
            return;
        }
        $score = get_post_meta( $post_id, NZSA_META_SCORE, true );
        echo '' === $score ? '—' : esc_html( $score . ' / 100' );
    }
}
