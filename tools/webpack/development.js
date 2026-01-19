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
 * These scripts enable hot module replacement for plugins
 * using `@wordpress/scripts` with the `--hot` flag.
 *
 * Returns two separate configs:
 * 1. Runtime config - bundles react-refresh/runtime and exposes it as window.ReactRefreshRuntime
 * 2. Entry config - uses the window global as an external to ensure both scripts share the same runtime instance
 *
 * @param {Object} env             Environment options.
 * @param {string} env.buildTarget Build target directory.
 * @param {boolean} env.watch      Whether to watch for changes.
 * @return {Object[]} Array of webpack configuration objects.
 */
module.exports = function( env = { buildTarget: 'src/', watch: false } ) {
	const buildTarget = env.buildTarget || 'src/';

	const baseConfig = {
		target: 'browserslist',
		// Must use development mode to preserve process.env.NODE_ENV checks
		// in the source files. These scripts are only used during development.
		mode: 'development',
		devtool: false,
		cache: true,
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

	// Config for react-refresh-runtime.js - bundles the runtime and exposes
	// it as window.ReactRefreshRuntime. No externals - this creates the global.
	const runtimeConfig = {
		...baseConfig,
		name: 'runtime',
		entry: {
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
		},
	};

	// Config for react-refresh-entry.js - uses window.ReactRefreshRuntime as an
	// external instead of bundling its own copy. This ensures the hooks set up
	// by the entry are on the same runtime instance that plugins use for
	// performReactRefresh().
	const entryConfig = {
		...baseConfig,
		name: 'entry',
		entry: {
			[ buildTarget + 'wp-includes/js/dist/development/react-refresh-entry.js' ]:
				'@pmmmwh/react-refresh-webpack-plugin/client/ReactRefreshEntry.js',
			[ buildTarget + 'wp-includes/js/dist/development/react-refresh-entry.min.js' ]:
				'@pmmmwh/react-refresh-webpack-plugin/client/ReactRefreshEntry.js',
		},
		externals: {
			'react-refresh/runtime': 'ReactRefreshRuntime',
		},
	};

	return [ runtimeConfig, entryConfig ];
};
