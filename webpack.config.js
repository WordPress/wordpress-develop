const mediaConfig = require( './tools/webpack/media' );
const packagesConfig = require( './tools/webpack/packages' );

module.exports = function( env = { environment: "production", watch: false } ) {
	if ( ! env.watch ) {
		env.watch = false;
	}

	const config = [
		mediaConfig( env ),
		packagesConfig( env ),
	];

	return config;
};
