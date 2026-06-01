<?php
/**
 * Plugin Name:       ReadMeWP
 * Plugin URI:        https://github.com/welbinator/readmewp
 * Description:       Create role- and user-specific README documents visible in the WordPress admin menu.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            James Welbes
 * Author URI:        https://github.com/welbinator
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       readmewp
 * Domain Path:       /languages
 *
 * @package ReadMeWP
 */

declare( strict_types=1 );

namespace ReadMeWP;

defined( 'ABSPATH' ) || exit;

// Plugin constants.
define( 'READMEWP_VERSION',  '0.1.0' );
define( 'READMEWP_FILE',     __FILE__ );
define( 'READMEWP_PATH',     plugin_dir_path( __FILE__ ) );
define( 'READMEWP_URL',      plugin_dir_url( __FILE__ ) );
define( 'READMEWP_BASENAME', plugin_basename( __FILE__ ) );

// Autoload classes.
require_once READMEWP_PATH . 'includes/class-post-type.php';
require_once READMEWP_PATH . 'includes/class-permissions.php';
require_once READMEWP_PATH . 'includes/class-ownership.php';
require_once READMEWP_PATH . 'includes/class-settings.php';
require_once READMEWP_PATH . 'includes/class-access.php';
require_once READMEWP_PATH . 'includes/class-menu.php';
require_once READMEWP_PATH . 'includes/class-viewer.php';

/**
 * Bootstrap the plugin.
 */
function init(): void {
	( new Post_Type() )->register();
	( new Permissions() )->register();
	( new Ownership() )->register();
	( new Settings() )->register();
	( new Access() )->register();
	( new Menu() )->register();
	( new Viewer() )->register();
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\init' );
