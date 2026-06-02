<?php
/**
 * Renders the read-only README viewer page inside wp-admin.
 *
 * Also handles direct URL access attempts — if a user tries to reach
 * a README they don't have permission for, they get an error message.
 *
 * @package ReadMeWP
 */

declare( strict_types=1 );

namespace ReadMeWP;

defined( 'ABSPATH' ) || exit;

/**
 * Class Viewer
 */
class Viewer {

	/**
	 * Hook into WordPress.
	 * Registers the access-guard for direct ?page=readmewp-{id} URL attempts.
	 */
	public function register(): void {
		add_action( 'admin_init', [ $this, 'guard_direct_access' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Intercept direct URL access to a README the user can't read.
	 *
	 * WordPress will have already registered the submenu page callback
	 * only for READMEs the user can see. But if someone crafts a URL
	 * manually we need to stop them here.
	 */
	public function guard_direct_access(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

		if ( ! str_starts_with( $page, Menu::MENU_SLUG . '-' ) ) {
			return;
		}

		// C-02: Use substr (not str_replace) so only the leading prefix is stripped,
		// not every occurrence of the slug within the string.
		$post_id = (int) substr( $page, strlen( Menu::MENU_SLUG . '-' ) );
		if ( $post_id <= 0 ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post || Post_Type::SLUG !== $post->post_type ) {
			return;
		}

		$permissions = new Permissions();
		if ( ! $permissions->user_can_read( $post_id, get_current_user_id() ) ) {
			wp_die(
				esc_html__( 'You do not have permission to view this README.', 'readmewp' ),
				esc_html__( 'Access Denied', 'readmewp' ),
				[ 'response' => 403, 'back_link' => true ]
			);
		}
	}

	/**
	 * Enqueue viewer stylesheet on ReadMeWP viewer pages.
	 * Note: admin.css is already enqueued by Settings::enqueue_assets() for
	 * post-type edit screens. This covers the custom viewer menu pages.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		// Settings::enqueue_assets() handles post-type screens.
		// We only need to enqueue on the custom viewer pages (toplevel_page_readmewp*).
		if ( ! str_contains( $hook_suffix, Menu::MENU_SLUG ) ) {
			return;
		}
		// Avoid double-enqueue — Settings may have already registered this handle.
		if ( ! wp_style_is( 'readmewp-admin', 'enqueued' ) ) {
			wp_enqueue_style(
				'readmewp-admin',
				READMEWP_URL . 'admin/css/admin.css',
				[],
				READMEWP_VERSION
			);
		}
	}

	/**
	 * Render the read-only README content.
	 *
	 * @param \WP_Post $readme The README post to display.
	 */
	public function render( \WP_Post $readme ): void {
		// Double-check permission (belt-and-suspenders).
		$permissions = new Permissions();
		if ( ! $permissions->user_can_read( $readme->ID, get_current_user_id() ) ) {
			wp_die(
				esc_html__( 'You do not have permission to view this README.', 'readmewp' ),
				esc_html__( 'Access Denied', 'readmewp' ),
				[ 'response' => 403, 'back_link' => true ]
			);
		}
		?>
		<div class="wrap readmewp-viewer">

			<h1 class="readmewp-viewer__title">
				<?php echo esc_html( $readme->post_title ); ?>
				<?php
			// C-03: Only show Edit link when the user can actually edit this post
			// (respects owner-lock — non-owning admins won't see the button on locked READMEs).
			if ( current_user_can( 'edit_post', $readme->ID ) ) :
			?>
					<a href="<?php echo esc_url( admin_url( 'post.php?post=' . $readme->ID . '&action=edit' ) ); ?>"
					   class="page-title-action readmewp-edit-link">
						<?php esc_html_e( 'Edit', 'readmewp' ); ?>
					</a>
				<?php endif; ?>
			</h1>

			<div class="readmewp-viewer__body">
				<?php
				// the_content filters handle shortcodes, blocks, embeds, etc.
				// We set up the global $post so filters work correctly, then restore it.
				global $post;
				$original_post = $post;
				// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
				$post = $readme;
				setup_postdata( $readme );

				echo wp_kses_post( apply_filters( 'the_content', $readme->post_content ) );

				wp_reset_postdata();
				// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
				$post = $original_post;
				?>
			</div>

		</div>
		<?php
	}
}
