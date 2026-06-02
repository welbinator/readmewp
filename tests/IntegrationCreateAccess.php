<?php
/**
 * Integration tests: create access control (who can publish READMEs).
 *
 * Scenarios:
 *  - Admin can always create.
 *  - Editor granted create access via role setting.
 *  - Editor blocked when role not in settings.
 *  - Specific subscriber granted create access by user ID.
 *  - Subscriber blocked when not in settings.
 *  - Removing a role from settings revokes create access.
 *  - current_user_can('publish_readmewps') returns correct result for creator.
 *  - current_user_can('publish_readmewps') returns false for non-creator.
 *
 * @package ReadMeWP
 */

namespace ReadMeWP\Tests;

use ReadMeWP\Settings;
use ReadMeWP\Access;
use WP_UnitTestCase;

/**
 * Class IntegrationCreateAccess
 */
class IntegrationCreateAccess extends WP_UnitTestCase {

	/** @var Access */
	private Access $access;

	/** @var int */
	private int $admin_id;

	/** @var int */
	private int $editor_id;

	/** @var int */
	private int $subscriber_id;

	public function set_up(): void {
		parent::set_up();
		$this->access        = new Access();
		$this->access->register();
		$this->admin_id      = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$this->editor_id     = self::factory()->user->create( [ 'role' => 'editor' ] );
		$this->subscriber_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		// Start each test with empty settings.
		delete_option( Settings::OPTION_KEY );
	}

	public function tear_down(): void {
		delete_option( Settings::OPTION_KEY );
		parent::tear_down();
	}

	/** Admin can create with empty settings. */
	public function test_admin_can_always_create(): void {
		$this->assertTrue( Settings::user_can_create( $this->admin_id ) );
	}

	/** Editor granted create access by role. */
	public function test_editor_can_create_when_role_granted(): void {
		update_option( Settings::OPTION_KEY, [
			'allow_creator_roles' => [ 'editor' ],
			'allow_creator_users' => [],
		] );
		$this->assertTrue( Settings::user_can_create( $this->editor_id ) );
	}

	/** Editor blocked when only subscriber role is in settings. */
	public function test_editor_blocked_when_role_not_granted(): void {
		update_option( Settings::OPTION_KEY, [
			'allow_creator_roles' => [ 'subscriber' ],
			'allow_creator_users' => [],
		] );
		$this->assertFalse( Settings::user_can_create( $this->editor_id ) );
	}

	/** Subscriber granted create access by user ID. */
	public function test_specific_user_can_create_when_granted_by_id(): void {
		update_option( Settings::OPTION_KEY, [
			'allow_creator_roles' => [],
			'allow_creator_users' => [ $this->subscriber_id ],
		] );
		$this->assertTrue( Settings::user_can_create( $this->subscriber_id ) );
	}

	/** Subscriber blocked when not in user list. */
	public function test_subscriber_blocked_when_not_in_user_list(): void {
		$other = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		update_option( Settings::OPTION_KEY, [
			'allow_creator_roles' => [],
			'allow_creator_users' => [ $this->subscriber_id ],
		] );
		$this->assertFalse( Settings::user_can_create( $other ) );
	}

	/** Revoking a role immediately removes create access. */
	public function test_removing_role_revokes_create_access(): void {
		update_option( Settings::OPTION_KEY, [
			'allow_creator_roles' => [ 'editor' ],
			'allow_creator_users' => [],
		] );
		$this->assertTrue( Settings::user_can_create( $this->editor_id ) );

		// Now revoke.
		update_option( Settings::OPTION_KEY, [
			'allow_creator_roles' => [],
			'allow_creator_users' => [],
		] );
		$this->assertFalse( Settings::user_can_create( $this->editor_id ) );
	}

	/** current_user_can('publish_readmewps') works for a granted editor. */
	public function test_publish_cap_granted_to_creator_role(): void {
		update_option( Settings::OPTION_KEY, [
			'allow_creator_roles' => [ 'editor' ],
			'allow_creator_users' => [],
		] );
		wp_set_current_user( $this->editor_id );
		$this->assertTrue( current_user_can( 'publish_readmewps' ) );
	}

	/** current_user_can('publish_readmewps') is false when not a creator. */
	public function test_publish_cap_denied_to_non_creator(): void {
		delete_option( Settings::OPTION_KEY );
		wp_set_current_user( $this->editor_id );
		$this->assertFalse( current_user_can( 'publish_readmewps' ) );
	}
}
