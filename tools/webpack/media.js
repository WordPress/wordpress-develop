/**
 * External dependencies
 */
const TerserPlugin = require( 'terser-webpack-plugin' );

/**
 * Internal dependencies
 */
const { baseDir } = require( './shared' );

module.exports = function( env = { environment: 'production', watch: false, buildTarget: false } ) {
	const entry = {
		[ env.buildTarget + 'wp-includes/js/media-audiovideo.js' ]: ['./src/js/_enqueues/wp/media/audiovideo.js'],
		[ env.buildTarget + 'wp-includes/js/media-audiovideo.min.js' ]: ['./src/js/_enqueues/wp/media/audiovideo.js'],
		[ env.buildTarget + 'wp-includes/js/media-grid.js' ]: ['./src/js/_enqueues/wp/media/grid.js'],
		[ env.buildTarget + 'wp-includes/js/media-grid.min.js' ]: ['./src/js/_enqueues/wp/media/grid.js'],
		[ env.buildTarget + 'wp-includes/js/media-models.js' ]: ['./src/js/_enqueues/wp/media/models.js'],
		[ env.buildTarget + 'wp-includes/js/media-models.min.js' ]: ['./src/js/_enqueues/wp/media/models.js'],
		[ env.buildTarget + 'wp-includes/js/media-views.js' ]: ['./src/js/_enqueues/wp/media/views.js'],
		[ env.buildTarget + 'wp-includes/js/media-views.min.js' ]: ['./src/js/_enqueues/wp/media/views.js'],
	};

	const mediaConfig = {
		target: 'browserslist',
		mode: "production",
		cache: true,
		entry,
		output: {
			path: baseDir,
			filename: '[name]',
		},
		optimization: {
			minimize: true,
			moduleIds: 'deterministic',
			minimizer: [
				new TerserPlugin( {
					include: /\.min\.js$/,
					extractComments: false,
				} ),
			]
		},
		watch: env.watch,
	};

	return mediaConfig;
};
