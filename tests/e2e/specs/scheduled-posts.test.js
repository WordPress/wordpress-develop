/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Reads the server's clock from an HTTP Date header, so scheduling is
 * immune to clock skew between the test host and the server.
 *
 * @param {import('@wordpress/e2e-test-utils-playwright').RequestUtils} requestUtils The request utils fixture.
 * @return {Promise<Date>} The server's current time.
 */
async function getServerNow( requestUtils ) {
	const response = await requestUtils.request.get(
		new URL( '/', requestUtils.baseURL ).toString()
	);
	return new Date( response.headers().date );
}

/**
 * Formats a date the way the REST API expects (Y-m-d\TH:i:s, UTC).
 *
 * @param {Date} date The date to format.
 * @return {string} The formatted GMT date string.
 */
function toRestDateGmt( date ) {
	return date.toISOString().slice( 0, 19 );
}

test.describe( 'Scheduled Posts', () => {
	test.beforeEach( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();
	} );

	test( 'publishes a scheduled post when its time arrives', async ( {
		requestUtils,
	} ) => {
		// Schedule the post shortly into the future, by the server's clock.
		const serverNow = await getServerNow( requestUtils );
		const post = await requestUtils.createPost( {
			title: 'Scheduled Post',
			content: '<p>Published by WP-Cron.</p>',
			status: 'future',
			date_gmt: toRestDateGmt( new Date( serverNow.getTime() + 60_000 ) ),
		} );

		expect( post.status ).toBe( 'future' );

		// Let the scheduled time pass with no requests to the site. Any
		// ordinary request that observes a due event spawns WordPress's
		// loopback cron and takes the doing_cron lock — and in an
		// environment that cannot perform loopback requests, the spawned
		// run never executes, so the lock only shuts out wp-cron.php for
		// the next minute. A quiet wait leaves the lock free (or stale) the
		// moment the event is due, letting the explicit wp-cron.php request
		// below claim it and run the transition deterministically.
		await new Promise( ( resolve ) => {
			setTimeout( resolve, 65_000 );
		} );

		await expect
			.poll(
				async () => {
					// Drive cron explicitly: the request runs due events
					// synchronously when it can claim the lock, with no
					// dependency on loopback self-spawning.
					await requestUtils.request.get(
						new URL(
							'/wp-cron.php',
							requestUtils.baseURL
						).toString()
					);
					const updated = await requestUtils.rest( {
						path: `/wp/v2/posts/${ post.id }`,
						params: { context: 'edit' },
					} );
					return updated.status;
				},
				{
					message:
						'the scheduled post should transition to publish once its date passes',
					timeout: 60_000,
				}
			)
			.toBe( 'publish' );
	} );

	test( 'lists a scheduled post in the Scheduled view', async ( {
		admin,
		page,
		requestUtils,
	} ) => {
		// Schedule the post far enough out that it cannot publish mid-test.
		const serverNow = await getServerNow( requestUtils );
		await requestUtils.createPost( {
			title: 'Future Post',
			status: 'future',
			date_gmt: toRestDateGmt(
				new Date( serverNow.getTime() + 2 * 60 * 60 * 1_000 )
			),
		} );

		await admin.visitAdminPage( '/edit.php' );

		// Switch to the Scheduled status view.
		await page
			.locator( '.subsubsub' )
			.getByRole( 'link', { name: /^Scheduled/ } )
			.click();

		const listTable = page.getByRole( 'table', {
			name: 'Table ordered by',
		} );
		await expect( listTable ).toBeVisible();

		// The scheduled post is listed with a scheduled date, not a
		// published one.
		await expect(
			listTable.getByRole( 'link', { name: 'Future Post', exact: true } )
		).toBeVisible();
		await expect(
			listTable.getByRole( 'cell', { name: /^Scheduled/ } )
		).toBeVisible();
	} );
} );
