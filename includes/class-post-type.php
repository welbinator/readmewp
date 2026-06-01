<?php
/**
 * Registers the readmewp custom post type.
 *
 * @package ReadMeWP
 */

declare( strict_types=1 );

namespace ReadMeWP;

defined( 'ABSPATH' ) || exit;

/**
 * Class Post_Type
 */
class Post_Type {

	/**
	 * CPT slug.
	 */
	const SLUG = 'readmewp';

	/**
	 * Hook into WordPress.
	 */
	public function register(): void {
		add_action( 'init', [ $this, 'register_post_type' ] );
	}

	/**
	 * Register the post type.
	 */
	public function register_post_type(): void {
		$labels = [
			'name'               => _x( 'READMEs', 'post type general name', 'readmewp' ),
			'singular_name'      => _x( 'README', 'post type singular name', 'readmewp' ),
			'add_new'            => __( 'Add New README', 'readmewp' ),
			'add_new_item'       => __( 'Add New README', 'readmewp' ),
			'edit_item'          => __( 'Edit README', 'readmewp' ),
			'new_item'           => __( 'New README', 'readmewp' ),
			'view_item'          => __( 'View README', 'readmewp' ),
			'search_items'       => __( 'Search READMEs', 'readmewp' ),
			'not_found'          => __( 'No READMEs found.', 'readmewp' ),
			'not_found_in_trash' => __( 'No READMEs found in Trash.', 'readmewp' ),
			'all_items'          => __( 'All READMEs', 'readmewp' ),
			'menu_name'          => __( 'READMEs', 'readmewp' ),
		];

		$args = [
			'labels'              => $labels,
			'public'              => false,        // Never front-end accessible.
			'publicly_queryable'  => false,
			'show_ui'             => true,         // Show the editor for admins.
			'show_in_menu'        => true,         // Admins manage READMEs here.
			'show_in_admin_bar'   => false,
			'show_in_rest'        => true,         // Enable Gutenberg editor.
			'capability_type'     => [ 'readmewp', 'readmewps' ],
			'map_meta_cap'        => true,
			'hierarchical'        => false,
			'supports'            => [ 'title', 'editor', 'revisions' ],
			'rewrite'             => false,
			'query_var'           => false,
			'menu_icon'           => 'dashicons-media-text',
		];

		register_post_type( self::SLUG, $args );
	}
}
