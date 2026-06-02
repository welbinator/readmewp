/**
 * Fixture helpers — create and tear down test READMEs via WP-CLI (ddev exec).
 *
 * All fixtures use a `[e2e-test]` prefix in the title so they can be
 * identified and cleaned up reliably without touching real content.
 *
 * Usage in tests:
 *   const { createReadme, deleteReadme, cleanupTestReadmes } = require('../utils/fixtures');
 */

const { execSync } = require( 'child_process' );

/**
 * Run a WP-CLI command inside DDEV and return stdout.
 *
 * @param {string} cmd  WP-CLI command (everything after `ddev wp`)
 * @returns {string}
 */
function wp( cmd ) {
	return execSync( `ddev wp ${ cmd }`, {
		cwd: process.env.READMEWP_SITE_DIR || '/home/highprrrr/sites/readmewp',
		encoding: 'utf8',
	} ).trim();
}

/**
 * Run arbitrary PHP inside WP via WP-CLI eval.
 *
 * @param {string} php
 * @returns {string}
 */
function wpEval( php ) {
	// Escape double-quotes inside the PHP string for shell safety.
	const escaped = php.replace( /"/g, '\\"' );
	return execSync( `ddev wp eval "${ escaped }"`, {
		cwd: process.env.READMEWP_SITE_DIR || '/home/highprrrr/sites/readmewp',
		encoding: 'utf8',
	} ).trim();
}

/**
 * Create a test README post.
 *
 * @param {object}   opts
 * @param {string}   opts.title
 * @param {string[]} [opts.roles]      Role slugs allowed to read (e.g. ['editor'])
 * @param {number[]} [opts.users]      User IDs allowed to read
 * @param {boolean}  [opts.ownerOnly]  Lock editing to owner only (default true)
 * @param {number}   [opts.authorId]   WP user ID to create the post as (default 1 = admin)
 * @returns {number} Post ID
 */
function createReadme( { title, roles = [], users = [], ownerOnly = true, authorId = 1 } ) {
	const postId = parseInt(
		wp(
			`post create --post_type=readmewp --post_status=publish --post_author=${ authorId } --post_title="[e2e-test] ${ title }" --porcelain`
		),
		10
	);

	const rolesJson = JSON.stringify( roles ).replace( /'/g, "\\'" );
	const usersJson = JSON.stringify( users ).replace( /'/g, "\\'" );

	wpEval(
		`update_post_meta( ${ postId }, '_readmewp_roles', json_decode( '${ rolesJson }', true ) );` +
		`update_post_meta( ${ postId }, '_readmewp_users', json_decode( '${ usersJson }', true ) );` +
		`update_post_meta( ${ postId }, '_readmewp_owner_id', ${ authorId } );` +
		`update_post_meta( ${ postId }, '_readmewp_owner_only', ${ ownerOnly ? "'1'" : "''" } );`
	);

	return postId;
}

/**
 * Delete a README post by ID (force delete, bypass trash).
 *
 * @param {number} postId
 */
function deleteReadme( postId ) {
	wp( `post delete ${ postId } --force` );
}

/**
 * Delete ALL e2e test READMEs (title starts with [e2e-test]).
 * Call this in afterAll to keep the site clean.
 */
function cleanupTestReadmes() {
	try {
		const ids = wp(
			`post list --post_type=readmewp --post_status=any --fields=ID --format=csv --posts_per_page=200`
		)
			.split( '\n' )
			.slice( 1 ) // skip header
			.map( ( id ) => id.trim() )
			.filter( Boolean );

		for ( const id of ids ) {
			try {
				const title = wp( `post get ${ id } --field=post_title` );
				if ( title.startsWith( '[e2e-test]' ) ) {
					deleteReadme( parseInt( id, 10 ) );
				}
			} catch ( _ ) {
				// ignore individual failures
			}
		}
	} catch ( _ ) {
		// ignore if no posts exist
	}
}

module.exports = { createReadme, deleteReadme, cleanupTestReadmes, wp, wpEval };
