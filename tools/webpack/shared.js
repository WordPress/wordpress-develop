/**
 * External dependencies
 */
const { DefinePlugin } = require( 'webpack' );
const TerserPlugin = require( 'terser-webpack-plugin' );
const postcss = require( 'postcss' );
const { join } = require( 'path' );

const baseDir = join( __dirname, '../../' );

const getBaseConfig = ( env ) => {
	const mode = env.environment;

	const config = {
		target: 'browserslist',
		mode,
		optimization: {
			moduleIds: mode === 'production' ? 'deterministic' : 'named',
			minimizer: [
				new TerserPlugin( {
					extractComments: false,
				} ),
			],
		},
		module: {
			rules: [
				{
					test: /\.js$/,
					use: [ 'source-map-loader' ],
					enforce: 'pre',
				},
			],
		},
		resolve: {
			modules: [ baseDir, 'node_modules' ],
			alias: {
				'lodash-es': 'lodash',
			},
		},
		stats: 'errors-only',
		watch: env.watch,
		plugins: [
			new DefinePlugin( {
				// Inject the `IS_GUTENBERG_PLUGIN` global, used for feature flagging.
				'globalThis.IS_GUTENBERG_PLUGIN': JSON.stringify( false ),
				// Inject the `IS_WORDPRESS_CORE` global, used for feature flagging.
				'globalThis.IS_WORDPRESS_CORE': JSON.stringify( true ),
				// Inject the `SCRIPT_DEBUG` global, used for dev versions of JavaScript.
				'globalThis.SCRIPT_DEBUG': JSON.stringify(
					mode === 'development'
				),
			} ),
		],
	};

	if ( mode === 'development' && env.buildTarget === 'build/' ) {
		config.mode = 'production';
		config.optimization = {
			minimize: false,
			moduleIds: 'deterministic',
		};
	} else if ( mode !== 'production' ) {
		config.devtool = process.env.SOURCEMAP || 'source-map';
	}

	return config;
};

const stylesTransform = ( mode ) => ( content ) => {
	return postcss( [
		require( 'cssnano' )( {
			preset:
				mode === 'production'
					? 'default'
					: [
							'default',
							{
								discardComments: {
									removeAll:
										! content.includes( 'Copyright' ) &&
										! content.includes( 'License' ),
								},
								normalizeWhitespace: false,
							},
					  ],
		} ),
	] )
		.process( content, { from: 'src/app.css', to: 'dest/app.css' } )
		.then( ( result ) => result.css );
};

const normalizeJoin = ( ...paths ) => join( ...paths ).replace( /\\/g, '/' );

const BUNDLED_PACKAGES = [
	'@wordpress/dataviews',
	'@wordpress/icons',
	'@wordpress/interface',
	'@wordpress/interactivity',
	'@wordpress/sync',
];
const MODULES = [
	'@wordpress/interactivity',
	'@wordpress/interactivity-router',
];
const SCRIPT_AND_MODULE_DUAL_PACKAGES = [
	'@wordpress/a11y',
	'@wordpress/block-library',
];
const WORDPRESS_NAMESPACE = '@wordpress/';

module.exports = {
	baseDir,
	getBaseConfig,
	normalizeJoin,
	stylesTransform,
	BUNDLED_PACKAGES,
	MODULES,
	SCRIPT_AND_MODULE_DUAL_PACKAGES,
	WORDPRESS_NAMESPACE,
};
