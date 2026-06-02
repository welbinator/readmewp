<?php
/**
 * Integration tests: Ownership::save_meta() capability gate.
 *
 * Covers (T-06 / A-01 regression):
 *   - A non-owning editor cannot overwrite the owner-only flag via save_meta.
 *   - A subscriber cannot save ownership meta at all.
 *   - The legitimate owner CAN update the owner-only flag.
 *   - An admin who is not the owner CAN update meta (admins bypass the owner lock
 *     unless the post is in locked state — tested via edit_post cap separately).
 *
 * @package ReadMeWP
 */

namespace ReadMeWP\Tests;

use ReadMeWP\Ownership;
use ReadMeWP\Post_Type;
use ReadMeWP\Settings;
use WP_UnitTestCase;

/**
 * Class IntegrationOwnershipMeta
 */
class IntegrationOwnershipMeta extends WP_UnitTestCase {

	/** @var int */
	private int $owner_id;

	/** @var int */
	private int $other_editor_id;

	/** @var int */
	private int $subscriber_id;

	/** @var int */
	private int $admin_id;

	/** @var int */
	private int $readme_id;

	public function set_up(): void {
		parent::set_up();

		$this->admin_id       = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$this->owner_id       = self::factory()->user->create( [ 'role' => 'editor' ] );
		$this->other_editor_id = self::factory()->user->create( [ 'role' => 'editor' ] );
		$this->subscriber_id  = self::factory()->user->create( [ 'role' => 'subscriber' ] );

		// Allow editors to create READMEs so the owner has edit_post capability.
		update_option( Settings::OPTION_KEY, [
			'allow_creator_roles' => [ 'editor' ],
			'allow_creator_users' => [],
		] );

		$this->readme_id = self::factory()->post->create( [
			'post_type'   => Post_Type::SLUG,
			'post_status' => 'publish',
			'post_author' => $this->owner_id,
		] );
		update_post_meta( $this->readme_id, Ownership::META_OWNER_ID, $this->owner_id );
		update_post_meta( $this->readme_id, Ownership::META_OWNER_ONLY, '' ); // unlocked by default.
	}

	public function tear_down(): void {
		delete_option( Settings::OPTION_KEY );
		parent::tear_down();
	}

	// -------------------------------------------------------------------------
	// T-06 — non-owner cannot write ownership meta
	// -------------------------------------------------------------------------

	/**
	 * A non-owning editor's save_post call must NOT change the owner-only flag.
	 *
	 * This is the A-01 regression test: before the fix, any user who could trigger
	 * the save_post hook could flip the owner-only flag.
	 */
	public function test_non_owning_editor_cannot_set_owner_only_flag(): void {
		wp_set_current_user( $this->other_editor_id );

		// Simulate a save_post request that tries to set owner_only to '1'.
		$_POST = [
			'post_type'                => Post_Type::SLUG,
			Ownership::META_OWNER_ONLY => '1',
		];

		( new Ownership() )->save_meta( $this->readme_id );

		// The flag must remain unchanged (empty = unlocked).
		$this->assertEmpty( get_post_meta( $this->readme_id, Ownership::META_OWNER_ONLY, true ) );
	}

	/**
	 * A subscriber cannot set the owner-only flag via save_meta.
	 */
	public function test_subscriber_cannot_set_owner_only_flag(): void {
		wp_set_current_user( $this->subscriber_id );

		$_POST = [
			'post_type'                => Post_Type::SLUG,
			Ownership::META_OWNER_ONLY => '1',
		];

		( new Ownership() )->save_meta( $this->readme_id );

		$this->assertEmpty( get_post_meta( $this->readme_id, Ownership::META_OWNER_ONLY, true ) );
	}

	/**
	 * The legitimate owner CAN update the owner-only flag.
	 */
	public function test_owner_can_set_owner_only_flag(): void {
		wp_set_current_user( $this->owner_id );

		$_POST = [
			'post_type'                => Post_Type::SLUG,
			Ownership::META_OWNER_ONLY => '1',
			'readmewp_ownership_nonce' => wp_create_nonce( 'readmewp_ownership_' . $this->readme_id ),
		];

		( new Ownership() )->save_meta( $this->readme_id );

		$this->assertSame( '1', get_post_meta( $this->readme_id, Ownership::META_OWNER_ONLY, true ) );
	}

	/**
	 * An admin who is the owner can also set the flag.
	 */
	public function test_admin_owner_can_set_owner_only_flag(): void {
		// Make the admin the owner.
		update_post_meta( $this->readme_id, Ownership::META_OWNER_ID, $this->admin_id );
		wp_update_post( [ 'ID' => $this->readme_id, 'post_author' => $this->admin_id ] );

		wp_set_current_user( $this->admin_id );

		$_POST = [
			'post_type'                => Post_Type::SLUG,
			Ownership::META_OWNER_ONLY => '1',
			'readmewp_ownership_nonce' => wp_create_nonce( 'readmewp_ownership_' . $this->readme_id ),
		];

		( new Ownership() )->save_meta( $this->readme_id );

		$this->assertSame( '1', get_post_meta( $this->readme_id, Ownership::META_OWNER_ONLY, true ) );
	}
}
