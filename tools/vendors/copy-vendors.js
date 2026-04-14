#!/usr/bin/env node

/**
 * Copy Vendor Scripts
 *
 * This script copies vendor dependencies from node_modules to wp-includes/js/dist/vendor/.
 * These are Core's own dependencies (moment, lodash, regenerator-runtime, polyfills, etc.)
 * separate from Gutenberg packages.
 *
 * @package WordPress
 */

const fs = require( 'fs' );
const path = require( 'path' );

// Paths
const rootDir = path.resolve( __dirname, '../..' );
const nodeModulesDir = path.join( rootDir, 'node_modules' );

// Parse command line arguments
const args = process.argv.slice( 2 );
const buildDirArg = args.find( arg => arg.startsWith( '--build-dir=' ) );
const buildTarget = buildDirArg
	? buildDirArg.split( '=' )[1]
	: ( args.includes( '--dev' ) ? 'src' : 'build' );

const vendorDir = path.join( rootDir, buildTarget, 'wp-includes/js/dist/vendor' );

/**
 * Vendor files to copy from node_modules.
 */
const VENDOR_FILES = {
	// Moment.js
	'moment': {
		files: [
			{ from: 'moment/moment.js', to: 'moment.js' },
			{ from: 'moment/min/moment.min.js', to: 'moment.min.js' },
		],
	},

	// Lodash
	'lodash': {
		files: [
			{ from: 'lodash/lodash.js', to: 'lodash.js' },
			{ from: 'lodash/lodash.min.js', to: 'lodash.min.js' },
		],
	},

	// Regenerator Runtime
	'regenerator-runtime': {
		files: [
			{ from: 'regenerator-runtime/runtime.js', to: 'regenerator-runtime.js' },
			{ from: 'regenerator-runtime/runtime.js', to: 'regenerator-runtime.min.js' },
		],
	},

	// React (UMD builds from node_modules)
	'react': {
		files: [
			{ from: 'react/umd/react.development.js', to: 'react.js' },
			{ from: 'react/umd/react.production.min.js', to: 'react.min.js' },
		],
	},

	// React DOM (UMD builds from node_modules)
	'react-dom': {
		files: [
			{ from: 'react-dom/umd/react-dom.development.js', to: 'react-dom.js' },
			{ from: 'react-dom/umd/react-dom.production.min.js', to: 'react-dom.min.js' },
		],
	},

	// Main Polyfill bundle
	'wp-polyfill': {
		files: [
			{ from: '@wordpress/babel-preset-default/build/polyfill.js', to: 'wp-polyfill.js' },
			{ from: '@wordpress/babel-preset-default/build/polyfill.min.js', to: 'wp-polyfill.min.js' },
		],
	},

	// Polyfills - Fetch (same source for both - was minified by webpack)
	'wp-polyfill-fetch': {
		files: [
			{ from: 'whatwg-fetch/dist/fetch.umd.js', to: 'wp-polyfill-fetch.js' },
			{ from: 'whatwg-fetch/dist/fetch.umd.js', to: 'wp-polyfill-fetch.min.js' },
		],
	},

	// Polyfills - FormData
	'wp-polyfill-formdata': {
		files: [
			{ from: 'formdata-polyfill/FormData.js', to: 'wp-polyfill-formdata.js' },
			{ from: 'formdata-polyfill/formdata.min.js', to: 'wp-polyfill-formdata.min.js' },
		],
	},

	// Polyfills - Element Closest (same for both)
	'wp-polyfill-element-closest': {
		files: [
			{ from: 'element-closest/browser.js', to: 'wp-polyfill-element-closest.js' },
			{ from: 'element-closest/browser.js', to: 'wp-polyfill-element-closest.min.js' },
		],
	},

	// Polyfills - Object Fit
	'wp-polyfill-object-fit': {
		files: [
			{ from: 'objectFitPolyfill/src/objectFitPolyfill.js', to: 'wp-polyfill-object-fit.js' },
			{ from: 'objectFitPolyfill/dist/objectFitPolyfill.min.js', to: 'wp-polyfill-object-fit.min.js' },
		],
	},

	// Polyfills - Inert
	'wp-polyfill-inert': {
		files: [
			{ from: 'wicg-inert/dist/inert.js', to: 'wp-polyfill-inert.js' },
			{ from: 'wicg-inert/dist/inert.min.js', to: 'wp-polyfill-inert.min.js' },
		],
	},

	// Polyfills - URL
	'wp-polyfill-url': {
		files: [
			{ from: 'core-js-url-browser/url.js', to: 'wp-polyfill-url.js' },
			{ from: 'core-js-url-browser/url.min.js', to: 'wp-polyfill-url.min.js' },
		],
	},

	// Polyfills - DOMRect (same source for both - was minified by webpack)
	'wp-polyfill-dom-rect': {
		files: [
			{ from: 'polyfill-library/polyfills/__dist/DOMRect/raw.js', to: 'wp-polyfill-dom-rect.js' },
			{ from: 'polyfill-library/polyfills/__dist/DOMRect/raw.js', to: 'wp-polyfill-dom-rect.min.js' },
		],
	},

	// Polyfills - Node.contains (same source for both - was minified by webpack)
	'wp-polyfill-node-contains': {
		files: [
			{ from: 'polyfill-library/polyfills/__dist/Node.prototype.contains/raw.js', to: 'wp-polyfill-node-contains.js' },
			{ from: 'polyfill-library/polyfills/__dist/Node.prototype.contains/raw.js', to: 'wp-polyfill-node-contains.min.js' },
		],
	},
};

/**
 * Main execution function.
 */
async function main() {
	console.log( '📦 Copying vendor scripts from node_modules...' );
	console.log( `   Build target: ${ buildTarget }/` );

	// Create vendor directory
	fs.mkdirSync( vendorDir, { recursive: true } );

	let copied = 0;
	let skipped = 0;

	for ( const [ vendor, config ] of Object.entries( VENDOR_FILES ) ) {
		for ( const file of config.files ) {
			const srcPath = path.join( nodeModulesDir, file.from );
			const destPath = path.join( vendorDir, file.to );

			if ( fs.existsSync( srcPath ) ) {
				fs.copyFileSync( srcPath, destPath );
				copied++;
			} else {
				console.log( `   ⚠️  Skipping ${ file.to }: source not found` );
				skipped++;
			}
		}
	}

	console.log( `\n✅ Vendor scripts copied!` );
	console.log( `   Copied: ${ copied } files` );
	if ( skipped > 0 ) {
		console.log( `   Skipped: ${ skipped } files` );
	}
}

// Run main function
main().catch( ( error ) => {
	console.error( '❌ Unexpected error:', error );
	process.exit( 1 );
} );
