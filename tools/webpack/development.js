/**
 * External dependencies
 */
const TerserPlugin = require( 'terser-webpack-plugin' );

/**
 * Internal dependencies
 */
const { baseDir } = require( './shared' );

/**
 * Webpack configuration for development scripts (React Refresh).
 *
 * These scripts enable hot module replacement for block development
 * when using `@wordpress/scripts` with the `--hot` flag.
 *
 * @param {Object} env             Environment options.
 * @param {string} env.buildTarget Build target directory.
 * @param {boolean} env.watch      Whether to watch for changes.
 * @return {Object} Webpack configuration object.
 */
module.exports = function( env = { buildTarget: 'src/', watch: false } ) {
	const buildTarget = env.buildTarget || 'src/';

	const entry = {
		// React Refresh runtime - exposes ReactRefreshRuntime global.
		[ buildTarget + 'wp-includes/js/dist/development/react-refresh-runtime.js' ]: {
			import: 'react-refresh/runtime',
			library: {
				name: 'ReactRefreshRuntime',
				type: 'window',
			},
		},
		[ buildTarget + 'wp-includes/js/dist/development/react-refresh-runtime.min.js' ]: {
			import: 'react-refresh/runtime',
			library: {
				name: 'ReactRefreshRuntime',
				type: 'window',
			},
		},
		// React Refresh entry - injects runtime into global hook before React loads.
		[ buildTarget + 'wp-includes/js/dist/development/react-refresh-entry.js' ]:
			'@pmmmwh/react-refresh-webpack-plugin/client/ReactRefreshEntry.js',
		[ buildTarget + 'wp-includes/js/dist/development/react-refresh-entry.min.js' ]:
			'@pmmmwh/react-refresh-webpack-plugin/client/ReactRefreshEntry.js',
	};

	return {
		target: 'browserslist',
		// Must use development mode to preserve process.env.NODE_ENV checks
		// in the source files. These scripts are only used during development.
		mode: 'development',
		devtool: false,
		cache: true,
		entry,
		output: {
			path: baseDir,
			filename: '[name]',
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
};
