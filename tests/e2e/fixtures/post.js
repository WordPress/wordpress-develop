/**
 * External dependencies
 */
import { randomUUID } from 'node:crypto';

/**
 * Creates posts and pages, then force-deletes them in teardown.
 *
 * Pages go before posts, and later-created items first, so children go before
 * parents. Deleting a post also deletes its comments, including replies.
 *
 * @param {Object}                                                      fixtures              The fixtures this one depends on.
 * @param {import('@wordpress/e2e-test-utils-playwright').RequestUtils} fixtures.requestUtils The request utils fixture.
 * @param {Function}                                                    use                   Hands the factory to the test.
 * @return {Promise<void>}
 */
export const postFactory = async ( { requestUtils }, use ) => {
	const pagePaths = [];
	const postPaths = [];

	await use( {
		async create( data ) {
			const post = await requestUtils.createPost( data );
			postPaths.push( `/wp/v2/posts/${ post.id }` );
			return post;
		},
		async createPage( data ) {
			const page = await requestUtils.createPage( data );
			pagePaths.push( `/wp/v2/pages/${ page.id }` );
			return page;
		},
		uniqueTitle: ( prefix ) => `${ prefix } ${ randomUUID() }`,
	} );

	const failures = [];
	for ( const path of [ ...pagePaths.reverse(), ...postPaths.reverse() ] ) {
		try {
			await requestUtils.rest( {
				method: 'DELETE',
				path,
				params: { force: true },
			} );
		} catch ( error ) {
			// A test can delete its own post through the UI.
			if ( error.data?.status !== 404 ) {
				failures.push( `${ path }: ${ error.message }` );
			}
		}
	}

	if ( failures.length ) {
		throw new Error(
			`postFactory could not delete:\n${ failures.join( '\n' ) }`
		);
	}
};
