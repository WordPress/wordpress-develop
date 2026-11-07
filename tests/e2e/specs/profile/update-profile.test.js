/**
 * External dependencies
 */
import { randomUUID } from 'node:crypto';

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe( 'Profile', () => {
	test( 'saves changes to name fields', async ( {
		admin,
		page,
		requestUtils,
	} ) => {
		const suffix = randomUUID().slice( 0, 8 );
		const original = await requestUtils.rest( {
			path: '/wp/v2/users/me',
			params: { context: 'edit' },
		} );

		const firstName = `First-${ suffix }`;
		const lastName = `Last-${ suffix }`;
		const nickname = `Nick-${ suffix }`;

		try {
			await admin.visitAdminPage( 'profile.php' );

			await page
				.getByLabel( 'First Name', { exact: true } )
				.fill( firstName );
			await page
				.getByLabel( 'Last Name', { exact: true } )
				.fill( lastName );
			await page.getByLabel( 'Nickname (required)' ).fill( nickname );
			await page
				.getByRole( 'button', { name: 'Update Profile', exact: true } )
				.click();

			await expect( page.locator( '#message' ) ).toContainText(
				'Profile updated.'
			);
			await expect(
				page.getByLabel( 'First Name', { exact: true } )
			).toHaveValue( firstName );
			await expect(
				page.getByLabel( 'Last Name', { exact: true } )
			).toHaveValue( lastName );
			await expect( page.getByLabel( 'Nickname (required)' ) ).toHaveValue(
				nickname
			);

			const updated = await requestUtils.rest( {
				path: '/wp/v2/users/me',
				params: { context: 'edit' },
			} );
			expect( updated.first_name ).toBe( firstName );
			expect( updated.last_name ).toBe( lastName );
			expect( updated.nickname ).toBe( nickname );
		} finally {
			await requestUtils.rest( {
				method: 'POST',
				path: '/wp/v2/users/me',
				data: {
					first_name: original.first_name,
					last_name: original.last_name,
					nickname: original.nickname,
					name: original.name,
				},
			} );
		}
	} );
} );
