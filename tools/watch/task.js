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
 * changes made with it, or while its tasks ran, have run. The new process is
 * handed the changes made while it starts, which the previous one still watches.
 *
 * A target's tasks must not write files that match its own patterns, which
 * would run them again without end.
 *
 * @package WordPress
 */

const path = require( 'path' );
const { spawn } = require( 'child_process' );
const chokidar = require( 'chokidar' );

/**
 * Time to wait for more changes before running the tasks, in milliseconds.
 *
 * An editor or a version control checkout often changes several files at once.
 */
const DEBOUNCE_DELAY = 250;

/**
 * Environment variable set for a watch process started by a reload, which takes over the changes that the
 * previous process saw while it started.
 */
const HAND_OFF_ENV = 'WP_WATCH_HAND_OFF';

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
	let failureReported = false;

	// The changes seen while restarting, as chokidar event names and paths, to hand to the new process.
	let handOffChanges = null;

	// Changed files since the tasks last ran, keyed by path, and the targets to run for them.
	const changes = new Map();
	const queuedTargets = new Set();

	// The targets whose tasks are queued in the current run, until each one's tasks finish.
	let runningTargets = [];

	grunt.registerTask( '_watch-target', 'Marks the tasks of a watch target as started or finished.', ( step, name ) => {
		failureReported = false;
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

		// Once restarting, the changes are for the new process, which is handed them when it is ready.
		if ( handOffChanges ) {
			handOffChanges.push( [ event, filepath ] );
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

		/*
		 * Keep watching until the new process is ready, which can take several seconds for its initial scan, and
		 * hand it the changes seen meanwhile, which it would otherwise miss.
		 */
		handOffChanges = [];

		const child = spawn(
			process.execPath,
			process.execArgv.concat( process.argv[ 1 ], nameArgs, grunt.option.flags() ),
			{
				stdio: [ 'inherit', 'inherit', 'inherit', 'ipc' ],
				env: { ...process.env, [ HAND_OFF_ENV ]: '1' },
			}
		);
		child.on( 'message', ( message ) => {
			if ( ! message || 'watch-ready' !== message.type ) {
				return;
			}
			watcher.close();
			child.send( { type: 'watch-changes', changes: handOffChanges }, () => child.disconnect() );
			handOffChanges = [];
		} );
		child.on( 'exit', ( code ) => process.exit( code === null ? 1 : code ) );

		// Stop the new process with this one, which only waits for it.
		[ 'SIGINT', 'SIGTERM', 'SIGHUP' ].forEach( ( signal ) => {
			process.on( signal, () => child.kill( signal ) );
		} );
	}

	/**
	 * Takes over the changes that the previous watch process saw while this one started, when started by a reload.
	 *
	 * The previous process keeps watching until this one reports that it is ready, then hands over its changes.
	 */
	function takeOverChanges() {
		if ( ! process.send || ! process.env[ HAND_OFF_ENV ] ) {
			return;
		}

		process.on( 'message', ( message ) => {
			if ( message && 'watch-changes' === message.type ) {
				message.changes.forEach( ( [ event, filepath ] ) => recordChange( event, filepath ) );
			}
		} );
		process.send( { type: 'watch-ready' } );
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
		 * Skip the rest of the failed target's tasks, but still run those of the other targets in the run.
		 */
		function rerun() {
			runningTargets = runningTargets.filter( ( running ) => running !== currentTarget );
			currentTarget = null;
			grunt.task.clearQueue();
			grunt.task.run( getTasks( runningTargets ).concat( nameArgs ) );
		}

		/*
		 * Unlike grunt's own handlers, which exit, these return to the failed task, which can then fail again on
		 * the result it did not get, as grunt-contrib-uglify does after "Uglification failed.". Only the first
		 * failure is reported, until the next task starts.
		 */
		function reportFailure( prefix, error ) {
			const message = prefix + ( typeof error === 'string' ? error : error.message );

			if ( failureReported ) {
				grunt.verbose.writeln( message );
				return;
			}
			failureReported = true;
			grunt.log.writeln( 'Fatal error: ' === prefix ? message.red : message.yellow );
			rerun();
		}

		grunt.warn = grunt.fail.warn = ( error ) => {
			if ( grunt.option( 'force' ) ) {
				grunt.log.writeln( ( 'Warning: ' + ( typeof error === 'string' ? error : error.message ) ).yellow );
				return;
			}
			reportFailure( 'Warning: ', error );
		};
		grunt.fatal = grunt.fail.fatal = ( error ) => {
			reportFailure( 'Fatal error: ', error );
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
			// Grunt's own minimatch, which it provides for its file matching.
			.map( ( pattern ) => new grunt.file.minimatch.Minimatch( pattern, { dot: true } ) );
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
		failureReported = false;

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
		startWatching( () => {
			grunt.log.writeln( 'Waiting...' );
			takeOverChanges();
		} );
	};
};
