/**
 * WordPress dependencies
 */
const DependencyExtractionPlugin = require( '@wordpress/dependency-extraction-webpack-plugin' );

/**
 * Internal dependencies
 */
const {
	baseDir,
	getBaseConfig,
	normalizeJoin,
	MODULES,
	WORDPRESS_NAMESPACE,
} = require( './shared' );

module.exports = function (
	env = { environment: 'production', watch: false, buildTarget: false }
) {
	const mode = env.environment;
	const suffix = mode === 'production' ? '.min' : '';
	let buildTarget = env.buildTarget
		? env.buildTarget
		: mode === 'production'
		? 'build'
		: 'src';
	buildTarget = buildTarget + '/wp-includes';

	const baseConfig = getBaseConfig( env );
	const config = {
		...baseConfig,
		entry: MODULES.map( ( packageName ) =>
			packageName.replace( WORDPRESS_NAMESPACE, '' )
		).reduce( ( memo, packageName ) => {
			memo[ packageName ] = {
				import: normalizeJoin(
					baseDir,
					`node_modules/@wordpress/${ packageName }`
				),
			};

			return memo;
		}, {} ),
		experiments: {
			outputModule: true,
		},
		output: {
			devtoolNamespace: 'wp',
			filename: `[name]${ suffix }.js`,
			path: normalizeJoin( baseDir, `${ buildTarget }/js/dist` ),
			library: {
				type: 'module',
			},
			environment: { module: true },
		},
		externalsType: 'module',
		externals: {
			'@wordpress/interactivity': '@wordpress/interactivity',
			'@wordpress/interactivity-router':
				'import @wordpress/interactivity-router',
		},
		plugins: [
			...baseConfig.plugins,
			new DependencyExtractionPlugin( {
				injectPolyfill: false,
				useDefaults: false,
			} ),
		],
	};

	return config;
};
