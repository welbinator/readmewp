// @ts-check
const { defineConfig, devices } = require( '@playwright/test' );

module.exports = defineConfig( {
	testDir: './tests/e2e/specs',
	timeout: 30_000,
	retries: 0,
	workers: 1, // serial — tests share a WP install, run one at a time
	reporter: [ [ 'list' ], [ 'html', { open: 'never', outputFolder: 'tests/e2e/report' } ] ],
	use: {
		baseURL: 'https://readmewp.ddev.site',
		ignoreHTTPSErrors: true, // DDEV uses a self-signed cert
		screenshot: 'only-on-failure',
		video: 'off',
		launchOptions: {
			args: [ '--ignore-certificate-errors' ],
		},
	},
	projects: [
		{
			name: 'chromium',
			use: { ...devices[ 'Desktop Chrome' ] },
		},
	],
} );
