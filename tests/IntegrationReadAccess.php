<?php
/**
 * Integration tests: read access control against a real WP test DB.
 *
 * Scenarios:
 *  - Admin can always read any README.
 *  - Admin locked out when administrator role unchecked.
 *  - Editor can read when editor role is allowed.
 *  - Editor blocked when only administrator role is allowed.
 *  - Individual user granted access by user ID.
 *  - Owner can always read their own README (even with no roles/users set).
 *  - get_readable_posts() returns only permitted posts for a given user.
 *
 * @package ReadMeWP
 */

namespace ReadMeWP\Tests;

use ReadMeWP\Permissions;
use ReadMeWP\Ownership;
use ReadMeWP\Post_Type;
use WP_UnitTestCase;

/**
 * Class IntegrationReadAccess
 */
class IntegrationReadAccess extends WP_UnitTestCase {

	/** @var Permissions */
	private Permissions $permissions;

	/** @var int Admin user ID */
	private int $admin_id;

	/** @var int Editor user ID */
	private int $editor_id;

	/** @var int Subscriber user ID */
	private int $subscriber_id;

	public function set_up(): void {
		parent::set_up();
		$this->permissions   = new Permissions();
		$this->admin_id      = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$this->editor_id     = self::factory()->user->create( [ 'role' => 'editor' ] );
		$this->subscriber_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
	}

	/** Helper: create a published README with specific role/user permissions. */
	private function make_readme( array $roles = [], array $user_ids = [], int $author_id = 0 ): int {
		$post_id = self::factory()->post->create( [
			'post_type'   => Post_Type::SLUG,
			'post_status' => 'publish',
			'post_title'  => 'Test README',
			'post_author' => $author_id ?: $this->admin_id,
		] );
		update_post_meta( $post_id, Permissions::META_ROLES, $roles );
		update_post_meta( $post_id, Permissions::META_USERS, $user_ids );
		return $post_id;
	}

	/** Admin can read a README that has administrator in allowed roles. */
	public function test_admin_can_read_when_administrator_role_allowed(): void {
		$post_id = $this->make_readme( [ 'administrator' ] );
		$this->assertTrue( $this->permissions->user_can_read( $post_id, $this->admin_id ) );
	}

	/** Admin is blocked when administrator role is explicitly NOT in the allowed list. */
	public function test_admin_blocked_when_administrator_role_not_allowed(): void {
		// Author must NOT be the admin being tested — ownership grants unconditional read.
		$post_id = $this->make_readme( [ 'editor' ], [], $this->editor_id ); // administrator not included
		// Also ensure the admin is not recorded as owner.
		update_post_meta( $post_id, Ownership::META_OWNER_ID, $this->editor_id );
		$this->assertFalse( $this->permissions->user_can_read( $post_id, $this->admin_id ) );
	}

	/** Editor can read when editor role is in the allowed list. */
	public function test_editor_can_read_when_editor_role_allowed(): void {
		$post_id = $this->make_readme( [ 'editor' ] );
		$this->assertTrue( $this->permissions->user_can_read( $post_id, $this->editor_id ) );
	}

	/** Editor is blocked when only administrator role is allowed. */
	public function test_editor_blocked_when_only_administrator_allowed(): void {
		$post_id = $this->make_readme( [ 'administrator' ] );
		$this->assertFalse( $this->permissions->user_can_read( $post_id, $this->editor_id ) );
	}

	/** A subscriber granted access by user ID can read. */
	public function test_specific_user_can_read_when_granted_by_user_id(): void {
		$post_id = $this->make_readme( [], [ $this->subscriber_id ] );
		$this->assertTrue( $this->permissions->user_can_read( $post_id, $this->subscriber_id ) );
	}

	/** A subscriber NOT in the user list cannot read. */
	public function test_user_blocked_when_not_in_user_list(): void {
		$other = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		$post_id = $this->make_readme( [], [ $this->subscriber_id ] );
		$this->assertFalse( $this->permissions->user_can_read( $post_id, $other ) );
	}

	/** Owner can always read their own README even when no roles/users set. */
	public function test_owner_can_always_read_own_readme(): void {
		$post_id = $this->make_readme( [], [], $this->editor_id );
		update_post_meta( $post_id, Ownership::META_OWNER_ID, $this->editor_id );
		$this->assertTrue( $this->permissions->user_can_read( $post_id, $this->editor_id ) );
	}

	/** No meta saved yet (new post) — admin defaults to allowed. */
	public function test_admin_can_read_post_with_no_meta_saved(): void {
		$post_id = self::factory()->post->create( [
			'post_type'   => Post_Type::SLUG,
			'post_status' => 'publish',
			'post_author' => $this->admin_id,
		] );
		// Deliberately do NOT set any meta.
		$this->assertTrue( $this->permissions->user_can_read( $post_id, $this->admin_id ) );
	}

	/** get_readable_posts() returns only the posts a user is allowed to read. */
	public function test_get_readable_posts_filters_correctly(): void {
		$allowed_post   = $this->make_readme( [ 'editor' ] );
		$forbidden_post = $this->make_readme( [ 'administrator' ] );

		$readable = $this->permissions->get_readable_posts( $this->editor_id );
		$ids      = wp_list_pluck( $readable, 'ID' );

		$this->assertContains( $allowed_post, $ids );
		$this->assertNotContains( $forbidden_post, $ids );
	}

	/** get_readable_posts() returns an empty array when user has access to nothing. */
	public function test_get_readable_posts_returns_empty_for_no_access(): void {
		$this->make_readme( [ 'administrator' ] );
		$readable = $this->permissions->get_readable_posts( $this->subscriber_id );
		$this->assertEmpty( $readable );
	}
}
