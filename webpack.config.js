const mediaConfig = require( './tools/webpack/media' );

module.exports = function (
	env = { environment: 'production', watch: false, buildTarget: false }
) {
	if ( ! env.watch ) {
		env.watch = false;
	}

	if ( ! env.buildTarget ) {
		env.buildTarget = env.mode === 'production' ? 'build/' : 'src/';
	}

	// Only building Core-specific media files.
	// Blocks, packages, script modules, and vendors are now sourced from
	// the Gutenberg build (see tools/gutenberg/copy-gutenberg-build.js).
	const config = [ mediaConfig( env ) ];

	return config;
};
