// WP_ADMIN_USER=... WP_ADMIN_PASS=... node bin/screenshot-admin.js
// Required: WP_ADMIN_USER, WP_ADMIN_PASS. Optional: WP_BASE_URL (default https://experiment.test).

'use strict';

const fs = require( 'fs' );
const path = require( 'path' );
const { chromium } = require( 'playwright' );

const USAGE =
	'Usage: WP_ADMIN_USER=... WP_ADMIN_PASS=... node bin/screenshot-admin.js\n' +
	'Required env vars: WP_ADMIN_USER, WP_ADMIN_PASS. Optional: WP_BASE_URL.';

const BASE_URL = ( process.env.WP_BASE_URL || 'https://experiment.test' ).replace( /\/+$/, '' );
const USER = process.env.WP_ADMIN_USER || '';
const PASS = process.env.WP_ADMIN_PASS || '';
const VIEWPORT_WIDTH = 1440;
const VIEWPORT_HEIGHT = 900;
const OUT_DIR = path.join( __dirname, '..', 'screenshots', 'baseline' );

// Pages registered in includes/class-admin-menu-handler.php (top-level, sub-menu,
// and hidden pages). Usage & Costs is Pro-only; we still visit it if present.
const SCREENS = [
	{ slug: 'dashboard', page: 'agent-builder' },
	{ slug: 'chat', page: 'agentic-chat' },
	{ slug: 'agents', page: 'agentic-agents' },
	{ slug: 'publish', page: 'agentic-deployment' },
	{ slug: 'run-task', page: 'agentic-run-task' },
	{ slug: 'agent-wizard', page: 'agentic-agent-wizard' },
	{ slug: 'knowledge-wizard', page: 'agentic-knowledge-wizard' },
	{ slug: 'deploy-wizard', page: 'agentic-deploy-wizard' },
	{ slug: 'knowledge', page: 'agentic-train-data' },
	{ slug: 'tools', page: 'agentic-tools' },
	{ slug: 'skills', page: 'agentic-skills' },
	{ slug: 'approvals', page: 'agentic-approvals' },
	{ slug: 'safety-center', page: 'agentic-safety-center' },
	{ slug: 'passport', page: 'agentic-agent-ready' },
	{ slug: 'logs', page: 'agentic-audit-log' },
	{ slug: 'settings', page: 'agentic-settings' },
	{ slug: 'settings-providers', page: 'agentic-settings', query: 'tab=providers' },
	{ slug: 'setup', page: 'agentic-setup' },
	{ slug: 'signup', page: 'agentic-signup' },
	{ slug: 'usage-costs', page: 'agentic-costs' },
];

const REACT_ROOTS = [
	'#agentic-dashboard-app-root',
	'#agentic-admin-pages-root',
	'#agentic-settings-app-root',
	'#agentic-agent-wizard-root',
	'#agentic-knowledge-wizard-root',
	'#agentic-deploy-wizard-root',
];

function adminUrl( screen ) {
	let url = `${ BASE_URL }/wp-admin/admin.php?page=${ encodeURIComponent( screen.page ) }`;
	if ( screen.query ) {
		url += `&${ screen.query }`;
	}
	return url;
}

async function sleep( ms ) {
	return new Promise( ( resolve ) => setTimeout( resolve, ms ) );
}

async function waitForScreen( page ) {
	await page.waitForSelector( '#wpbody-content, #error-page, .login', { timeout: 30000 } );

	for ( const sel of REACT_ROOTS ) {
		const handle = await page.$( sel );
		if ( ! handle ) {
			continue;
		}
		try {
			await page.waitForFunction(
				( s ) => {
					const n = document.querySelector( s );
					return n && n.childElementCount > 0;
				},
				sel,
				{ timeout: 20000 }
			);
		} catch ( _err ) {
			// Screen may still be useful even if the React island is empty.
		}
		break;
	}

	try {
		await page.waitForFunction(
			() => document.querySelectorAll( '.components-spinner' ).length === 0,
			{ timeout: 8000 }
		);
	} catch ( _err ) {
		// Spinner may remain on live-updating views; still screenshot.
	}

	await sleep( 600 );
}

async function login( page ) {
	await page.goto( `${ BASE_URL }/wp-login.php`, { waitUntil: 'domcontentloaded', timeout: 45000 } );
	await page.waitForSelector( '#user_login', { timeout: 15000 } );
	await page.fill( '#user_login', USER );
	await page.fill( '#user_pass', PASS );
	await page.click( '#wp-submit' );
	await page.waitForSelector( '#login_error, #wpadminbar', { timeout: 30000 } );

	const loginError = await page.$( '#login_error' );
	if ( loginError || page.url().includes( 'wp-login.php' ) ) {
		const msg = loginError ? ( await loginError.innerText() ).trim() : 'still on wp-login.php';
		throw new Error( `WordPress login failed (${ msg }). Check WP_ADMIN_USER / WP_ADMIN_PASS.` );
	}
}

async function screenshotScreen( page, screen ) {
	const dest = path.join( OUT_DIR, `${ screen.slug }.png` );
	try {
		await page.goto( adminUrl( screen ), { waitUntil: 'domcontentloaded', timeout: 45000 } );
		await waitForScreen( page );
		await page.screenshot( { path: dest, fullPage: true } );
		console.log( `ok  ${ screen.slug }  →  ${ path.relative( process.cwd(), dest ) }` );
	} catch ( err ) {
		// Idempotent: a missing/empty/error screen still gets a capture if possible.
		try {
			await page.screenshot( { path: dest, fullPage: true } );
			console.log( `warn  ${ screen.slug }  (${ err.message }) — saved whatever rendered` );
		} catch ( shotErr ) {
			console.log( `skip  ${ screen.slug }  (${ err.message }; screenshot: ${ shotErr.message })` );
		}
	}
}

async function main() {
	if ( ! USER || ! PASS ) {
		console.error( USAGE );
		process.exit( 1 );
	}

	fs.mkdirSync( OUT_DIR, { recursive: true } );

	// Bundled Chromium is not published for every distro (e.g. Ubuntu 26.04).
	// Prefer system Google Chrome, then PLAYWRIGHT_CHROME_PATH, then bundled Chromium.
	const launchOptions = { headless: true };
	if ( process.env.PLAYWRIGHT_CHROME_PATH ) {
		launchOptions.executablePath = process.env.PLAYWRIGHT_CHROME_PATH;
	} else {
		launchOptions.channel = 'chrome';
	}
	let browser;
	try {
		browser = await chromium.launch( launchOptions );
	} catch ( launchErr ) {
		browser = await chromium.launch( { headless: true } );
	}
	const context = await browser.newContext( {
		ignoreHTTPSErrors: true,
		viewport: { width: VIEWPORT_WIDTH, height: VIEWPORT_HEIGHT },
	} );
	const page = await context.newPage();

	try {
		await login( page );
		for ( const screen of SCREENS ) {
			await screenshotScreen( page, screen );
		}
	} finally {
		await browser.close();
	}
}

main().catch( ( err ) => {
	console.error( err.message || err );
	process.exit( 1 );
} );
