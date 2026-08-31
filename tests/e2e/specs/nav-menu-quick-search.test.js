/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe( 'Navigation menu quick search', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts( 'pages' );

		const titles = [
			...Array.from( { length: 10 }, ( _value, index ) => `Ticket 38224 exact ten ${ index }` ),
			...Array.from( { length: 11 }, ( _value, index ) => `Ticket 38224 eleven ${ index }` ),
		];

		await Promise.all(
			titles.map( ( title ) =>
				requestUtils.rest( {
					method: 'POST',
					path: '/wp/v2/pages',
					data: { title, status: 'publish' },
				} )
			)
		);
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts( 'pages' );
	} );

	test( 'returns pagination metadata', async ( { admin, page } ) => {
		await admin.visitAdminPage( '/' );

		const search = ( query, paged = 1 ) =>
			page.evaluate(
				async ( request ) => {
					const response = await fetch( window.ajaxurl, {
						method: 'POST',
						body: new URLSearchParams( {
							action: 'menu-quick-search',
							'response-format': 'markup',
							type: 'quick-search-posttype-page',
							q: request.query,
							paged: request.paged,
						} ),
					} );
					const markup = await response.text();
					const document = new DOMParser().parseFromString( `<ul>${ markup }</ul>`, 'text/html' );

					return {
						hasMore: response.headers.get( 'X-WP-Menu-Quick-Search-Has-More' ),
						results: document.querySelectorAll( 'li' ).length,
					};
				},
				{ query, paged: paged.toString() }
			);

		await expect( search( 'Ticket 38224 exact ten' ) ).resolves.toEqual( {
			hasMore: '0',
			results: 10,
		} );
		await expect( search( 'Ticket 38224 eleven' ) ).resolves.toEqual( {
			hasMore: '1',
			results: 10,
		} );
		await expect( search( 'Ticket 38224 eleven', 2 ) ).resolves.toEqual( {
			hasMore: '0',
			results: 1,
		} );
	} );
} );
