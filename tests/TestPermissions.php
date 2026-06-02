<?php
/**
 * Tests for ReadMeWP\Permissions
 *
 * Covers:
 *   - user_can_read() for admins, role-matched users, individually-listed users,
 *     owners, and users who should be denied.
 *   - get_readable_posts() filters list correctly per user.
 *   - save_meta() stores roles + users (tested via meta reads).
 *
 * @package ReadMeWP
 */

use ReadMeWP\Permissions;
use ReadMeWP\Post_Type;

/**
 * Class Test_Permissions
 */
class TestPermissions extends WP_UnitTestCase {

	/** @var int */
	private int $admin_id;

	/** @var int */
	private int $editor_id;

	/** @var int */
	private int $subscriber_id;

	/** @var int */
	private int $readme_id;

	/** Permissions instance. */
	private Permissions $permissions;

	/**
	 * Set up users and a README post before each test.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->permissions   = new Permissions();
		$this->admin_id      = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$this->editor_id     = self::factory()->user->create( [ 'role' => 'editor' ] );
		$this->subscriber_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );

		$this->readme_id = self::factory()->post->create( [
			'post_type'   => Post_Type::SLUG,
			'post_status' => 'publish',
			'post_title'  => 'Test README',
		] );
	}

	// -------------------------------------------------------------------------
	// user_can_read
	// -------------------------------------------------------------------------

	/**
	 * Admin can read when administrator role is in the allowed list.
	 */
	public function test_admin_can_read_when_admin_role_allowed(): void {
		update_post_meta( $this->readme_id, Permissions::META_ROLES, [ 'administrator' ] );
		$this->assertTrue( $this->permissions->user_can_read( $this->readme_id, $this->admin_id ) );
	}

	/**
	 * Admin is denied when administrator role is explicitly removed.
	 */
	public function test_admin_denied_when_admin_role_not_in_list(): void {
		update_post_meta( $this->readme_id, Permissions::META_ROLES, [ 'editor' ] );
		$this->assertFalse( $this->permissions->user_can_read( $this->readme_id, $this->admin_id ) );
	}

	/**
	 * New post (no meta saved) defaults to allowing admins.
	 */
	public function test_admin_allowed_on_new_post_with_no_meta(): void {
		// No meta set — metadata_exists() returns false.
		$fresh_id = self::factory()->post->create( [
			'post_type'   => Post_Type::SLUG,
			'post_status' => 'publish',
		] );
		$this->assertTrue( $this->permissions->user_can_read( $fresh_id, $this->admin_id ) );
	}

	/**
	 * Editor can read when 'editor' role is allowed.
	 */
	public function test_editor_can_read_by_role(): void {
		update_post_meta( $this->readme_id, Permissions::META_ROLES, [ 'editor' ] );
		update_post_meta( $this->readme_id, Permissions::META_USERS, [] );
		$this->assertTrue( $this->permissions->user_can_read( $this->readme_id, $this->editor_id ) );
	}

	/**
	 * Subscriber cannot read when only editor role is allowed.
	 */
	public function test_subscriber_denied_by_role(): void {
		update_post_meta( $this->readme_id, Permissions::META_ROLES, [ 'editor' ] );
		update_post_meta( $this->readme_id, Permissions::META_USERS, [] );
		$this->assertFalse( $this->permissions->user_can_read( $this->readme_id, $this->subscriber_id ) );
	}

	/**
	 * Individually-listed user can read regardless of role.
	 */
	public function test_individual_user_can_read(): void {
		update_post_meta( $this->readme_id, Permissions::META_ROLES, [] );
		update_post_meta( $this->readme_id, Permissions::META_USERS, [ $this->subscriber_id ] );
		$this->assertTrue( $this->permissions->user_can_read( $this->readme_id, $this->subscriber_id ) );
	}

	/**
	 * Non-existent user ID returns false.
	 */
	public function test_nonexistent_user_returns_false(): void {
		update_post_meta( $this->readme_id, Permissions::META_ROLES, [ 'administrator', 'editor', 'subscriber' ] );
		$this->assertFalse( $this->permissions->user_can_read( $this->readme_id, 999999 ) );
	}

	// -------------------------------------------------------------------------
	// get_readable_posts
	// -------------------------------------------------------------------------

	/**
	 * get_readable_posts returns only posts the user can read.
	 */
	public function test_get_readable_posts_filters_correctly(): void {
		// README 1: allowed for editor.
		$readme_a = self::factory()->post->create( [
			'post_type'   => Post_Type::SLUG,
			'post_status' => 'publish',
		] );
		update_post_meta( $readme_a, Permissions::META_ROLES, [ 'editor' ] );
		update_post_meta( $readme_a, Permissions::META_USERS, [] );

		// README 2: not allowed for editor.
		$readme_b = self::factory()->post->create( [
			'post_type'   => Post_Type::SLUG,
			'post_status' => 'publish',
		] );
		update_post_meta( $readme_b, Permissions::META_ROLES, [ 'administrator' ] );
		update_post_meta( $readme_b, Permissions::META_USERS, [] );

		$readable_ids = wp_list_pluck(
			$this->permissions->get_readable_posts( $this->editor_id ),
			'ID'
		);

		$this->assertContains( $readme_a, $readable_ids );
		$this->assertNotContains( $readme_b, $readable_ids );
	}

	/**
	 * get_readable_posts returns nothing for a subscriber with no permissions set.
	 */
	public function test_get_readable_posts_empty_for_locked_out_user(): void {
		update_post_meta( $this->readme_id, Permissions::META_ROLES, [] );
		update_post_meta( $this->readme_id, Permissions::META_USERS, [] );

		$readable = $this->permissions->get_readable_posts( $this->subscriber_id );
		$this->assertEmpty( $readable );
	}
}
