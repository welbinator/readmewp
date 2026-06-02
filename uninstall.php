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
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$rmwp_post_ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->prepare(
		"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s",
		'readmewp'
	)
);

foreach ( $rmwp_post_ids as $rmwp_post_id ) {
	// Delete post meta rows directly — wp_delete_post() triggers many hooks
	// and loads unnecessary code during uninstall.
	$wpdb->delete( $wpdb->postmeta, array( 'post_id' => (int) $rmwp_post_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->delete( $wpdb->posts, array( 'ID' => (int) $rmwp_post_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
}
// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

// -------------------------------------------------------------------------
// Delete plugin options (none defined yet, placeholder for future use).
// -------------------------------------------------------------------------
delete_option( 'readmewp_version' );
delete_option( 'readmewp_settings' );

// -------------------------------------------------------------------------
// Flush rewrite rules.
// -------------------------------------------------------------------------
flush_rewrite_rules();
