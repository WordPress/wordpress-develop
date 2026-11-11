/**
 * Uploads attachments, records the ones the browser uploads, then deletes
 * them in teardown.
 *
 * @param {Object}                                                      fixtures              The fixtures this one depends on.
 * @param {import('@wordpress/e2e-test-utils-playwright').RequestUtils} fixtures.requestUtils The request utils fixture.
 * @param {Function}                                                    use                   Hands the fixture to the test.
 * @return {Promise<void>}
 */
export const attachments = async ( { requestUtils }, use ) => {
	const ids = new Set();

	await use( {
		async upload( absolutePath ) {
			const attachment = await requestUtils.uploadMedia( absolutePath );
			ids.add( attachment.id );
			return attachment;
		},
		track( id ) {
			ids.add( id );
		},
	} );

	const failures = [];
	for ( const id of ids ) {
		try {
			await requestUtils.deleteMedia( id );
		} catch ( error ) {
			// A test can delete its own attachment through the UI.
			if ( error.data?.status !== 404 ) {
				failures.push( `${ id }: ${ error.message }` );
			}
		}
	}

	if ( failures.length ) {
		throw new Error(
			`attachments could not delete:\n${ failures.join( '\n' ) }`
		);
	}
};
