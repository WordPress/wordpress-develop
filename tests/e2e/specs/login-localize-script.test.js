/**
 * External dependencies
 */
import fs from 'node:fs';
import path from 'node:path';

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe( 'Localize Script on wp-login.php', () => {
	const muPlugins = path.normalize(
		path.join( process.cwd(), 'wp-content/mu-plugins' )
	);
	const muPluginFile = path.normalize(
		path.join( muPlugins, 'login-test.php' )
	);

	test.beforeAll( async () => {
		const muPluginCode = `<?php
		add_action(
			'login_enqueue_scripts',
			function() {
			  wp_localize_script(
				'wp-util',
				'testData',
				[
				  'answerToTheUltimateQuestionOfLifeTheUniverseAndEverything' => 42,
				]
			  );
			}
		  );`;

		if ( ! fs.existsSync( muPlugins ) ) {
			fs.mkdirSync( muPlugins, { recursive: true } );
		}
		fs.writeFileSync( muPluginFile, muPluginCode );
	} );

	test.afterAll( async () => {
		fs.unlinkSync( muPluginFile );
	} );

	test( 'should localize script', async ( { page } ) => {
		await page.goto( '/wp-login.php' );
		await page.waitForSelector( '#login' );
		const testData = await page.evaluate( () => window.testData );
		expect(
			testData.answerToTheUltimateQuestionOfLifeTheUniverseAndEverything
		).toBe( '42' );
	} );
} );
