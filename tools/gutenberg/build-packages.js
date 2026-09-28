/* jshint node:true */
/* jshint esversion: 6 */

/**
 * Builds the `@wordpress/*` packages in `gb-src/packages` so that they can be
 * consumed through the `file:` references in package.json.
 *
 * This produces the `build`, `build-module` and `build-style` directories for
 * each package using the Gutenberg build script and its own locked toolchain
 * (installed into `gb-src/node_modules` by the root `postinstall` script).
 */

/**
 * External dependencies
 */
const { spawnSync } = require( 'child_process' );
const fs = require( 'fs' );
const path = require( 'path' );

const GB_DIR = path.resolve( __dirname, '../../gb-src' );
const PACKAGES_DIR = path.join( GB_DIR, 'packages' );
const BUILD_DIRS = [ 'build', 'build-module', 'build-style' ];

if ( ! fs.existsSync( path.join( GB_DIR, 'node_modules' ) ) ) {
	console.error( 'gb-src/node_modules is missing. Run `npm install` in the project root first.' );
	process.exit( 1 );
}

// Remove any previous build output so that deleted source files do not linger.
fs.readdirSync( PACKAGES_DIR, { withFileTypes: true } ).forEach( ( entry ) => {
	if ( ! entry.isDirectory() ) {
		return;
	}

	BUILD_DIRS.forEach( ( buildDir ) => {
		const packageName = entry.name;
		fs.rmSync( path.join( PACKAGES_DIR, packageName, buildDir ), { recursive: true, force: true } );
	} );
} );

const result = spawnSync(
	process.execPath,
	[ path.join( GB_DIR, 'bin/packages/build.js' ) ],
	{
		cwd: GB_DIR,
		stdio: 'inherit',
	}
);

process.exit( result.status === null ? 1 : result.status );
