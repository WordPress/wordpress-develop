import { createHash } from 'node:crypto';
import { readFile } from 'node:fs/promises';

const FULL_COMMIT = /^[0-9a-f]{40}$/;
const FULL_DIGEST = /^[0-9a-f]{64}$/;
const VERSION = /^\d+\.\d+\.\d+$/;
const API_BETA_VERSION = /^\d+\.\d+(?:\.\d+)?-(?:beta\d+|RC\d+)$/;
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

function assertObject( value, label ) {
	if ( ! value || typeof value !== 'object' || Array.isArray( value ) ) {
		throw new Error( `${ label } must be an object.` );
	}
}

function assertPositiveInteger( value, label ) {
	if ( ! Number.isSafeInteger( value ) || value < 1 ) {
		throw new Error( `${ label } must be a positive integer.` );
	}
}

function assertDigest( value, label ) {
	if ( typeof value !== 'string' || ! FULL_DIGEST.test( value ) ) {
		throw new Error( `${ label } must be a lowercase SHA-256 digest.` );
	}
}

export function validateDependencies( dependencies ) {
	assertObject( dependencies, 'Dependency manifest' );
	if ( dependencies.schemaVersion !== 1 ) {
		throw new Error( `Unsupported dependency schema ${ dependencies.schemaVersion }.` );
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
		throw new Error( 'The WordPress beta resolver must use the official API.' );
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
		throw new Error( 'The dependency manifest contains an unknown repository.' );
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

export async function loadDependencies( filename ) {
	const bytes = await readFile( filename );
	const dependencies = validateDependencies( JSON.parse( bytes ) );
	return {
		dependencies,
		digest: createHash( 'sha256' ).update( bytes ).digest( 'hex' ),
	};
}

export async function resolveWordPressBeta(
	fetchImplementation = globalThis.fetch
) {
	const response = await fetchImplementation( VERSION_API, {
		headers: { Accept: 'application/json' },
	} );
	if ( ! response.ok ) {
		throw new Error( `WordPress beta API returned HTTP ${ response.status }.` );
	}
	const body = await response.json();
	const offer = body?.offers?.find(
		( candidate ) =>
			API_BETA_VERSION.test( candidate?.version ) &&
			candidate.download ===
				`https://downloads.wordpress.org/release/wordpress-${ candidate.version }.zip`
	);
	if ( ! offer ) {
		throw new Error( 'The WordPress beta API returned no concrete beta build.' );
	}
	return Object.freeze( {
		channel: 'beta',
		version: offer.version,
		downloadUrl: offer.download,
	} );
}

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
		if ( typeof value !== 'string' || ! /^[A-Za-z0-9_.-]+$/.test( value ) ) {
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

export function isDeploymentEnabled( repository, stagingValue = '' ) {
	return (
		DEPLOYMENT_REPOSITORIES.has( repository ) &&
		( repository === 'WordPress/wordpress-develop' || stagingValue === 'true' )
	);
}
