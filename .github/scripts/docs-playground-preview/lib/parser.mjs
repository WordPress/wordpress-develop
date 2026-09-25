import { mkdir, readFile, writeFile } from 'node:fs/promises';
import path from 'node:path';

import { stageCorePhp } from './files.mjs';
import { run } from './process.mjs';

const HOOK_TYPE = /^(?:action|filter)(?:_(?:deprecated|reference))?$/;
const IMPORT_SOURCE_ROOT = '/tmp/docs-preview-source';
const SAFE_VERSION_ROOT = '/tmp/docs-preview-version';

/**
 * @param {Record<string, any> | null | undefined} value
 */
function hooksIn( value ) {
	return Array.isArray( value?.hooks ) ? value.hooks : [];
}

/**
 * @param {any[]} hooks
 * @param {Record<string, any>} counts
 */
function countHooks( hooks, counts ) {
	for ( const hook of hooks ) {
		if (
			! hook ||
			typeof hook !== 'object' ||
			! HOOK_TYPE.test( hook.type )
		) {
			throw new Error(
				'Parser JSON contains a hook with an invalid type.'
			);
		}
		if ( hook.type.startsWith( 'action' ) ) {
			counts.hooks++;
		} else {
			counts.filters++;
		}
	}
}

/**
 * @param {any[]} records
 * @param {Record<string, any>} minimumSymbols
 */
export function inspectParserRecords( records, minimumSymbols ) {
	if ( ! Array.isArray( records ) ) {
		throw new Error( 'Parser JSON must be an array.' );
	}
	/** @type {Record<string, number>} */
	const counts = {
		classes: 0,
		methods: 0,
		functions: 0,
		hooks: 0,
		filters: 0,
	};
	for ( const record of records ) {
		if (
			! record ||
			typeof record !== 'object' ||
			typeof record.path !== 'string'
		) {
			throw new Error( 'Parser JSON contains an invalid file record.' );
		}
		if (
			record.path.startsWith( 'wp-content/plugins/' ) ||
			record.path.startsWith( 'wp-content/themes/' )
		) {
			throw new Error(
				`Parser JSON contains excluded source: ${ record.path }.`
			);
		}
		const functions = Array.isArray( record.functions )
			? record.functions
			: [];
		const classes = Array.isArray( record.classes ) ? record.classes : [];
		counts.functions += functions.length;
		counts.classes += classes.length;
		for ( const item of functions ) {
			countHooks( hooksIn( item ), counts );
		}
		for ( const item of classes ) {
			const methods = Array.isArray( item?.methods ) ? item.methods : [];
			counts.methods += methods.length;
			for ( const method of methods ) {
				countHooks( hooksIn( method ), counts );
			}
		}
		countHooks( hooksIn( record ), counts );
		record.root =
			record.path === 'wp-includes/version.php'
				? SAFE_VERSION_ROOT
				: IMPORT_SOURCE_ROOT;
	}
	const failures =
		records.length === 0 ? [ 'Parser produced no file records.' ] : [];
	failures.push(
		...Object.entries( minimumSymbols )
			.filter( ( [ type, minimum ] ) => counts[ type ] < minimum )
			.map(
				( [ type, minimum ] ) =>
					`Parser produced ${ counts[ type ] } ${ type }; expected at least ${ minimum }.`
			)
	);
	return { records: records.length, counts, failures };
}

/**
 * @param {Record<string, any>} options
 */
export async function generateParserJson( options ) {
	const runImplementation = options.runImplementation || run;
	const staged = await stageCorePhp( options.source, options.stagedSource );
	await mkdir( path.dirname( options.output ), { recursive: true } );
	await runImplementation(
		'php',
		[
			'-d',
			'memory_limit=4G',
			path.join( options.parser, 'generate-json-manually.php' ),
			'-d',
			options.stagedSource,
			'-o',
			options.output,
		],
		{
			capture: Boolean( options.logFile ),
			logFile: options.logFile,
			label: 'generate Code Reference parser JSON',
		}
	);
	const records = JSON.parse( await readFile( options.output, 'utf8' ) );
	const inspection = inspectParserRecords( records, options.minimumSymbols );
	await writeFile( options.output, `${ JSON.stringify( records ) }\n` );
	return { ...inspection, sourceFiles: staged.files };
}
