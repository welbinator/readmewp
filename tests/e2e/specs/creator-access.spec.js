/**
 * Creator access tests.
 *
 * Verifies that:
 * - Admin can create READMEs (Add New visible, editor screen loads)
 * - Editor can also create READMEs (plugin grants this by default)
 * - The Permissions panel is visible in the block editor sidebar ("Who Can Read This README?")
 */

const { test, expect } = require( '@playwright/test' );
const { login } = require( '../utils/wp-login' );

test.describe( 'Creator / Add New access', () => {
	test.beforeEach( async ( { page } ) => {
		await login( page, 'admin' );
	} );

	test( 'admin sees Add New button on list screen', async ( { page } ) => {
		await page.goto( '/wp-admin/edit.php?post_type=readmewp' );
		await expect( page.getByRole( 'link', { name: /Add New/i } ).first() ).toBeVisible();
	} );

	test( 'admin can open the new README editor', async ( { page } ) => {
		await page.goto( '/wp-admin/post-new.php?post_type=readmewp' );
		// Block editor toolbar is a reliable sign the editor loaded.
		await expect(
			page.locator( '.edit-post-header, .editor-header' ).first()
		).toBeVisible( { timeout: 20000 } );
	} );

	test( 'editor sees Add New button (plugin grants editors create access by default)', async ( { page } ) => {
		await login( page, 'editor' );
		await page.goto( '/wp-admin/edit.php?post_type=readmewp' );
		await expect( page.getByRole( 'link', { name: /Add New/i } ).first() ).toBeVisible();
	} );

	test( 'editor can open the new README editor', async ( { page } ) => {
		await login( page, 'editor' );
		await page.goto( '/wp-admin/post-new.php?post_type=readmewp' );
		await expect(
			page.locator( '.edit-post-header, .editor-header' ).first()
		).toBeVisible( { timeout: 20000 } );
	} );

	test( 'README edit screen shows "Who Can Read This README?" permissions panel', async ( { page } ) => {
		await page.goto( '/wp-admin/post-new.php?post_type=readmewp' );
		// Wait for block editor to render (not networkidle — it makes persistent requests).
		await expect(
			page.getByText( 'Who Can Read This README?' ).first()
		).toBeVisible( { timeout: 20000 } );
	} );
} );
