/**
 * Internal dependencies
 */
import { test, expect } from '../fixtures';

test.describe( 'Localize Script on wp-login.php', () => {
	test( 'should localize script', async ( { page, muPlugin } ) => {
		await muPlugin.write(
			'login-test.php',
			`<?php
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
		  );`
		);

		await page.goto( '/wp-login.php' );
		await expect(
			page.getByRole( 'button', { name: 'Log In', exact: true } )
		).toBeVisible();
		await expect
			.poll( () =>
				page.evaluate(
					() =>
						window.testData
							?.answerToTheUltimateQuestionOfLifeTheUniverseAndEverything
				)
			)
			.toBe( '42' );
	} );
} );
