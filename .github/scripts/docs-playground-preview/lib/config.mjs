import { createHash } from 'node:crypto';
import { readFile } from 'node:fs/promises';

import { REQUEST_TIMEOUT_MS } from './http.mjs';

const FULL_COMMIT = /^[0-9a-f]{40}$/;
const FULL_DIGEST = /^[0-9a-f]{64}$/;
const VERSION = /^\d+\.\d+\.\d+$/;
const API_BETA_VERSION = /^\d+\.\d+(?:\.\d+)?-(?:beta\d+|RC\d+)$/;
const API_STABLE_VERSION = /^\d+\.\d+(?:\.\d+)?$/;
const OFFICIAL_REPOSITORIES = Object.freeze( {
	phpdocParser: 'WordPress/phpdoc-parser',
	wporgDeveloper: 'WordPress/wporg-developer',
	wporgParent2021: 'WordPress/wporg-parent-2021',
	wporgMuPlugins: 'WordPress/wporg-mu-plugins',
	postsToPosts: 'scribu/wp-posts-to-posts',
	codeSyntaxBlock: 'mkaz/code-syntax-block',
} );
const DEPLOYMENT_REPOSITORIES = new Set( [
	'WordPress/wordpress-develop',
	'sirreal/wordpress-develop',
] );
const VERSION_API =
	'https://api.wordpress.org/core/version-check/1.7/?channel=beta';

/**
 * @param {unknown} value
 * @param {string} label
 */
function assertObject( value, label ) {
	if ( ! value || typeof value !== 'object' || Array.isArray( value ) ) {
		throw new Error( `${ label } must be an object.` );
	}
}

/**
 * @param {unknown} value
 * @param {string} label
 */
function assertPositiveInteger( value, label ) {
	if (
		typeof value !== 'number' ||
		! Number.isSafeInteger( value ) ||
		value < 1
	) {
		throw new Error( `${ label } must be a positive integer.` );
	}
}

/**
 * @param {unknown} value
 * @param {string} label
 */
function assertDigest( value, label ) {
	if ( typeof value !== 'string' || ! FULL_DIGEST.test( value ) ) {
		throw new Error( `${ label } must be a lowercase SHA-256 digest.` );
	}
}

/**
 * @param {Record<string, any>} dependencies
 */
export function validateDependencies( dependencies ) {
	assertObject( dependencies, 'Dependency manifest' );
	if ( dependencies.schemaVersion !== 1 ) {
		throw new Error(
			`Unsupported dependency schema ${ dependencies.schemaVersion }.`
		);
	}
	assertPositiveInteger(
		dependencies.cacheSchemaVersion,
		'cacheSchemaVersion'
	);
	assertObject( dependencies.toolchain, 'toolchain' );
	assertObject( dependencies.playground, 'playground' );
	for ( const name of [
		'nodeVersion',
		'npmVersion',
		'composerVersion',
		'yarnVersion',
		'wpScriptsVersion',
		'wpI18nVersion',
	] ) {
		if ( ! VERSION.test( dependencies.toolchain[ name ] ) ) {
			throw new Error( `toolchain.${ name } must be an exact version.` );
		}
	}
	if ( dependencies.playground.cliVersion !== '3.1.48' ) {
		throw new Error( 'Unexpected Playground CLI version.' );
	}
	if ( dependencies.playground.phpVersion !== '8.4' ) {
		throw new Error( 'The Playground PHP version must be 8.4.' );
	}
	if ( dependencies.playground.wordpressChannel !== 'beta' ) {
		throw new Error( 'The WordPress runtime channel must be beta.' );
	}
	if ( dependencies.playground.wordpressVersionApi !== VERSION_API ) {
		throw new Error(
			'The WordPress beta resolver must use the official API.'
		);
	}
	if ( dependencies.limits?.snapshotBytes !== 104857600 ) {
		throw new Error( 'The snapshot limit must be exactly 100 MiB.' );
	}

	assertObject( dependencies.repositories, 'repositories' );
	for ( const [ name, repository ] of Object.entries(
		OFFICIAL_REPOSITORIES
	) ) {
		const dependency = dependencies.repositories[ name ];
		assertObject( dependency, `repositories.${ name }` );
		if ( dependency.repository !== repository ) {
			throw new Error(
				`repositories.${ name } must use ${ repository }.`
			);
		}
		if ( ! FULL_COMMIT.test( dependency.commit ) ) {
			throw new Error(
				`repositories.${ name }.commit must be a full commit hash.`
			);
		}
		if ( typeof dependency.path !== 'string' || dependency.path === '' ) {
			throw new Error( `repositories.${ name }.path must be nonempty.` );
		}
	}
	if (
		Object.keys( dependencies.repositories ).length !==
		Object.keys( OFFICIAL_REPOSITORIES ).length
	) {
		throw new Error(
			'The dependency manifest contains an unknown repository.'
		);
	}

	assertObject( dependencies.validation?.minimumSymbols, 'minimumSymbols' );
	for ( const type of [
		'classes',
		'methods',
		'functions',
		'hooks',
		'filters',
	] ) {
		assertPositiveInteger(
			dependencies.validation.minimumSymbols[ type ],
			`minimumSymbols.${ type }`
		);
	}
	return dependencies;
}

/**
 * @param {string} filename
 */
export async function loadDependencies( filename ) {
	const bytes = await readFile( filename );
	const dependencies = validateDependencies(
		JSON.parse( bytes.toString( 'utf8' ) )
	);
	return {
		dependencies,
		digest: createHash( 'sha256' ).update( bytes ).digest( 'hex' ),
	};
}

/**
 * @param {(...args: any[]) => Promise<any>} [fetchImplementation]
 */
export async function resolveWordPressBeta(
	fetchImplementation = globalThis.fetch
) {
	const response = await fetchImplementation( VERSION_API, {
		headers: { Accept: 'application/json' },
		signal: AbortSignal.timeout( REQUEST_TIMEOUT_MS ),
	} );
	if ( ! response.ok ) {
		throw new Error(
			`WordPress beta API returned HTTP ${ response.status }.`
		);
	}
	const body = await response.json();
	const findOfficialOffer = /** @param {RegExp} versionPattern */ (
		versionPattern
	) =>
		body?.offers?.find(
			/**
			 * @param {Record<string, any>} candidate
			 */
			( candidate ) =>
				versionPattern.test( candidate?.version ) &&
				candidate.download ===
					`https://downloads.wordpress.org/release/wordpress-${ candidate.version }.zip`
		);
	const betaOffer = findOfficialOffer( API_BETA_VERSION );
	if ( betaOffer ) {
		return Object.freeze( {
			channel: 'beta',
			version: betaOffer.version,
			downloadUrl: betaOffer.download,
		} );
	}
	const stableOffer = findOfficialOffer( API_STABLE_VERSION );
	if ( ! stableOffer ) {
		throw new Error(
			'The WordPress beta API returned no concrete beta or stable build.'
		);
	}
	process.stderr.write(
		`::notice::The WordPress beta channel offers no beta or RC build; tracking the stable release ${ stableOffer.version } until the next beta appears.\n`
	);
	return Object.freeze( {
		channel: 'stable',
		version: stableOffer.version,
		downloadUrl: stableOffer.download,
	} );
}

/**
 * @param {Record<string, any>} inputs
 */
export function makeBaseCacheKey( inputs ) {
	assertObject( inputs, 'Cache inputs' );
	assertPositiveInteger(
		inputs.cacheSchemaVersion,
		'Cache cacheSchemaVersion'
	);
	for ( const [ name, value ] of Object.entries( {
		platform: inputs.platform,
		architecture: inputs.architecture,
		runnerImage: inputs.runnerImage,
		phpVersion: inputs.phpVersion,
		wordpressVersion: inputs.wordpressVersion,
	} ) ) {
		if (
			typeof value !== 'string' ||
			! /^[A-Za-z0-9_.-]+$/.test( value )
		) {
			throw new Error( `Cache ${ name } is invalid.` );
		}
	}
	assertDigest( inputs.dependencyDigest, 'Cache dependencyDigest' );
	assertDigest( inputs.harnessDigest, 'Cache harnessDigest' );
	const identity = createHash( 'sha256' )
		.update( JSON.stringify( inputs ) )
		.digest( 'hex' );
	return `docs-preview-base-v${ inputs.cacheSchemaVersion }-${ identity }`;
}

/**
 * @param {string} repository
 */
export function isDeploymentEnabled( repository, stagingValue = '' ) {
	return (
		DEPLOYMENT_REPOSITORIES.has( repository ) &&
		( repository === 'WordPress/wordpress-develop' ||
			stagingValue === 'true' )
	);
}
