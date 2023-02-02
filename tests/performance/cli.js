#!/usr/bin/env node

/**
 * External dependencies
 */
const program = require( 'commander' );

const catchException = ( command ) => {
	return async ( ...args ) => {
		try {
			await command( ...args );
		} catch ( error ) {
			console.error( error );
			process.exitCode = 1;
		}
	};
};

/**
 * Internal dependencies
 */
const { runPerformanceTests } = require( './commands/performance' );

const ciOption = [ '-c, --ci', 'Run in CI (non interactive)' ];

program
	.command( 'performance-tests [branches...]' )
	.alias( 'perf' )
	.option( ...ciOption )
	.option(
		'--rounds <count>',
		'Run each test suite this many times for each branch; results are summarized, default = 1'
	)
	.option(
		'--tests-branch <branch>',
		"Use this branch's performance test files"
	)
	.option(
		'--wp-version <version>',
		'Specify a WordPress version on which to test all branches'
	)
	.description(
		'Runs performance tests on two separate branches and outputs the result'
	)
	.action( catchException( runPerformanceTests ) );

program.parse( process.argv );
