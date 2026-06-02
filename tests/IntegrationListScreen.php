<?php
/**
 * Integration tests: list screen filtering (pre_get_posts).
 *
 * Scenarios:
 *  - Admin sees all READMEs on the list screen.
 *  - Editor sees only READMEs they have read permission for.
 *  - Editor does NOT see READMEs they have no permission for.
 *  - Editor sees their own draft (authored by them) even if not published.
 *  - User with no readable READMEs gets an empty list screen.
 *  - post__in is not set for admins (they see everything naturally).
 *
 * @package ReadMeWP
 */

namespace ReadMeWP\Tests;

use ReadMeWP\Menu;
use ReadMeWP\Ownership;
use ReadMeWP\Permissions;
use ReadMeWP\Post_Type;
use WP_Query;
use WP_UnitTestCase;

/**
 * Class IntegrationListScreen
 */
class IntegrationListScreen extends WP_UnitTestCase {

	/** @var Menu */
	private Menu $menu;

	/** @var int */
	private int $admin_id;

	/** @var int */
	private int $editor_id;

	/** @var int */
	private int $subscriber_id;

	public function set_up(): void {
		parent::set_up();
		$this->menu          = new Menu();
		$this->menu->register();
		$this->admin_id      = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$this->editor_id     = self::factory()->user->create( [ 'role' => 'editor' ] );
		$this->subscriber_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		// Simulate an admin context so is_admin() returns true inside filter_list_screen.
		set_current_screen( 'edit-' . Post_Type::SLUG );
	}

	public function tear_down(): void {
		set_current_screen( 'front' );
		parent::tear_down();
	}

	/** Helper: create a published README with role permissions. */
	private function make_readme( array $roles = [], int $author_id = 0 ): int {
		$post_id = self::factory()->post->create( [
			'post_type'   => Post_Type::SLUG,
			'post_status' => 'publish',
			'post_author' => $author_id ?: $this->admin_id,
		] );
		update_post_meta( $post_id, Permissions::META_ROLES, $roles );
		update_post_meta( $post_id, Permissions::META_USERS, [] );
		return $post_id;
	}

	/**
	 * Helper: create a WP_Query that passes is_main_query() and apply filter_list_screen to it.
	 * WP_Query::is_main_query() checks `$this === $GLOBALS['wp_the_query']`, so we swap it.
	 */
	private function run_list_query( int $user_id, array $args = [] ): WP_Query {
		wp_set_current_user( $user_id );
		$args = array_merge( [
			'post_type'      => Post_Type::SLUG,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
		], $args );
		$query = new WP_Query( $args );
		// Make this query appear as the main query.
		$GLOBALS['wp_the_query'] = $query;
		$this->menu->filter_list_screen( $query );
		$query->get_posts();
		return $query;
	}

	/** Editor sees README they have role access to. */
	public function test_editor_sees_permitted_readme(): void {
		$allowed   = $this->make_readme( [ 'editor' ] );
		$forbidden = $this->make_readme( [ 'administrator' ] );

		$query = $this->run_list_query( $this->editor_id );
		$ids   = wp_list_pluck( $query->posts, 'ID' );

		$this->assertContains( $allowed, $ids );
		$this->assertNotContains( $forbidden, $ids );
	}

	/** User with no permissions gets an empty list. */
	public function test_subscriber_with_no_access_gets_empty_list(): void {
		$this->make_readme( [ 'administrator' ] );
		$query = $this->run_list_query( $this->subscriber_id );
		$this->assertEmpty( $query->posts );
	}

	/** Editor always sees their own authored posts (e.g. drafts). */
	public function test_editor_sees_own_draft(): void {
		$draft_id = self::factory()->post->create( [
			'post_type'   => Post_Type::SLUG,
			'post_status' => 'draft',
			'post_author' => $this->editor_id,
		] );
		update_post_meta( $draft_id, Permissions::META_ROLES, [] );
		update_post_meta( $draft_id, Permissions::META_USERS, [] );

		$query = $this->run_list_query( $this->editor_id, [ 'post_status' => 'any' ] );
		$ids   = wp_list_pluck( $query->posts, 'ID' );

		$this->assertContains( $draft_id, $ids );
	}

	/** Admin query is not restricted — post__in is not set. */
	public function test_admin_query_not_restricted(): void {
		$this->make_readme( [ 'editor' ] );
		$this->make_readme( [ 'administrator' ] );

		wp_set_current_user( $this->admin_id );
		$query = new WP_Query( [
			'post_type'      => Post_Type::SLUG,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
		] );
		$GLOBALS['wp_the_query'] = $query;
		$this->menu->filter_list_screen( $query );

		// filter_list_screen bails early for admins — post__in should not be set.
		$this->assertEmpty( $query->get( 'post__in' ) );
	}
}
