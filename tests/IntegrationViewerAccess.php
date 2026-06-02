<?php
/**
 * Integration tests: Viewer access guards.
 *
 * Covers (T-02, T-05):
 *   - guard_direct_access() calls wp_die() for a user without read permission.
 *   - guard_direct_access() passes silently for a permitted user.
 *   - render() calls wp_die() for a user without read permission.
 *   - render() outputs content for a permitted user.
 *
 * @package ReadMeWP
 */

namespace ReadMeWP\Tests;

use ReadMeWP\Ownership;
use ReadMeWP\Permissions;
use ReadMeWP\Post_Type;
use ReadMeWP\Viewer;
use WP_UnitTestCase;

/**
 * Class IntegrationViewerAccess
 */
class IntegrationViewerAccess extends WP_UnitTestCase {

	/** @var int */
	private int $permitted_id;

	/** @var int */
	private int $denied_id;

	/** @var int */
	private int $readme_id;

	public function set_up(): void {
		parent::set_up();

		$owner_id           = self::factory()->user->create( [ 'role' => 'editor' ] );
		$this->permitted_id = self::factory()->user->create( [ 'role' => 'editor' ] );
		$this->denied_id    = self::factory()->user->create( [ 'role' => 'subscriber' ] );

		$this->readme_id = self::factory()->post->create( [
			'post_type'    => Post_Type::SLUG,
			'post_status'  => 'publish',
			'post_author'  => $owner_id,
		] );

		update_post_meta( $this->readme_id, Ownership::META_OWNER_ID, $owner_id );

		// Grant read to $permitted_id only.
		update_post_meta( $this->readme_id, Permissions::META_USERS, [ $this->permitted_id ] );
		update_post_meta( $this->readme_id, Permissions::META_ROLES, [] );
	}

	// -------------------------------------------------------------------------
	// T-02 — guard_direct_access
	// -------------------------------------------------------------------------

	/**
	 * guard_direct_access() throws WPDieException (403) for an unpermitted user.
	 */
	public function test_guard_blocks_unpermitted_user(): void {
		wp_set_current_user( $this->denied_id );
		$_GET['page'] = \ReadMeWP\Menu::MENU_SLUG . '-' . $this->readme_id;

		$this->expectException( \WPDieException::class );
		( new Viewer() )->guard_direct_access();
	}

	/**
	 * guard_direct_access() is silent for a permitted user.
	 */
	public function test_guard_passes_permitted_user(): void {
		wp_set_current_user( $this->permitted_id );
		$_GET['page'] = \ReadMeWP\Menu::MENU_SLUG . '-' . $this->readme_id;

		// No exception should be thrown.
		( new Viewer() )->guard_direct_access();
		$this->assertTrue( true ); // Reached here = pass.
	}

	/**
	 * guard_direct_access() ignores unrelated ?page values.
	 */
	public function test_guard_ignores_unrelated_pages(): void {
		wp_set_current_user( $this->denied_id );
		$_GET['page'] = 'some-other-plugin-page';

		( new Viewer() )->guard_direct_access();
		$this->assertTrue( true );
	}

	// -------------------------------------------------------------------------
	// T-05 — render() permission gate
	// -------------------------------------------------------------------------

	/**
	 * render() throws WPDieException (403) for an unpermitted user.
	 */
	public function test_render_blocks_unpermitted_user(): void {
		wp_set_current_user( $this->denied_id );
		$post = get_post( $this->readme_id );

		$this->expectException( \WPDieException::class );
		( new Viewer() )->render( $post );
	}

	/**
	 * render() outputs the post title for a permitted user.
	 */
	public function test_render_shows_content_to_permitted_user(): void {
		$post = get_post( $this->readme_id );
		wp_update_post( [ 'ID' => $this->readme_id, 'post_title' => 'Test README Title' ] );
		$post = get_post( $this->readme_id );

		wp_set_current_user( $this->permitted_id );

		ob_start();
		( new Viewer() )->render( $post );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Test README Title', $output );
	}
}
