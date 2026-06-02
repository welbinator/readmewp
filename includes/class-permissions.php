<?php
/**
 * Handles the permissions meta box and persistence for each README.
 *
 * Stores two pieces of post meta:
 *   _readmewp_roles  — array of WP role slugs that can read this README.
 *   _readmewp_users  — array of user IDs (int) that can read this README.
 *
 * Access logic (additive):
 *   A user may read a README if ANY of the following are true:
 *     1. The user has manage_options (administrator).
 *     2. The user's role is in _readmewp_roles.
 *     3. The user's ID is in _readmewp_users.
 *
 * @package ReadMeWP
 */

declare( strict_types=1 );

namespace ReadMeWP;

defined( 'ABSPATH' ) || exit;

/**
 * Class Permissions
 */
class Permissions {

	/**
	 * Meta key for allowed roles.
	 */
	const META_ROLES = '_readmewp_roles';

	/**
	 * Meta key for allowed user IDs.
	 */
	const META_USERS = '_readmewp_users';

	/**
	 * Hook into WordPress.
	 */
	public function register(): void {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post_' . Post_Type::SLUG, array( $this, 'save_meta' ), 10, 2 );
	}

	/**
	 * Register the meta box.
	 */
	public function add_meta_box(): void {
		add_meta_box(
			'readmewp_permissions',
			__( 'Who Can Read This README?', 'readmewp' ),
			array( $this, 'render_meta_box' ),
			Post_Type::SLUG,
			'side',
			'high'
		);
	}

	/**
	 * Render the meta box HTML.
	 *
	 * @param \WP_Post $post Current post object.
	 */
	public function render_meta_box( \WP_Post $post ): void {
		wp_nonce_field( 'readmewp_save_permissions', 'readmewp_nonce' );

		$saved_roles = $this->get_allowed_roles( $post->ID );
		$saved_users = $this->get_allowed_users( $post->ID );

		// For new posts (no meta saved yet), default administrator to checked.
		$meta_exists = metadata_exists( 'post', $post->ID, self::META_ROLES );

		// --- Roles section ---
		$all_roles = $this->get_all_roles();
		?>
		<div class="readmewp-meta-box">

			<p><strong><?php esc_html_e( 'Roles', 'readmewp' ); ?></strong></p>
			<p class="description"><?php esc_html_e( 'All members of the selected roles can read this README.', 'readmewp' ); ?></p>
			<ul class="readmewp-roles-list">
				<?php foreach ( $all_roles as $role_slug => $role_name ) : ?>
					<li>
						<label>
							<input
								type="checkbox"
								name="readmewp_roles[]"
								value="<?php echo esc_attr( $role_slug ); ?>"
								<?php
								if ( 'administrator' === $role_slug ) {
									// Checked by default on new posts; otherwise use saved value.
									$is_checked = $meta_exists ? in_array( 'administrator', $saved_roles, true ) : true;
									checked( $is_checked );
								} else {
									checked( in_array( $role_slug, $saved_roles, true ) );
								}
								?>
							>
							<?php echo esc_html( $role_name ); ?>
						</label>
					</li>
				<?php endforeach; ?>
			</ul>

			<hr>

			<p><strong><?php esc_html_e( 'Specific Users', 'readmewp' ); ?></strong></p>
			<p class="description"><?php esc_html_e( 'These individual users can read this README regardless of role.', 'readmewp' ); ?></p>

			<div class="readmewp-user-search-wrap">
				<input
					type="text"
					id="readmewp-user-search"
					placeholder="<?php esc_attr_e( 'Search by name or email…', 'readmewp' ); ?>"
					autocomplete="off"
				>
				<ul id="readmewp-user-suggestions" class="readmewp-suggestions" style="display:none;"></ul>
			</div>

			<ul id="readmewp-selected-users" class="readmewp-selected-users">
				<?php
				foreach ( $saved_users as $user_id ) :
					$user = get_userdata( $user_id );
					if ( ! $user ) {
						continue;
					}
					?>
					<li data-user-id="<?php echo esc_attr( (string) $user_id ); ?>">
						<span class="readmewp-user-label">
							<?php echo esc_html( $user->display_name . ' (' . $user->user_email . ')' ); ?>
						</span>
						<button type="button" class="readmewp-remove-user" aria-label="<?php esc_attr_e( 'Remove', 'readmewp' ); ?>">&#x2715;</button>
						<input type="hidden" name="readmewp_users[]" value="<?php echo esc_attr( (string) $user_id ); ?>">
					</li>
				<?php endforeach; ?>
			</ul>

		</div>

		<?php
		$this->enqueue_meta_box_assets();
	}

	/**
	 * Save meta box data.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public function save_meta( int $post_id, \WP_Post $post ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		// Nonce check.
		if (
			! isset( $_POST['readmewp_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['readmewp_nonce'] ) ), 'readmewp_save_permissions' )
		) {
			return;
		}

		// Autosave / bulk edit bail.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Capability check — admin, or the owner of this README.
		if ( ! current_user_can( 'manage_options' ) && ! Ownership::is_owner( $post_id, get_current_user_id() ) ) {
			return;
		}

		// --- Save roles ---
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$raw_roles      = isset( $_POST['readmewp_roles'] ) ? array_map( 'wp_unslash', (array) $_POST['readmewp_roles'] ) : array();
		$all_role_slugs = array_keys( $this->get_all_roles() );
		$clean_roles    = array_values(
			array_intersect(
				array_map( 'sanitize_key', $raw_roles ),
				$all_role_slugs
			)
		);
		update_post_meta( $post_id, self::META_ROLES, $clean_roles );

		// --- Save users ---
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$raw_users   = isset( $_POST['readmewp_users'] ) ? array_map( 'wp_unslash', (array) $_POST['readmewp_users'] ) : array();
		$clean_users = array_values(
			array_filter(
				array_map( 'absint', $raw_users ),
				static fn( int $id ) => $id > 0 && get_userdata( $id ) !== false
			)
		);
		update_post_meta( $post_id, self::META_USERS, $clean_users );
	}

	// -------------------------------------------------------------------------
	// Public helpers used by Menu and Viewer classes.
	// -------------------------------------------------------------------------

	/**
	 * Check whether a given user may read a specific README.
	 *
	 * @param int $post_id README post ID.
	 * @param int $user_id WordPress user ID.
	 * @return bool
	 */
	public function user_can_read( int $post_id, int $user_id ): bool {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}

		// The owner of a README can always read it.
		if ( Ownership::is_owner( $post_id, $user_id ) ) {
			return true;
		}

		// Administrators: respect the 'administrator' role checkbox.
		// Default to allowed when the meta has never been saved (new/unpublished post).
		if ( user_can( $user_id, 'manage_options' ) ) {
			$meta_exists = metadata_exists( 'post', $post_id, self::META_ROLES );
			if ( ! $meta_exists ) {
				return true; // New post default.
			}
			$allowed_roles = $this->get_allowed_roles( $post_id );
			return in_array( 'administrator', $allowed_roles, true );
		}

		// Role match.
		$allowed_roles = $this->get_allowed_roles( $post_id );
		if ( array_intersect( (array) $user->roles, $allowed_roles ) ) {
			return true;
		}

		// Individual user match.
		$allowed_users = $this->get_allowed_users( $post_id );
		if ( in_array( $user_id, $allowed_users, true ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Get all published READMEs visible to a specific user.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return \WP_Post[]
	 */
	public function get_readable_posts( int $user_id ): array {
		$posts = get_posts(
			array(
				'post_type'      => Post_Type::SLUG,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		return array_values(
			array_filter(
				$posts,
				fn( \WP_Post $p ) => $this->user_can_read( $p->ID, $user_id )
			)
		);
	}

	// -------------------------------------------------------------------------
	// Private helpers.
	// -------------------------------------------------------------------------

	/**
	 * Get saved allowed roles for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return string[]
	 */
	private function get_allowed_roles( int $post_id ): array {
		$value = get_post_meta( $post_id, self::META_ROLES, true );
		return is_array( $value ) ? $value : array();
	}

	/**
	 * Get saved allowed user IDs for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return int[]
	 */
	private function get_allowed_users( int $post_id ): array {
		$value = get_post_meta( $post_id, self::META_USERS, true );
		if ( ! is_array( $value ) ) {
			return array();
		}
		return array_map( 'intval', $value );
	}

	/**
	 * Get all registered WP roles as slug => display-name.
	 *
	 * @return array<string, string>
	 */
	private function get_all_roles(): array {
		global $wp_roles;
		$roles = array();
		foreach ( $wp_roles->roles as $slug => $data ) {
			$roles[ $slug ] = translate_user_role( $data['name'] );
		}
		return $roles;
	}

	/**
	 * Enqueue JS/CSS for the meta box (admin-only, this post type only).
	 * Assets and AJAX are now managed by Settings::enqueue_assets() and
	 * Settings::ajax_user_search() so they work on both the edit screen
	 * and the settings page from a single registration point.
	 */
	private function enqueue_meta_box_assets(): void {
		// Assets are enqueued globally for all ReadMeWP screens by Settings.
		// This method is intentionally a no-op to avoid duplicate enqueues.
	}
}
