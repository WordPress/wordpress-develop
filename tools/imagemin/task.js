/**
 * Image Optimization Task
 *
 * Losslessly optimizes the images matched by the `imagemin` task configuration,
 * in place. It replaces grunt-contrib-imagemin, last released in 2020, which
 * pins versions of its image binaries that were published in 2018.
 *
 * @package WordPress
 */

const os = require( 'os' );
const path = require( 'path' );
const { spawnSync } = require( 'child_process' );
const { compressJpeg, losslessCompressPng } = require( '@napi-rs/image' );

/**
 * Builds the imagemin task.
 *
 * @param {Object} grunt The grunt instance.
 *
 * @return {Function} The task function.
 */
module.exports = ( grunt ) => function() {
	const done = this.async();
	const files = this.files.slice();
	const options = this.options( {
		/*
		 * Optimizers keep finding a handful of bytes in images that are already
		 * optimal. Rewriting a versioned binary file for that is not worth it.
		 */
		minSavedBytes: 64
	} );
	let gifsicle;

	/**
	 * Resolves the gifsicle binary, importing the package at most once.
	 *
	 * @return {Promise<string|boolean>} Path of the binary, or false when the
	 *                                   platform has no prebuilt binary.
	 */
	function resolveGifsicle() {
		if ( undefined === gifsicle ) {
			// Imported rather than required because the package is ESM only.
			gifsicle = import( '@343dev/gifsicle' ).then(
				( imported ) => imported.default,
				() => {
					grunt.log.warn( 'No gifsicle binary is available for this platform, leaving GIF images untouched.' );
					return false;
				}
			);
		}

		return gifsicle;
	}

	/**
	 * Optimizes a single image.
	 *
	 * @param {string} src      Path of the image, used to pick the optimizer.
	 * @param {Buffer} contents Contents of the image.
	 *
	 * @return {Promise<Buffer>} The optimized image.
	 */
	async function optimize( src, contents ) {
		const extension = path.extname( src ).toLowerCase();

		if ( '.png' === extension ) {
			return losslessCompressPng( contents );
		}

		if ( '.jpg' === extension || '.jpeg' === extension ) {
			/*
			 * Quality 100 rewrites the file as progressive and optimizes its
			 * Huffman tables, leaving the decoded pixels untouched.
			 */
			return compressJpeg( contents, { quality: 100 } );
		}

		const binary = await resolveGifsicle();

		if ( ! binary ) {
			return contents;
		}

		const result = spawnSync( binary, [ '--optimize=3', '--interlace', '--output', '-', src ], { maxBuffer: Infinity } );

		if ( result.error ) {
			throw result.error;
		}

		if ( 0 !== result.status ) {
			throw new Error( result.stderr.toString().trim() );
		}

		return result.stdout;
	}

	/**
	 * Optimizes images from the queue until it is empty.
	 *
	 * @return {Promise<Object>} The number of images optimized and the bytes saved.
	 */
	async function work() {
		let file,
			optimized = 0,
			savedBytes = 0;

		while ( ( file = files.shift() ) ) {
			const src = file.src[ 0 ];
			const contents = grunt.file.read( src, { encoding: null } );
			let result;

			try {
				result = await optimize( src, contents );
			} catch ( error ) {
				error.message = src + ': ' + error.message;
				grunt.warn( error );
				continue;
			}

			const saved = contents.length - result.length;
			const output = saved >= options.minSavedBytes ? result : contents;

			if ( output !== contents ) {
				optimized++;
				savedBytes += saved;
				grunt.verbose.writeln( src + ' (saved ' + saved + ' bytes)' );
			} else {
				grunt.verbose.writeln( src + ' (already optimized)' );
			}

			// Unchanged images are only written when they are written elsewhere.
			if ( output !== contents || file.dest !== src ) {
				grunt.file.write( file.dest, output );
			}
		}

		return { optimized: optimized, savedBytes: savedBytes };
	}

	/**
	 * Optimizes every image in the task configuration.
	 *
	 * @return {Promise<void>}
	 */
	async function run() {
		const total = files.length;
		const workers = Math.min( Math.max( os.cpus().length, 1 ), total );

		// Several images at a time, as grunt-contrib-imagemin also did.
		const results = await Promise.allSettled( Array.from( { length: workers }, work ) );
		const failure = results.find( ( result ) => 'rejected' === result.status );

		// Failures are reported once no worker can still be writing a file.
		if ( failure ) {
			throw failure.reason;
		}

		const optimized = results.reduce( ( sum, result ) => sum + result.value.optimized, 0 );
		const savedBytes = results.reduce( ( sum, result ) => sum + result.value.savedBytes, 0 );

		grunt.log.writeln( 'Optimized ' + optimized + ' of ' + total + ' images, saving ' + savedBytes + ' bytes.' );
	}

	run().then( done, done );
};
