<?php
/**
 * Builds the top-level ReadMeWP admin menu and per-README submenus.
 *
 * Only READMEs the current user is permitted to read appear in the menu.
 * If a user has no readable READMEs, the top-level menu item is not shown.
 *
 * @package ReadMeWP
 */

declare( strict_types=1 );

namespace ReadMeWP;

defined( 'ABSPATH' ) || exit;

/**
 * Class Menu
 */
class Menu {

	/**
	 * Top-level menu slug.
	 */
	const MENU_SLUG = 'readmewp';

	/**
	 * Hook into WordPress.
	 */
	public function register(): void {
		add_action( 'admin_menu', [ $this, 'build_menu' ] );
		add_action( 'pre_get_posts', [ $this, 'filter_list_screen' ] );
		add_filter( 'post_row_actions', [ $this, 'row_actions' ], 10, 2 );
	}

	/**
	 * Build the admin menu for the current user.
	 */
	public function build_menu(): void {
		$user_id     = get_current_user_id();
		$permissions = new Permissions();
		$readmes     = $permissions->get_readable_posts( $user_id );

		// Non-admins with no visible READMEs and no create access: don't show menu at all.
		$is_admin   = current_user_can( 'manage_options' );
		$can_create = ! $is_admin && Settings::user_can_create( $user_id );
		if ( ! $is_admin && empty( $readmes ) && ! $can_create ) {
			return;
		}

		// Top-level menu page — everyone lands on the CPT list screen.
		// pre_get_posts restricts what non-admins see on that screen.
		$top_level_slug = 'edit.php?post_type=' . Post_Type::SLUG;

		// Add a submenu item per readable README.
		foreach ( $readmes as $readme ) {
			$page_slug = self::MENU_SLUG . '-' . $readme->ID;

			add_submenu_page(
				$top_level_slug,
				esc_html( $readme->post_title ),
				esc_html( $readme->post_title ),
				'read',
				$page_slug,
				static function () use ( $readme ) {
					( new Viewer() )->render( $readme );
				}
			);
		}

		// "Add New README" link for non-admins who have create access.
		if ( $can_create ) {
			add_submenu_page(
				$top_level_slug,
				__( 'Add New README', 'readmewp' ),
				'+ ' . __( 'Add New README', 'readmewp' ),
				'edit_readmewps',
				'post-new.php?post_type=' . Post_Type::SLUG
			);
		}
	}

	/**
	 * Restrict the CPT list screen to only show READMEs the current user can read.
	 * Admins always see everything.
	 *
	 * @param \WP_Query $query The current query.
	 */
	public function filter_list_screen( \WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}
		if ( $query->get( 'post_type' ) !== Post_Type::SLUG ) {
			return;
		}

		// Admins see everything — no post__in restriction.
		// This lets WP's Trash, Draft, and other status tabs work naturally.
		if ( current_user_can( 'manage_options' ) ) {
			return;
		}

		$user_id     = get_current_user_id();
		$permissions = new Permissions();

		// IDs of published READMEs this user has read permission on.
		$readable     = $permissions->get_readable_posts( $user_id );
		$readable_ids = wp_list_pluck( $readable, 'ID' );

		// Also include posts this user authored, across all statuses.
		// This ensures their own drafts appear on the "Mine" tab and their
		// own trashed posts appear on the "Trash" tab.
		$own_ids = get_posts( [
			'post_type'      => Post_Type::SLUG,
			'post_status'    => 'any',
			'author'         => $user_id,
			'posts_per_page' => -1,
			'fields'         => 'ids',
		] );

		$allowed_ids = array_unique( array_merge( $readable_ids, (array) $own_ids ) );

		if ( empty( $allowed_ids ) ) {
			// No accessible READMEs — return an impossible ID so query yields nothing.
			$query->set( 'post__in', [ 0 ] );
			return;
		}

		$query->set( 'post__in', $allowed_ids );
	}

	/**
	 * Customize row actions on the CPT list screen.
	 *
	 * - Owners get an Edit link.
	 * - Everyone with read access gets a View link (opens the viewer page).
	 * - Non-admins never see Trash/Delete on posts they don't own.
	 *
	 * @param array    $actions Default actions.
	 * @param \WP_Post $post    Current post.
	 * @return array
	 */
	public function row_actions( array $actions, \WP_Post $post ): array {
		if ( Post_Type::SLUG !== $post->post_type ) {
			return $actions;
		}

		$user_id    = get_current_user_id();
		$is_admin   = current_user_can( 'manage_options' );
		$is_owner   = Ownership::is_owner( $post->ID, $user_id );
		$is_trashed = 'trash' === $post->post_status;

		// Start fresh — rebuild row actions so View is always present where applicable.
		$new_actions = [];

		if ( $is_trashed ) {
			// Trash tab: only admins and owners can restore / permanently delete.
			if ( $is_admin || $is_owner ) {
				$restore_url = wp_nonce_url(
					admin_url( 'post.php?post=' . $post->ID . '&action=untrash' ),
					'untrash-post_' . $post->ID
				);
				$new_actions['untrash'] = sprintf(
					'<a href="%s">%s</a>',
					esc_url( $restore_url ),
					esc_html__( 'Restore', 'readmewp' )
				);

				$delete_url = wp_nonce_url(
					admin_url( 'post.php?post=' . $post->ID . '&action=delete' ),
					'delete-post_' . $post->ID
				);
				$new_actions['delete'] = sprintf(
					'<a href="%s" class="submitdelete" aria-label="%s">%s</a>',
					esc_url( $delete_url ),
					esc_attr( sprintf( __( 'Permanently delete &#8220;%s&#8221;', 'readmewp' ), $post->post_title ) ),
					esc_html__( 'Delete Permanently', 'readmewp' )
				);
			}
			// No View link for trashed posts — the submenu page only exists for published posts.
			return $new_actions;
		}

		// Non-trash: Edit + Trash for admins/owners, View for published posts.
		if ( $is_admin || $is_owner ) {
			$new_actions['edit'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'post.php?post=' . $post->ID . '&action=edit' ) ),
				esc_html__( 'Edit', 'readmewp' )
			);

			$trash_url = wp_nonce_url(
				admin_url( 'post.php?post=' . $post->ID . '&action=trash' ),
				'trash-post_' . $post->ID
			);
			$new_actions['trash'] = sprintf(
				'<a href="%s" class="submitdelete" aria-label="%s">%s</a>',
				esc_url( $trash_url ),
				esc_attr( sprintf( __( 'Move &#8220;%s&#8221; to the Trash', 'readmewp' ), $post->post_title ) ),
				esc_html__( 'Trash', 'readmewp' )
			);

			// Non-owning admins: check owner-lock — if locked, downgrade to View only.
			if ( $is_admin && ! $is_owner ) {
				$owner_only = (bool) get_post_meta( $post->ID, Ownership::META_OWNER_ONLY, true );
				if ( $owner_only ) {
					unset( $new_actions['edit'], $new_actions['trash'] );
				}
			}
		}

		// View link only makes sense for published posts — the submenu page only
		// exists for published READMEs and is what the viewer route resolves to.
		if ( 'publish' === $post->post_status ) {
			$page_slug = self::MENU_SLUG . '-' . $post->ID;
			$new_actions['view'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'admin.php?page=' . $page_slug ) ),
				esc_html__( 'View', 'readmewp' )
			);
		}

		return $new_actions;
	}

	/**
	 * Fallback: no READMEs and no create access — should rarely be seen since
	 * build_menu() hides the menu entirely in this case.
	 */
	public function render_no_selection(): void {
		echo '<div class="wrap"><h1>' . esc_html__( 'ReadMeWP', 'readmewp' ) . '</h1>';
		echo '<p>' . esc_html__( 'No READMEs have been shared with you yet.', 'readmewp' ) . '</p>';
		echo '</div>';
	}
}
