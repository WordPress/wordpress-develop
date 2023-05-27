/**
 * External dependencies
 */
const { join } = require( 'path' );

/**
 * WordPress dependencies
 */
const DependencyExtractionWebpackPlugin = require( '@wordpress/dependency-extraction-webpack-plugin' );

const baseDir = join( __dirname, '../../' );

module.exports = function( env = { environment: 'production', buildTarget: false } ) {
	const mode = env.environment;
	const suffix = mode === 'production' ? '.min' : '';
	let buildTarget = env.buildTarget ? env.buildTarget : ( mode === 'production' ? 'build' : 'src' );
	buildTarget = buildTarget  + '/wp-includes';

	const sharedConfig = {
		mode: 'development',
		target: 'browserslist',
		output: {
			filename: `[name]${ suffix }.js`,
			path: join( baseDir, `${ buildTarget }/js/dist/development` ),
		},
	};

	// See https://github.com/pmmmwh/react-refresh-webpack-plugin/blob/main/docs/TROUBLESHOOTING.md#externalising-react.
	return [
		{
			...sharedConfig,
			name: 'react-refresh-entry',
			entry: {
				'react-refresh-entry':
				'@pmmmwh/react-refresh-webpack-plugin/client/ReactRefreshEntry.js',
			},
			plugins: [ new DependencyExtractionWebpackPlugin( {
				outputFilename: `../../../assets/script-loader-[name]${ suffix }.php`,
			} ) ],
		},
		{
			...sharedConfig,
			name: 'react-refresh-runtime',
			entry: {
				'react-refresh-runtime': {
					import: 'react-refresh/runtime.js',
					library: {
						name: 'ReactRefreshRuntime',
						type: 'window',
					},
				},
			},
			plugins: [
				new DependencyExtractionWebpackPlugin( {
					useDefaults: false,
					outputFilename: `../../../assets/script-loader-[name]${ suffix }.php`
				} ),
			],
		},
	];
};
