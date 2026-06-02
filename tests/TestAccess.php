<?php
/**
 * Tests for ReadMeWP\Access (user_has_cap filter callbacks)
 *
 * Covers:
 *   - grant_admin_caps(): admins get all readmewp* caps.
 *   - filter_caps() creator path: non-admins in settings list get publish_readmewps.
 *   - filter_caps() owner path: owners keep edit/delete caps on their own posts.
 *   - filter_caps() owner-lock path: non-owners are denied edit/delete on locked posts.
 *   - filter_caps() list-screen cap: readable-post users get edit_readmewps.
 *
 * Strategy: we call the filter methods directly on an Access instance,
 * constructing the $allcaps/$caps/$args/$user arrays that WordPress would pass.
 * This avoids needing a real HTTP request — it's pure logic testing.
 *
 * @package ReadMeWP
 */

use ReadMeWP\Access;
use ReadMeWP\Ownership;
use ReadMeWP\Permissions;
use ReadMeWP\Post_Type;
use ReadMeWP\Settings;

/**
 * Class Test_Access
 */
class TestAccess extends WP_UnitTestCase {

	/** @var Access */
	private Access $access;

	/** @var int */
	private int $admin_id;

	/** @var int */
	private int $editor_id;

	/** @var int */
	private int $subscriber_id;

	/** @var int */
	private int $readme_id;

	/**
	 * Set up.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->access        = new Access();
		$this->admin_id      = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$this->editor_id     = self::factory()->user->create( [ 'role' => 'editor' ] );
		$this->subscriber_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );

		$this->readme_id = self::factory()->post->create( [
			'post_type'   => Post_Type::SLUG,
			'post_status' => 'publish',
			'post_author' => $this->editor_id,
		] );
		update_post_meta( $this->readme_id, Ownership::META_OWNER_ID, $this->editor_id );
	}

	// -------------------------------------------------------------------------
	// grant_admin_caps
	// -------------------------------------------------------------------------

	/**
	 * Administrators get all readmewp* caps via grant_admin_caps.
	 */
	public function test_grant_admin_caps_for_administrator(): void {
		$admin = get_userdata( $this->admin_id );
		$allcaps = $admin->allcaps;
		$caps    = [ 'publish_readmewps', 'edit_readmewps', 'read_readmewp' ];
		$args    = [ 'publish_readmewps', $this->admin_id ];

		$result = $this->access->grant_admin_caps( $allcaps, $caps, $args, $admin );

		$this->assertTrue( $result['publish_readmewps'] );
		$this->assertTrue( $result['edit_readmewps'] );
		$this->assertTrue( $result['read_readmewp'] );
	}

	/**
	 * Non-admins are NOT given caps by grant_admin_caps.
	 */
	public function test_grant_admin_caps_skips_non_admin(): void {
		$editor  = get_userdata( $this->editor_id );
		$allcaps = $editor->allcaps;
		$caps    = [ 'publish_readmewps' ];
		$args    = [ 'publish_readmewps', $this->editor_id ];

		$result = $this->access->grant_admin_caps( $allcaps, $caps, $args, $editor );

		$this->assertArrayNotHasKey( 'publish_readmewps', $result );
	}

	// -------------------------------------------------------------------------
	// filter_caps — creator grants
	// -------------------------------------------------------------------------

	/**
	 * Non-admin in creator settings gets publish_readmewps.
	 */
	public function test_creator_gets_publish_cap(): void {
		// Add subscriber to allowed creator users.
		update_option( Settings::OPTION_KEY, [
			'allow_creator_roles' => [],
			'allow_creator_users' => [ $this->subscriber_id ],
		] );

		$user    = get_userdata( $this->subscriber_id );
		$allcaps = $user->allcaps;
		$caps    = [ 'publish_readmewps', 'edit_readmewps' ];
		$args    = [ 'publish_readmewps', $this->subscriber_id ];

		$result = $this->access->filter_caps( $allcaps, $caps, $args, $user );

		$this->assertTrue( $result['publish_readmewps'] );
		$this->assertTrue( $result['edit_readmewps'] );
	}

	/**
	 * Non-admin NOT in creator settings does not get publish_readmewps.
	 */
	public function test_non_creator_does_not_get_publish_cap(): void {
		update_option( Settings::OPTION_KEY, [
			'allow_creator_roles' => [],
			'allow_creator_users' => [],
		] );

		$user    = get_userdata( $this->subscriber_id );
		$allcaps = $user->allcaps;
		$caps    = [ 'publish_readmewps' ];
		$args    = [ 'publish_readmewps', $this->subscriber_id ];

		$result = $this->access->filter_caps( $allcaps, $caps, $args, $user );

		$this->assertEmpty( $result['publish_readmewps'] ?? false );
	}

	// -------------------------------------------------------------------------
	// filter_caps — owner-lock
	// -------------------------------------------------------------------------

	/**
	 * Non-owner is denied edit_post on a locked README.
	 */
	public function test_non_owner_denied_edit_on_locked_post(): void {
		update_post_meta( $this->readme_id, Ownership::META_OWNER_ONLY, '1' );

		$user    = get_userdata( $this->admin_id );
		$allcaps = array_merge( $user->allcaps, [ 'edit_readmewps' => true ] );
		$caps    = [ 'edit_readmewps' ];
		$args    = [ 'edit_post', $this->admin_id, $this->readme_id ];

		$result = $this->access->filter_caps( $allcaps, $caps, $args, $user );

		// The primitive cap should be stripped.
		$this->assertFalse( $result['edit_readmewps'] );
	}

	/**
	 * Owner retains edit_post access even when owner-lock is set.
	 */
	public function test_owner_retains_edit_on_locked_post(): void {
		update_post_meta( $this->readme_id, Ownership::META_OWNER_ONLY, '1' );

		$user    = get_userdata( $this->editor_id );
		$allcaps = $user->allcaps;
		$caps    = [ 'edit_readmewps' ];
		$args    = [ 'edit_post', $this->editor_id, $this->readme_id ];

		$result = $this->access->filter_caps( $allcaps, $caps, $args, $user );

		// Owner should have the cap (step 4 of filter_caps adds it back even if step 1 would strip it).
		$this->assertTrue( $result['edit_readmewps'] ?? false );
	}

	// -------------------------------------------------------------------------
	// filter_caps — list-screen cap (edit_readmewps for readable-post users)
	// -------------------------------------------------------------------------

	/**
	 * A user with at least one readable post gets edit_readmewps so the list screen works.
	 */
	public function test_readable_user_gets_list_screen_cap(): void {
		// Give the subscriber access to the README.
		update_post_meta( $this->readme_id, Permissions::META_ROLES, [] );
		update_post_meta( $this->readme_id, Permissions::META_USERS, [ $this->subscriber_id ] );

		$user    = get_userdata( $this->subscriber_id );
		$allcaps = $user->allcaps;
		$caps    = [ 'edit_readmewps' ];
		$args    = [ 'edit_readmewps', $this->subscriber_id ];

		$result = $this->access->filter_caps( $allcaps, $caps, $args, $user );

		$this->assertTrue( $result['edit_readmewps'] );
	}
}
