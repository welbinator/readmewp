/**
 * Login helpers.
 * Usage: await login( page, 'admin' ) or await login( page, 'editor' )
 */

const USERS = {
	admin: { username: 'admin', password: 'admin123' },
	editor: { username: 'editor_test', password: 'testpass123' },
};

/**
 * Log in to WordPress as a named user.
 *
 * @param {import('@playwright/test').Page} page
 * @param {'admin'|'editor'} role
 */
async function login( page, role ) {
	const { username, password } = USERS[ role ];
	await page.goto( '/wp-login.php' );
	await page.fill( '#user_login', username );
	await page.fill( '#user_pass', password );
	await page.click( '#wp-submit' );
	// Wait for the admin bar — more reliable than URL pattern matching.
	await page.waitForSelector( '#wpadminbar', { timeout: 20000 } );
}

/**
 * Log out of WordPress.
 *
 * @param {import('@playwright/test').Page} page
 */
async function logout( page ) {
	await page.goto( '/wp-login.php?action=logout' );
	const confirmLink = page.locator( 'a[href*="action=logout"]' );
	if ( await confirmLink.isVisible() ) {
		await confirmLink.click();
	}
}

module.exports = { login, logout, USERS };
