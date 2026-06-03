<?php
/**
 * GitHub Updater for ReadMeWP.
 *
 * Hooks into WordPress's plugin update system and checks the GitHub Releases
 * API for a newer version. When a newer release is found, WordPress shows the
 * standard "Update available" notice on the Plugins page and allows one-click
 * updates — exactly as if the plugin were hosted on WordPress.org.
 *
 * Results are cached for 12 hours to avoid hammering the GitHub API.
 *
 * @package ReadMeWP
 * @since   0.1.1
 */

declare( strict_types=1 );

namespace ReadMeWP;

defined( 'ABSPATH' ) || exit;

/**
 * Check GitHub for a newer release and populate the WordPress update transient.
 *
 * Hooked onto `pre_set_site_transient_update_plugins`.
 *
 * @param object $transient The update_plugins site transient.
 * @return object
 */
function readmewp_check_for_update( $transient ) {
	if ( empty( $transient->checked ) ) {
		return $transient;
	}

	$plugin_basename = READMEWP_BASENAME;
	$current_version = $transient->checked[ $plugin_basename ] ?? null;

	if ( ! $current_version ) {
		return $transient;
	}

	$release = readmewp_get_latest_github_release();
	if ( ! $release ) {
		return $transient;
	}

	if ( version_compare( $release['version'], $current_version, '<=' ) ) {
		// Cached release is not newer — delete the transient so the next WP
		// update check always fetches fresh data from GitHub rather than
		// serving stale cached "no update" results for up to 12 hours.
		delete_transient( 'readmewp_github_latest_release' );
		return $transient;
	}

	$transient->response[ $plugin_basename ] = (object) array(
		'id'           => 'github.com/welbinator/readmewp',
		'slug'         => dirname( $plugin_basename ),
		'plugin'       => $plugin_basename,
		'new_version'  => $release['version'],
		'url'          => 'https://github.com/welbinator/readmewp',
		'package'      => $release['download_url'],
		'icons'        => array(),
		'banners'      => array(),
		'tested'       => '',
		'requires'     => '6.4',
		'requires_php' => '8.1',
	);

	return $transient;
}
add_filter( 'pre_set_site_transient_update_plugins', __NAMESPACE__ . '\\readmewp_check_for_update' );

/**
 * Populate the plugin info popup ("View version X.X.X details" link).
 *
 * Hooked onto `plugins_api`.
 *
 * @param false|object|array $result The result — false if not set.
 * @param string             $action The API action being requested.
 * @param object             $args   Request arguments.
 * @return false|object
 */
function readmewp_plugin_info( $result, $action, $args ) {
	if ( 'plugin_information' !== $action ) {
		return $result;
	}

	if ( ! isset( $args->slug ) || dirname( READMEWP_BASENAME ) !== $args->slug ) {
		return $result;
	}

	$release = readmewp_get_latest_github_release();
	if ( ! $release ) {
		return $result;
	}

	return (object) array(
		'name'          => 'ReadMeWP',
		'slug'          => dirname( READMEWP_BASENAME ),
		'version'       => $release['version'],
		'author'        => '<a href="https://github.com/welbinator">James Welbes</a>',
		'homepage'      => 'https://github.com/welbinator/readmewp',
		'download_link' => $release['download_url'],
		'sections'      => array(
			'description' => 'Create role- and user-specific README documents visible in the WordPress admin menu.',
			'changelog'   => nl2br( esc_html( $release['body'] ) ),
		),
		'last_updated'  => $release['published_at'],
		'requires'      => '6.4',
		'tested'        => get_bloginfo( 'version' ),
		'requires_php'  => '8.1',
	);
}
add_filter( 'plugins_api', __NAMESPACE__ . '\\readmewp_plugin_info', 20, 3 );

/**
 * Fetch the latest non-prerelease GitHub release, with 12-hour caching.
 *
 * Prefers the explicitly-built zip asset attached to the release (the one
 * our release workflow uploads with the correct folder name). Falls back to
 * GitHub's auto-generated source zip only if no asset is found.
 *
 * @return array|false Associative array with keys: version, download_url, sha256_url, body, published_at.
 *                     Returns false on any failure.
 */
function readmewp_get_latest_github_release() {
	$cache_key = 'readmewp_github_latest_release';
	$cached    = get_transient( $cache_key );
	if ( false !== $cached ) {
		return $cached;
	}

	$response = wp_remote_get(
		'https://api.github.com/repos/welbinator/readmewp/releases/latest',
		array(
			'timeout' => 10,
			'headers' => array(
				'Accept'               => 'application/vnd.github+json',
				'User-Agent'           => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
				'X-GitHub-Api-Version' => '2022-11-28',
			),
		)
	);

	if ( is_wp_error( $response ) ) {
		return false;
	}

	if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		return false;
	}

	$release = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( ! is_array( $release ) || empty( $release['tag_name'] ) ) {
		return false;
	}

	// Skip pre-releases — only offer stable versions as updates.
	if ( ! empty( $release['prerelease'] ) ) {
		return false;
	}

	// Prefer the explicitly-uploaded zip asset (correct folder name).
	$download_url = '';
	$sha256_url   = '';
	if ( ! empty( $release['assets'] ) ) {
		foreach ( $release['assets'] as $asset ) {
			if ( ! isset( $asset['browser_download_url'] ) || '' === $asset['browser_download_url'] ) {
				continue;
			}
			if ( isset( $asset['content_type'] ) && 'application/zip' === $asset['content_type'] && '' === $download_url ) {
				$download_url = $asset['browser_download_url'];
			}
			if ( isset( $asset['name'] ) && str_ends_with( $asset['name'], '.sha256' ) && '' === $sha256_url ) {
				$sha256_url = $asset['browser_download_url'];
			}
		}
	}

	// Require a sha256 asset — no hash means no update (fail closed).
	if ( '' === $sha256_url ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[ReadMeWP] Update blocked: no .sha256 asset found for release ' . $release['tag_name'] . '.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
		return false;
	}

	// Fall back to GitHub's auto-generated source zip.
	if ( '' === $download_url ) {
		$tag          = rawurlencode( $release['tag_name'] );
		$download_url = 'https://github.com/welbinator/readmewp/archive/refs/tags/' . $tag . '.zip';
	}

	$data = array(
		'version'      => ltrim( $release['tag_name'], 'v' ),
		'download_url' => esc_url_raw( $download_url ),
		'sha256_url'   => esc_url_raw( $sha256_url ),
		'body'         => wp_strip_all_tags( $release['body'] ?? '' ),
		'published_at' => $release['published_at'] ?? '',
	);

	set_transient( $cache_key, $data, 12 * HOUR_IN_SECONDS );

	return $data;
}

/**
 * Bust the release cache immediately after a successful plugin update.
 *
 * Ensures the next update check reflects the newly installed version
 * rather than serving stale cached data.
 *
 * @param \WP_Upgrader $upgrader Upgrader instance.
 * @param array        $options  Upgrade options.
 */
function readmewp_bust_update_cache( $upgrader, $options ) {
	if (
		'update' === ( $options['action'] ?? '' ) &&
		'plugin' === ( $options['type'] ?? '' ) &&
		! empty( $options['plugins'] ) &&
		in_array( READMEWP_BASENAME, (array) $options['plugins'], true )
	) {
		delete_transient( 'readmewp_github_latest_release' );
	}
}
add_action( 'upgrader_process_complete', __NAMESPACE__ . '\\readmewp_bust_update_cache', 10, 2 );

/**
 * Verify the SHA-256 checksum of the downloaded package before installation.
 *
 * Hooked onto `upgrader_pre_install`. If the hash does not match the
 * published .sha256 asset, the install is aborted with a WP_Error.
 *
 * @param bool|\WP_Error $response   Installation response (pass-through).
 * @param array          $hook_extra Extra data passed by the upgrader.
 * @return bool|\WP_Error
 */
function readmewp_verify_package_integrity( $response, $hook_extra ) {
	// Only act on our own plugin update.
	if (
		empty( $hook_extra['plugin'] ) ||
		READMEWP_BASENAME !== $hook_extra['plugin']
	) {
		return $response;
	}

	// Bail early if a previous step already errored.
	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$release = readmewp_get_latest_github_release();
	if ( ! $release || empty( $release['sha256_url'] ) ) {
		return new \WP_Error(
			'readmewp_no_checksum',
			__( 'ReadMeWP update aborted: no integrity checksum available for this release.', 'readmewp' )
		);
	}

	// Fetch the expected hash from the .sha256 asset.
	$hash_response = wp_remote_get(
		$release['sha256_url'],
		array(
			'timeout'    => 10,
			'user-agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
		)
	);

	if ( is_wp_error( $hash_response ) || 200 !== (int) wp_remote_retrieve_response_code( $hash_response ) ) {
		return new \WP_Error(
			'readmewp_checksum_fetch_failed',
			__( 'ReadMeWP update aborted: could not retrieve integrity checksum.', 'readmewp' )
		);
	}

	$expected_hash = trim( wp_remote_retrieve_body( $hash_response ) );
	if ( ! preg_match( '/^[a-f0-9]{64}$/', $expected_hash ) ) {
		return new \WP_Error(
			'readmewp_checksum_invalid',
			__( 'ReadMeWP update aborted: integrity checksum is malformed.', 'readmewp' )
		);
	}

	// Locate the downloaded package file via the global set by fhw_capture_package_path.
	global $wp_filesystem;
	if ( empty( $wp_filesystem ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
	}

	$package_path = '';
	if ( isset( $GLOBALS['readmewp_upgrader_package_path'] ) ) {
		$package_path = $GLOBALS['readmewp_upgrader_package_path'];
	}

	if ( '' === $package_path || ! file_exists( $package_path ) ) {
		return new \WP_Error(
			'readmewp_package_not_found',
			__( 'ReadMeWP update aborted: downloaded package could not be located for integrity check.', 'readmewp' )
		);
	}

	$actual_hash = hash_file( 'sha256', $package_path );
	if ( $actual_hash !== $expected_hash ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[ReadMeWP] Integrity check FAILED. Expected: ' . $expected_hash . ' Got: ' . $actual_hash ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
		return new \WP_Error(
			'readmewp_checksum_mismatch',
			__( 'ReadMeWP update aborted: integrity check failed. The package may have been tampered with.', 'readmewp' )
		);
	}

	return $response;
}
add_filter( 'upgrader_pre_install', __NAMESPACE__ . '\\readmewp_verify_package_integrity', 10, 2 );

/**
 * Capture the downloaded package path before installation begins.
 *
 * WordPress doesn't expose the temp file path to upgrader_pre_install,
 * so we hook upgrader_source_selection (which fires just before pre_install)
 * to grab it.
 *
 * @param string $source        Extracted source directory.
 * @param string $remote_source Temp path of the downloaded zip.
 * @param object $upgrader      WP_Upgrader instance.
 * @param array  $hook_extra    Extra hook data.
 * @return string
 */
function readmewp_capture_package_path( $source, $remote_source, $upgrader, $hook_extra ) {
	if (
		! empty( $hook_extra['plugin'] ) &&
		READMEWP_BASENAME === $hook_extra['plugin']
	) {
		$GLOBALS['readmewp_upgrader_package_path'] = $remote_source;
	}
	return $source;
}
add_filter( 'upgrader_source_selection', __NAMESPACE__ . '\\readmewp_capture_package_path', 9, 4 );
