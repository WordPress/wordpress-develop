var path         = require( 'path' ),
	SOURCE_DIR   = 'src/',
	mediaEntries  = {},
	mediaBuilds  = [ 'audiovideo', 'grid', 'models', 'views' ],
	webpack      = require( 'webpack' );

mediaBuilds.forEach( function ( build ) {
	var path = SOURCE_DIR + 'wp-includes/js/media';
	mediaEntries[ build ] = './' + path + '/' + build + '.manifest.js';
} );

module.exports = function( env = { environment: "production" } ) {
	const mode = env.environment;

	const mediaConfig = {
		mode,
		cache: true,
		entry: mediaEntries,
		output: {
			path: path.join( __dirname, 'src/wp-includes/js' ),
			filename: 'media-[name].js'
		},
		optimization: {
			// The files are minified by uglify afterwards. We could change this
			// later, but for now prevent doing the work twice.
			minimize: false
		}
	};

	if ( mode === 'development' ) {
		mediaConfig.watch = true;
	}

	return mediaConfig;
};
