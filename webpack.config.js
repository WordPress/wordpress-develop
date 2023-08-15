const blocksConfig = require( './tools/webpack/blocks' );
const developmentConfig = require( './tools/webpack/development' );
const mediaConfig = require( './tools/webpack/media' );
const packagesConfig = require( './tools/webpack/packages' );

module.exports = function( env = { environment: "production", watch: false, buildTarget: false } ) {
	if ( ! env.watch ) {
		env.watch = false;
	}

	if ( ! env.buildTarget ) {
		env.buildTarget = ( env.mode === 'production' ? 'build/' : 'src/' );
	}

	const config = [
		blocksConfig( env ),
		...developmentConfig( env ),
		mediaConfig( env ),
		packagesConfig( env ),
	];

	return config;
};
