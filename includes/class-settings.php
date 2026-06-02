<?php
/**
 * ReadMeWP Settings page (admin-only).
 *
 * Options stored:
 *   readmewp_settings (array)
 *     allow_creator_roles  — string[]  role slugs that can create READMEs
 *     allow_creator_users  — int[]     specific user IDs that can create READMEs
 *
 * @package ReadMeWP
 */

declare( strict_types=1 );

namespace ReadMeWP;

defined( 'ABSPATH' ) || exit;

/**
 * Class Settings
 */
class Settings {

	const OPTION_KEY     = 'readmewp_settings';
	const PAGE_SLUG      = 'readmewp-settings';
	const NONCE_SETTINGS = 'readmewp_save_settings';

	// -------------------------------------------------------------------------
	// Bootstrap
	// -------------------------------------------------------------------------

	/**
	 * Hook into WordPress.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'handle_save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		// AJAX user search (shared with Permissions meta box).
		add_action( 'wp_ajax_readmewp_user_search', array( $this, 'ajax_user_search' ) );
	}

	// -------------------------------------------------------------------------
	// Menu
	// -------------------------------------------------------------------------

	/**
	 * Add the Settings submenu under the ReadMeWP top-level menu.
	 */
	public function add_settings_page(): void {
		add_options_page(
			__( 'ReadMeWP Settings', 'readmewp' ),
			__( 'ReadMeWP', 'readmewp' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	// -------------------------------------------------------------------------
	// Assets
	// -------------------------------------------------------------------------

	/**
	 * Enqueue JS/CSS on ReadMeWP admin pages.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		// Settings page and the post-type edit screens.
		$is_readmewp_page = str_contains( $hook_suffix, self::PAGE_SLUG )
			|| str_contains( $hook_suffix, Post_Type::SLUG );

		if ( ! $is_readmewp_page ) {
			return;
		}

		wp_enqueue_style(
			'readmewp-admin',
			READMEWP_URL . 'admin/css/admin.css',
			array(),
			READMEWP_VERSION
		);

		wp_enqueue_script(
			'readmewp-admin',
			READMEWP_URL . 'admin/js/admin.js',
			array( 'jquery' ),
			READMEWP_VERSION,
			true
		);

		wp_localize_script(
			'readmewp-admin',
			'ReadMeWP',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'readmewp_user_search' ),
				'strings' => array(
					'remove' => __( 'Remove', 'readmewp' ),
				),
			)
		);
	}

	// -------------------------------------------------------------------------
	// Render
	// -------------------------------------------------------------------------

	/**
	 * Render the settings page.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'readmewp' ) );
		}

		$settings      = self::get();
		$creator_roles = $settings['allow_creator_roles'] ?? array();
		$creator_users = $settings['allow_creator_users'] ?? array();
		$creators_on   = ! empty( $creator_roles ) || ! empty( $creator_users );
		$all_roles     = $this->get_all_roles();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'ReadMeWP Settings', 'readmewp' ); ?></h1>

			<?php if ( isset( $_GET['readmewp_saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Settings saved.', 'readmewp' ); ?></p>
				</div>
			<?php endif; ?>

			<form method="post" action="">
				<?php wp_nonce_field( self::NONCE_SETTINGS, 'readmewp_settings_nonce' ); ?>

				<h2 class="title"><?php esc_html_e( 'README Creation', 'readmewp' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'By default only administrators can create READMEs. You can grant creation access to specific roles or individual users below.', 'readmewp' ); ?>
				</p>

				<table class="form-table" role="presentation">

					<tr>
						<th scope="row"><?php esc_html_e( 'Roles that can create READMEs', 'readmewp' ); ?></th>
						<td>
							<ul class="readmewp-roles-list">
								<?php foreach ( $all_roles as $slug => $name ) : ?>
									<?php
									if ( 'administrator' === $slug ) {
										continue;}
									?>
									<li>
										<label>
											<input
												type="checkbox"
												name="readmewp_creator_roles[]"
												value="<?php echo esc_attr( $slug ); ?>"
												<?php checked( in_array( $slug, $creator_roles, true ) ); ?>
											>
											<?php echo esc_html( $name ); ?>
										</label>
									</li>
								<?php endforeach; ?>
							</ul>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Specific users that can create READMEs', 'readmewp' ); ?></th>
						<td>
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
								foreach ( $creator_users as $uid ) :
									$u = get_userdata( (int) $uid );
									if ( ! $u ) {
										continue;
									}
									?>
									<li data-user-id="<?php echo esc_attr( (string) $uid ); ?>">
										<span class="readmewp-user-label">
											<?php echo esc_html( $u->display_name . ' (' . $u->user_email . ')' ); ?>
										</span>
										<button type="button" class="readmewp-remove-user" aria-label="<?php esc_attr_e( 'Remove', 'readmewp' ); ?>">&#x2715;</button>
										<input type="hidden" name="readmewp_creator_users[]" value="<?php echo esc_attr( (string) $uid ); ?>">
									</li>
								<?php endforeach; ?>
							</ul>
						</td>
					</tr>

				</table>

				<?php submit_button( __( 'Save Settings', 'readmewp' ) ); ?>
			</form>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Save
	// -------------------------------------------------------------------------

	/**
	 * Handle settings form submission.
	 */
	public function handle_save(): void {
		if (
			! isset( $_POST['readmewp_settings_nonce'] ) ||
			! wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST['readmewp_settings_nonce'] ) ),
				self::NONCE_SETTINGS
			)
		) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$roles = isset( $_POST['readmewp_creator_roles'] )
			? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['readmewp_creator_roles'] ) )
			: array();

		$users = isset( $_POST['readmewp_creator_users'] )
			? array_map( 'intval', (array) wp_unslash( $_POST['readmewp_creator_users'] ) )
			: array();

		// Validate roles against real WP roles.
		$valid_roles = array_keys( wp_roles()->roles );
		$roles       = array_values( array_intersect( $roles, $valid_roles ) );

		// Validate user IDs.
		$users = array_values( array_filter( $users, static fn( $id ) => $id > 0 && get_user_by( 'ID', $id ) ) );

		// C-01: Capture previous settings BEFORE update_option so we flush
		// users who were removed from the list, not just those who are still in it.
		$prev_settings = self::get();
		$prev_users    = $prev_settings['allow_creator_users'] ?? array();

		update_option(
			self::OPTION_KEY,
			array(
				'allow_creator_roles' => $roles,
				'allow_creator_users' => $users,
			)
		);

		// Bust user capability caches for anyone listed so the change
		// takes effect immediately without waiting for a new session.
		foreach ( $users as $uid ) {
			clean_user_cache( $uid );
		}
		// Bust caches for users who were just removed from the list.
		foreach ( $prev_users as $uid ) {
			clean_user_cache( (int) $uid );
		}

		$redirect_url = wp_get_referer() ? wp_get_referer() : self::settings_url();
		wp_safe_redirect( add_query_arg( 'readmewp_saved', '1', $redirect_url ) );
		exit;
	}

	// -------------------------------------------------------------------------
	// AJAX
	// -------------------------------------------------------------------------

	/**
	 * AJAX: search users by name or email.
	 * (Shared by Permissions meta box and Settings page.)
	 */
	public function ajax_user_search(): void {
		check_ajax_referer( 'readmewp_user_search', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array(), 403 );
		}

		// J-01 (PHP side): Read term from POST — JS was changed to $.post, so the
		// parameter arrives in $_POST. Fall back to $_GET for backward compatibility.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$raw_term = isset( $_POST['term'] ) ? $_POST['term'] : ( isset( $_GET['term'] ) ? $_GET['term'] : '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$term     = sanitize_text_field( wp_unslash( (string) $raw_term ) );
		if ( strlen( $term ) < 2 ) {
			wp_send_json_success( array() );
		}

		$users = get_users(
			array(
				'search'         => '*' . $term . '*',
				'search_columns' => array( 'display_name', 'user_email', 'user_login' ),
				'number'         => 10,
				'fields'         => array( 'ID', 'display_name', 'user_email' ),
			)
		);

		$results = array_map(
			static fn( $u ) => array(
				'id'    => (int) $u->ID,
				'label' => $u->display_name . ' (' . $u->user_email . ')',
			),
			$users
		);

		wp_send_json_success( $results );
	}

	// -------------------------------------------------------------------------
	// Static helpers (used by Access class)
	// -------------------------------------------------------------------------

	/**
	 * Get the full settings array.
	 *
	 * @return array{allow_creator_roles: string[], allow_creator_users: int[]}
	 */
	public static function get(): array {
		$defaults = array(
			'allow_creator_roles' => array(),
			'allow_creator_users' => array(),
		);
		return wp_parse_args( get_option( self::OPTION_KEY, array() ), $defaults );
	}

	/**
	 * Check whether a user is allowed to create READMEs.
	 * Admins always can; this only matters for non-admins.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return bool
	 */
	public static function user_can_create( int $user_id ): bool {
		if ( user_can( $user_id, 'manage_options' ) ) {
			return true;
		}

		$settings = self::get();
		$user     = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}

		// Role match.
		if ( array_intersect( (array) $user->roles, $settings['allow_creator_roles'] ) ) {
			return true;
		}

		// Individual user match.
		if ( in_array( $user_id, $settings['allow_creator_users'], true ) ) {
			return true;
		}

		return false;
	}

	/**
	 * URL to the settings page.
	 */
	public static function settings_url(): string {
		return add_query_arg(
			array(
				'post_type' => Post_Type::SLUG,
				'page'      => self::PAGE_SLUG,
			),
			admin_url( 'edit.php' )
		);
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Get all registered WordPress roles.
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
}
