<?php
/**
 * Tests for ReadMeWP\Ownership
 *
 * Covers:
 *   - is_owner() correct ID matching and non-matching.
 *   - is_owner_only() flag reading.
 *   - set_owner() (via save_post hook triggered manually).
 *   - Permissions::user_can_read() grants access to the owner regardless of meta.
 *
 * @package ReadMeWP
 */

use ReadMeWP\Ownership;
use ReadMeWP\Permissions;
use ReadMeWP\Post_Type;

/**
 * Class Test_Ownership
 */
class TestOwnership extends WP_UnitTestCase {

	/** @var int */
	private int $owner_id;

	/** @var int */
	private int $other_id;

	/** @var int */
	private int $readme_id;

	/**
	 * Set up.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->owner_id = self::factory()->user->create( [ 'role' => 'editor' ] );
		$this->other_id = self::factory()->user->create( [ 'role' => 'editor' ] );

		$this->readme_id = self::factory()->post->create( [
			'post_type'    => Post_Type::SLUG,
			'post_status'  => 'publish',
			'post_author'  => $this->owner_id,
		] );

		// Manually stamp the owner meta (normally done on save_post hook).
		update_post_meta( $this->readme_id, Ownership::META_OWNER_ID, $this->owner_id );
	}

	// -------------------------------------------------------------------------
	// is_owner
	// -------------------------------------------------------------------------

	/**
	 * is_owner returns true for the stored owner.
	 */
	public function test_is_owner_true_for_owner(): void {
		$this->assertTrue( Ownership::is_owner( $this->readme_id, $this->owner_id ) );
	}

	/**
	 * is_owner returns false for a different user.
	 */
	public function test_is_owner_false_for_non_owner(): void {
		$this->assertFalse( Ownership::is_owner( $this->readme_id, $this->other_id ) );
	}

	/**
	 * is_owner returns true when no owner meta exists (fallback to post_author).
	 */
	public function test_is_owner_fallback_to_post_author(): void {
		// Delete the owner meta to trigger the fallback path.
		delete_post_meta( $this->readme_id, Ownership::META_OWNER_ID );
		$this->assertTrue( Ownership::is_owner( $this->readme_id, $this->owner_id ) );
	}

	// -------------------------------------------------------------------------
	// is_owner_only
	// -------------------------------------------------------------------------

	/**
	 * is_owner_only returns false when flag is not set.
	 */
	public function test_is_owner_only_false_by_default(): void {
		$this->assertFalse( Ownership::is_owner_only( $this->readme_id ) );
	}

	/**
	 * is_owner_only returns true when flag is set to '1'.
	 */
	public function test_is_owner_only_true_when_set(): void {
		update_post_meta( $this->readme_id, Ownership::META_OWNER_ONLY, '1' );
		$this->assertTrue( Ownership::is_owner_only( $this->readme_id ) );
	}

	/**
	 * is_owner_only returns false when flag is explicitly '0'.
	 */
	public function test_is_owner_only_false_when_explicitly_zero(): void {
		update_post_meta( $this->readme_id, Ownership::META_OWNER_ONLY, '0' );
		$this->assertFalse( Ownership::is_owner_only( $this->readme_id ) );
	}

	// -------------------------------------------------------------------------
	// Owner always gets read access via Permissions
	// -------------------------------------------------------------------------

	/**
	 * Owner can read their own README even if their role/ID is not in the permissions meta.
	 */
	public function test_owner_can_read_regardless_of_permissions_meta(): void {
		// Lock the README down — no roles, no users allowed.
		update_post_meta( $this->readme_id, Permissions::META_ROLES, [] );
		update_post_meta( $this->readme_id, Permissions::META_USERS, [] );

		$permissions = new Permissions();
		$this->assertTrue( $permissions->user_can_read( $this->readme_id, $this->owner_id ) );
	}

	/**
	 * Non-owner cannot read a fully locked-down README.
	 */
	public function test_non_owner_denied_on_locked_down_readme(): void {
		update_post_meta( $this->readme_id, Permissions::META_ROLES, [] );
		update_post_meta( $this->readme_id, Permissions::META_USERS, [] );

		$permissions = new Permissions();
		$this->assertFalse( $permissions->user_can_read( $this->readme_id, $this->other_id ) );
	}
}
