<?php
/**
 * PHPUnit bootstrap file for ReadMeWP.
 *
 * @package ReadMeWP
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = '/tmp/wordpress-tests-lib';
}

// PHPUnit Polyfills — installed via Composer.
define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', __DIR__ . '/../vendor/yoast/phpunit-polyfills' );

if ( ! file_exists( "{$_tests_dir}/includes/functions.php" ) ) {
	echo "Could not find {$_tests_dir}/includes/functions.php — run bin/install-wp-tests.sh first." . PHP_EOL;
	exit( 1 );
}

require_once "{$_tests_dir}/includes/functions.php";

/**
 * Manually load the plugin.
 */
function _manually_load_plugin(): void {
	require dirname( __DIR__ ) . '/readmewp.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Boot the WP test environment.
require "{$_tests_dir}/includes/bootstrap.php";
