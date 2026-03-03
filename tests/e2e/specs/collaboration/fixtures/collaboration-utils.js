/**
 * External dependencies
 */
import { expect } from '@playwright/test';

/**
 * WordPress dependencies
 */
import { Editor } from '@wordpress/e2e-test-utils-playwright';

export const SECOND_USER = {
	username: 'collaborator',
	email: 'collaborator@example.com',
	firstName: 'Test',
	lastName: 'Collaborator',
	password: 'password',
	roles: [ 'editor' ],
};

export const THIRD_USER = {
	username: 'collaborator2',
	email: 'collaborator2@example.com',
	firstName: 'Another',
	lastName: 'Collaborator',
	password: 'password',
	roles: [ 'editor' ],
};

const BASE_URL = process.env.WP_BASE_URL || 'http://localhost:8889';

export default class CollaborationUtils {
	constructor( { admin, editor, requestUtils, page } ) {
		this.admin = admin;
		this.editor = editor;
		this.requestUtils = requestUtils;
		this.primaryPage = page;

		this._secondContext = null;
		this._secondPage = null;
		this._secondEditor = null;

		this._thirdContext = null;
		this._thirdPage = null;
		this._thirdEditor = null;
	}

	/**
	 * Set the real-time collaboration WordPress setting.
	 *
	 * Uses the form-based approach because this setting is registered
	 * on admin_init in the "writing" group and is not exposed via
	 * /wp/v2/settings.
	 *
	 * @param {boolean} enabled Whether to enable or disable collaboration.
	 */
	async setCollaboration( enabled ) {
		const response = await this.requestUtils.request.get(
			'/wp-admin/options-writing.php'
		);
		const html = await response.text();
		const nonce = html.match( /name="_wpnonce" value="([^"]+)"/ )[ 1 ];

		const formData = {
			option_page: 'writing',
			action: 'update',
			_wpnonce: nonce,
			_wp_http_referer: '/wp-admin/options-writing.php',
			submit: 'Save Changes',
			default_category: 1,
			default_post_format: 0,
		};

		if ( enabled ) {
			formData.wp_enable_real_time_collaboration = 1;
		}

		await this.requestUtils.request.post( '/wp-admin/options.php', {
			form: formData,
			failOnStatusCode: true,
		} );
	}

	/**
	 * Log a user into WordPress via the login form on a given page.
	 *
	 * @param {import('@playwright/test').Page} page     The page to log in on.
	 * @param {Object}                          userInfo User credentials.
	 */
	async loginUser( page, userInfo ) {
		await page.goto( '/wp-login.php' );

		// Retry filling if the page resets during a cold Docker start.
		await expect( async () => {
			await page.locator( '#user_login' ).fill( userInfo.username );
			await page.locator( '#user_pass' ).fill( userInfo.password );
			await expect( page.locator( '#user_pass' ) ).toHaveValue(
				userInfo.password
			);
		} ).toPass( { timeout: 15_000 } );

		await page.getByRole( 'button', { name: 'Log In' } ).click();
		await page.waitForURL( '**/wp-admin/**' );
	}

	/**
	 * Set up a new browser context for a collaborator user.
	 *
	 * @param {Object} userInfo User credentials and info.
	 * @return {Object} An object with context, page, and editor.
	 */
	async setupCollaboratorContext( userInfo ) {
		const context = await this.admin.browser.newContext( {
			baseURL: BASE_URL,
		} );
		const page = await context.newPage();

		await this.loginUser( page, userInfo );

		return { context, page };
	}

	/**
	 * Navigate a page to the post editor and dismiss the welcome guide.
	 *
	 * @param {import('@playwright/test').Page} page   The page to navigate.
	 * @param {number}                          postId The post ID to edit.
	 */
	async navigateToEditor( page, postId ) {
		await page.goto(
			`/wp-admin/post.php?post=${ postId }&action=edit`
		);
		await page.waitForFunction(
			() => window?.wp?.data && window?.wp?.blocks
		);
		await page.evaluate( () => {
			window.wp.data
				.dispatch( 'core/preferences' )
				.set( 'core/edit-post', 'welcomeGuide', false );
			window.wp.data
				.dispatch( 'core/preferences' )
				.set( 'core/edit-post', 'fullscreenMode', false );
		} );
	}

	/**
	 * Open a collaborative editing session where all 3 users are editing
	 * the same post.
	 *
	 * @param {number} postId The post ID to collaboratively edit.
	 */
	async openCollaborativeSession( postId ) {
		// Set up the second and third browser contexts.
		const second = await this.setupCollaboratorContext( SECOND_USER );
		this._secondContext = second.context;
		this._secondPage = second.page;

		const third = await this.setupCollaboratorContext( THIRD_USER );
		this._thirdContext = third.context;
		this._thirdPage = third.page;

		// Navigate User 1 (admin) to the post editor.
		await this.admin.visitAdminPage(
			'post.php',
			`post=${ postId }&action=edit`
		);
		await this.editor.setPreferences( 'core/edit-post', {
			welcomeGuide: false,
			fullscreenMode: false,
		} );

		// Wait for collaboration to be enabled on User 1's page.
		await this.waitForCollaborationReady( this.primaryPage );

		// Navigate User 2 and User 3 to the same post editor.
		await this.navigateToEditor( this._secondPage, postId );
		await this.navigateToEditor( this._thirdPage, postId );

		// Create Editor instances for the additional pages.
		this._secondEditor = new Editor( { page: this._secondPage } );
		this._thirdEditor = new Editor( { page: this._thirdPage } );

		// Wait for collaboration to be enabled on all pages.
		await Promise.all( [
			this.waitForCollaborationReady( this._secondPage ),
			this.waitForCollaborationReady( this._thirdPage ),
		] );

		// Wait for all users to discover each other via awareness.
		await Promise.all( [
			this.primaryPage
				.getByRole( 'button', { name: /Collaborators list/ } )
				.waitFor( { timeout: 15000 } ),
			this._secondPage
				.getByRole( 'button', { name: /Collaborators list/ } )
				.waitFor( { timeout: 15000 } ),
			this._thirdPage
				.getByRole( 'button', { name: /Collaborators list/ } )
				.waitFor( { timeout: 15000 } ),
		] );

		// Allow a full round of polling after awareness is established
		// so all CRDT docs are synchronized.
		await this.waitForAllSynced();
	}

	/**
	 * Wait for the collaboration runtime to be ready on a page.
	 *
	 * @param {import('@playwright/test').Page} page The Playwright page to wait on.
	 */
	async waitForCollaborationReady( page ) {
		await page.waitForFunction(
			() =>
				window._wpCollaborationEnabled === true &&
				window?.wp?.data &&
				window?.wp?.blocks,
			{ timeout: 15000 }
		);
	}

	/**
	 * Wait for sync polling cycles to complete on the given page.
	 *
	 * @param {import('@playwright/test').Page} page   The page to wait on.
	 * @param {number}                          cycles Number of sync responses to wait for.
	 */
	async waitForSyncCycle( page, cycles = 3 ) {
		for ( let i = 0; i < cycles; i++ ) {
			await page.waitForResponse(
				( response ) =>
					response.url().includes( 'wp-sync' ) &&
					response.status() === 200,
				{ timeout: 10000 }
			);
		}
	}

	/**
	 * Wait for sync cycles on all 3 pages in parallel.
	 *
	 * @param {number} cycles Number of sync responses to wait for per page.
	 */
	async waitForAllSynced( cycles = 3 ) {
		const pages = [ this.primaryPage ];
		if ( this._secondPage ) {
			pages.push( this._secondPage );
		}
		if ( this._thirdPage ) {
			pages.push( this._thirdPage );
		}
		await Promise.all(
			pages.map( ( page ) => this.waitForSyncCycle( page, cycles ) )
		);
	}

	/**
	 * Get the second user's Page instance.
	 */
	get page2() {
		if ( ! this._secondPage ) {
			throw new Error(
				'Second page not available. Call openCollaborativeSession() first.'
			);
		}
		return this._secondPage;
	}

	/**
	 * Get the second user's Editor instance.
	 */
	get editor2() {
		if ( ! this._secondEditor ) {
			throw new Error(
				'Second editor not available. Call openCollaborativeSession() first.'
			);
		}
		return this._secondEditor;
	}

	/**
	 * Get the third user's Page instance.
	 */
	get page3() {
		if ( ! this._thirdPage ) {
			throw new Error(
				'Third page not available. Call openCollaborativeSession() first.'
			);
		}
		return this._thirdPage;
	}

	/**
	 * Get the third user's Editor instance.
	 */
	get editor3() {
		if ( ! this._thirdEditor ) {
			throw new Error(
				'Third editor not available. Call openCollaborativeSession() first.'
			);
		}
		return this._thirdEditor;
	}

	/**
	 * Clean up: close extra browser contexts, disable collaboration,
	 * delete test users.
	 */
	async teardown() {
		if ( this._thirdContext ) {
			await this._thirdContext.close();
			this._thirdContext = null;
			this._thirdPage = null;
			this._thirdEditor = null;
		}
		if ( this._secondContext ) {
			await this._secondContext.close();
			this._secondContext = null;
			this._secondPage = null;
			this._secondEditor = null;
		}
		await this.setCollaboration( false );
		await this.requestUtils.deleteAllUsers();
	}
}
