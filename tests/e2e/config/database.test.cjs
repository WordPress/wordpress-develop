const { test } = require( 'node:test' );
const assert = require( 'node:assert/strict' );
const { getBaseURL, isDefaultEnvironment, setupE2EDatabase } = require( './database.js' );

test( 'uses the local environment port when classifying a URL', ( t ) => {
	const originalPort = process.env.LOCAL_PORT;
	const originalBaseURL = process.env.WP_BASE_URL;
	t.after( () => {
		if ( originalPort === undefined ) {
			delete process.env.LOCAL_PORT;
		} else {
			process.env.LOCAL_PORT = originalPort;
		}
		if ( originalBaseURL === undefined ) {
			delete process.env.WP_BASE_URL;
		} else {
			process.env.WP_BASE_URL = originalBaseURL;
		}
	} );

	process.env.LOCAL_PORT = '8899';
	delete process.env.WP_BASE_URL;
	assert.equal( getBaseURL(), 'http://localhost:8899/' );
	assert.equal( isDefaultEnvironment( 'http://localhost:8899' ), true );
	assert.equal( isDefaultEnvironment( 'http://127.0.0.1:8899' ), true );
	assert.equal( isDefaultEnvironment( 'http://localhost:8889' ), false );
	assert.equal( isDefaultEnvironment( 'https://localhost:8899' ), false );
} );

test( 'custom test sites require explicit isolation confirmation', async ( t ) => {
	const originalIsolation = process.env.WP_E2E_ISOLATED;
	t.after( () => {
		if ( originalIsolation === undefined ) {
			delete process.env.WP_E2E_ISOLATED;
		} else {
			process.env.WP_E2E_ISOLATED = originalIsolation;
		}
	} );

	delete process.env.WP_E2E_ISOLATED;
	await assert.rejects(
		setupE2EDatabase( 'https://wordpress.example.test' ),
		/WP_BASE_URL is customized/i
	);

	process.env.WP_E2E_ISOLATED = 'true';
	assert.equal(
		await setupE2EDatabase( 'https://wordpress.example.test' ),
		false
	);
} );
