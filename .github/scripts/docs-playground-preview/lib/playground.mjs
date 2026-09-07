import path from 'node:path';
import { pathToFileURL } from 'node:url';

export const PLAYGROUND_BUILD_WORKERS = 1;
export const PLAYGROUND_SERVER_WORKERS = 6;

/**
 * @param {Record<string, any>} options
 */
export function createSnapshotArguments( options ) {
	return {
		...options,
		command: 'build-snapshot',
		workers: PLAYGROUND_BUILD_WORKERS,
	};
}

/**
 * @param {string} playgroundCli
 * @param {Record<string, any>} options
 */
export async function buildSnapshot( playgroundCli, options ) {
	const modulePath = path.resolve(
		path.dirname( playgroundCli ),
		'../@wp-playground/cli/index.js'
	);
	const { runCLI } = await import( pathToFileURL( modulePath ).href );
	await runCLI( createSnapshotArguments( options ) );
}
