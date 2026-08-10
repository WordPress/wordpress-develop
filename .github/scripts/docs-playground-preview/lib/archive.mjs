import { cp, mkdir, mkdtemp, rm } from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';

import { run } from './process.mjs';

const DEFAULT_EXCLUDES = [ '.git', '.github', 'node_modules', 'tests' ];

/**
 * @param {string} relative
 * @param {string[]} excludes
 */
function isExcluded( relative, excludes ) {
	return excludes.some(
		/**
		 * @param {string} excluded
		 */
		( excluded ) =>
			relative === excluded || relative.startsWith( `${ excluded }/` )
	);
}

/**
 * @param {string} source
 * @param {string} destination
 * @param {string[]} excludes
 */
export async function copyDirectory( source, destination, excludes = [] ) {
	const root = path.resolve( source );
	await mkdir( destination, { recursive: true } );
	await cp( root, destination, {
		recursive: true,
		filter: ( filename ) => {
			const relative = path.relative( root, filename );
			return ! relative || ! isExcluded( relative, excludes );
		},
	} );
}

/**
 * @param {string} source
 * @param {string} output
 * @param {string} rootName
 */
export async function zipDirectory(
	source,
	output,
	rootName,
	runImplementation = run
) {
	const temporary = await mkdtemp(
		path.join( os.tmpdir(), 'docs-preview-zip-' )
	);
	const target =
		rootName === '.' ? temporary : path.join( temporary, rootName );
	try {
		await copyDirectory( source, target, DEFAULT_EXCLUDES );
		await rm( output, { force: true } );
		await runImplementation(
			'zip',
			[ '-rq', path.resolve( output ), '.' ],
			{
				cwd: temporary,
				label: `package ${ rootName }`,
			}
		);
	} finally {
		await rm( temporary, { recursive: true, force: true } );
	}
}

/**
 * @param {string} url
 * @param {string} output
 */
export async function downloadFile( url, output, runImplementation = run ) {
	await mkdir( path.dirname( output ), { recursive: true } );
	await runImplementation(
		'curl',
		[ '--fail', '--location', '--output', output, url ],
		{ label: `download ${ url }` }
	);
}
