const mediaConfig = require( './tools/webpack/media' );
const developmentConfig = require( './tools/webpack/development' );

module.exports = function (
	env = { environment: 'production', watch: false, buildTarget: false }
) {
	if ( ! env.watch ) {
		env.watch = false;
	}

	if ( ! env.buildTarget ) {
		env.buildTarget = env.mode === 'production' ? 'build/' : 'src/';
	}

	// Only building Core-specific media files and development scripts.
	// Blocks, packages, script modules, and vendors are now sourced from
	// the Gutenberg build (see tools/gutenberg/copy-gutenberg-build.js).
	// Note: developmentConfig returns an array of configs, so we spread it.
	const config = [
		mediaConfig( env ),
		...developmentConfig( env ),
	];

	return config;
};
