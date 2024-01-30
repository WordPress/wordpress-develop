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
		module: {
			rules: [
				{
					test: /\.(j|t)sx?$/,
					use: [
						{
							loader: require.resolve( 'babel-loader' ),
							options: {
								cacheDirectory:
									process.env.BABEL_CACHE_DIRECTORY || true,
								babelrc: false,
								configFile: false,
								presets: [
									[
										'@babel/preset-react',
										{
											runtime: 'automatic',
											importSource: 'preact',
										},
									],
								],
							},
						},
					],
				},
			],
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
