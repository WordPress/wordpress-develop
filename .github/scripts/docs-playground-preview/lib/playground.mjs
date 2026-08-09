import path from 'node:path';
import { pathToFileURL } from 'node:url';

export const PLAYGROUND_WORKERS = 6;

export function createSnapshotArguments( options ) {
	return {
		...options,
		command: 'build-snapshot',
		workers: PLAYGROUND_WORKERS,
	};
}

export async function buildSnapshot( playgroundCli, options ) {
	const modulePath = path.resolve(
		path.dirname( playgroundCli ),
		'../@wp-playground/cli/index.js'
	);
	const { runCLI } = await import( pathToFileURL( modulePath ) );
	await runCLI( createSnapshotArguments( options ) );
}
