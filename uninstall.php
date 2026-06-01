<?php
/**
 * Runs when the plugin is deleted from the WordPress admin.
 * Removes all readmewp posts, their meta, and any plugin options.
 *
 * @package ReadMeWP
 */

// Guard: only run when WordPress triggers an uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// -------------------------------------------------------------------------
// Delete all readmewp posts and their post meta.
// -------------------------------------------------------------------------
$post_ids = $wpdb->get_col(
	$wpdb->prepare(
		"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s",
		'readmewp'
	)
);

foreach ( $post_ids as $post_id ) {
	// Delete post meta rows directly — wp_delete_post() triggers many hooks
	// and loads unnecessary code during uninstall.
	$wpdb->delete( $wpdb->postmeta, [ 'post_id' => (int) $post_id ], [ '%d' ] );
	$wpdb->delete( $wpdb->posts, [ 'ID' => (int) $post_id ], [ '%d' ] );
}

// -------------------------------------------------------------------------
// Delete plugin options (none defined yet, placeholder for future use).
// -------------------------------------------------------------------------
delete_option( 'readmewp_version' );
delete_option( 'readmewp_settings' );

// -------------------------------------------------------------------------
// Flush rewrite rules.
// -------------------------------------------------------------------------
flush_rewrite_rules();
