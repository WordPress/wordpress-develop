/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Resolves a path against the site's base URL, preserving any subdirectory
 * the base URL carries. Root-relative URLs resolve against the origin
 * alone — new URL( '/wp-cron.php', 'http://localhost/wp' ) addresses the
 * server root, outside the install — so paths here are resolved relative
 * to the base URL, normalized to end in a slash.
 *
 * @param {import('@wordpress/e2e-test-utils-playwright').RequestUtils} requestUtils The request utils fixture.
 * @param {string}                                                      [path]       A path under the WordPress root, without a leading slash.
 * @return {URL} The resolved URL.
 */
function siteUrl( requestUtils, path = '' ) {
	const baseURL = requestUtils.baseURL.endsWith( '/' )
		? requestUtils.baseURL
		: `${ requestUtils.baseURL }/`;
	return new URL( path, baseURL );
}

/**
 * Reads the server's clock from an HTTP Date header, so scheduling is
 * immune to clock skew between the test host and the server.
 *
 * @param {import('@wordpress/e2e-test-utils-playwright').RequestUtils} requestUtils The request utils fixture.
 * @return {Promise<Date>} The server's current time.
 */
async function getServerNow( requestUtils ) {
	// `doing_wp_cron` makes spawn_cron() return early, so reading the clock
	// cannot take the doing_cron lock. See driveCron() for why that matters.
	const url = siteUrl( requestUtils );
	url.searchParams.set( 'doing_wp_cron', '1' );

	const response = await requestUtils.request.get( url.toString() );
	const dateHeader = response.headers().date;
	if ( ! dateHeader ) {
		throw new Error(
			'The response had no Date header to read the server clock from.'
		);
	}
	return new Date( dateHeader );
}

/**
 * Formats a date for the REST API with an explicit UTC designator, so
 * the value cannot be reinterpreted against another timezone.
 *
 * @param {Date} date The date to format.
 * @return {string} The formatted GMT date string.
 */
function toRestDateGmt( date ) {
	return date.toISOString().slice( 0, 19 ) + 'Z';
}

/**
 * Reads a GMT date string returned by the REST API, which carries no timezone
 * designator, as the UTC instant it denotes.
 *
 * @param {string} dateGmt A `date_gmt` value from the REST API.
 * @return {Date} The instant it denotes.
 */
function fromRestDateGmt( dateGmt ) {
	return new Date( `${ dateGmt.replace( /Z$/, '' ) }Z` );
}

/**
 * Asks WordPress to run its due cron events, without waiting for them.
 *
 * This is the only request in the spec that may take the doing_cron lock;
 * every other one carries `doing_wp_cron` so that spawn_cron() returns early.
 * Keeping the lock under these drives' sole control is what makes the retry
 * cadence below work: an ordinary request that takes the lock holds it for
 * WP_CRON_LOCK_TIMEOUT and then delegates the run to a loopback request, so
 * where loopback cannot be made, a stream of polling requests would take the
 * lock in turn, deliver nothing, and shut every later drive out of it.
 *
 * How long wp-cron.php holds the connection open depends on the server: under
 * PHP-FPM it calls fastcgi_finish_request() and answers immediately, while
 * under mod_php the response waits for every due event to finish. Since one
 * slow event would then stall the caller for as long as it runs, the request is
 * given a short timeout and its failure ignored — wp-cron.php sets
 * ignore_user_abort( true ), so the run continues server-side either way, and
 * what matters here is only that it was asked to start.
 *
 * @param {import('@wordpress/e2e-test-utils-playwright').RequestUtils} requestUtils The request utils fixture.
 * @return {Promise<void>}
 */
async function driveCron( requestUtils ) {
	try {
		await requestUtils.request.get(
			siteUrl( requestUtils, 'wp-cron.php' ).toString(),
			{ timeout: 5_000 }
		);
	} catch {
		// Timed out holding the connection open; the run itself is unaffected.
	}
}

/**
 * Reads a post's status, including statuses only visible to an editor.
 *
 * @param {import('@wordpress/e2e-test-utils-playwright').RequestUtils} requestUtils The request utils fixture.
 * @param {number}                                                      postId       The post ID.
 * @return {Promise<string>} The post status.
 */
async function readPostStatus( requestUtils, postId ) {
	const post = await requestUtils.rest( {
		path: `/wp/v2/posts/${ postId }`,
		// `doing_wp_cron` makes spawn_cron() return early, so polling cannot
		// take the doing_cron lock. See driveCron() for why that matters.
		params: { context: 'edit', doing_wp_cron: '1' },
	} );
	return post.status;
}

test.describe( 'Scheduled Posts', () => {
	test.beforeEach( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();
	} );

	test( 'publishes a scheduled post when its time arrives', async ( {
		requestUtils,
	} ) => {
		// The test spans real time by design: it waits out the post's own
		// scheduled date, then allows cron long enough to run it. Ninety
		// seconds of scheduling plus the poll's budget below puts the failure
		// path past the inherited per-test limit, so triple it — a genuine
		// failure should surface as the poll's message rather than as a
		// generic test timeout.
		test.slow();

		// Schedule the post far enough out to clear the coercion in
		// wp_insert_post(): a post submitted as `future` is silently stored as
		// `publish` unless its date is at least MINUTE_IN_SECONDS ahead of the
		// server's clock, compared at whole-second resolution. Scheduling
		// exactly a minute out would therefore depend on the insert landing in
		// the same clock second as the reading it was derived from, which is
		// not something a loaded machine guarantees.
		const serverNow = await getServerNow( requestUtils );
		const post = await requestUtils.createPost( {
			title: 'Scheduled Post',
			content: '<p>Published by WP-Cron.</p>',
			status: 'future',
			date_gmt: toRestDateGmt( new Date( serverNow.getTime() + 90_000 ) ),
		} );

		expect(
			post.status,
			'the post should be stored as scheduled; `publish` here means its date landed under a minute ahead of the server clock'
		).toBe( 'future' );

		// Wait out the date the server actually stored, measured against the
		// server's clock as it reads now, so the wait carries no estimate made
		// before the post existed. The extra five seconds put the explicit
		// drive that follows safely past the moment the event comes due.
		const dueAt = fromRestDateGmt( post.date_gmt );
		const clockBeforeWait = await getServerNow( requestUtils );
		const waitMs =
			Math.max( 0, dueAt.getTime() - clockBeforeWait.getTime() ) + 5_000;

		// Let the scheduled time pass with no requests to the site. Creating
		// the post was itself an ordinary request, so it may have spawned cron
		// and taken the doing_cron lock on the way out; a quiet wait longer
		// than WP_CRON_LOCK_TIMEOUT leaves that lock stale by the time the
		// event comes due, so the first drive below can claim it straight
		// away rather than being turned away.
		await new Promise( ( resolve ) => {
			setTimeout( resolve, waitMs );
		} );

		// Drive cron explicitly rather than waiting for WordPress to spawn it:
		// a spawned run is a loopback request to the site's own address, which
		// not every containerised environment can make.
		//
		// Drive repeatedly, on a cadence just past WP_CRON_LOCK_TIMEOUT, since
		// one drive is not guaranteed to reach this post's event. wp-cron.php
		// runs every due event in timestamp order, and an install has a queue
		// of unrelated core events — privacy cleanups, do_pings, transient
		// expiry — already due ahead of anything created during a test. A slow
		// event in that queue leaves the drive still working when its own lock
		// expires a minute later, at which point it stops. A later drive
		// resumes from there, because every event the previous one reached was
		// unscheduled before it ran.
		//
		// This is why the budget below is well over a minute: the recovery path
		// becomes available exactly when the lock expires, so a timeout equal
		// to WP_CRON_LOCK_TIMEOUT is a dead heat rather than a limit.
		const driveIntervalMs = 65_000;
		let nextDriveAt = 0;

		await expect
			.poll(
				async () => {
					if ( Date.now() >= nextDriveAt ) {
						nextDriveAt = Date.now() + driveIntervalMs;
						await driveCron( requestUtils );
					}
					return readPostStatus( requestUtils, post.id );
				},
				{
					message:
						'the scheduled post should transition to publish once its date passes and cron runs',
					intervals: [ 1_000 ],
					timeout: 150_000,
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
