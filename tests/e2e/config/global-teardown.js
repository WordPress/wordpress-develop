/**
 * Internal dependencies
 */
import database from './database.js';

const { isDefaultEnvironment, restoreE2EDatabase } = database;

/**
 * Restore the development environment configuration after the E2E suite completes.
 *
 * @return {Promise<void>}
 */
async function globalTeardown( config ) {
	const { baseURL } = config.projects[ 0 ].use;
	if ( isDefaultEnvironment( baseURL ) ) {
		await restoreE2EDatabase();
	}
}

export default globalTeardown;
