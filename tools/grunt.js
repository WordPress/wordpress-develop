#!/usr/bin/env node

/**
 * Runs Grunt with V8's concurrent Maglev and Sparkplug compilers disabled.
 *
 * Grunt exits via `process.exit()`, which can deadlock against a parked
 * background compile job. `NODE_OPTIONS` rejects V8 options.
 *
 * @see https://github.com/nodejs/node/issues/64274
 * @package WordPress
 */

const { spawn } = require( 'child_process' );
const os = require( 'os' );
const path = require( 'path' );

const gruntBin = path.resolve( __dirname, '../node_modules/grunt/bin/grunt' );
const forwardedSignals = [ 'SIGINT', 'SIGTERM', 'SIGHUP' ];

const child = spawn(
	process.execPath,
	[
		'--no-maglev',
		'--no-concurrent-sparkplug',
		gruntBin,
		...process.argv.slice( 2 ),
	],
	{ stdio: 'inherit' }
);

/*
 * Without this, a supervisor that signals only this process leaves Grunt
 * running. Terminal interrupts already reach both through the process group.
 */
const forward = ( signal ) => child.kill( signal );
forwardedSignals.forEach( ( signal ) => process.on( signal, forward ) );

// Listeners hold the event loop open, so drop them once Grunt is gone.
const release = () =>
	forwardedSignals.forEach( ( signal ) => process.off( signal, forward ) );

child.on( 'error', ( error ) => {
	release();
	console.error( error.message );
	process.exitCode = 1;
} );

child.on( 'exit', ( code, signal ) => {
	release();

	if ( ! signal ) {
		process.exitCode = code ?? 1;
		return;
	}

	console.error( `Grunt was terminated by ${ signal }.` );

	const signalNumber = os.constants.signals[ signal ];
	process.exitCode = signalNumber ? 128 + signalNumber : 1;
} );
