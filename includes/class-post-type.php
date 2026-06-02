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
		add_action( 'init', array( $this, 'register_post_type' ) );
		// S-01: Gate REST API collection and single-item reads on Permissions::user_can_read().
		add_filter( 'rest_readmewp_query', array( $this, 'rest_restrict_collection' ), 10, 2 );
		add_filter( 'rest_prepare_readmewp', array( $this, 'rest_prepare_item' ), 10, 3 );
	}

	/**
	 * Register the post type.
	 */
	public function register_post_type(): void {
		$labels = array(
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
		);

		$args = array(
			'labels'             => $labels,
			'public'             => false,        // Never front-end accessible.
			'publicly_queryable' => false,
			'show_ui'            => true,         // Show the editor for admins.
			'show_in_menu'       => true,         // Admins manage READMEs here.
			'show_in_admin_bar'  => false,
			'show_in_rest'       => true,         // Enable Gutenberg editor.
			'capability_type'    => array( 'readmewp', 'readmewps' ),
			'map_meta_cap'       => true,
			'hierarchical'       => false,
			'supports'           => array( 'title', 'editor', 'revisions' ),
			'rewrite'            => false,
			'query_var'          => false,
			'menu_icon'          => 'dashicons-media-text',
		);

		register_post_type( self::SLUG, $args );
	}

	// -------------------------------------------------------------------------
	// REST API permission guards (S-01)
	// -------------------------------------------------------------------------

	/**
	 * Restrict REST collection queries to posts the current user may read.
	 *
	 * Without this, any user who has `edit_readmewps` (granted so the admin
	 * list screen works) could enumerate all READMEs via the REST endpoint.
	 *
	 * @param array            $args    WP_Query args built by the REST controller.
	 * @param \WP_REST_Request $request Current REST request.
	 * @return array
	 */
	public function rest_restrict_collection( array $args, \WP_REST_Request $request ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$user_id = get_current_user_id();
		if ( user_can( $user_id, 'manage_options' ) ) {
			return $args; // Admins see everything.
		}
		$permissions = new Permissions();
		$readable    = wp_list_pluck( $permissions->get_readable_posts( $user_id ), 'ID' );
		// Force an empty result if nothing is readable.
		$args['post__in'] = empty( $readable ) ? array( 0 ) : $readable;
		return $args;
	}

	/**
	 * Block REST single-item reads for posts the current user may not read.
	 *
	 * @param \WP_REST_Response|\WP_Error $response REST response.
	 * @param \WP_Post                    $post      The README post.
	 * @param \WP_REST_Request            $request   Current REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function rest_prepare_item( $response, \WP_Post $post, \WP_REST_Request $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$user_id = get_current_user_id();
		if ( user_can( $user_id, 'manage_options' ) ) {
			return $response;
		}
		$permissions = new Permissions();
		if ( ! $permissions->user_can_read( $post->ID, $user_id ) ) {
			return new \WP_Error(
				'rest_cannot_read',
				__( 'You do not have permission to view this README.', 'readmewp' ),
				array( 'status' => 403 )
			);
		}
		return $response;
	}
}
