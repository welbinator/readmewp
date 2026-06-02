<?php
/**
 * Tests for ReadMeWP\Settings
 *
 * Covers:
 *   - Settings::get() returns defaults when option is empty.
 *   - Settings::user_can_create() for admins, role-allowed users, individually-allowed users,
 *     and users who should be denied.
 *
 * @package ReadMeWP
 */

use ReadMeWP\Settings;

/**
 * Class Test_Settings
 */
class TestSettings extends WP_UnitTestCase {

	/** @var int */
	private int $admin_id;

	/** @var int */
	private int $editor_id;

	/** @var int */
	private int $subscriber_id;

	/**
	 * Set up.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->admin_id      = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$this->editor_id     = self::factory()->user->create( [ 'role' => 'editor' ] );
		$this->subscriber_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );

		// Start each test with a clean slate.
		delete_option( Settings::OPTION_KEY );
	}

	// -------------------------------------------------------------------------
	// Settings::get
	// -------------------------------------------------------------------------

	/**
	 * get() returns default empty arrays when option doesn't exist.
	 */
	public function test_get_returns_defaults(): void {
		$settings = Settings::get();
		$this->assertIsArray( $settings['allow_creator_roles'] );
		$this->assertIsArray( $settings['allow_creator_users'] );
		$this->assertEmpty( $settings['allow_creator_roles'] );
		$this->assertEmpty( $settings['allow_creator_users'] );
	}

	// -------------------------------------------------------------------------
	// Settings::user_can_create
	// -------------------------------------------------------------------------

	/**
	 * Admins can always create regardless of settings.
	 */
	public function test_admin_can_always_create(): void {
		$this->assertTrue( Settings::user_can_create( $this->admin_id ) );
	}

	/**
	 * Editor can create when their role is in the allow list.
	 */
	public function test_role_allowed_user_can_create(): void {
		update_option( Settings::OPTION_KEY, [
			'allow_creator_roles' => [ 'editor' ],
			'allow_creator_users' => [],
		] );

		$this->assertTrue( Settings::user_can_create( $this->editor_id ) );
	}

	/**
	 * Subscriber cannot create when only editor role is allowed.
	 */
	public function test_non_allowed_role_cannot_create(): void {
		update_option( Settings::OPTION_KEY, [
			'allow_creator_roles' => [ 'editor' ],
			'allow_creator_users' => [],
		] );

		$this->assertFalse( Settings::user_can_create( $this->subscriber_id ) );
	}

	/**
	 * Individually-listed user can create regardless of role.
	 */
	public function test_individual_user_can_create(): void {
		update_option( Settings::OPTION_KEY, [
			'allow_creator_roles' => [],
			'allow_creator_users' => [ $this->subscriber_id ],
		] );

		$this->assertTrue( Settings::user_can_create( $this->subscriber_id ) );
	}

	/**
	 * Non-existent user cannot create.
	 */
	public function test_nonexistent_user_cannot_create(): void {
		update_option( Settings::OPTION_KEY, [
			'allow_creator_roles' => [ 'administrator', 'editor', 'subscriber' ],
			'allow_creator_users' => [],
		] );
		// User ID 999999 doesn't exist.
		$this->assertFalse( Settings::user_can_create( 999999 ) );
	}

	/**
	 * Empty settings — only admins can create.
	 */
	public function test_empty_settings_only_admin_can_create(): void {
		$this->assertFalse( Settings::user_can_create( $this->editor_id ) );
		$this->assertFalse( Settings::user_can_create( $this->subscriber_id ) );
		$this->assertTrue( Settings::user_can_create( $this->admin_id ) );
	}
}
