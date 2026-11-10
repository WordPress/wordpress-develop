/**
 * Watch Task
 *
 * Runs the tasks of the `_watch` targets whose files change. It replaces
 * grunt-contrib-watch, which missed changes: it polls each watched file through
 * gaze, which finds every file in the directories that a target's patterns
 * exclude before filtering them out; it recreates its watchers after each run,
 * missing the changes made meanwhile; and it leaves the changes seen while
 * another target's tasks run waiting for the next change.
 *
 * Files are watched with chokidar, through the native file system events of
 * each platform. Set `CHOKIDAR_USEPOLLING=1` to poll instead, where the events
 * are not delivered, as on network drives or for Windows drives under WSL.
 *
 * Each target takes `files` patterns, the `tasks` to run when one of them
 * changes, and the options `dot`, to match files whose names begin with a dot,
 * and `reload`, to restart the watch task, as for a change to the Gruntfile.
 * The `ignored` option of the task lists the patterns of the directories that
 * are never watched.
 *
 * The tasks run in the watch process, and a `watch` event is emitted for each
 * change of a target before they run, so that a listener can configure them for
 * the changed files. Changes made while tasks run are run once they finish. When
 * a task fails, the rest of its target's tasks are skipped, and those of the
 * other targets still run. A reload happens after the tasks of the other
 * changes made with it, or while its tasks ran, have run.
 *
 * A target's tasks must not write files that match its own patterns, which
 * would run them again without end.
 *
 * @package WordPress
 */

const path = require( 'path' );
const { spawn } = require( 'child_process' );
const chokidar = require( 'chokidar' );
const { Minimatch } = require( 'minimatch' );

/**
 * Time to wait for more changes before running the tasks, in milliseconds.
 *
 * An editor or a version control checkout often changes several files at once.
 */
const DEBOUNCE_DELAY = 250;

/**
 * Change actions, keyed by the chokidar event names.
 */
const ACTIONS = {
	add: 'added',
	change: 'changed',
	unlink: 'deleted',
};

/**
 * Gets the directory that a pattern searches, before any part with glob syntax.
 *
 * @param {string} pattern The pattern.
 *
 * @return {string} The directory, or the file when the pattern has no glob syntax.
 */
function getPatternBase( pattern ) {
	const segments = [];

	for ( const segment of pattern.split( '/' ) ) {
		if ( /[*?[\]{}()!+@]/.test( segment ) ) {
			break;
		}
		segments.push( segment );
	}

	return segments.join( '/' ) || '.';
}

/**
 * Gets the paths to watch for the patterns, without those inside another one.
 *
 * @param {string[]} patterns The patterns.
 *
 * @return {string[]} The paths to watch.
 */
function getWatchPaths( patterns ) {
	const bases = [ ...new Set(
		patterns
			.filter( ( pattern ) => ! pattern.startsWith( '!' ) )
			.map( getPatternBase )
	) ];
	const isInside = ( child, parent ) => parent === '.' || child.startsWith( parent + '/' );

	return bases.filter( ( base ) => ! bases.some( ( other ) => other !== base && isInside( base, other ) ) );
}

/**
 * Builds the watch task.
 *
 * @param {Object} grunt The grunt instance.
 *
 * @return {Function} The task function.
 */
module.exports = ( grunt ) => {
	let watcher = null;
	let taskName = '_watch';
	let targetNames = [];
	let nameArgs = '';
	let resume = null;
	let timer = null;
	let reload = false;
	let restartPending = false;
	let startedAt = null;
	let currentTarget = null;

	// Changed files since the tasks last ran, keyed by path, and the targets to run for them.
	const changes = new Map();
	const queuedTargets = new Set();

	// The targets whose tasks are queued in the current run, until each one's tasks finish.
	let runningTargets = [];

	grunt.registerTask( '_watch-target', 'Marks the tasks of a watch target as started or finished.', ( step, name ) => {
		if ( 'start' === step ) {
			currentTarget = name;
		} else {
			currentTarget = null;
			runningTargets = runningTargets.filter( ( running ) => running !== name );
		}
	} );

	/**
	 * Gets the configuration of a target.
	 *
	 * @param {string} name The target name.
	 *
	 * @return {{files: string[], tasks: string[], options: Object}} The target configuration.
	 */
	function getTarget( name ) {
		const target = grunt.config( [ taskName, name ] );

		return {
			files: [].concat( target.files || [] ).map( ( pattern ) => grunt.config.process( pattern ) ),
			tasks: [].concat( target.tasks || [] ),
			options: target.options || {},
		};
	}

	/**
	 * Gets the tasks to run for targets, each between the tasks that mark it as started and finished.
	 *
	 * @param {string[]} names The target names.
	 *
	 * @return {string[]} The tasks.
	 */
	function getTasks( names ) {
		return names.flatMap( ( name ) => [ '_watch-target:start:' + name ].concat(
			getTarget( name ).tasks,
			'_watch-target:done:' + name
		) );
	}

	/**
	 * Records a change, for each target whose patterns match the file.
	 *
	 * @param {string} event    The chokidar event name.
	 * @param {string} filepath The path of the changed file.
	 */
	function recordChange( event, filepath ) {
		const action = ACTIONS[ event ];
		let matched = false;

		if ( ! action ) {
			return;
		}

		filepath = path.relative( process.cwd(), filepath ).replace( /\\/g, '/' );

		targetNames.forEach( ( name ) => {
			const target = getTarget( name );

			if ( ! grunt.file.isMatch( { dot: !! target.options.dot }, target.files, filepath ) ) {
				return;
			}

			matched = true;
			queuedTargets.add( name );
			if ( target.options.reload ) {
				reload = true;
			}

			grunt.event.emit( 'watch', action, filepath, name );
		} );

		if ( matched ) {
			changes.set( filepath, action );
			clearTimeout( timer );
			timer = setTimeout( runQueued, DEBOUNCE_DELAY );
		}
	}

	/**
	 * Runs the tasks of the targets that have changes, unless tasks are running.
	 *
	 * The changes are then kept until the tasks finish and the watch task resumes.
	 */
	function runQueued() {
		const done = resume;

		timer = null;
		if ( ! done ) {
			return;
		}

		changes.forEach( ( action, filepath ) => {
			grunt.log.ok( 'File "' + filepath + '" ' + action + '.' );
		} );

		// Restart once the tasks for the other changes have run, as the new process does not see those changes.
		if ( reload ) {
			restartPending = true;
			reload = false;
		}

		runningTargets = targetNames.filter( ( name ) => queuedTargets.has( name ) );
		changes.clear();
		queuedTargets.clear();
		resume = null;
		startedAt = Date.now();

		// Run the watch task again after the tasks, to wait for the next changes.
		grunt.task.run( getTasks( runningTargets ).concat( nameArgs ) );
		done();
	}

	/**
	 * Restarts the watch task in a new process, so that it loads the changed configuration.
	 */
	function restart() {
		grunt.log.writeln( 'Reloading watch config...'.cyan );
		watcher.close();

		const child = spawn(
			process.execPath,
			process.execArgv.concat( process.argv[ 1 ], nameArgs, grunt.option.flags() ),
			{ stdio: 'inherit' }
		);
		child.on( 'exit', ( code ) => process.exit( code === null ? 1 : code ) );

		// Stop the new process with this one, which only waits for it.
		[ 'SIGINT', 'SIGTERM', 'SIGHUP' ].forEach( ( signal ) => {
			process.on( signal, () => child.kill( signal ) );
		} );
	}

	/**
	 * Keeps the watch task running when a task fails, instead of exiting.
	 *
	 * The failure is logged, and the watch task resumes, waiting for the next changes.
	 */
	function keepWatchingOnFailure() {
		/*
		 * Exit once the output can no longer be written, as when the terminal is closed. Otherwise, the error
		 * of each attempt to log would be reported as another failure, without end.
		 */
		[ process.stdout, process.stderr ].forEach( ( stream ) => {
			stream.on( 'error', ( error ) => {
				if ( error.code === 'EPIPE' ) {
					process.exit( 1 );
				}
			} );
		} );

		/*
		 * Skip the rest of the failed target's tasks, but still run those of the other targets in the run. A task
		 * can report more than one failure, after which the same targets are queued again.
		 */
		function rerun() {
			runningTargets = runningTargets.filter( ( running ) => running !== currentTarget );
			currentTarget = null;
			grunt.task.clearQueue();
			grunt.task.run( getTasks( runningTargets ).concat( nameArgs ) );
		}

		grunt.warn = grunt.fail.warn = ( error ) => {
			grunt.log.writeln( ( 'Warning: ' + ( typeof error === 'string' ? error : error.message ) ).yellow );
			if ( ! grunt.option( 'force' ) ) {
				rerun();
			}
		};
		grunt.fatal = grunt.fail.fatal = ( error ) => {
			grunt.log.writeln( ( 'Fatal error: ' + ( typeof error === 'string' ? error : error.message ) ).red );
			rerun();
		};
	}

	/**
	 * Starts watching the files of the targets.
	 *
	 * @param {Function} onReady Called once the initial scan is complete.
	 */
	function startWatching( onReady ) {
		// Compiled once, as every path found is checked against them.
		const ignored = ( grunt.config( [ taskName, 'options', 'ignored' ] ) || [] )
			.flatMap(
				// Match each ignored directory itself, so that it is not searched.
				( pattern ) => pattern.endsWith( '/**' ) ? [ pattern, pattern.slice( 0, -3 ) ] : [ pattern ]
			)
			.map( ( pattern ) => new Minimatch( pattern, { dot: true } ) );
		const patterns = targetNames.flatMap( ( name ) => getTarget( name ).files );

		watcher = chokidar.watch( getWatchPaths( patterns ), {
			ignoreInitial: true,
			ignored: ( filepath ) => {
				const relative = path.relative( process.cwd(), filepath ).replace( /\\/g, '/' );
				return ignored.some( ( matcher ) => matcher.match( relative ) );
			},
		} );

		watcher.on( 'all', recordChange );
		watcher.on( 'error', ( error ) => grunt.log.error( 'Watch error: ' + error.message ) );
		watcher.once( 'ready', onReady );
	}

	return function( target ) {
		const config = grunt.config( this.name ) || {};

		taskName = this.name;
		nameArgs = this.nameArgs;
		resume = this.async();

		if ( watcher ) {
			if ( startedAt ) {
				grunt.log.writeln( ( 'Completed in ' + ( ( Date.now() - startedAt ) / 1000 ).toFixed( 3 ) + 's at ' + new Date().toString() ).cyan + ' - Waiting...' );
				startedAt = null;
			}

			// Run the changes made while the tasks ran, now or once the debounce timer fires, before any restart.
			if ( changes.size ) {
				if ( ! timer ) {
					runQueued();
				}
				return;
			}

			if ( restartPending ) {
				restart();
			}
			return;
		}

		targetNames = target ? [ target ] : Object.keys( config ).filter( ( name ) => name !== 'options' );
		const missing = targetNames.filter( ( name ) => ! config[ name ] );
		if ( missing.length ) {
			grunt.fail.warn( 'No "' + this.name + '" targets found: ' + missing.join( ', ' ) );
			return;
		}

		keepWatchingOnFailure();
		startWatching( () => grunt.log.writeln( 'Waiting...' ) );
	};
};
