const mediaConfig = require( './tools/webpack/media' );
const blocksConfig = require( './tools/webpack/blocks' );
const packagesConfig = require( './tools/webpack/packages' );

module.exports = function( env = { environment: "production", watch: false, buildTarget: false } ) {
	if ( ! env.watch ) {
		env.watch = false;
	}

	if ( ! env.buildTarget ) {
		env.buildTarget = ( env.mode === 'production' ? 'build/' : 'src/' );
	}

	const config = [
		mediaConfig( env ),
		packagesConfig( env ),
		blocksConfig( env ),
	];

	return config;
};
