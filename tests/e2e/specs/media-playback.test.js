/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * External dependencies
 */
import path from 'path';

const AUDIO_FIXTURE = path.join( __dirname, '../assets/small-audio.mp3' );
const VIDEO_FIXTURE = path.join( __dirname, '../assets/small-video.webm' );

/**
 * Returns the current playback position of the first media element on the page.
 *
 * @param {import('@playwright/test').Page} page The Playwright page.
 * @return {Promise<number>} The currentTime of the media element.
 */
function getCurrentTime( page ) {
	return page.evaluate(
		() => document.querySelector( '.mejs-container audio, .mejs-container video' ).currentTime
	);
}

/**
 * Asserts the MediaElement player rendered its controls from the SVG icon
 * sprite. A missing or unresolvable sprite path is a hard failure mode in
 * MediaElement.js 7.x, so the play button must reference mejs-controls.svg.
 *
 * @param {import('@playwright/test').Locator} container The .mejs-container locator.
 */
async function expectSpriteControls( container ) {
	const useElement = container.locator( '.mejs-playpause-button svg use' ).first();
	await expect( useElement ).toHaveAttribute( 'xlink:href', /mejs-controls\.svg#/ );
}

test.describe( 'MediaElement.js front-end playback', () => {
	test.afterAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();
		await requestUtils.deleteAllMedia();
	} );

	test( '[audio] shortcode renders a working player', async ( { page, requestUtils } ) => {
		const media = await requestUtils.uploadMedia( AUDIO_FIXTURE );
		const post = await requestUtils.createPost( {
			status: 'publish',
			content: `[audio src="${ media.source_url }"]`,
		} );

		await page.goto( post.link );

		const container = page.locator( '.mejs-container.mejs-audio' );
		await expect( container ).toBeVisible();
		await expectSpriteControls( container );

		// The player must register itself so wp.media views can manage it.
		const playerCount = await page.evaluate( () => Object.keys( window.mejs.players ).length );
		expect( playerCount ).toBe( 1 );

		// Clicking play must start actual playback.
		await container.locator( '.mejs-playpause-button button' ).click();
		await expect.poll( () => getCurrentTime( page ) ).toBeGreaterThan( 0 );

		// Clicking again must pause.
		await container.locator( '.mejs-playpause-button button' ).click();
		const pausedAt = await getCurrentTime( page );
		await page.waitForTimeout( 500 );
		expect( await getCurrentTime( page ) ).toBe( pausedAt );
	} );

	test( '[video] shortcode renders a working player', async ( { page, requestUtils } ) => {
		const media = await requestUtils.uploadMedia( VIDEO_FIXTURE );
		const post = await requestUtils.createPost( {
			status: 'publish',
			content: `[video src="${ media.source_url }"]`,
		} );

		await page.goto( post.link );

		const container = page.locator( '.mejs-container.mejs-video' );
		await expect( container ).toBeVisible();
		await expectSpriteControls( container );

		await container.locator( '.mejs-playpause-button button' ).click();
		await expect.poll( () => getCurrentTime( page ) ).toBeGreaterThan( 0 );
	} );

	test( '[playlist] shortcode plays and switches audio tracks', async ( { page, requestUtils } ) => {
		const trackOne = await requestUtils.uploadMedia( AUDIO_FIXTURE );
		const trackTwo = await requestUtils.uploadMedia( AUDIO_FIXTURE );
		const post = await requestUtils.createPost( {
			status: 'publish',
			content: `[playlist ids="${ trackOne.id },${ trackTwo.id }"]`,
		} );

		await page.goto( post.link );

		const playlist = page.locator( '.wp-playlist.wp-audio-playlist' );
		await expect( playlist ).toBeVisible();
		await expect( playlist.locator( '.mejs-container.mejs-audio' ) ).toBeVisible();

		const items = playlist.locator( '.wp-playlist-item' );
		await expect( items ).toHaveCount( 2 );
		await expect( items.first() ).toHaveClass( /wp-playlist-playing/ );

		// Switching tracks must load the new source and start playback.
		await items.nth( 1 ).locator( 'a.wp-playlist-caption' ).click();
		await expect( items.nth( 1 ) ).toHaveClass( /wp-playlist-playing/ );
		await expect
			.poll( () =>
				page.evaluate(
					() => document.querySelector( '.wp-playlist audio' ).currentSrc
				)
			)
			.toBe( trackTwo.source_url );
		await expect.poll( () => getCurrentTime( page ) ).toBeGreaterThan( 0 );
	} );

	test( '[playlist type="video"] shortcode renders a working player', async ( { page, requestUtils } ) => {
		const clipOne = await requestUtils.uploadMedia( VIDEO_FIXTURE );
		const clipTwo = await requestUtils.uploadMedia( VIDEO_FIXTURE );
		const post = await requestUtils.createPost( {
			status: 'publish',
			content: `[playlist type="video" ids="${ clipOne.id },${ clipTwo.id }"]`,
		} );

		await page.goto( post.link );

		const playlist = page.locator( '.wp-playlist.wp-video-playlist' );
		await expect( playlist ).toBeVisible();

		const container = playlist.locator( '.mejs-container.mejs-video' );
		await expect( container ).toBeVisible();
		await expect( playlist.locator( '.wp-playlist-item' ) ).toHaveCount( 2 );

		await container.locator( '.mejs-playpause-button button' ).click();
		await expect.poll( () => getCurrentTime( page ) ).toBeGreaterThan( 0 );
	} );
} );

test.describe( 'MediaElement.js external renderers', () => {
	test.afterAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();
	} );

	test( '[video] shortcode with a YouTube URL loads the YouTube renderer', async ( { page, requestUtils } ) => {
		const post = await requestUtils.createPost( {
			status: 'publish',
			content: '[video src="https://www.youtube.com/watch?v=jNQXAC9IVRw"]',
		} );

		await page.goto( post.link );

		const container = page.locator( '.mejs-container' );
		await expect( container ).toBeVisible();
		// The renderer embeds the video in a YouTube iframe.
		await expect(
			container.locator( 'iframe[src*="youtube.com"]' )
		).toBeAttached( { timeout: 15000 } );
	} );

	test( '[video] shortcode with a Vimeo URL loads the Vimeo renderer', async ( { page, requestUtils } ) => {
		const post = await requestUtils.createPost( {
			status: 'publish',
			content: '[video src="https://vimeo.com/76979871"]',
		} );

		await page.goto( post.link );

		// The Vimeo renderer ships as a separate script that must be enqueued.
		await expect(
			page.locator( 'script[src*="mediaelement/renderers/vimeo" ]' )
		).toBeAttached();

		const container = page.locator( '.mejs-container' );
		await expect( container ).toBeVisible();
		await expect(
			container.locator( 'iframe[src*="player.vimeo.com"]' )
		).toBeAttached( { timeout: 15000 } );
	} );
} );
