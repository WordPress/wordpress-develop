const path         = require( 'path' );
const SOURCE_DIR   = 'src/';
const mediaEntries  = {};
const mediaBuilds  = [ 'audiovideo', 'grid', 'models', 'views' ];

mediaBuilds.forEach( function ( build ) {
	var path = SOURCE_DIR + 'wp-includes/js/media';
	mediaEntries[ build ] = './' + path + '/' + build + '.manifest.js';
} );

const baseDir = path.join( __dirname, '../../' );

module.exports = function( env = { environment: 'production', watch: false } ) {
	const mode = env.environment;

	const mediaConfig = {
		mode,
		cache: true,
		entry: mediaEntries,
		output: {
			path: path.join( baseDir, 'src/wp-includes/js' ),
			filename: 'media-[name].js'
		},
		optimization: {
			// The files are minified by uglify afterwards. We could change this
			// later, but for now prevent doing the work twice.
			minimize: false
		},
		watch: env.watch,
	};

	return mediaConfig;
};
