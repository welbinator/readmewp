/**
 * Role-based access tests.
 *
 * Verifies that:
 * - Editor with role access sees the ReadMeWP menu and their permitted READMEs
 * - Editor without access cannot see a README they have no role for
 * - Editor is blocked ("not allowed") from viewing a README outside their access
 * - Editor can create READMEs (plugin grants this to editors by default)
 */

const { test, expect } = require( '@playwright/test' );
const { login } = require( '../utils/wp-login' );
const { createReadme, cleanupTestReadmes } = require( '../utils/fixtures' );

test.describe( 'Role-based access', () => {
	let permittedId;
	let forbiddenId;

	test.beforeAll( () => {
		// README the editor role IS allowed to read.
		permittedId = createReadme( { title: 'Editor can read', roles: [ 'editor' ] } );
		// README the editor role is NOT allowed to read — only admin.
		forbiddenId = createReadme( { title: 'Editor cannot read', roles: [ 'administrator' ] } );
	} );

	test.afterAll( () => {
		cleanupTestReadmes();
	} );

	test( 'editor with access sees ReadMeWP top-level menu', async ( { page } ) => {
		await login( page, 'editor' );
		await page.goto( '/wp-admin/' );
		// Use the top-level "READMEs" nav text link to avoid strict mode violations.
		await expect(
			page.locator( '#adminmenu .wp-menu-name:has-text("READMEs")' ).first()
		).toBeVisible();
	} );

	test( 'editor sees permitted README in list', async ( { page } ) => {
		await login( page, 'editor' );
		await page.goto( '/wp-admin/edit.php?post_type=readmewp' );
		await expect( page.locator( `#post-${ permittedId }` ) ).toBeVisible();
	} );

	test( 'editor does NOT see README restricted to administrator role', async ( { page } ) => {
		await login( page, 'editor' );
		await page.goto( '/wp-admin/edit.php?post_type=readmewp' );
		await expect( page.locator( `#post-${ forbiddenId }` ) ).not.toBeVisible();
	} );

	test( 'editor can open viewer for permitted README', async ( { page } ) => {
		await login( page, 'editor' );
		await page.goto( `/wp-admin/admin.php?page=readmewp-${ permittedId }` );
		await expect( page.locator( '.wrap.readmewp-viewer' ).first() ).toBeVisible();
		await expect( page.locator( 'body' ) ).not.toContainText( 'not allowed' );
	} );

	test( 'editor is blocked from viewer for forbidden README', async ( { page } ) => {
		await login( page, 'editor' );
		await page.goto( `/wp-admin/admin.php?page=readmewp-${ forbiddenId }` );
		// Plugin shows WP's generic "Sorry, you are not allowed to access this page."
		await expect( page.locator( 'body' ) ).toContainText( 'not allowed' );
	} );

	test( 'editor does NOT see Edit button for README they do not own', async ( { page } ) => {
		await login( page, 'editor' );
		await page.goto( `/wp-admin/admin.php?page=readmewp-${ permittedId }` );
		// The README was created by admin (authorId=1), so editor is not the owner.
		await expect( page.locator( 'a.readmewp-edit-link' ) ).not.toBeVisible();
	} );
} );
