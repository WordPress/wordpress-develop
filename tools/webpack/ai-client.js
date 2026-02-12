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
		[ env.buildTarget + 'wp-includes/js/dist/ai-client.js' ]: [ './src/js/_enqueues/wp/ai-client/index.js' ],
		[ env.buildTarget + 'wp-includes/js/dist/ai-client.min.js' ]: [ './src/js/_enqueues/wp/ai-client/index.js' ],
	};

	const aiClientConfig = {
		target: 'browserslist',
		mode: 'production',
		cache: true,
		entry,
		output: {
			path: baseDir,
			filename: '[name]',
		},
		externals: {
			'@wordpress/api-fetch': 'wp.apiFetch',
			'@wordpress/data': 'wp.data',
		},
		optimization: {
			minimize: true,
			moduleIds: 'deterministic',
			minimizer: [
				new TerserPlugin( {
					include: /\.min\.js$/,
					extractComments: false,
				} ),
			],
		},
		watch: env.watch,
	};

	return aiClientConfig;
};
