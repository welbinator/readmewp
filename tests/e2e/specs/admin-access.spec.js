/**
 * Admin access tests.
 *
 * Per plugin design (class-permissions.php): admins always have full access —
 * manage_options bypasses all role/user restrictions. These tests confirm that.
 */

const { test, expect } = require( '@playwright/test' );
const { login } = require( '../utils/wp-login' );
const { createReadme, cleanupTestReadmes } = require( '../utils/fixtures' );

test.describe( 'Admin access', () => {
	let editorOnlyId;
	let adminPlusEditorId;

	test.beforeAll( () => {
		// README only giving Editor role access — admin bypasses this.
		editorOnlyId = createReadme( { title: 'Editor only', roles: [ 'editor' ] } );
		// README giving both Admin and Editor — admin can access.
		adminPlusEditorId = createReadme( {
			title: 'Admin and editor',
			roles: [ 'administrator', 'editor' ],
		} );
	} );

	test.afterAll( () => {
		cleanupTestReadmes();
	} );

	test( 'admin sees the list screen', async ( { page } ) => {
		await login( page, 'admin' );
		await page.goto( '/wp-admin/edit.php?post_type=readmewp' );
		await expect( page ).toHaveTitle( /READMEs/i );
	} );

	test( 'admin always sees ALL READMEs regardless of role setting (bypass by design)', async ( { page } ) => {
		await login( page, 'admin' );
		await page.goto( '/wp-admin/edit.php?post_type=readmewp' );
		// Admin should see BOTH the editor-only and the admin+editor READMEs.
		await expect( page.locator( `#post-${ editorOnlyId }` ) ).toBeVisible();
		await expect( page.locator( `#post-${ adminPlusEditorId }` ) ).toBeVisible();
	} );

	test( 'admin can open viewer for any README', async ( { page } ) => {
		await login( page, 'admin' );
		await page.goto( `/wp-admin/admin.php?page=readmewp-${ editorOnlyId }` );
		// Should load the viewer without a permissions error.
		await expect( page.locator( '.wrap.readmewp-viewer' ).first() ).toBeVisible();
		await expect( page.locator( 'body' ) ).not.toContainText( 'not allowed' );
	} );

	test( 'admin sees Edit button in viewer', async ( { page } ) => {
		await login( page, 'admin' );
		await page.goto( `/wp-admin/admin.php?page=readmewp-${ adminPlusEditorId }` );
		await expect( page.locator( 'a.readmewp-edit-link, a.page-title-action' ).first() ).toBeVisible();
	} );

	test( 'admin sees Add New README button', async ( { page } ) => {
		await login( page, 'admin' );
		await page.goto( '/wp-admin/edit.php?post_type=readmewp' );
		await expect( page.locator( 'a.page-title-action:has-text("Add New")' ) ).toBeVisible();
	} );
} );
