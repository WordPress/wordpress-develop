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
const buildDirArg = args.find( arg => arg.startsWith( '--build-dir=' ) );
const buildTarget = buildDirArg
	? buildDirArg.split( '=' )[1]
	: ( args.includes( '--dev' ) ? 'src' : 'build' );

const wpIncludesDir = path.join( rootDir, buildTarget, 'wp-includes' );

/**
 * Copy configuration.
 * Defines what to copy from Gutenberg build and where it goes in Core.
 */
const COPY_CONFIG = {
	// PHP infrastructure files (to wp-includes/build/)
	phpInfrastructure: {
		destination: 'build',
		files: [
			'routes.php',
			'pages.php',
		],
		directories: [
			'pages',
			'routes',
		],
		transform: true, // Apply PHP transformations
	},

	// JavaScript packages (to wp-includes/js/dist/)
	scripts: {
		source: 'scripts',
		destination: 'js/dist',
		copyDirectories: true, // Copy subdirectories
		patterns: [ '*.js', '*.js.map' ],
		// Rename vendors/ to vendor/ when copying
		directoryRenames: {
			'vendors': 'vendor'
		}
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
	// Note: Copies JS/CSS/JSON from build, PHP from packages source (no prefixes)
	blocks: {
		source: 'scripts/block-library',
		destination: 'blocks',
		copyAll: true,
		excludePHP: true, // PHP files copied separately from packages source
		excludeExperimental: true, // Skip experimental blocks
	},

	// PHP source files (copied from packages, not build, to avoid Gutenberg prefixes)
	phpSource: {
		files: [
			{
				// Block parser classes
				package: 'block-serialization-default-parser',
				files: [
					'class-wp-block-parser.php',
					'class-wp-block-parser-block.php',
					'class-wp-block-parser-frame.php',
				],
				destination: '', // Root of wp-includes
				transform: false, // These don't need path transformations
			},
			{
				// Block library PHP files
				package: 'block-library/src',
				pattern: '*/index.php', // Each block directory
				destination: 'blocks',
				renamePattern: { from: '/index.php', to: '.php' }, // comment-template/index.php → comment-template.php
				transform: true,
			},
			{
				// Widgets PHP files
				package: 'widgets/src/blocks',
				files: [
					{ from: 'legacy-widget/index.php', to: 'blocks/legacy-widget.php' },
					{ from: 'widget-group/index.php', to: 'blocks/widget-group.php' },
				],
				transform: true,
			},
		],
	},

	// Widget block.json files (from build, not source)
	widgetBlockJson: {
		source: 'scripts/widgets/blocks',
		files: [
			{ from: 'legacy-widget/block.json', to: 'blocks/legacy-widget/block.json' },
			{ from: 'widget-group/block.json', to: 'blocks/widget-group/block.json' },
		],
	},

	// Theme JSON files (from Gutenberg lib directory)
	themeJson: {
		files: [
			{ from: 'theme.json', to: 'theme.json' },
			{ from: 'theme-i18n.json', to: 'theme-i18n.json' },
		],
		transform: true,
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
		const blockJson = JSON.parse( fs.readFileSync( blockJsonPath, 'utf8' ) );
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
					content = transform( content.toString(), srcPath, destPath );
				} catch ( error ) {
					console.error( `   ⚠️  Transform error in ${ entry.name }:`, error.message );
				}
			}

			fs.writeFileSync( destPath, content );
		}
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
				const normalizedPath = relativePath.split( path.sep ).join( '/' );
				const jsPathMin = normalizedPath.replace( /\.asset\.php$/, '.js' );
				const jsPathRegular = jsPathMin.replace( /\.min\.js$/, '.js' );

				try {
					// Read and parse the PHP asset file
					const phpContent = fs.readFileSync( fullPath, 'utf8' );
					// Extract the array from PHP: <?php return array(...);
					const match = phpContent.match( /return\s+array\(([\s\S]*?)\);/  );
					if ( match ) {
						// Parse PHP array to JavaScript object
						const assetData = parsePHPArray( match[1] );

						// Create entries for both minified and non-minified versions
						assetsMin[ jsPathMin ] = assetData;
						assetsRegular[ jsPathRegular ] = assetData;
					}
				} catch ( error ) {
					console.error( `   ⚠️  Error reading ${ relativePath }:`, error.message );
				}
			}
		}
	}

	processDirectory( modulesDir, modulesDir );

	// Generate both minified and non-minified PHP files using json2php
	const phpContentMin = '<?php return ' + json2php.make( {
		linebreak: '\n',
		indent: '  ',
		shortArraySyntax: false
	} )( assetsMin ) + ';';

	const phpContentRegular = '<?php return ' + json2php.make( {
		linebreak: '\n',
		indent: '  ',
		shortArraySyntax: false
	} )( assetsRegular ) + ';';

	const outputPathMin = path.join( wpIncludesDir, 'assets/script-modules-packages.min.php' );
	const outputPathRegular = path.join( wpIncludesDir, 'assets/script-modules-packages.php' );

	fs.mkdirSync( path.dirname( outputPathMin ), { recursive: true } );
	fs.writeFileSync( outputPathMin, phpContentMin );
	fs.writeFileSync( outputPathRegular, phpContentRegular );

	console.log( `   ✅ Generated with ${ Object.keys( assetsMin ).length } modules` );
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

		const assetFile = path.join( scriptsDir, entry.name, 'index.min.asset.php' );
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
			console.error( `   ⚠️  Error reading ${ entry.name }/index.min.asset.php:`, error.message );
		}
	}

	// Generate both minified and non-minified PHP files using json2php
	const phpContentMin = '<?php return ' + json2php.make( {
		linebreak: '\n',
		indent: '  ',
		shortArraySyntax: false
	} )( assetsMin ) + ';';

	const phpContentRegular = '<?php return ' + json2php.make( {
		linebreak: '\n',
		indent: '  ',
		shortArraySyntax: false
	} )( assetsRegular ) + ';';

	const outputPathMin = path.join( wpIncludesDir, 'assets/script-loader-packages.min.php' );
	const outputPathRegular = path.join( wpIncludesDir, 'assets/script-loader-packages.php' );

	fs.mkdirSync( path.dirname( outputPathMin ), { recursive: true } );
	fs.writeFileSync( outputPathMin, phpContentMin );
	fs.writeFileSync( outputPathRegular, phpContentRegular );

	console.log( `   ✅ Generated with ${ Object.keys( assetsMin ).length } packages` );
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
${ dynamicBlocks.map( name => `require_once ABSPATH . WPINC . '/blocks/${ name }.php';` ).join( '\n' ) }
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
${ staticBlocks.map( name => `\t'${ name }',` ).join( '\n' ) }
);
`;

	fs.writeFileSync(
		path.join( wpIncludesDir, 'blocks/require-static-blocks.php' ),
		staticContent
	);

	console.log( `   ✅ Generated: ${ dynamicBlocks.length } dynamic, ${ staticBlocks.length } static blocks` );
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
				const blockJson = JSON.parse( fs.readFileSync( blockJsonPath, 'utf8' ) );
				blocks[ entry.name ] = blockJson;
			} catch ( error ) {
				console.error( `   ⚠️  Error reading ${ entry.name }/block.json:`, error.message );
			}
		}
	}

	// Generate the PHP file content using json2php for consistent formatting
	const phpContent = '<?php return ' + json2php.make( {
		linebreak: '\n',
		indent: '  ',
		shortArraySyntax: false
	} )( blocks ) + ';';

	fs.writeFileSync(
		path.join( wpIncludesDir, 'blocks/blocks-json.php' ),
		phpContent
	);

	console.log( `   ✅ Generated with ${ Object.keys( blocks ).length } blocks` );
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
		const char = content[i];

		// Track strings
		if ( ( char === "'" || char === '"' ) && ( i === 0 || content[i - 1] !== '\\' ) ) {
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
						content = content.substring( 0, arrayStart ) + placeholder + content.substring( i + 1 );
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
		const char = content[i];

		if ( ( char === "'" || char === '"' ) && ( i === 0 || content[i - 1] !== '\\' ) ) {
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
			let key = arrowMatch[1].trim().replace( /^['"]|['"]$/g, '' );
			let value = arrowMatch[2].trim();

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
 * @param {string} srcPath  - Source file path.
 * @param {string} destPath - Destination file path.
 * @return {string} Transformed content.
 */
function transformPHPContent( content, srcPath, destPath ) {
	let transformed = content;

	// Replace plugins_url() with includes_url()
	// Handles patterns like: plugins_url( 'build/...' . $var, dirname( __FILE__ ) )
	transformed = transformed.replace(
		/plugins_url\(\s*([^,]+),\s*(?:dirname\(\s*__FILE__\s*\)|__FILE__)\s*\)/g,
		( match, firstArg ) => {
			return `includes_url( ${ firstArg.trim() } )`;
		}
	);

	// Replace plugin_dir_path( __FILE__ ) with ABSPATH . WPINC . '/build/'
	transformed = transformed.replace(
		/plugin_dir_path\(\s*__FILE__\s*\)/g,
		"ABSPATH . WPINC . '/build/'"
	);

	// Replace dirname( __FILE__ ) patterns in path construction
	transformed = transformed.replace(
		/dirname\(\s*__FILE__\s*\)/g,
		"ABSPATH . WPINC . '/build'"
	);

	// Replace __DIR__ with ABSPATH . WPINC . '/build'
	transformed = transformed.replace(
		/__DIR__\s*\.\s*['"]\/\.\.\/(.*?)['"]/g,
		( match, relativePath ) => {
			return `ABSPATH . WPINC . '/build/${ relativePath }'`;
		}
	);

	// Special transformations for page-wp-admin.php files
	if ( destPath.includes( 'page-wp-admin.php' ) ) {
		// Fix boot module asset file path
		// FROM: ABSPATH . WPINC . '/build/' . '../../modules/boot/index.min.asset.php'
		// TO:   ABSPATH . WPINC . '/js/dist/script-modules/boot/index.min.asset.php'
		transformed = transformed.replace(
			/ABSPATH\s*\.\s*WPINC\s*\.\s*['"]\/build\/['"]\s*\.\s*['"]\.\.\/\.\.\/modules\/boot\/index\.min\.asset\.php['"]/g,
			"ABSPATH . WPINC . '/js/dist/script-modules/boot/index.min.asset.php'"
		);

		// Fix loader.js path - replace plugin_dir_url with data URI for empty module
		// FROM: plugin_dir_url( __FILE__ ) . 'loader.js'
		// TO:   'data:text/javascript,' (empty module for dependency registration only)
		transformed = transformed.replace(
			/plugin_dir_url\(\s*__FILE__\s*\)\s*\.\s*['"]loader\.js['"]/g,
			"'data:text/javascript,'"
		);

		// Fix enqueue condition to also work for direct page files (e.g., fonts.php)
		// This allows the page to work both via menu (admin.php?page=X) and direct file (X.php)
		transformed = transformed.replace(
			/\/\/ Only enqueue on our page\n\s+if \( ! isset\( \$_GET\['page'\] \) \|\| '([^']+)' !== \$_GET\['page'\] \) { \/\/ phpcs:ignore WordPress\.Security\.NonceVerification\.Recommended\n\s+return;\n\s+}/,
			( match, pageName ) => {
				// Extract the base name (e.g., 'font-library' from 'font-library-wp-admin')
				const baseName = pageName.replace( '-wp-admin', '' );
				return `// Only enqueue on our page (either ${baseName}.php or the menu page)
		$is_menu_page = isset( $_GET['page'] ) && '${pageName}' === $_GET['page']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$is_direct_page = '${baseName}.php' === $hook_suffix;

		if ( ! $is_menu_page && ! $is_direct_page ) {
			return;
		}`;
			}
		);
	}

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
			if ( phpConfig.transform ) {
				content = transformPHPContent( content, src, dest );
			}
			fs.writeFileSync( dest, content );
			console.log( `   ✅ ${ file }` );
		} else {
			console.log( `   ⚠️  ${ file } not found (may not exist in this Gutenberg version)` );
		}
	}

	// Copy PHP directories
	for ( const dir of phpConfig.directories ) {
		const src = path.join( gutenbergBuildDir, dir );
		const dest = path.join( phpDest, dir );

		if ( fs.existsSync( src ) ) {
			console.log( `   📁 Copying ${ dir }/...` );
			const transform = phpConfig.transform ? transformPHPContent : null;
			copyDirectory( src, dest, transform );
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
				if ( scriptsConfig.copyDirectories && scriptsConfig.directoryRenames && scriptsConfig.directoryRenames[ entry.name ] ) {
					// Copy special directories with rename (vendors/ → vendor/)
					// Only copy react-jsx-runtime from vendors (react and react-dom come from Core's node_modules)
					const destName = scriptsConfig.directoryRenames[ entry.name ];
					const dest = path.join( scriptsDest, destName );

					if ( entry.name === 'vendors' ) {
						// Only copy react-jsx-runtime files, skip react and react-dom
						const vendorFiles = fs.readdirSync( src );
						let copiedCount = 0;
						for ( const file of vendorFiles ) {
							if ( file.startsWith( 'react-jsx-runtime' ) ) {
								const srcFile = path.join( src, file );
								const destFile = path.join( dest, file );
								fs.mkdirSync( dest, { recursive: true } );

								if ( file.endsWith( '.js' ) && ! file.endsWith( '.js.map' ) ) {
									let content = fs.readFileSync( srcFile, 'utf8' );
									content = removeSourceMaps( content );
									fs.writeFileSync( destFile, content );
								} else {
									fs.copyFileSync( srcFile, destFile );
								}
								copiedCount++;
							}
						}
						console.log( `   ✅ ${ entry.name }/ → ${ destName }/ (react-jsx-runtime only, ${ copiedCount } files)` );
					} else {
						// Copy other special directories normally
						copyDirectory( src, dest, removeSourceMaps );
						console.log( `   ✅ ${ entry.name }/ → ${ destName }/` );
					}
				} else {
					// Flatten package structure: package-name/index.js → package-name.js
					// This matches Core's expected file structure
					const packageFiles = fs.readdirSync( src );

					for ( const file of packageFiles ) {
						if ( /^index\.(js|js\.map|min\.js|min\.js\.map|min\.asset\.php)$/.test( file ) ) {
							const srcFile = path.join( src, file );
							// Replace 'index.' with 'package-name.'
							const destFile = file.replace( /^index\./, `${ entry.name }.` );
							const destPath = path.join( scriptsDest, destFile );

							fs.mkdirSync( path.dirname( destPath ), { recursive: true } );

							// Apply source map removal for .js files
							if ( file.endsWith( '.js' ) && ! file.endsWith( '.js.map' ) ) {
								let content = fs.readFileSync( srcFile, 'utf8' );
								content = removeSourceMaps( content );
								fs.writeFileSync( destPath, content );
							} else {
								// Copy other files as-is
								fs.copyFileSync( srcFile, destPath );
							}
						}
					}
				}
			} else if ( entry.isFile() && /\.(js|js\.map)$/.test( entry.name ) ) {
				// Copy root-level JS files
				const dest = path.join( scriptsDest, entry.name );
				fs.mkdirSync( path.dirname( dest ), { recursive: true } );

				if ( entry.name.endsWith( '.js' ) && ! entry.name.endsWith( '.js.map' ) ) {
					let content = fs.readFileSync( src, 'utf8' );
					content = removeSourceMaps( content );
					fs.writeFileSync( dest, content );
				} else {
					fs.copyFileSync( src, dest );
				}
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

	// 5. Copy blocks (excluding PHP - copied separately from source)
	console.log( '\n📦 Copying blocks...' );
	const blocksConfig = COPY_CONFIG.blocks;
	const blocksSrc = path.join( gutenbergBuildDir, blocksConfig.source );
	const blocksDest = path.join( wpIncludesDir, blocksConfig.destination );

	if ( fs.existsSync( blocksSrc ) ) {
		// Transform function to remove source map comments from blocks/index.js and index.min.js
		const blocksTransform = ( content, srcPath, destPath ) => {
			// Only process blocks/index.js and blocks/index.min.js
			if ( destPath.endsWith( path.join( 'blocks', 'index.js' ) ) ||
				destPath.endsWith( path.join( 'blocks', 'index.min.js' ) ) ) {
				// Remove sourceMappingURL comment
				return content.replace( /\/\/# sourceMappingURL=.*$/m, '' ).trimEnd();
			}
			return content;
		};

		copyDirectory( blocksSrc, blocksDest, blocksTransform, {
			excludePHP: blocksConfig.excludePHP,
			excludeExperimental: blocksConfig.excludeExperimental,
		} );
		console.log( '   ✅ Blocks copied (JS/CSS/JSON, excluding experimental)' );
	}

	// 6. Copy PHP source files (from packages, to avoid Gutenberg prefixes)
	console.log( '\n📦 Copying PHP source files...' );
	const phpSourceConfig = COPY_CONFIG.phpSource;

	for ( const fileGroup of phpSourceConfig.files ) {
		const packageSrc = path.join( gutenbergPackagesDir, fileGroup.package );

		if ( ! fs.existsSync( packageSrc ) ) {
			console.log( `   ⚠️  Package not found: ${ fileGroup.package }` );
			continue;
		}

		// Simple file list
		if ( fileGroup.files && Array.isArray( fileGroup.files ) && typeof fileGroup.files[0] === 'string' ) {
			for ( const file of fileGroup.files ) {
				const src = path.join( packageSrc, file );
				const dest = path.join( wpIncludesDir, fileGroup.destination, file );

				if ( fs.existsSync( src ) ) {
					fs.mkdirSync( path.dirname( dest ), { recursive: true } );
					let content = fs.readFileSync( src, 'utf8' );

					if ( fileGroup.transform ) {
						content = transformPHPContent( content, src, dest );
					}

					fs.writeFileSync( dest, content );
					console.log( `   ✅ ${ file }` );
				}
			}
		}

		// Pattern-based (e.g., */index.php for all block directories)
		if ( fileGroup.pattern ) {
			const entries = fs.readdirSync( packageSrc, { withFileTypes: true } );

			for ( const entry of entries ) {
				if ( ! entry.isDirectory() ) continue;

				// Check if this block is experimental
				const blockJsonPath = path.join( packageSrc, entry.name, 'block.json' );
				if ( isExperimentalBlock( blockJsonPath ) ) {
					continue;
				}

				const src = path.join( packageSrc, entry.name, 'index.php' );
				if ( ! fs.existsSync( src ) ) continue;

				// Apply rename pattern: comment-template/index.php → comment-template.php
				const destFileName = fileGroup.renamePattern
					? entry.name + fileGroup.renamePattern.to
					: entry.name + '/index.php';

				const dest = path.join( wpIncludesDir, fileGroup.destination, destFileName );

				fs.mkdirSync( path.dirname( dest ), { recursive: true } );
				let content = fs.readFileSync( src, 'utf8' );

				if ( fileGroup.transform ) {
					content = transformPHPContent( content, src, dest );
				}

				fs.writeFileSync( dest, content );
			}
			console.log( `   ✅ ${ fileGroup.package } PHP files (excluding experimental)` );
		}

		// Files with from/to mapping (widgets)
		if ( fileGroup.files && Array.isArray( fileGroup.files ) && typeof fileGroup.files[0] === 'object' ) {
			for ( const fileMap of fileGroup.files ) {
				const src = path.join( packageSrc, fileMap.from );
				const dest = path.join( wpIncludesDir, fileMap.to );

				if ( fs.existsSync( src ) ) {
					fs.mkdirSync( path.dirname( dest ), { recursive: true } );
					let content = fs.readFileSync( src, 'utf8' );

					if ( fileGroup.transform ) {
						content = transformPHPContent( content, src, dest );
					}

					fs.writeFileSync( dest, content );
					console.log( `   ✅ ${ fileMap.to }` );
				}
			}
		}
	}

	// 7. Copy widget block.json files (from build)
	console.log( '\n📦 Copying widget block.json files...' );
	const widgetBlockJsonConfig = COPY_CONFIG.widgetBlockJson;
	const widgetSrc = path.join( gutenbergBuildDir, widgetBlockJsonConfig.source );

	if ( fs.existsSync( widgetSrc ) ) {
		for ( const fileMap of widgetBlockJsonConfig.files ) {
			const src = path.join( widgetSrc, fileMap.from );
			const dest = path.join( wpIncludesDir, fileMap.to );

			if ( fs.existsSync( src ) ) {
				fs.mkdirSync( path.dirname( dest ), { recursive: true } );
				fs.copyFileSync( src, dest );
				console.log( `   ✅ ${ fileMap.to }` );
			} else {
				console.log( `   ⚠️  Not found: ${ fileMap.from }` );
			}
		}
	}

	// 8. Copy theme JSON files (from Gutenberg lib directory)
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

	// 9. Generate script-modules-packages.min.php from individual asset files
	console.log( '\n📦 Generating script-modules-packages.min.php...' );
	generateScriptModulesPackages();

	// 10. Generate script-loader-packages.min.php
	console.log( '\n📦 Generating script-loader-packages.min.php...' );
	generateScriptLoaderPackages();

	// 11. Generate require-dynamic-blocks.php and require-static-blocks.php
	console.log( '\n📦 Generating block registration files...' );
	generateBlockRegistrationFiles();

	// 12. Generate blocks-json.php from block.json files
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
