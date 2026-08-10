import { fileURLToPath } from 'node:url';
import { cp, mkdir, readFile, rm, writeFile } from 'node:fs/promises';
import path from 'node:path';

import { copyDirectory, downloadFile, zipDirectory } from './archive.mjs';
import { acquireRepositories, exists, sha256File } from './files.mjs';
import { buildSnapshot } from './playground.mjs';
import { run } from './process.mjs';

const LIBRARY_ROOT = path.dirname( fileURLToPath( import.meta.url ) );
const SCRIPT_ROOT = path.dirname( LIBRARY_ROOT );
const TOOLING_ROOT = path.resolve(
	LIBRARY_ROOT,
	'../../../docs-playground-preview'
);
const PHP_ROOT = path.join( SCRIPT_ROOT, 'php' );

// Official sha256 digests for getcomposer.org composer.phar releases, keyed
// by the toolchain.composerVersion pinned in the dependency manifest.
const COMPOSER_DIGESTS = Object.freeze( {
	'2.8.12':
		'f446ea719708bb85fcbf4ef18def5d0515f1f9b4d703f6d820c9c1656e10a2f2',
} );

/**
 * @param {string} name
 */
function executable( name ) {
	return path.join(
		TOOLING_ROOT,
		'node_modules/.bin',
		process.platform === 'win32' ? `${ name }.cmd` : name
	);
}

/**
 * @param {Record<string, any>} roots
 * @param {string} composer
 */
export function dependencyBuildPlan( roots, composer ) {
	const nodePath = path.join( TOOLING_ROOT, 'node_modules' );
	const wpScriptsEnvironment = { NODE_PATH: nodePath };
	return [
		{
			command: 'php',
			args: [
				composer,
				'install',
				'--no-interaction',
				'--no-dev',
				'--prefer-dist',
			],
			cwd: roots.phpdocParser,
			label: 'install phpdoc-parser dependencies',
		},
		{
			command: executable( 'yarn' ),
			args: [ 'install', '--frozen-lockfile', '--ignore-scripts' ],
			cwd: roots.wporgParent2021,
			label: 'install wporg-parent-2021 dependencies',
		},
		{
			command: executable( 'yarn' ),
			args: [ 'build:theme' ],
			cwd: roots.wporgParent2021,
			label: 'build wporg-parent-2021',
		},
		{
			command: executable( 'wp-scripts' ),
			args: [ 'build' ],
			cwd: roots.wporgDeveloperTheme,
			env: wpScriptsEnvironment,
			label: 'build wporg-developer-2023',
		},
		{
			command: 'npm',
			args: [ 'ci', '--ignore-scripts', '--no-audit', '--no-fund' ],
			cwd: roots.wporgMuPlugins,
			label: 'install wporg-mu-plugins dependencies',
		},
		{
			command: 'php',
			args: [
				composer,
				'install',
				'--no-interaction',
				'--no-dev',
				'--prefer-dist',
			],
			cwd: roots.wporgMuPlugins,
			label: 'install wporg-mu-plugins Composer dependencies',
		},
		{
			command: 'npm',
			args: [ 'run', 'build' ],
			cwd: roots.wporgMuPlugins,
			label: 'build wporg-mu-plugins',
		},
		{
			command: 'php',
			args: [
				composer,
				'config',
				'allow-plugins.composer/installers',
				'true',
			],
			cwd: roots.postsToPosts,
			label: 'configure posts-to-posts installer',
		},
		{
			command: 'php',
			args: [
				composer,
				'install',
				'--no-interaction',
				'--no-dev',
				'--prefer-dist',
			],
			cwd: roots.postsToPosts,
			label: 'install posts-to-posts dependencies',
		},
		{
			command: executable( 'wp-scripts' ),
			args: [ 'build' ],
			cwd: roots.codeSyntaxBlock,
			env: wpScriptsEnvironment,
			label: 'build code-syntax-block',
		},
	];
}

/**
 * @param {string} resourcePath
 */
function bundled( resourcePath ) {
	return { resource: 'bundled', path: resourcePath };
}

/**
 * @param {Record<string, any>} inputs
 * @returns {Record<string, any>}
 */
export function createInvariantBaseBlueprint( inputs ) {
	return {
		$schema: inputs.dependencies.playground.blueprintSchema,
		meta: {
			title: 'WordPress Core Code Reference invariant base',
			author: 'WordPress',
			description:
				'Pinned DevHub dependencies with an empty reference index.',
		},
		preferredVersions: {
			php: inputs.dependencies.playground.phpVersion,
			wp: inputs.wordpress.version,
		},
		landingPage: '/reference/',
		login: false,
		features: { networking: false },
		extraLibraries: [ 'wp-cli' ],
		steps: [
			{
				step: 'writeFile',
				path: '/wordpress/wp-content/mu-plugins/000-docs-preview-build.php',
				data: bundled( 'php/base-policy.php' ),
			},
			{
				step: 'unzip',
				zipFile: bundled( 'bundles/wporg-mu-plugins.zip' ),
				extractToPath: '/wordpress/wp-content/mu-plugins',
			},
			{
				step: 'installTheme',
				themeData: bundled( 'bundles/wporg-parent-2021.zip' ),
				ifAlreadyInstalled: 'overwrite',
			},
			{
				step: 'installTheme',
				themeData: bundled( 'bundles/wporg-developer-2023.zip' ),
				ifAlreadyInstalled: 'overwrite',
			},
			...[ 'code-syntax-block', 'posts-to-posts', 'phpdoc-parser' ].map(
				( plugin ) => ( {
					step: 'unzip',
					zipFile: bundled( `bundles/${ plugin }.zip` ),
					extractToPath: '/wordpress/wp-content/plugins',
				} )
			),
			{
				step: 'writeFile',
				path: '/tmp/docs-preview-configure-base.php',
				data: bundled( 'php/configure-base.php' ),
			},
			{
				step: 'wp-cli',
				command: 'wp eval-file /tmp/docs-preview-configure-base.php',
			},
		],
	};
}

/**
 * @param {string} muPlugins
 */
export async function prunePreviewFonts( muPlugins ) {
	await rm( path.join( muPlugins, 'global-fonts/NotoSerif' ), {
		recursive: true,
		force: true,
	} );
	const stylesheet = path.join( muPlugins, 'global-fonts/style.css' );
	const css = await readFile( stylesheet, 'utf8' );
	await writeFile(
		stylesheet,
		css
			.split( '\n' )
			.filter( ( line ) => ! line.includes( '@import "./NotoSerif/' ) )
			.join( '\n' )
	);
}

/**
 * @param {Record<string, any>} inputs
 * @param {string} tools
 * @param {(...args: any[]) => any} runImplementation
 * @param {Record<string, string>} [digests]
 */
export async function ensureComposer(
	inputs,
	tools,
	runImplementation,
	digests = COMPOSER_DIGESTS
) {
	const version = inputs.dependencies.toolchain.composerVersion;
	const digest = digests[ version ];
	if ( ! digest ) {
		throw new Error(
			`Composer ${ version } has no pinned SHA-256 digest.`
		);
	}
	const composer = path.join( tools, `composer-${ version }.phar` );
	if (
		( await exists( composer ) ) &&
		( await sha256File( composer ) ) !== digest
	) {
		await rm( composer, { force: true } );
	}
	if ( ! ( await exists( composer ) ) ) {
		await downloadFile(
			`https://getcomposer.org/download/${ version }/composer.phar`,
			composer,
			runImplementation
		);
		const received = await sha256File( composer );
		if ( received !== digest ) {
			await rm( composer, { force: true } );
			throw new Error(
				`Composer ${ version } digest mismatch: expected ${ digest }, received ${ received }.`
			);
		}
	}
	return composer;
}

/**
 * @param {string} upstreams
 * @param {Record<string, any>} inputs
 */
function repositoryRoots( upstreams, inputs ) {
	const root = /** @param {string} name */ ( name ) =>
		path.join( upstreams, name );
	const selected = /** @param {string} name */ ( name ) =>
		path.join(
			root( name ),
			inputs.dependencies.repositories[ name ].path
		);
	return {
		phpdocParser: selected( 'phpdocParser' ),
		wporgDeveloperTheme: selected( 'wporgDeveloper' ),
		wporgParent2021: root( 'wporgParent2021' ),
		wporgParentTheme: selected( 'wporgParent2021' ),
		wporgMuPlugins: root( 'wporgMuPlugins' ),
		wporgMuPluginsFiles: selected( 'wporgMuPlugins' ),
		postsToPosts: selected( 'postsToPosts' ),
		codeSyntaxBlock: selected( 'codeSyntaxBlock' ),
	};
}

/**
 * @param {Record<string, any>} inputs
 * @param {Record<string, any>} options
 */
async function buildInvariantBase( inputs, options ) {
	const runImplementation = options.runImplementation || run;
	const work = path.join( inputs.cacheDirectory, 'work' );
	const upstreams = path.join( work, 'upstreams' );
	const bundles = path.join( work, 'bundles' );
	const tools = path.join( work, 'tools' );
	await mkdir( bundles, { recursive: true } );
	await acquireRepositories(
		inputs.dependencies.repositories,
		upstreams,
		runImplementation
	);
	const roots = repositoryRoots( upstreams, inputs );
	const composer = await ensureComposer( inputs, tools, runImplementation );
	for ( const task of dependencyBuildPlan( roots, composer ) ) {
		await runImplementation( task.command, task.args, task );
	}
	await prunePreviewFonts( roots.wporgMuPluginsFiles );

	for ( const [ name, source, rootName ] of [
		[ 'wporg-parent-2021', roots.wporgParentTheme, 'wporg-parent-2021' ],
		[
			'wporg-developer-2023',
			roots.wporgDeveloperTheme,
			'wporg-developer-2023',
		],
		[ 'wporg-mu-plugins', roots.wporgMuPluginsFiles, '.' ],
		[ 'posts-to-posts', roots.postsToPosts, 'posts-to-posts' ],
		[ 'phpdoc-parser', roots.phpdocParser, 'phpdoc-parser' ],
		[ 'code-syntax-block', roots.codeSyntaxBlock, 'code-syntax-block' ],
	] ) {
		await zipDirectory(
			source,
			path.join( bundles, `${ name }.zip` ),
			rootName,
			runImplementation
		);
	}

	await mkdir( path.join( work, 'php' ), { recursive: true } );
	for ( const script of [ 'base-policy.php', 'configure-base.php' ] ) {
		await cp(
			path.join( PHP_ROOT, script ),
			path.join( work, 'php', script )
		);
	}
	const blueprint = path.join( work, 'base-blueprint.json' );
	await writeFile(
		blueprint,
		`${ JSON.stringify(
			createInvariantBaseBlueprint( inputs ),
			null,
			2
		) }\n`
	);
	await ( options.buildSnapshotImplementation || buildSnapshot )(
		options.playgroundCli || executable( 'wp-playground-cli' ),
		{
			php: inputs.dependencies.playground.phpVersion,
			wp: inputs.wordpress.version,
			blueprint,
			'blueprint-may-read-adjacent-files': true,
			outfile: path.join( inputs.cacheDirectory, 'base.zip' ),
			verbosity: 'normal',
		}
	);
	await copyDirectory(
		roots.phpdocParser,
		path.join( inputs.cacheDirectory, 'parser' ),
		[ '.git' ]
	);
	await rm( work, { recursive: true, force: true } );
}

/**
 * @param {Record<string, any>} inputs
 * @param {Record<string, any>} [options]
 */
export async function ensureInvariantBase( inputs, options = {} ) {
	const markerFile = path.join( inputs.cacheDirectory, 'base.json' );
	if ( await exists( markerFile ) ) {
		const marker = JSON.parse( await readFile( markerFile, 'utf8' ) );
		if (
			marker.cacheKey === inputs.cacheKey &&
			( await exists(
				path.join( inputs.cacheDirectory, 'base.zip' )
			) ) &&
			( await exists(
				path.join(
					inputs.cacheDirectory,
					'parser/generate-json-manually.php'
				)
			) )
		) {
			return { ...marker, cacheHit: true };
		}
	}
	await rm( inputs.cacheDirectory, { recursive: true, force: true } );
	await mkdir( inputs.cacheDirectory, { recursive: true } );
	await ( options.buildImplementation || buildInvariantBase )(
		inputs,
		options
	);
	const marker = {
		schemaVersion: 1,
		cacheKey: inputs.cacheKey,
		wordpress: inputs.wordpress,
		dependencyDigest: inputs.cacheInputs.dependencyDigest,
		harnessDigest: inputs.cacheInputs.harnessDigest,
	};
	await writeFile( markerFile, `${ JSON.stringify( marker, null, 2 ) }\n` );
	return { ...marker, cacheHit: false };
}
