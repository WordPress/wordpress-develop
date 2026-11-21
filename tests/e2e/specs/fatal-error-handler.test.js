/**
 * External dependencies
 */
import { randomUUID } from 'node:crypto';

/**
 * Internal dependencies
 */
import { test, expect } from '../fixtures';

test.describe( 'Fatal error handler', () => {
	test( 'should display fatal error notice', async ( { admin, page, muPlugin } ) => {
		// Other workers share the site, so only this test's request fails.
		const trigger = randomUUID();
		await muPlugin.write(
			'fatal-error.php',
			`<?php
			if ( isset( $_GET['${ trigger }'] ) ) {
				new NonExistentClass();
			}`
		);

		await admin.visitAdminPage( '/', trigger );

		await expect(
			page.getByText( /Fatal error:/ ),
			'should display PHP error message'
		).toBeVisible();

		await expect(
			page.getByText( /There has been a critical error on this website/ ),
			'should display WordPress fatal error handler message'
		).toBeVisible();
	} );
} );
