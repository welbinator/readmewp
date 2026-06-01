<?php
/**
 * Handles the per-README owner lock.
 *
 * Meta box on the edit screen lets the author choose whether other admins
 * can also edit this README, or whether they alone can edit it.
 *
 * Meta keys:
 *   _readmewp_owner_id   — int, set automatically on first publish/save.
 *   _readmewp_owner_only — '1' if only the owner may edit; '' otherwise.
 *
 * @package ReadMeWP
 */

declare( strict_types=1 );

namespace ReadMeWP;

defined( 'ABSPATH' ) || exit;

/**
 * Class Ownership
 */
class Ownership {

	const META_OWNER_ID   = '_readmewp_owner_id';
	const META_OWNER_ONLY = '_readmewp_owner_only';

	/**
	 * Hook into WordPress.
	 */
	public function register(): void {
		add_action( 'add_meta_boxes', [ $this, 'add_meta_box' ] );
		add_action( 'save_post_' . Post_Type::SLUG, [ $this, 'save_meta' ], 10, 2 );
	}

	/**
	 * Register the meta box.
	 */
	public function add_meta_box(): void {
		add_meta_box(
			'readmewp_ownership',
			__( 'Edit Access', 'readmewp' ),
			[ $this, 'render_meta_box' ],
			Post_Type::SLUG,
			'side',
			'default'
		);
	}

	/**
	 * Render the meta box HTML.
	 *
	 * @param \WP_Post $post Current post.
	 */
	public function render_meta_box( \WP_Post $post ): void {
		wp_nonce_field( 'readmewp_save_ownership', 'readmewp_ownership_nonce' );

		// If the meta has never been saved (new post), default to checked.
		$meta_exists = metadata_exists( 'post', $post->ID, self::META_OWNER_ONLY );
		$owner_only  = $meta_exists ? (bool) get_post_meta( $post->ID, self::META_OWNER_ONLY, true ) : true;
		$owner_id   = (int) get_post_meta( $post->ID, self::META_OWNER_ID, true );
		$owner      = $owner_id ? get_userdata( $owner_id ) : null;
		?>
		<div class="readmewp-meta-box">

			<?php if ( $owner ) : ?>
				<p class="description">
					<?php
					printf(
						/* translators: %s: display name of the README owner */
						esc_html__( 'Owner: %s', 'readmewp' ),
						'<strong>' . esc_html( $owner->display_name ) . '</strong>'
					);
					?>
				</p>
			<?php endif; ?>

			<label style="display:flex;align-items:flex-start;gap:8px;margin-top:10px;cursor:pointer;">
				<input
					type="checkbox"
					name="readmewp_owner_only"
					value="1"
					style="margin-top:3px;flex-shrink:0;"
					<?php checked( $owner_only ); ?>
				>
				<span>
					<?php esc_html_e( 'Only I can edit this README', 'readmewp' ); ?>
					<br>
					<span class="description">
						<?php esc_html_e( 'When checked, other admins can view but not edit this README.', 'readmewp' ); ?>
					</span>
				</span>
			</label>

		</div>
		<?php
	}

	/**
	 * Persist ownership meta on save.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public function save_meta( int $post_id, \WP_Post $post ): void {
		// Bail on autosave / revisions / missing nonce.
		if (
			wp_is_post_autosave( $post_id ) ||
			wp_is_post_revision( $post_id ) ||
			! isset( $_POST['readmewp_ownership_nonce'] ) ||
			! wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST['readmewp_ownership_nonce'] ) ),
				'readmewp_save_ownership'
			)
		) {
			return;
		}

		// Set owner ID on first save; never overwrite after that.
		$existing_owner = get_post_meta( $post_id, self::META_OWNER_ID, true );
		if ( ! $existing_owner ) {
			update_post_meta( $post_id, self::META_OWNER_ID, get_current_user_id() );
		}

		// Owner-only flag.
		$owner_only = isset( $_POST['readmewp_owner_only'] ) ? '1' : '';
		update_post_meta( $post_id, self::META_OWNER_ONLY, $owner_only );
	}

	/**
	 * Check whether a specific user is the owner of a README.
	 *
	 * @param int $post_id Post ID.
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function is_owner( int $post_id, int $user_id ): bool {
		$owner_id = (int) get_post_meta( $post_id, self::META_OWNER_ID, true );
		if ( ! $owner_id ) {
			// No owner recorded — treat the WP post_author as the owner.
			$post = get_post( $post_id );
			return $post && (int) $post->post_author === $user_id;
		}
		return $owner_id === $user_id;
	}

	/**
	 * Check whether a README is locked to its owner only.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function is_owner_only( int $post_id ): bool {
		return (bool) get_post_meta( $post_id, self::META_OWNER_ONLY, true );
	}
}
