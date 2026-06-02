<?php
/**
 * Integration tests: AJAX user search handler.
 *
 * Covers (T-04):
 *   - ajax_user_search() sends 403 when nonce is invalid.
 *   - Non-admin receives 403 even with a valid nonce.
 *   - Admin with a valid nonce + term receives matching users.
 *   - Term shorter than 2 chars returns an empty success.
 *
 * Note: WP's wp_send_json_* calls wp_die() internally, so each path
 * triggers WPDieException which we catch to read the response body.
 *
 * @package ReadMeWP
 */

use ReadMeWP\Settings;

/**
 * Class TestAjaxUserSearch
 */
class TestAjaxUserSearch extends WP_UnitTestCase {

	/** @var int */
	private int $admin_id;

	/** @var int */
	private int $subscriber_id;

	public function set_up(): void {
		parent::set_up();
		$this->admin_id      = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$this->subscriber_id = self::factory()->user->create( [
			'role'         => 'subscriber',
			'display_name' => 'Searchable Sub',
			'user_email'   => 'searchablesub@example.com',
		] );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Run ajax_user_search() and capture the JSON body from the WPDieException.
	 *
	 * @return array{success: bool, data: mixed}
	 */
	private function run_search(): array {
		ob_start();
		try {
			( new Settings() )->ajax_user_search();
		} catch ( \WPDieException $e ) {
			// Expected — wp_send_json_* calls wp_die().
		}
		$body = ob_get_clean();
		return json_decode( (string) $body, true ) ?? [];
	}

	// -------------------------------------------------------------------------
	// T-04 — nonce gate
	// -------------------------------------------------------------------------

	/**
	 * Invalid nonce → wp_send_json_error or check_ajax_referer dies.
	 */
	public function test_invalid_nonce_blocked(): void {
		wp_set_current_user( $this->admin_id );
		$_REQUEST['nonce'] = 'bad-nonce';
		$_POST['term']     = 'search';

		$this->expectException( \WPDieException::class );
		( new Settings() )->ajax_user_search();
	}

	/**
	 * Non-admin with valid nonce → 403.
	 */
	public function test_non_admin_blocked_with_valid_nonce(): void {
		wp_set_current_user( $this->subscriber_id );
		$_REQUEST['nonce'] = wp_create_nonce( 'readmewp_user_search' );
		$_POST['term']     = 'search';

		$response = $this->run_search();
		$this->assertFalse( $response['success'] ?? true );
	}

	/**
	 * Term shorter than 2 chars → success with empty data.
	 */
	public function test_short_term_returns_empty(): void {
		wp_set_current_user( $this->admin_id );
		$_REQUEST['nonce'] = wp_create_nonce( 'readmewp_user_search' );
		$_POST['term']     = 'a';

		$response = $this->run_search();
		$this->assertTrue( $response['success'] );
		$this->assertEmpty( $response['data'] );
	}

	/**
	 * Valid admin search returns matching users.
	 */
	public function test_admin_search_returns_results(): void {
		wp_set_current_user( $this->admin_id );
		$_REQUEST['nonce'] = wp_create_nonce( 'readmewp_user_search' );
		$_POST['term']     = 'Searchable';

		$response = $this->run_search();
		$this->assertTrue( $response['success'] );
		$this->assertNotEmpty( $response['data'] );
		$this->assertStringContainsString( 'Searchable', $response['data'][0]['label'] );
	}
}
