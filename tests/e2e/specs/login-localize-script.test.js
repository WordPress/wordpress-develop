/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Node dependencies
 */
import fs from 'fs';
import path from 'node:path';

test.describe( 'Localize Script on wp-login.php', () => {
	const muFile = path.normalize(
		path.join( process.cwd(), 'src/wp-content/mu-plugins/login-test.php' )
	);
	console.log( muFile );

	test.beforeAll( async ( { requestUtils } ) => {
		const muplugin = `<?php
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
		fs.writeFileSync( muFile, muplugin );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		fs.unlinkSync( muFile );
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
