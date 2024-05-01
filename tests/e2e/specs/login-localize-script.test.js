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
	const srcMuFolder = path.normalize(
		path.join( process.cwd(), 'src/wp-content/mu-plugins' )
	);
	const srcMuFile = path.normalize( path.join( srcMuFolder, 'login-test.php' ) );
	const buildMuFolder = path.normalize(
		path.join( process.cwd(), 'build/wp-content/mu-plugins' )
	);
	const buildMuFile = path.normalize( path.join( buildMuFolder, 'login-test.php' ) );

	test.beforeAll( async () => {
		const mupluginCode = `<?php
		add_action(
			'login_enqueue_scripts',
			function() {
			  wp_localize_script(
				'wp-util',
				'testData',
				[
				  'answerToTheUltimateQuestionOfLifeTheUniverseAndEverything2' => 42,
				]
			  );
			}
		  );`;
		if ( ! fs.existsSync( srcMuFolder ) ) {
			fs.mkdirSync( srcMuFolder, { recursive: true } );
		}
		if ( ! fs.existsSync( buildMuFolder ) ) {
			fs.mkdirSync( buildMuFolder, { recursive: true } );
		}
		fs.writeFileSync( srcMuFile, mupluginCode );
		fs.writeFileSync( buildMuFile, mupluginCode );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		fs.unlinkSync( srcMuFile );
		fs.unlinkSync( buildMuFile );
	} );

	test( 'should localize script', async ( { page } ) => {
		await page.goto( '/wp-login.php' );
		await page.waitForSelector( '#login' );
		const testData = await page.evaluate( () => window.testData );
		expect(
			testData.answerToTheUltimateQuestionOfLifeTheUniverseAndEverything2
		).toBe( '42' );
	} );
} );
