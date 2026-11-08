<?php
/**
 * Uninstall: remove plugin options and all stored scores/keywords.
 * Runs only when the plugin is deleted from WordPress, not on deactivation.
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;
delete_option( 'nzsa_min_word_count' );
delete_option( 'nzsa_target_readability' );
delete_option( 'nzsa_post_types' );

$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->postmeta} WHERE meta_key IN (%s, %s)",
        '_nzsa_keyword',
        '_nzsa_score'
    )
);
