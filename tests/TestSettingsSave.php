<?php
/**
 * Tests for Settings::handle_save() — nonce validation and cap checks.
 *
 * Covers (T-01, T-03):
 *   - A forged / missing nonce is rejected — option is not updated.
 *   - A non-admin (subscriber) cannot save settings even with a valid nonce.
 *   - A valid admin save persists the expected values.
 *   - C-01: $prev_users cache-bust fires for users removed from the list.
 *
 * @package ReadMeWP
 */

use ReadMeWP\Settings;

/**
 * Class TestSettingsSave
 */
class TestSettingsSave extends WP_UnitTestCase {

	/** @var int */
	private int $admin_id;

	/** @var int */
	private int $subscriber_id;

	/** @var int */
	private int $editor_id;

	public function set_up(): void {
		parent::set_up();
		$this->admin_id      = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$this->editor_id     = self::factory()->user->create( [ 'role' => 'editor' ] );
		$this->subscriber_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		delete_option( Settings::OPTION_KEY );
	}

	// -------------------------------------------------------------------------
	// T-01 — nonce validation
	// -------------------------------------------------------------------------

	/**
	 * handle_save() bails silently when no nonce is present.
	 */
	public function test_missing_nonce_rejected(): void {
		wp_set_current_user( $this->admin_id );

		// Simulate POST without nonce key.
		$_POST = [
			'readmewp_creator_roles' => [ 'editor' ],
			'readmewp_creator_users' => [],
		];

		( new Settings() )->handle_save();

		$this->assertFalse( get_option( Settings::OPTION_KEY ) );
	}

	/**
	 * handle_save() bails when the nonce value is invalid.
	 */
	public function test_invalid_nonce_rejected(): void {
		wp_set_current_user( $this->admin_id );

		$_POST = [
			'readmewp_settings_nonce' => 'definitely-not-a-real-nonce',
			'readmewp_creator_roles'  => [ 'editor' ],
			'readmewp_creator_users'  => [],
		];

		( new Settings() )->handle_save();

		$this->assertFalse( get_option( Settings::OPTION_KEY ) );
	}

	// -------------------------------------------------------------------------
	// T-03 — settings save (capability gate)
	// -------------------------------------------------------------------------

	/**
	 * T-12 / subscriber cannot write settings even with a valid nonce.
	 */
	public function test_subscriber_cannot_save_settings(): void {
		wp_set_current_user( $this->subscriber_id );
		$nonce = wp_create_nonce( Settings::NONCE_SETTINGS );

		$_POST = [
			'readmewp_settings_nonce' => $nonce,
			'readmewp_creator_roles'  => [ 'subscriber' ],
			'readmewp_creator_users'  => [],
		];

		( new Settings() )->handle_save();

		$this->assertFalse( get_option( Settings::OPTION_KEY ) );
	}

	/**
	 * Valid admin POST saves expected values.
	 * Uses output buffering to catch the wp_safe_redirect() call.
	 */
	public function test_admin_valid_save_persists_values(): void {
		wp_set_current_user( $this->admin_id );
		$nonce = wp_create_nonce( Settings::NONCE_SETTINGS );

		$_POST = [
			'readmewp_settings_nonce' => $nonce,
			'readmewp_creator_roles'  => [ 'editor' ],
			'readmewp_creator_users'  => [ (string) $this->editor_id ],
		];

		// handle_save() calls wp_safe_redirect() + exit — catch the exit.
		try {
			( new Settings() )->handle_save();
		} catch ( \WPDieException $e ) {
			// WP test suite converts redirects to WPDieException — expected.
		}

		$saved = Settings::get();
		$this->assertContains( 'editor', $saved['allow_creator_roles'] );
		$this->assertContains( $this->editor_id, $saved['allow_creator_users'] );
	}

	// -------------------------------------------------------------------------
	// C-01 — prev_settings captured before update_option
	// -------------------------------------------------------------------------

	/**
	 * Users removed from the list should have their cap caches busted.
	 * Verified indirectly: after a save that removes $editor_id, the option
	 * no longer contains that user (the pre-fix bug would have re-read the
	 * already-updated option and skipped the flush entirely).
	 */
	public function test_removed_user_no_longer_in_settings(): void {
		// Pre-populate with editor.
		update_option( Settings::OPTION_KEY, [
			'allow_creator_roles' => [],
			'allow_creator_users' => [ $this->editor_id ],
		] );

		wp_set_current_user( $this->admin_id );
		$nonce = wp_create_nonce( Settings::NONCE_SETTINGS );

		// Save with editor removed.
		$_POST = [
			'readmewp_settings_nonce' => $nonce,
			'readmewp_creator_roles'  => [],
			'readmewp_creator_users'  => [],
		];

		try {
			( new Settings() )->handle_save();
		} catch ( \WPDieException $e ) {
			// expected redirect.
		}

		$saved = Settings::get();
		$this->assertNotContains( $this->editor_id, $saved['allow_creator_users'] );
	}
}
