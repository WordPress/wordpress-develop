#!/usr/bin/env node

/**
 * Copy Gutenberg Build Script
 *
 * This script copies and transforms Gutenberg's build output to WordPress Core.
 * It handles path transformations from plugin structure to Core structure.
 *
 * Since a number of files sourced from the downloaded zip file are subject to
 * version control, the `src/` directory is used as the destination for all
 * outputs of this file (both versioned and unversioned).
 *
 * Grunt will copy the files appropriately when running `build` instead of
 * `build:dev`, and the repository's configured ignore rules will manage what
 * can be committed.
 *
 * @package WordPress
 */

const fs = require( 'fs' );
const path = require( 'path' );
const json2php = /** @type {typeof import('json2php').default} */ (
	/** @type {unknown} */ ( require( 'json2php' ) )
);
const { fromString } = require( 'php-array-reader' );

const rootDir = path.resolve( __dirname, '../..' );
const gutenbergDir = path.join( rootDir, 'gutenberg' );
const gutenbergBuildDir = path.join( gutenbergDir, 'build' );
const wpIncludesDir = path.join( rootDir, 'src', 'wp-includes' );

/**
 * JS package copy configuration.
 *
 * @typedef ScriptsConfig
 * @type {object}
 * @property {string}                 source           - Gutenberg-relative source directory (e.g. `'scripts'`).
 * @property {string}                 destination      - Subpath under `wp-includes/` where packages land (e.g. `'js/dist'`).
 * @property {boolean}                copyDirectories  - Whether to copy whole directories (with optional renames) as-is.
 * @property {Record<string, string>} directoryRenames - Map of source directory name → destination directory name.
 */

/**
 * One block family entry — block library, widget blocks, etc.
 *
 * @typedef BlockConfigSource
 * @type {object}
 * @property {string} name    - Human-readable label (e.g. `'block-library'`, `'widgets'`).
 * @property {string} scripts - Gutenberg-relative path to the block scripts directory.
 * @property {string} styles  - Gutenberg-relative path to the block styles directory.
 * @property {string} php     - Gutenberg-relative path to the block PHP directory.
 */

/**
 * Block copy configuration.
 *
 * @typedef BlockConfig
 * @type {object}
 * @property {string}              destination - Subpath under `wp-includes/` where blocks land (e.g. `'blocks'`).
 * @property {BlockConfigSource[]} sources     - One entry per block family.
 */

/**
 * Copy configuration.
 * Defines what to copy from Gutenberg build and where it goes in Core.
 */
const COPY_CONFIG = {
	// JavaScript packages (to wp-includes/js/dist/).
	scripts: {
		source: 'scripts',
		destination: 'js/dist',
		copyDirectories: true,
		// Rename vendors/ to vendor/ when copying.
		directoryRenames: {
			vendors: 'vendor',
		},
	},

	/*
	 * Blocks (to wp-includes/blocks/).
	 * Unified configuration for all block types.
	 */
	blocks: {
		destination: 'blocks',
		sources: [
			{
				// Block library blocks.
				name: 'block-library',
				scripts: 'scripts/block-library',
				styles: 'styles/block-library',
				php: 'scripts/block-library',
			},
			{
				// Widget blocks.
				name: 'widgets',
				scripts: 'scripts/widgets/blocks',
				styles: 'styles/widgets',
				php: 'scripts/widgets/blocks',
			},
		],
	},
};

/**
 * Given a path to a PHP file which returns a single value, converts that
 * value into a native JavaScript value (limited by JSON serialization).
 *
 * @throws Error when PHP source file unable to be read or parsed.
 *
 * @param {string} phpFilepath Absolute path of PHP file returning a single value.
 * @return {any} JavaScript representation of value from input file.
 */
function readReturnedValueFromPHPFile( phpFilepath ) {
	const content = fs.readFileSync( phpFilepath, 'utf8' );
	return fromString( content );
}

/**
 * Check if a block is experimental by reading its block.json.
 *
 * @param {string} blockJsonPath - Path to block.json file.
 * @return {boolean} True if block is experimental.
 */
function isExperimentalBlock( blockJsonPath ) {
	try {
		if ( ! fs.existsSync( blockJsonPath ) ) {
			return false;
		}
		const blockJson = JSON.parse(
			fs.readFileSync( blockJsonPath, 'utf8' )
		);
		return !! blockJson.__experimental;
	} catch ( error ) {
		return false;
	}
}

/**
 * Generate a list of stable blocks.
 *
 * Blocks marked as `"__experimental": true` in a `block.json` file are excluded.
 *
 * @param {string} scriptsSrc - Path to the Gutenberg scripts source (e.g. `scripts/block-library`).
 * @return {string[]} Stable block directory names.
 */
function getStableBlocks( scriptsSrc ) {
	if ( ! fs.existsSync( scriptsSrc ) ) {
		return [];
	}
	return fs
		.readdirSync( scriptsSrc, { withFileTypes: true } )
		.filter( ( entry ) => entry.isDirectory() )
		.map( ( entry ) => entry.name )
		.filter( ( blockName ) => ! isExperimentalBlock(
			path.join( scriptsSrc, blockName, 'block.json' )
		) );
}

/**
 * Copy JavaScript files.
 *
 * @param {ScriptsConfig} config - Scripts configuration from `COPY_CONFIG.scripts`.
 */
function copyScripts( config ) {
	const scriptsSrc = path.join( gutenbergBuildDir, config.source );
	const scriptsDest = path.join( wpIncludesDir, config.destination );

	if ( ! fs.existsSync( scriptsSrc ) ) {
		return;
	}

	const entries = fs.readdirSync( scriptsSrc, { withFileTypes: true } );

	for ( const entry of entries ) {
		const src = path.join( scriptsSrc, entry.name );

		if ( entry.isDirectory() ) {
			// Check if this should be copied as a directory (like vendors/).
			if (
				config.copyDirectories &&
				config.directoryRenames &&
				config.directoryRenames[ entry.name ]
			) {
				/*
				 * Copy special directories with rename (vendors/ → vendor/).
				 * Only copy react-jsx-runtime from vendors (react and react-dom come from Core's node_modules).
				 */
				const destName = config.directoryRenames[ entry.name ];
				const dest = path.join( scriptsDest, destName );

				if ( entry.name === 'vendors' ) {
					// Only copy react-jsx-runtime files, skip react and react-dom.
					const vendorFiles = fs.readdirSync( src );
					let copiedCount = 0;
					fs.mkdirSync( dest, { recursive: true } );
					for ( const file of vendorFiles ) {
						if (
							file.startsWith( 'react-jsx-runtime' ) &&
							file.endsWith( '.js' )
						) {
							const srcFile = path.join( src, file );
							const destFile = path.join( dest, file );

							fs.copyFileSync( srcFile, destFile );
							copiedCount++;
						}
					}
					console.log(
						`   ✅ ${ entry.name }/ → ${ destName }/ (react-jsx-runtime only, ${ copiedCount } files)`
					);
				}
			} else {
				/*
				 * Flatten package structure: package-name/index.js → package-name.js.
				 * This matches Core's expected file structure.
				 */
				const packageFiles = fs.readdirSync( src );

				for ( const file of packageFiles ) {
					if ( /^index\.(js|min\.js)$/.test( file ) ) {
						const srcFile = path.join( src, file );
						// Replace 'index.' with 'package-name.'.
						const destFile = file.replace(
							/^index\./,
							`${ entry.name }.`
						);
						const destPath = path.join( scriptsDest, destFile );

						fs.mkdirSync( path.dirname( destPath ), {
							recursive: true,
						} );

						fs.copyFileSync( srcFile, destPath );
					}
				}
			}
		} else if ( entry.isFile() && entry.name.endsWith( '.js' ) ) {
			// Copy root-level JS files.
			const dest = path.join( scriptsDest, entry.name );
			fs.mkdirSync( path.dirname( dest ), { recursive: true } );
			fs.copyFileSync( src, dest );
		}
	}

	console.log( '   ✅ JavaScript packages copied' );
}

/**
 * Copy `block.json` files for every stable block.
 *
 * @param {BlockConfig} config - Block configuration from `COPY_CONFIG.blocks`.
 */
function copyBlockJson( config ) {
	const blocksDest = path.join( wpIncludesDir, config.destination );

	for ( const source of config.sources ) {
		const scriptsSrc = path.join( gutenbergBuildDir, source.scripts );
		const blocks = getStableBlocks( scriptsSrc );

		for ( const blockName of blocks ) {
			const blockSrc = path.join( scriptsSrc, blockName );
			const blockDest = path.join( blocksDest, blockName );
			fs.mkdirSync( blockDest, { recursive: true } );

			const blockJsonSrc = path.join( blockSrc, 'block.json' );
			if ( fs.existsSync( blockJsonSrc ) ) {
				fs.copyFileSync(
					blockJsonSrc,
					path.join( blockDest, 'block.json' )
				);
			}
		}

		console.log(
			`   ✅ ${ source.name } block.json copied (${ blocks.length } blocks)`
		);
	}
}

/**
 * Copy block PHP files for every stable block.
 *
 * Handles both the top-level `<block>.php` dynamic block files and any nested
 * `*.php` helpers under `<block>/` (e.g. `navigation-link/shared/render-submenu-icon.php`).
 *
 * @param {BlockConfig} config - Block configuration from `COPY_CONFIG.blocks`.
 */
function copyBlockPhp( config ) {
	const blocksDest = path.join( wpIncludesDir, config.destination );

	for ( const source of config.sources ) {
		const scriptsSrc = path.join( gutenbergBuildDir, source.scripts );
		const phpSrc = path.join( gutenbergBuildDir, source.php );
		const blocks = getStableBlocks( scriptsSrc );

		for ( const blockName of blocks ) {
			// Top-level <block>.php (dynamic block file).
			const topLevelPhpSrc = path.join( phpSrc, `${ blockName }.php` );
			const topLevelPhpDest = path.join( blocksDest, `${ blockName }.php` );
			if ( fs.existsSync( topLevelPhpSrc ) ) {
				fs.mkdirSync( blocksDest, { recursive: true } );
				fs.copyFileSync( topLevelPhpSrc, topLevelPhpDest );
			}

			// Nested PHP helpers under <block>/, excluding the block's own index.php.
			const blockPhpDir = path.join( phpSrc, blockName );
			if ( fs.existsSync( blockPhpDir ) ) {
				const blockDest = path.join( blocksDest, blockName );
				const rootIndex = path.join( blockPhpDir, 'index.php' );

				/**
				 * @param {string} src
				 * @return {boolean}
				 */
				function hasPhpFiles( src ) {
					const stat = fs.statSync( src );
					if ( stat.isDirectory() ) {
						return fs.readdirSync( src, { withFileTypes: true } ).some(
							( entry ) => hasPhpFiles( path.join( src, entry.name ) )
						);
					}
					return src.endsWith( '.php' ) && src !== rootIndex;
				}

				fs.cpSync( blockPhpDir, blockDest, {
					recursive: true,
					filter: hasPhpFiles,
				} );
			}
		}

		console.log(
			`   ✅ ${ source.name } block PHP copied (${ blocks.length } blocks)`
		);
	}
}

/**
 * Copy per-block CSS files for every stable block.
 *
 * @param {BlockConfig} config - Block configuration from `COPY_CONFIG.blocks`.
 */
function copyBlockStyles( config ) {
	const blocksDest = path.join( wpIncludesDir, config.destination );

	for ( const source of config.sources ) {
		const scriptsSrc = path.join( gutenbergBuildDir, source.scripts );
		const stylesSrc = path.join( gutenbergBuildDir, source.styles );
		const blocks = getStableBlocks( scriptsSrc );

		let stylesCopied = 0;
		for ( const blockName of blocks ) {
			const blockStylesSrc = path.join( stylesSrc, blockName );
			if ( ! fs.existsSync( blockStylesSrc ) ) {
				continue;
			}

			const blockDest = path.join( blocksDest, blockName );
			fs.mkdirSync( blockDest, { recursive: true } );

			const cssFiles = fs
				.readdirSync( blockStylesSrc )
				.filter( ( file ) => file.endsWith( '.css' ) );
			for ( const cssFile of cssFiles ) {
				fs.copyFileSync(
					path.join( blockStylesSrc, cssFile ),
					path.join( blockDest, cssFile )
				);
			}
			if ( cssFiles.length > 0 ) {
				stylesCopied++;
			}
		}

		console.log(
			`   ✅ ${ source.name } block CSS copied (${ stylesCopied } blocks)`
		);
	}
}

/**
 * Generate script-modules-packages.php from individual asset files.
 * Recursively scans the Gutenberg modules/ directory for *.min.asset.php files
 * and combines their contents into a single PHP file.
 */
function generateScriptModulesPackages() {
	const modulesDir = path.join( gutenbergBuildDir, 'modules' );
	/** @type {Record<string, any>} */
	const assets = {};

	/**
	 * Recursively process directory to find .asset.php files.
	 *
	 * @param {string} dir - Directory to process.
	 * @param {string} baseDir - Base directory for relative paths.
	 */
	function processDirectory( dir, baseDir ) {
		if ( ! fs.existsSync( dir ) ) {
			return;
		}

		const entries = fs.readdirSync( dir, { withFileTypes: true } );

		for ( const entry of entries ) {
			const fullPath = path.join( dir, entry.name );

			if ( entry.isDirectory() ) {
				processDirectory( fullPath, baseDir );
			} else if ( entry.name.endsWith( '.min.asset.php' ) ) {
				const relativePath = path.relative( baseDir, fullPath );
				// Normalize path separators to forward slashes for cross-platform consistency.
				const normalizedPath = relativePath
					.split( path.sep )
					.join( '/' );
				const jsPath = normalizedPath
					.replace( /\.asset\.php$/, '.js' )
					.replace( /\.min\.js$/, '.js' );

				try {
					const assetData = readReturnedValueFromPHPFile( fullPath );
					assets[ jsPath ] = assetData;
				} catch ( error ) {
					console.error(
						`   ⚠️  Error reading ${ relativePath }:`,
						error instanceof Error ? error.message : String( error )
					);
				}
			}
		}
	}

	processDirectory( modulesDir, modulesDir );

	const phpContent =
		'<?php return ' +
		json2php.make( {
			linebreak: '\n',
			indent: '\t',
			shortArraySyntax: false,
		} )( assets ) +
		';';

	const outputPath = path.join(
		wpIncludesDir,
		'assets/script-modules-packages.php'
	);

	fs.mkdirSync( path.dirname( outputPath ), { recursive: true } );
	fs.writeFileSync( outputPath, phpContent );

	console.log(
		`   ✅ Generated with ${ Object.keys( assets ).length } modules`
	);
}

/**
 * Generate script-loader-packages.php from individual asset files.
 * Reads all .min.asset.php files from scripts/ and combines them into a PHP file for script registration.
 */
function generateScriptLoaderPackages() {
	const scriptsDir = path.join( gutenbergBuildDir, 'scripts' );
	/** @type {Record<string, any>} */
	const assets = {};

	if ( ! fs.existsSync( scriptsDir ) ) {
		console.log( '   ⚠️  Scripts directory not found' );
		return;
	}

	const entries = fs.readdirSync( scriptsDir, { withFileTypes: true } );

	for ( const entry of entries ) {
		if ( ! entry.isDirectory() ) {
			continue;
		}

		const assetFile = path.join(
			scriptsDir,
			entry.name,
			'index.min.asset.php'
		);
		if ( ! fs.existsSync( assetFile ) ) {
			continue;
		}

		try {
			const assetData = readReturnedValueFromPHPFile( assetFile );

			// For regular scripts, use dependencies as-is.
			if ( ! assetData.dependencies ) {
				assetData.dependencies = [];
			}

			assets[ `${ entry.name }.js` ] = assetData;
		} catch ( error ) {
			console.error(
				`   ⚠️  Error reading ${ entry.name }/index.min.asset.php:`,
				error instanceof Error ? error.message : String( error )
			);
		}
	}

	const phpContent =
		'<?php return ' +
		json2php.make( {
			linebreak: '\n',
			indent: '\t',
			shortArraySyntax: false,
		} )( assets ) +
		';';

	const outputPath = path.join(
		wpIncludesDir,
		'assets/script-loader-packages.php'
	);

	fs.mkdirSync( path.dirname( outputPath ), { recursive: true } );
	fs.writeFileSync( outputPath, phpContent );

	console.log(
		`   ✅ Generated with ${ Object.keys( assets ).length } packages`
	);
}

/**
 * Generate `require-*-blocks.php` files.
 *
 * Reads all `block.json` files from the block-library (widgets are ignored) and
 * creates `require-dynamic-blocks.php` and `require-static-blocks.php` files.
 */
function generateBlockRegistrationFiles() {
	const blocksDir = path.join( wpIncludesDir, 'blocks' );
	const dynamicBlocks = [];
	const staticBlocks = [];

	// Widget blocks to exclude (from @wordpress/widgets package).
	const widgetBlocks = [ 'legacy-widget', 'widget-group' ];

	if ( ! fs.existsSync( blocksDir ) ) {
		console.error( '   ⚠️  Blocks directory not found' );
		return;
	}

	const entries = fs.readdirSync( blocksDir, { withFileTypes: true } );

	for ( const entry of entries ) {
		if ( ! entry.isDirectory() ) {
			continue;
		}

		// Skip widget blocks.
		if ( widgetBlocks.includes( entry.name ) ) {
			continue;
		}

		const blockDir = path.join( blocksDir, entry.name );
		const blockJsonPath = path.join( blockDir, 'block.json' );
		const phpFilePath = path.join( blocksDir, `${ entry.name }.php` );

		// Skip if block.json doesn't exist.
		if ( ! fs.existsSync( blockJsonPath ) ) {
			continue;
		}

		// Check if it's experimental.
		if ( isExperimentalBlock( blockJsonPath ) ) {
			continue;
		}

		// Determine if it's dynamic (has a PHP file).
		if ( fs.existsSync( phpFilePath ) ) {
			dynamicBlocks.push( entry.name );
		} else {
			staticBlocks.push( entry.name );
		}
	}

	// Sort alphabetically.
	dynamicBlocks.sort();
	staticBlocks.sort();

	// Generate require-dynamic-blocks.php.
	const dynamicContent = `<?php

// This file was autogenerated by tools/gutenberg/copy.js, do not change manually!
// Requires files for dynamic blocks necessary for core blocks registration.
${ dynamicBlocks
	.map(
		( name ) => `require_once ABSPATH . WPINC . '/blocks/${ name }.php';`
	)
	.join( '\n' ) }
`;

	fs.writeFileSync(
		path.join( wpIncludesDir, 'blocks/require-dynamic-blocks.php' ),
		dynamicContent
	);

	// Generate require-static-blocks.php.
	const staticContent = `<?php

// This file was autogenerated by tools/gutenberg/copy.js, do not change manually!
// Returns folder names for static blocks necessary for core blocks registration.
return array(
${ staticBlocks.map( ( name ) => `\t'${ name }',` ).join( '\n' ) }
);
`;

	fs.writeFileSync(
		path.join( wpIncludesDir, 'blocks/require-static-blocks.php' ),
		staticContent
	);

	console.log(
		`   ✅ Generated: ${ dynamicBlocks.length } dynamic, ${ staticBlocks.length } static blocks`
	);
}

/**
 * Generate a `blocks-json.php` file.
 *
 * Reads all `block.json` files and combines them into a single PHP array.
 *
 * This must run after `copyBlockJson` has populated `wp-includes/blocks/`.
 */
function generateBlocksJson() {
	const blocksDir = path.join( wpIncludesDir, 'blocks' );
	/** @type {Record<string, any>} */
	const blocks = {};

	if ( ! fs.existsSync( blocksDir ) ) {
		console.error( '   ⚠️  Blocks directory not found' );
		return;
	}

	const entries = fs.readdirSync( blocksDir, { withFileTypes: true } );

	for ( const entry of entries ) {
		if ( ! entry.isDirectory() ) {
			continue;
		}

		const blockJsonPath = path.join( blocksDir, entry.name, 'block.json' );

		if ( fs.existsSync( blockJsonPath ) ) {
			try {
				const blockJson = JSON.parse(
					fs.readFileSync( blockJsonPath, 'utf8' )
				);
				blocks[ entry.name ] = blockJson;
			} catch ( error ) {
				console.error(
					`   ⚠️  Error reading ${ entry.name }/block.json:`,
					error instanceof Error ? error.message : String( error )
				);
			}
		}
	}

	// Generate the PHP file content using json2php for consistent formatting.
	const phpContent =
		'<?php return ' +
		json2php.make( {
			linebreak: '\n',
			indent: '\t',
			shortArraySyntax: false,
		} )( blocks ) +
		';';

	fs.writeFileSync(
		path.join( wpIncludesDir, 'blocks/blocks-json.php' ),
		phpContent
	);

	console.log(
		`   ✅ Generated with ${ Object.keys( blocks ).length } blocks`
	);
}

/**
 * Main execution function.
 */
async function main() {
	console.log( '📦 Copying Gutenberg build to src/...' );

	if ( ! fs.existsSync( gutenbergBuildDir ) ) {
		console.error( '❌ Gutenberg build directory not found' );
		console.error( '   Run: npm run grunt gutenberg:download' );
		process.exit( 1 );
	}

	// 1. Copy JavaScript packages.
	console.log( '\n📦 Copying JavaScript packages...' );
	copyScripts( COPY_CONFIG.scripts );

	console.log( '\n📦 Copying block.json files...' );
	copyBlockJson( COPY_CONFIG.blocks );

	console.log( '\n📦 Copying block PHP files...' );
	copyBlockPhp( COPY_CONFIG.blocks );

	console.log( '\n📦 Copying block CSS files...' );
	copyBlockStyles( COPY_CONFIG.blocks );

	// 3. Generate script-modules-packages.php.
	console.log( '\n📦 Generating script-modules-packages.php...' );
	generateScriptModulesPackages();

	// 4. Generate script-loader-packages.php.
	console.log( '\n📦 Generating script-loader-packages.php...' );
	generateScriptLoaderPackages();

	// 5. Generate require-dynamic-blocks.php and require-static-blocks.php.
	console.log( '\n📦 Generating block registration files...' );
	generateBlockRegistrationFiles();

	// 6. Generate blocks-json.php from block.json files.
	console.log( '\n📦 Generating blocks-json.php...' );
	generateBlocksJson();

	console.log( '\n✅ Copy complete!' );
}

// Run main function.
main().catch( ( error ) => {
	console.error( '❌ Unexpected error:', error );
	process.exit( 1 );
} );
