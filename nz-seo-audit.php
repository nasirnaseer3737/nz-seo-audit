<?php
/**
 * Plugin Name:       NZ SEO Audit
 * Plugin URI:        https://nzgenix.digital/
 * Description:       On-page SEO audit for posts and pages: title, meta description, headings, images, links, keyword use and readability — scored 0–100 with a plain-language checklist in the post editor and a site-wide dashboard. No SEO plugin required, and it never overwrites Yoast or Rank Math.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Nasir Naseer
 * Author URI:        https://nzgenix.digital/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       nz-seo-audit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'NZSA_VERSION', '1.0.0' );
define( 'NZSA_PATH', plugin_dir_path( __FILE__ ) );
define( 'NZSA_URL', plugin_dir_url( __FILE__ ) );
define( 'NZSA_META_KEYWORD', '_nzsa_keyword' );
define( 'NZSA_META_SCORE', '_nzsa_score' );

require_once NZSA_PATH . 'includes/class-nzsa-audit.php';
require_once NZSA_PATH . 'includes/class-nzsa-admin.php';
require_once NZSA_PATH . 'includes/class-nzsa-rest.php';

/**
 * Activation: set defaults. Scores are computed on demand and cached as
 * post meta, so activation needs no table and no bulk scan.
 */
function nzsa_activate() {
    add_option( 'nzsa_min_word_count', 300 );
    add_option( 'nzsa_target_readability', 20 );
    add_option( 'nzsa_post_types', array( 'post', 'page' ) );
}
register_activation_hook( __FILE__, 'nzsa_activate' );

/**
 * Deactivation: nothing to clean up — scores live in post meta and are
 * harmless without the plugin. See uninstall.php for full removal.
 */
function nzsa_deactivate() {
    // Intentionally empty.
}
register_deactivation_hook( __FILE__, 'nzsa_deactivate' );

/**
 * Boot once WordPress has loaded its APIs.
 */
function nzsa_boot() {
    NZSA_Admin::init();
    NZSA_REST::init();
}
add_action( 'plugins_loaded', 'nzsa_boot' );

/**
 * Load translations.
 */
function nzsa_load_textdomain() {
    load_plugin_textdomain( 'nz-seo-audit', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'nzsa_load_textdomain' );

/**
 * Enqueue admin assets only on the screens that use them.
 */
function nzsa_enqueue_admin_assets( $hook ) {
    $screen = get_current_screen();
    if ( ! $screen ) {
        return;
    }
    $is_editor = in_array( $hook, array( 'post.php', 'post-new.php' ), true );
    $is_dash   = 'toplevel_page_nzsa-dashboard' === $hook;
    if ( $is_editor || $is_dash ) {
        wp_enqueue_style( 'nzsa-admin', NZSA_URL . 'assets/css/admin.css', array(), NZSA_VERSION );
        wp_enqueue_script( 'nzsa-admin', NZSA_URL . 'assets/js/admin.js', array(), NZSA_VERSION, true );
    }
}
add_action( 'admin_enqueue_scripts', 'nzsa_enqueue_admin_assets' );
