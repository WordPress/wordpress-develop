const mediaConfig = require( './tools/webpack/media' );
const packagesConfig = require( './tools/webpack/packages' );

module.exports = function( env = { environment: "production", watch: false, forceBuildTarget: false } ) {
	if ( ! env.watch ) {
		env.watch = false;
	}

	if ( ! env.forceBuildTarget ) {
		env.forceBuildTarget = false;
	}

	const config = [
		mediaConfig( env ),
		packagesConfig( env ),
	];

	return config;
};
