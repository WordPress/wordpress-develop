/**
 * Generates the PHP copy of the view-config REST schema from the canonical
 * JSON Schema.
 *
 * Reads from  : tools/rest-api/view-config.json
 * Publishes to: src/wp-includes/rest-api/view-config-schema.php
 *
 * The generated file returns the schema as a PHP array with all local `$ref`
 * pointers dereferenced, the `definitions` map dropped, and `description`
 * annotations wrapped in `__()` calls so they are picked up by the
 * translation pipeline.
 *
 * Usage:
 *   node tools/rest-api/gen-view-config-schema-php.mjs          # (re)generate
 *   node tools/rest-api/gen-view-config-schema-php.mjs --check  # fail if stale
 *
 * This script is dependency-free on purpose so the `--check` mode can run
 * in any environment.
 */

import fs from 'node:fs';
import { fileURLToPath } from 'node:url';

/**
 * Path to the canonical view-config JSON Schema.
 *
 * @type {string}
 */
const VIEW_CONFIG_SCHEMA_PATH = fileURLToPath(
	new URL( './view-config.json', import.meta.url )
);

/**
 * Path to the generated PHP schema file.
 *
 * @type {string}
 */
const PHP_SCHEMA_PATH = fileURLToPath(
	new URL(
		'../../src/wp-includes/rest-api/view-config-schema.php',
		import.meta.url
	)
);

/**
 * Resolves local `$ref` pointers (e.g. `#/definitions/foo` or
 * `#/definitions/foo/properties/bar`) against the schema root.
 *
 * @param {*}      node Schema node to resolve.
 * @param {Object} root Schema root the pointers are resolved against.
 * @return {*} The resolved node.
 */
function resolveRefs( node, root ) {
	if ( Array.isArray( node ) ) {
		return node.map( ( item ) => resolveRefs( item, root ) );
	}
	if ( ! node || typeof node !== 'object' ) {
		return node;
	}
	if ( typeof node.$ref === 'string' && node.$ref.startsWith( '#/' ) ) {
		let target = root;
		for ( const rawSegment of node.$ref.slice( 2 ).split( '/' ) ) {
			const segment = rawSegment
				.replaceAll( '~1', '/' )
				.replaceAll( '~0', '~' );
			if ( ! target || ! ( segment in target ) ) {
				throw new Error( `Unresolvable $ref \`${ node.$ref }\`.` );
			}
			target = target[ segment ];
		}
		return resolveRefs( target, root );
	}
	return Object.fromEntries(
		Object.entries( node ).map( ( [ key, value ] ) => [
			key,
			resolveRefs( value, root ),
		] )
	);
}

/**
 * Serializes a value as a PHP literal.
 *
 * `description` annotations are emitted as translatable strings, i.e.
 * `__( '…' )`. Only string values qualify: a schema *property* named
 * `description` (e.g. a form field description) maps to an object, so it is
 * serialized as a regular array.
 *
 * @param {*}      value  Value to serialize.
 * @param {string} indent Current indentation.
 * @return {string} PHP literal.
 */
function toPhp( value, indent = '' ) {
	if ( value === null ) {
		return 'null';
	}
	if ( typeof value === 'boolean' || typeof value === 'number' ) {
		return JSON.stringify( value );
	}
	if ( typeof value === 'string' ) {
		return `'${ value
			.replaceAll( '\\', '\\\\' )
			.replaceAll( "'", "\\'" ) }'`;
	}
	const inner = indent + '\t';
	if ( Array.isArray( value ) ) {
		if ( value.length === 0 ) {
			return 'array()';
		}
		const items = value
			.map( ( item ) => `${ inner }${ toPhp( item, inner ) },\n` )
			.join( '' );
		return `array(\n${ items }${ indent })`;
	}
	const entries = Object.entries( value );
	if ( entries.length === 0 ) {
		return 'array()';
	}
	const items = entries
		.map( ( [ key, item ] ) => {
			const serialized =
				key === 'description' && typeof item === 'string'
					? `__( ${ toPhp( item ) } )`
					: toPhp( item, inner );
			return `${ inner }${ toPhp( key ) } => ${ serialized },\n`;
		} )
		.join( '' );
	return `array(\n${ items }${ indent })`;
}

/**
 * Generates the content of the PHP schema file.
 *
 * @return {string} PHP source.
 */
export function generate() {
	const schema = JSON.parse(
		fs.readFileSync( VIEW_CONFIG_SCHEMA_PATH, 'utf8' )
	);
	const dereferenced = resolveRefs( schema, schema );
	delete dereferenced.definitions;

	return `<?php
/**
 * REST API: view-config endpoint schema.
 *
 * GENERATED FILE — DO NOT EDIT. Regenerate from the canonical JSON Schema
 * at \`tools/rest-api/view-config.json\` with:
 *
 *     node tools/rest-api/gen-view-config-schema-php.mjs
 *
 * @package    WordPress
 * @subpackage REST_API
 * @since      7.1.0
 */

// phpcs:ignoreFile

return ${ toPhp( dereferenced ) };
`;
}

const isMain =
	process.argv[ 1 ] &&
	import.meta.url === new URL( `file://${ process.argv[ 1 ] }` ).href;

if ( isMain ) {
	const content = generate();
	if ( process.argv.includes( '--check' ) ) {
		const current = fs.existsSync( PHP_SCHEMA_PATH )
			? fs.readFileSync( PHP_SCHEMA_PATH, 'utf8' )
			: null;
		if ( current !== content ) {
			console.error(
				'src/wp-includes/rest-api/view-config-schema.php is out of date with ' +
					'tools/rest-api/view-config.json. Regenerate it with ' +
					'`node tools/rest-api/gen-view-config-schema-php.mjs`.'
			);
			process.exit( 1 );
		}
	} else {
		fs.writeFileSync( PHP_SCHEMA_PATH, content );
		console.log( `Generated ${ PHP_SCHEMA_PATH }.` );
	}
}
