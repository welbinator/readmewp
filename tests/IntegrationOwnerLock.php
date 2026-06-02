<?php
/**
 * Integration tests: owner lock (only owner can edit).
 *
 * Scenarios:
 *  - Owner can edit their locked README.
 *  - Non-owning admin cannot edit a locked README.
 *  - Non-owning admin CAN edit when lock is off.
 *  - Owner can edit own README even when lock is off.
 *  - Owner-only flag defaults to true on new posts (meta not yet saved).
 *  - Non-owner admin can trash when lock is off.
 *  - Non-owner admin cannot trash a locked README.
 *  - Owner always retains edit cap even after being removed from creator settings.
 *
 * @package ReadMeWP
 */

namespace ReadMeWP\Tests;

use ReadMeWP\Access;
use ReadMeWP\Ownership;
use ReadMeWP\Post_Type;
use WP_UnitTestCase;

/**
 * Class IntegrationOwnerLock
 */
class IntegrationOwnerLock extends WP_UnitTestCase {

	/** @var Access */
	private Access $access;

	/** @var int */
	private int $admin_id;

	/** @var int */
	private int $owner_id;

	/** @var int */
	private int $other_admin_id;

	public function set_up(): void {
		parent::set_up();
		$this->access         = new Access();
		$this->access->register();
		$this->admin_id       = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$this->owner_id       = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$this->other_admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
	}

	/** Helper: create a README with owner and lock state set. */
	private function make_readme( int $owner_id, bool $owner_only ): int {
		$post_id = self::factory()->post->create( [
			'post_type'   => Post_Type::SLUG,
			'post_status' => 'publish',
			'post_author' => $owner_id,
		] );
		update_post_meta( $post_id, Ownership::META_OWNER_ID, $owner_id );
		update_post_meta( $post_id, Ownership::META_OWNER_ONLY, $owner_only ? '1' : '' );
		return $post_id;
	}

	/** Owner can edit their own locked README. */
	public function test_owner_can_edit_locked_readme(): void {
		$post_id = $this->make_readme( $this->owner_id, true );
		wp_set_current_user( $this->owner_id );
		$this->assertTrue( current_user_can( 'edit_post', $post_id ) );
	}

	/** Non-owning admin cannot edit a locked README. */
	public function test_non_owning_admin_cannot_edit_locked_readme(): void {
		$post_id = $this->make_readme( $this->owner_id, true );
		wp_set_current_user( $this->other_admin_id );
		$this->assertFalse( current_user_can( 'edit_post', $post_id ) );
	}

	/** Non-owning admin CAN edit when lock is off. */
	public function test_non_owning_admin_can_edit_unlocked_readme(): void {
		$post_id = $this->make_readme( $this->owner_id, false );
		wp_set_current_user( $this->other_admin_id );
		$this->assertTrue( current_user_can( 'edit_post', $post_id ) );
	}

	/** Owner can edit their own README when lock is off too. */
	public function test_owner_can_edit_unlocked_readme(): void {
		$post_id = $this->make_readme( $this->owner_id, false );
		wp_set_current_user( $this->owner_id );
		$this->assertTrue( current_user_can( 'edit_post', $post_id ) );
	}

	/** Non-owning admin cannot trash a locked README. */
	public function test_non_owning_admin_cannot_trash_locked_readme(): void {
		$post_id = $this->make_readme( $this->owner_id, true );
		wp_set_current_user( $this->other_admin_id );
		$this->assertFalse( current_user_can( 'delete_post', $post_id ) );
	}

	/** Non-owning admin CAN trash an unlocked README. */
	public function test_non_owning_admin_can_trash_unlocked_readme(): void {
		$post_id = $this->make_readme( $this->owner_id, false );
		wp_set_current_user( $this->other_admin_id );
		$this->assertTrue( current_user_can( 'delete_post', $post_id ) );
	}

	/**
	 * Owner retains edit cap on their own post even if they lose create access
	 * in settings (ownership is permanent).
	 */
	public function test_owner_retains_edit_cap_after_losing_creator_access(): void {
		$editor_id = self::factory()->user->create( [ 'role' => 'editor' ] );

		// Grant create access, create a README.
		update_option( \ReadMeWP\Settings::OPTION_KEY, [
			'allow_creator_roles' => [ 'editor' ],
			'allow_creator_users' => [],
		] );
		$post_id = self::factory()->post->create( [
			'post_type'   => Post_Type::SLUG,
			'post_status' => 'publish',
			'post_author' => $editor_id,
		] );
		update_post_meta( $post_id, Ownership::META_OWNER_ID, $editor_id );
		update_post_meta( $post_id, Ownership::META_OWNER_ONLY, '1' );

		// Revoke create access.
		update_option( \ReadMeWP\Settings::OPTION_KEY, [
			'allow_creator_roles' => [],
			'allow_creator_users' => [],
		] );

		wp_set_current_user( $editor_id );
		$this->assertTrue( current_user_can( 'edit_post', $post_id ) );

		delete_option( \ReadMeWP\Settings::OPTION_KEY );
	}
}
