/**
 * External dependencies
 */
import { existsSync, mkdirSync, writeFileSync, unlinkSync } from 'node:fs';
import { join } from 'node:path';

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe( 'Localize Script on wp-login.php', () => {
	const muPlugins = join(
		process.cwd(),
		process.env.LOCAL_DIR ?? 'src',
		'wp-content/mu-plugins'
	);
	const muPluginFile = join( muPlugins, 'login-test.php' );

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

		if ( ! existsSync( muPlugins ) ) {
			mkdirSync( muPlugins, { recursive: true } );
		}
		writeFileSync( muPluginFile, muPluginCode );
	} );

	test.afterAll( async () => {
		unlinkSync( muPluginFile );
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
