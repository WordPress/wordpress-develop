const UglifyJsPlugin = require( 'uglifyjs-webpack-plugin' );

var path        	= require( 'path' ),
	webpack       	= require( 'webpack' ),
	admin_files   	= {},
	include_files 	= {};

include_files = {
	'build/wp-includes/js/media-audiovideo.js': ['./src/js/_enqueues/wp/media/audiovideo.js'],
	'build/wp-includes/js/media-audiovideo.min.js': ['./src/js/_enqueues/wp/media/audiovideo.js'],
	'build/wp-includes/js/media-grid.js': ['./src/js/_enqueues/wp/media/grid.js'],
	'build/wp-includes/js/media-grid.min.js': ['./src/js/_enqueues/wp/media/grid.js'],
	'build/wp-includes/js/media-models.js': ['./src/js/_enqueues/wp/media/models.js'],
	'build/wp-includes/js/media-models.min.js': ['./src/js/_enqueues/wp/media/models.js'],
	'build/wp-includes/js/media-views.js': ['./src/js/_enqueues/wp/media/views.js'],
	'build/wp-includes/js/media-views.min.js': ['./src/js/_enqueues/wp/media/views.js'],
};

module.exports = function( env = { environment: "production" } ) {
	const mode = env.environment;

	const mediaConfig = {
		mode,
		cache: true,
		entry: Object.assign( admin_files, include_files ),
		output: {
			path: path.resolve( __dirname ),
			filename: '[name]',
		},
		optimization: {
			minimize: true,
			minimizer: [
				new UglifyJsPlugin( {
					include: /\.min\.js$/,
				} )
			]
		},
	};

	return mediaConfig;
};
