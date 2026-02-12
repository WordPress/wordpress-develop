#!/usr/bin/env node

/**
 * Copy Gutenberg Build Script
 *
 * This script copies and transforms Gutenberg's build output to WordPress Core.
 * It handles path transformations from plugin structure to Core structure.
 *
 * @package WordPress
 */

const fs = require( 'fs' );
const path = require( 'path' );
const json2php = require( 'json2php' );

// Paths
const rootDir = path.resolve( __dirname, '../..' );
const gutenbergDir = path.join( rootDir, 'gutenberg' );
const gutenbergBuildDir = path.join( gutenbergDir, 'build' );
const gutenbergPackagesDir = path.join( gutenbergDir, 'packages' );

// Determine build target from command line argument (--dev or --build-dir)
// Default to 'src' for development
const args = process.argv.slice( 2 );
const buildDirArg = args.find( ( arg ) => arg.startsWith( '--build-dir=' ) );
const buildTarget = buildDirArg
	? buildDirArg.split( '=' )[ 1 ]
	: args.includes( '--dev' )
	? 'src'
	: 'build';

const wpIncludesDir = path.join( rootDir, buildTarget, 'wp-includes' );

/**
 * Copy configuration.
 * Defines what to copy from Gutenberg build and where it goes in Core.
 */
const COPY_CONFIG = {
	// PHP infrastructure files (to wp-includes/build/)
	phpInfrastructure: {
		destination: 'build',
		files: [ 'routes.php', 'pages.php' ],
		directories: [ 'pages', 'routes' ],
	},

	// JavaScript packages (to wp-includes/js/dist/)
	scripts: {
		source: 'scripts',
		destination: 'js/dist',
		copyDirectories: true, // Copy subdirectories
		patterns: [ '*.js' ],
		// Rename vendors/ to vendor/ when copying
		directoryRenames: {
			vendors: 'vendor',
		},
	},

	// Script modules (to wp-includes/js/dist/script-modules/)
	modules: {
		source: 'modules',
		destination: 'js/dist/script-modules',
		copyAll: true,
	},

	// Styles (to wp-includes/css/dist/)
	styles: {
		source: 'styles',
		destination: 'css/dist',
		copyAll: true,
	},

	// Blocks (to wp-includes/blocks/)
	// Unified configuration for all block types
	blocks: {
		destination: 'blocks',
		sources: [
			{
				// Block library blocks
				name: 'block-library',
				scripts: 'scripts/block-library',
				styles: 'styles/block-library',
				php: 'block-library/src',
			},
			{
				// Widget blocks
				name: 'widgets',
				scripts: 'scripts/widgets/blocks',
				styles: 'styles/widgets',
				php: 'widgets/src/blocks',
			},
		],
	},

	// Theme JSON files (from Gutenberg lib directory)
	themeJson: {
		files: [
			{ from: 'theme.json', to: 'theme.json' },
			{ from: 'theme-i18n.json', to: 'theme-i18n.json' },
		],
	},
};

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
 * Recursively copy directory.
 *
 * @param {string}   src        - Source directory.
 * @param {string}   dest       - Destination directory.
 * @param {Function} transform  - Optional transform function for file contents.
 * @param {Object}   options    - Optional configuration.
 * @param {boolean}  options.excludePHP - Skip PHP files.
 * @param {boolean}  options.excludeExperimental - Skip experimental blocks.
 */
function copyDirectory( src, dest, transform = null, options = {} ) {
	if ( ! fs.existsSync( src ) ) {
		return;
	}

	fs.mkdirSync( dest, { recursive: true } );

	const entries = fs.readdirSync( src, { withFileTypes: true } );

	for ( const entry of entries ) {
		const srcPath = path.join( src, entry.name );
		const destPath = path.join( dest, entry.name );

		if ( entry.isDirectory() ) {
			// Check if this directory is an experimental block
			if ( options.excludeExperimental ) {
				const blockJsonPath = path.join( srcPath, 'block.json' );
				if ( isExperimentalBlock( blockJsonPath ) ) {
					continue;
				}
			}

			copyDirectory( srcPath, destPath, transform, options );
		} else {
			// Skip PHP files if excludePHP is true
			if ( options.excludePHP && /\.php$/.test( entry.name ) ) {
				continue;
			}

			let content = fs.readFileSync( srcPath );

			// Apply transformation if provided and file is text
			if ( transform && /\.(php|js|css)$/.test( entry.name ) ) {
				try {
					content = transform(
						content.toString(),
						srcPath,
						destPath
					);
				} catch ( error ) {
					console.error(
						`   ⚠️  Transform error in ${ entry.name }:`,
						error.message
					);
				}
			}

			fs.writeFileSync( destPath, content );
		}
	}
}

/**
 * Copy all assets for blocks from Gutenberg to Core.
 * Handles scripts, styles, PHP, and JSON for all block types in a unified way.
 *
 * @param {Object} config - Block configuration from COPY_CONFIG.blocks
 */
function copyBlockAssets( config ) {
	const blocksDest = path.join( wpIncludesDir, config.destination );

	for ( const source of config.sources ) {
		const scriptsSrc = path.join( gutenbergBuildDir, source.scripts );
		const stylesSrc = path.join( gutenbergBuildDir, source.styles );
		const phpSrc = path.join( gutenbergPackagesDir, source.php );

		if ( ! fs.existsSync( scriptsSrc ) ) {
			continue;
		}

		// Get all block directories from the scripts source
		const blockDirs = fs
			.readdirSync( scriptsSrc, { withFileTypes: true } )
			.filter( ( entry ) => entry.isDirectory() )
			.map( ( entry ) => entry.name );

		for ( const blockName of blockDirs ) {
			// Skip experimental blocks
			const blockJsonPath = path.join(
				scriptsSrc,
				blockName,
				'block.json'
			);
			if ( isExperimentalBlock( blockJsonPath ) ) {
				continue;
			}

			const blockDest = path.join( blocksDest, blockName );
			fs.mkdirSync( blockDest, { recursive: true } );

			// 1. Copy scripts/JSON (everything except PHP)
			const blockScriptsSrc = path.join( scriptsSrc, blockName );
			if ( fs.existsSync( blockScriptsSrc ) ) {
				fs.cpSync(
					blockScriptsSrc,
					blockDest,
					{
						recursive: true,
						// Skip PHP, copied from packages
						filter: f => ! f.endsWith( '.php' ),
					}
				);
			}

			// 2. Copy styles (if they exist in per-block directory)
			const blockStylesSrc = path.join( stylesSrc, blockName );
			if ( fs.existsSync( blockStylesSrc ) ) {
				const cssFiles = fs
					.readdirSync( blockStylesSrc )
					.filter( ( file ) => file.endsWith( '.css' ) );
				for ( const cssFile of cssFiles ) {
					fs.copyFileSync(
						path.join( blockStylesSrc, cssFile ),
						path.join( blockDest, cssFile )
					);
				}
			}

			// 3. Copy PHP from packages
			const blockPhpSrc = path.join( phpSrc, blockName, 'index.php' );
			if ( fs.existsSync( blockPhpSrc ) ) {
				const phpDest = path.join(
					wpIncludesDir,
					config.destination,
					`${ blockName }.php`
				);
				const content = fs.readFileSync( blockPhpSrc, 'utf8' );
				fs.writeFileSync( phpDest, content );
			}

			// 4. Copy PHP subdirectories from packages (e.g., shared/helpers.php)
			const blockPhpDir = path.join( phpSrc, blockName );
			if ( fs.existsSync( blockPhpDir ) ) {
				const rootIndex = path.join( blockPhpDir, 'index.php' );
				fs.cpSync( blockPhpDir, blockDest, {
					recursive: true,
					filter: function hasPhpFiles( src ) {
						const stat = fs.statSync( src );
						if ( stat.isDirectory() ) {
							return fs.readdirSync( src, { withFileTypes: true } ).some(
								( entry ) => hasPhpFiles( path.join( src, entry.name ) )
							);
						}
						// Copy PHP files, but skip root index.php (handled by step 3)
						return src.endsWith( '.php' ) && src !== rootIndex;
					},
				} );
			}
		}

		console.log(
			`   ✅ ${ source.name } blocks copied (${ blockDirs.length } blocks)`
		);
	}
}

/**
 * Generate script-modules-packages.min.php from individual asset files.
 * Reads all view.min.asset.php files from modules/block-library and combines them
 * into a single PHP file.
 */
function generateScriptModulesPackages() {
	const modulesDir = path.join( gutenbergBuildDir, 'modules' );
	const assetsMin = {};
	const assetsRegular = {};

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
				// Normalize path separators to forward slashes for cross-platform consistency
				const normalizedPath = relativePath
					.split( path.sep )
					.join( '/' );
				const jsPathMin = normalizedPath.replace(
					/\.asset\.php$/,
					'.js'
				);
				const jsPathRegular = jsPathMin.replace( /\.min\.js$/, '.js' );

				try {
					// Read and parse the PHP asset file
					const phpContent = fs.readFileSync( fullPath, 'utf8' );
					// Extract the array from PHP: <?php return array(...);
					const match = phpContent.match(
						/return\s+array\(([\s\S]*?)\);/
					);
					if ( match ) {
						// Parse PHP array to JavaScript object
						const assetData = parsePHPArray( match[ 1 ] );
						assetsMin[ jsPathMin ] = assetData;
						assetsRegular[ jsPathRegular ] = assetData;
					}
				} catch ( error ) {
					console.error(
						`   ⚠️  Error reading ${ relativePath }:`,
						error.message
					);
				}
			}
		}
	}

	processDirectory( modulesDir, modulesDir );

	// Generate both minified and non-minified PHP files using json2php
	const phpContentMin =
		'<?php return ' +
		json2php.make( {
			linebreak: '\n',
			indent: '  ',
			shortArraySyntax: false,
		} )( assetsMin ) +
		';';

	const phpContentRegular =
		'<?php return ' +
		json2php.make( {
			linebreak: '\n',
			indent: '  ',
			shortArraySyntax: false,
		} )( assetsRegular ) +
		';';

	const outputPathMin = path.join(
		wpIncludesDir,
		'assets/script-modules-packages.min.php'
	);
	const outputPathRegular = path.join(
		wpIncludesDir,
		'assets/script-modules-packages.php'
	);

	fs.mkdirSync( path.dirname( outputPathMin ), { recursive: true } );
	fs.writeFileSync( outputPathMin, phpContentMin );
	fs.writeFileSync( outputPathRegular, phpContentRegular );

	console.log(
		`   ✅ Generated with ${ Object.keys( assetsMin ).length } modules`
	);
}

/**
 * Generate script-loader-packages.php and script-loader-packages.min.php from individual asset files.
 * Reads all .min.asset.php files from scripts/ and combines them into PHP files for script registration.
 * Generates both minified and non-minified versions.
 */
function generateScriptLoaderPackages() {
	const scriptsDir = path.join( gutenbergBuildDir, 'scripts' );
	const assetsMin = {};
	const assetsRegular = {};

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
			// Read and parse the PHP asset file
			const phpContent = fs.readFileSync( assetFile, 'utf8' );
			// Extract the array from PHP: <?php return array(...);
			const match = phpContent.match( /return\s+array\(([\s\S]*?)\);/ );
			if ( match ) {
				// Parse PHP array to JavaScript object
				const assetData = parsePHPArray( match[ 1 ] );

				// For regular scripts, use dependencies as-is
				// Keep dependencies array (don't use module_dependencies)
				if ( ! assetData.dependencies ) {
					assetData.dependencies = [];
				}

				// Remove module_dependencies if present (not used for regular scripts)
				delete assetData.module_dependencies;

				// Create entries for both minified and non-minified versions
				const jsPathMin = `${ entry.name }.min.js`;
				const jsPathRegular = `${ entry.name }.js`;

				assetsMin[ jsPathMin ] = assetData;
				assetsRegular[ jsPathRegular ] = assetData;
			}
		} catch ( error ) {
			console.error(
				`   ⚠️  Error reading ${ entry.name }/index.min.asset.php:`,
				error.message
			);
		}
	}

	// Generate both minified and non-minified PHP files using json2php
	const phpContentMin =
		'<?php return ' +
		json2php.make( {
			linebreak: '\n',
			indent: '  ',
			shortArraySyntax: false,
		} )( assetsMin ) +
		';';

	const phpContentRegular =
		'<?php return ' +
		json2php.make( {
			linebreak: '\n',
			indent: '  ',
			shortArraySyntax: false,
		} )( assetsRegular ) +
		';';

	const outputPathMin = path.join(
		wpIncludesDir,
		'assets/script-loader-packages.min.php'
	);
	const outputPathRegular = path.join(
		wpIncludesDir,
		'assets/script-loader-packages.php'
	);

	fs.mkdirSync( path.dirname( outputPathMin ), { recursive: true } );
	fs.writeFileSync( outputPathMin, phpContentMin );
	fs.writeFileSync( outputPathRegular, phpContentRegular );

	console.log(
		`   ✅ Generated with ${ Object.keys( assetsMin ).length } packages`
	);
}

/**
 * Generate require-dynamic-blocks.php and require-static-blocks.php.
 * Reads all block.json files from wp-includes/blocks and categorizes them.
 * Only includes blocks from block-library, not widgets.
 */
function generateBlockRegistrationFiles() {
	const blocksDir = path.join( wpIncludesDir, 'blocks' );
	const dynamicBlocks = [];
	const staticBlocks = [];

	// Widget blocks to exclude (from @wordpress/widgets package)
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

		// Skip widget blocks
		if ( widgetBlocks.includes( entry.name ) ) {
			continue;
		}

		const blockDir = path.join( blocksDir, entry.name );
		const blockJsonPath = path.join( blockDir, 'block.json' );
		const phpFilePath = path.join( blocksDir, `${ entry.name }.php` );

		// Skip if block.json doesn't exist
		if ( ! fs.existsSync( blockJsonPath ) ) {
			continue;
		}

		// Check if it's experimental
		if ( isExperimentalBlock( blockJsonPath ) ) {
			continue;
		}

		// Determine if it's dynamic (has a PHP file)
		if ( fs.existsSync( phpFilePath ) ) {
			dynamicBlocks.push( entry.name );
		} else {
			staticBlocks.push( entry.name );
		}
	}

	// Sort alphabetically
	dynamicBlocks.sort();
	staticBlocks.sort();

	// Generate require-dynamic-blocks.php
	const dynamicContent = `<?php

// This file was autogenerated by tools/gutenberg/copy-gutenberg-build.js, do not change manually!
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

	// Generate require-static-blocks.php
	const staticContent = `<?php

// This file was autogenerated by tools/gutenberg/copy-gutenberg-build.js, do not change manually!
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
 * Generate blocks-json.php from all block.json files.
 * Reads all block.json files and combines them into a single PHP array.
 * Uses json2php to maintain consistency with Core's formatting.
 */
function generateBlocksJson() {
	const blocksDir = path.join( wpIncludesDir, 'blocks' );
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
					error.message
				);
			}
		}
	}

	// Generate the PHP file content using json2php for consistent formatting
	const phpContent =
		'<?php return ' +
		json2php.make( {
			linebreak: '\n',
			indent: '  ',
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
 * Parse PHP array syntax to JavaScript object.
 * Uses a simple but effective approach for the specific format in asset files.
 *
 * @param {string} phpArrayContent - PHP array content (without outer 'array(' and ')').
 * @return {Object|Array} Parsed JavaScript object or array.
 */
function parsePHPArray( phpArrayContent ) {
	phpArrayContent = phpArrayContent.trim();

	// First, extract all nested array() blocks and replace with placeholders
	const nestedArrays = [];
	let content = phpArrayContent;
	let depth = 0;
	let inString = false;
	let stringChar = '';
	let currentArray = '';
	let arrayStart = -1;

	for ( let i = 0; i < content.length; i++ ) {
		const char = content[ i ];

		// Track strings
		if (
			( char === "'" || char === '"' ) &&
			( i === 0 || content[ i - 1 ] !== '\\' )
		) {
			if ( ! inString ) {
				inString = true;
				stringChar = char;
			} else if ( char === stringChar ) {
				inString = false;
			}
		}

		if ( ! inString ) {
			// Look for array( keyword
			if ( content.substring( i, i + 6 ) === 'array(' ) {
				if ( depth === 0 ) {
					arrayStart = i;
					currentArray = '';
				}
				depth++;
				if ( depth > 1 ) {
					currentArray += 'array(';
				}
				i += 5; // Skip 'array('
				continue;
			}

			if ( depth > 0 ) {
				if ( char === '(' ) {
					depth++;
					currentArray += char;
				} else if ( char === ')' ) {
					depth--;
					if ( depth === 0 ) {
						// Found complete nested array
						const placeholder = `__ARRAY_${ nestedArrays.length }__`;
						nestedArrays.push( currentArray );
						content =
							content.substring( 0, arrayStart ) +
							placeholder +
							content.substring( i + 1 );
						i = arrayStart + placeholder.length - 1;
						currentArray = '';
					} else {
						currentArray += char;
					}
				} else {
					currentArray += char;
				}
			}
		} else if ( depth > 0 ) {
			currentArray += char;
		}
	}

	// Now parse the simplified content
	const result = {};
	const values = [];
	let isAssociative = false;

	// Split by top-level commas
	const parts = [];
	depth = 0;
	inString = false;
	let currentPart = '';

	for ( let i = 0; i < content.length; i++ ) {
		const char = content[ i ];

		if (
			( char === "'" || char === '"' ) &&
			( i === 0 || content[ i - 1 ] !== '\\' )
		) {
			inString = ! inString;
		}

		if ( ! inString && char === ',' && depth === 0 ) {
			parts.push( currentPart.trim() );
			currentPart = '';
		} else {
			currentPart += char;
			if ( ! inString ) {
				if ( char === '(' ) depth++;
				if ( char === ')' ) depth--;
			}
		}
	}
	if ( currentPart.trim() ) {
		parts.push( currentPart.trim() );
	}

	// Parse each part
	for ( const part of parts ) {
		const arrowMatch = part.match( /^(.+?)\s*=>\s*(.+)$/ );

		if ( arrowMatch ) {
			isAssociative = true;
			let key = arrowMatch[ 1 ].trim().replace( /^['"]|['"]$/g, '' );
			let value = arrowMatch[ 2 ].trim();

			// Replace placeholders
			while ( value.match( /__ARRAY_(\d+)__/ ) ) {
				value = value.replace( /__ARRAY_(\d+)__/, ( match, index ) => {
					return 'array(' + nestedArrays[ parseInt( index ) ] + ')';
				} );
			}

			result[ key ] = parseValue( value );
		} else {
			// No arrow, indexed array
			let value = part;

			// Replace placeholders
			while ( value.match( /__ARRAY_(\d+)__/ ) ) {
				value = value.replace( /__ARRAY_(\d+)__/, ( match, index ) => {
					return 'array(' + nestedArrays[ parseInt( index ) ] + ')';
				} );
			}

			values.push( parseValue( value ) );
		}
	}

	return isAssociative ? result : values;

	/**
	 * Parse a single value.
	 *
	 * @param {string} value - The value string to parse.
	 * @return {*} Parsed value.
	 */
	function parseValue( value ) {
		value = value.trim();

		if ( value.startsWith( 'array(' ) && value.endsWith( ')' ) ) {
			return parsePHPArray( value.substring( 6, value.length - 1 ) );
		} else if ( value.match( /^['"].*['"]$/ ) ) {
			return value.substring( 1, value.length - 1 );
		} else if ( value === 'true' ) {
			return true;
		} else if ( value === 'false' ) {
			return false;
		} else if ( ! isNaN( value ) && value !== '' ) {
			return parseInt( value, 10 );
		}
		return value;
	}
}

/**
 * Transform PHP file contents to work in Core.
 *
 * @param {string} content  - File content.
 * @return {string} Transformed content.
 */
function transformPHPContent( content ) {
	let transformed = content;

	// Fix boot module asset file path for Core's different directory structure
	// FROM: __DIR__ . '/../../modules/boot/index.min.asset.php'
	// TO:   ABSPATH . WPINC . '/js/dist/script-modules/boot/index.min.asset.php'
	// This is needed because Core copies modules to a different location than the plugin structure
	transformed = transformed.replace(
		/__DIR__\s*\.\s*['"]\/\.\.\/\.\.\/modules\/boot\/index\.min\.asset\.php['"]/g,
		"ABSPATH . WPINC . '/js/dist/script-modules/boot/index.min.asset.php'"
	);

	return transformed;
}

/**
 * Main execution function.
 */
async function main() {
	console.log( '🔍 Checking Gutenberg build...' );
	console.log( `   Build target: ${ buildTarget }/` );

	// Verify Gutenberg build exists
	if ( ! fs.existsSync( gutenbergBuildDir ) ) {
		console.error( '❌ Gutenberg build directory not found' );
		console.error( '   Run: node tools/gutenberg/build-gutenberg.js' );
		process.exit( 1 );
	}

	console.log( '✅ Gutenberg build found' );

	// 1. Copy PHP infrastructure
	console.log( '\n📦 Copying PHP infrastructure...' );
	const phpConfig = COPY_CONFIG.phpInfrastructure;
	const phpDest = path.join( wpIncludesDir, phpConfig.destination );

	// Copy PHP files
	for ( const file of phpConfig.files ) {
		const src = path.join( gutenbergBuildDir, file );
		const dest = path.join( phpDest, file );

		if ( fs.existsSync( src ) ) {
			fs.mkdirSync( path.dirname( dest ), { recursive: true } );
			let content = fs.readFileSync( src, 'utf8' );
			content = transformPHPContent( content );
			fs.writeFileSync( dest, content );
			console.log( `   ✅ ${ file }` );
		} else {
			console.log(
				`   ⚠️  ${ file } not found (may not exist in this Gutenberg version)`
			);
		}
	}

	// Copy PHP directories
	for ( const dir of phpConfig.directories ) {
		const src = path.join( gutenbergBuildDir, dir );
		const dest = path.join( phpDest, dir );

		if ( fs.existsSync( src ) ) {
			console.log( `   📁 Copying ${ dir }/...` );
			copyDirectory( src, dest, transformPHPContent );
			console.log( `   ✅ ${ dir }/ copied` );
		}
	}

	// 2. Copy JavaScript packages
	console.log( '\n📦 Copying JavaScript packages...' );
	const scriptsConfig = COPY_CONFIG.scripts;
	const scriptsSrc = path.join( gutenbergBuildDir, scriptsConfig.source );
	const scriptsDest = path.join( wpIncludesDir, scriptsConfig.destination );

	// Transform function to remove source map comments from all JS files
	const removeSourceMaps = ( content ) => {
		return content.replace( /\/\/# sourceMappingURL=.*$/m, '' ).trimEnd();
	};

	if ( fs.existsSync( scriptsSrc ) ) {
		const entries = fs.readdirSync( scriptsSrc, { withFileTypes: true } );

		for ( const entry of entries ) {
			const src = path.join( scriptsSrc, entry.name );

			if ( entry.isDirectory() ) {
				// Check if this should be copied as a directory (like vendors/)
				if (
					scriptsConfig.copyDirectories &&
					scriptsConfig.directoryRenames &&
					scriptsConfig.directoryRenames[ entry.name ]
				) {
					// Copy special directories with rename (vendors/ → vendor/)
					// Only copy react-jsx-runtime from vendors (react and react-dom come from Core's node_modules)
					const destName =
						scriptsConfig.directoryRenames[ entry.name ];
					const dest = path.join( scriptsDest, destName );

					if ( entry.name === 'vendors' ) {
						// Only copy react-jsx-runtime files, skip react and react-dom
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

								let content = fs.readFileSync(
									srcFile,
									'utf8'
								);
								content = removeSourceMaps( content );
								fs.writeFileSync( destFile, content );
								copiedCount++;
							}
						}
						console.log(
							`   ✅ ${ entry.name }/ → ${ destName }/ (react-jsx-runtime only, ${ copiedCount } files)`
						);
					} else {
						// Copy other special directories normally
						copyDirectory( src, dest, removeSourceMaps );
						console.log(
							`   ✅ ${ entry.name }/ → ${ destName }/`
						);
					}
				} else {
					// Flatten package structure: package-name/index.js → package-name.js
					// This matches Core's expected file structure
					const packageFiles = fs.readdirSync( src );

					for ( const file of packageFiles ) {
						if (
							/^index\.(js|min\.js|min\.asset\.php)$/.test( file )
						) {
							const srcFile = path.join( src, file );
							// Replace 'index.' with 'package-name.'
							const destFile = file.replace(
								/^index\./,
								`${ entry.name }.`
							);
							const destPath = path.join( scriptsDest, destFile );

							fs.mkdirSync( path.dirname( destPath ), {
								recursive: true,
							} );

							// Apply source map removal for .js files
							if ( file.endsWith( '.js' ) ) {
								let content = fs.readFileSync(
									srcFile,
									'utf8'
								);
								content = removeSourceMaps( content );
								fs.writeFileSync( destPath, content );
							} else {
								// Copy other files as-is (.min.asset.php)
								fs.copyFileSync( srcFile, destPath );
							}
						}
					}
				}
			} else if (
				entry.isFile() &&
				entry.name.endsWith( '.js' )
			) {
				// Copy root-level JS files
				const dest = path.join( scriptsDest, entry.name );
				fs.mkdirSync( path.dirname( dest ), { recursive: true } );

				let content = fs.readFileSync( src, 'utf8' );
				content = removeSourceMaps( content );
				fs.writeFileSync( dest, content );
			}
		}

		console.log( '   ✅ JavaScript packages copied' );
	}

	// 3. Copy script modules
	console.log( '\n📦 Copying script modules...' );
	const modulesConfig = COPY_CONFIG.modules;
	const modulesSrc = path.join( gutenbergBuildDir, modulesConfig.source );
	const modulesDest = path.join( wpIncludesDir, modulesConfig.destination );

	if ( fs.existsSync( modulesSrc ) ) {
		// Use the same source map removal transform
		copyDirectory( modulesSrc, modulesDest, removeSourceMaps );
		console.log( '   ✅ Script modules copied' );
	}

	// 4. Copy styles
	console.log( '\n📦 Copying styles...' );
	const stylesConfig = COPY_CONFIG.styles;
	const stylesSrc = path.join( gutenbergBuildDir, stylesConfig.source );
	const stylesDest = path.join( wpIncludesDir, stylesConfig.destination );

	if ( fs.existsSync( stylesSrc ) ) {
		copyDirectory( stylesSrc, stylesDest );
		console.log( '   ✅ Styles copied' );
	}

	// 5. Copy blocks (unified: scripts, styles, PHP, JSON)
	console.log( '\n📦 Copying blocks...' );
	const blocksDest = path.join(
		wpIncludesDir,
		COPY_CONFIG.blocks.destination
	);
	copyBlockAssets( COPY_CONFIG.blocks );

	// 6. Copy theme JSON files (from Gutenberg lib directory)
	console.log( '\n📦 Copying theme JSON files...' );
	const themeJsonConfig = COPY_CONFIG.themeJson;
	const gutenbergLibDir = path.join( gutenbergDir, 'lib' );

	for ( const fileMap of themeJsonConfig.files ) {
		const src = path.join( gutenbergLibDir, fileMap.from );
		const dest = path.join( wpIncludesDir, fileMap.to );

		if ( fs.existsSync( src ) ) {
			let content = fs.readFileSync( src, 'utf8' );

			if ( themeJsonConfig.transform && fileMap.from === 'theme.json' ) {
				// Transform schema URL for Core
				content = content.replace(
					'"$schema": "../schemas/json/theme.json"',
					'"$schema": "https://schemas.wp.org/trunk/theme.json"'
				);
			}

			fs.writeFileSync( dest, content );
			console.log( `   ✅ ${ fileMap.to }` );
		} else {
			console.log( `   ⚠️  Not found: ${ fileMap.from }` );
		}
	}

	// 7. Generate script-modules-packages.min.php from individual asset files
	console.log( '\n📦 Generating script-modules-packages.min.php...' );
	generateScriptModulesPackages();

	// 8. Generate script-loader-packages.min.php
	console.log( '\n📦 Generating script-loader-packages.min.php...' );
	generateScriptLoaderPackages();

	// 9. Generate require-dynamic-blocks.php and require-static-blocks.php
	console.log( '\n📦 Generating block registration files...' );
	generateBlockRegistrationFiles();

	// 10. Generate blocks-json.php from block.json files
	console.log( '\n📦 Generating blocks-json.php...' );
	generateBlocksJson();

	// Summary
	console.log( '\n✅ Copy complete!' );
	console.log( '\n📊 Summary:' );
	console.log( `   PHP infrastructure: ${ phpDest }` );
	console.log( `   JavaScript: ${ scriptsDest }` );
	console.log( `   Script modules: ${ modulesDest }` );
	console.log( `   Styles: ${ stylesDest }` );
	console.log( `   Blocks: ${ blocksDest }` );
}

// Run main function
main().catch( ( error ) => {
	console.error( '❌ Unexpected error:', error );
	process.exit( 1 );
} );
