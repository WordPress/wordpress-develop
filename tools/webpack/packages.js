/**
 * External dependencies
 */
const { DefinePlugin } = require( 'webpack' );
const CopyWebpackPlugin = require( 'copy-webpack-plugin' );
const LiveReloadPlugin = require( 'webpack-livereload-plugin' );
const UglifyJS = require( 'uglify-js' );

/**
 * WordPress dependencies
 */
const {
	camelCaseDash,
} = require( '@wordpress/dependency-extraction-webpack-plugin/lib/util' );
const DependencyExtractionPlugin = require( '@wordpress/dependency-extraction-webpack-plugin' );

/**
 * Internal dependencies
 */
const { normalizeJoin, stylesTransform, baseConfig, baseDir } = require( './shared' );
const { dependencies } = require( '../../package' );

const exportDefaultPackages = [
	'api-fetch',
	'deprecated',
	'dom-ready',
	'redux-routine',
	'token-list',
	'server-side-render',
	'shortcode',
	'warning',
];

/**
 * Maps vendors to copy commands for the CopyWebpackPlugin.
 *
 * @param {Object} vendors     Vendors to include in the vendor folder.
 * @param {string} buildTarget The folder in which to build the packages.
 *
 * @return {Object[]} Copy object suitable for the CopyWebpackPlugin.
 */
function mapVendorCopies( vendors, buildTarget ) {
	return Object.keys( vendors ).map( ( filename ) => ( {
		from: normalizeJoin(baseDir, `node_modules/${ vendors[ filename ] }` ),
		to: normalizeJoin(baseDir, `${ buildTarget }/js/dist/vendor/${ filename }` ),
	} ) );
}

module.exports = function( env = { environment: 'production', watch: false, buildTarget: false } ) {
	const mode = env.environment;
	const suffix = mode === 'production' ? '.min' : '';
	let buildTarget = env.buildTarget ? env.buildTarget : ( mode === 'production' ? 'build' : 'src' );
	buildTarget = buildTarget  + '/wp-includes';

	const WORDPRESS_NAMESPACE = '@wordpress/';
	const BUNDLED_PACKAGES = [ '@wordpress/icons', '@wordpress/interface' ];
	const packages = Object.keys( dependencies )
		.filter( ( packageName ) =>
 			! BUNDLED_PACKAGES.includes( packageName ) &&
 			packageName.startsWith( WORDPRESS_NAMESPACE )
 		)
		.map( ( packageName ) => packageName.replace( WORDPRESS_NAMESPACE, '' ) );

	const vendors = {
		'lodash.js': 'lodash/lodash.js',
		'wp-polyfill.js': '@wordpress/babel-preset-default/build/polyfill.js',
		'wp-polyfill-fetch.js': 'whatwg-fetch/dist/fetch.umd.js',
		'wp-polyfill-element-closest.js': 'element-closest/element-closest.js',
		'wp-polyfill-node-contains.js': 'polyfill-library/polyfills/__dist/Node.prototype.contains/raw.js',
		'wp-polyfill-url.js': 'core-js-url-browser/url.js',
		'wp-polyfill-dom-rect.js': 'polyfill-library/polyfills/__dist/DOMRect/raw.js',
		'wp-polyfill-formdata.js': 'formdata-polyfill/FormData.js',
		'wp-polyfill-object-fit.js': 'objectFitPolyfill/src/objectFitPolyfill.js',
		'moment.js': 'moment/moment.js',
		'react.js': 'react/umd/react.development.js',
		'react-dom.js': 'react-dom/umd/react-dom.development.js',
		'regenerator-runtime.js': 'regenerator-runtime/runtime.js',
	};

	const minifiedVendors = {
		'lodash.min.js': 'lodash/lodash.min.js',
		'wp-polyfill.min.js': '@wordpress/babel-preset-default/build/polyfill.min.js',
		'wp-polyfill-formdata.min.js': 'formdata-polyfill/formdata.min.js',
		'wp-polyfill-url.min.js': 'core-js-url-browser/url.min.js',
		'wp-polyfill-object-fit.min.js': 'objectFitPolyfill/dist/objectFitPolyfill.min.js',
		'moment.min.js': 'moment/min/moment.min.js',
		'react.min.js': 'react/umd/react.production.min.js',
		'react-dom.min.js': 'react-dom/umd/react-dom.production.min.js',
	};

	const minifyVendors = {
		'regenerator-runtime.min.js': 'regenerator-runtime/runtime.js',
		'wp-polyfill-fetch.min.js': 'whatwg-fetch/dist/fetch.umd.js',
		'wp-polyfill-element-closest.min.js': 'element-closest/element-closest.js',
		'wp-polyfill-node-contains.min.js': 'polyfill-library/polyfills/__dist/Node.prototype.contains/raw.js',
		'wp-polyfill-dom-rect.min.js': 'polyfill-library/polyfills/__dist/DOMRect/raw.js',
	};

	const phpFiles = {
		'block-serialization-default-parser/parser.php': 'wp-includes/class-wp-block-parser.php',
	};

	const developmentCopies = mapVendorCopies( vendors, buildTarget );
	const minifiedCopies = mapVendorCopies( minifiedVendors, buildTarget );
	const minifyCopies = mapVendorCopies( minifyVendors, buildTarget ).map( ( copyCommand ) => {
		return {
			...copyCommand,
			transform: ( content ) => {
				return UglifyJS.minify( content.toString() ).code;
			},
		};
	} );

	let vendorCopies = mode === "development" ? developmentCopies : [ ...minifiedCopies, ...minifyCopies ];

	let cssCopies = packages.map( ( packageName ) => ( {
		from: normalizeJoin(baseDir, `node_modules/@wordpress/${ packageName }/build-style/*.css` ),
		to: normalizeJoin(baseDir, `${ buildTarget }/css/dist/${ packageName }/[name]${ suffix }.css` ),
		transform: stylesTransform( mode ),
		noErrorOnMissing: true,
	} ) );

	const phpCopies = Object.keys( phpFiles ).map( ( filename ) => ( {
		from: normalizeJoin(baseDir, `node_modules/@wordpress/${ filename }` ),
		to: normalizeJoin(baseDir, `src/${ phpFiles[ filename ] }` ),
	} ) );

	const config = {
		...baseConfig( env ),
		entry: packages.reduce( ( memo, packageName ) => {
			memo[ packageName ] = {
				import: normalizeJoin(baseDir, `node_modules/@wordpress/${ packageName }` ),
				library: {
					name: [ 'wp', camelCaseDash( packageName ) ],
					type: 'window',
					export: exportDefaultPackages.includes( packageName )
						? 'default'
						: undefined,
				},
			};

			return memo;
		}, {} ),
		output: {
			devtoolNamespace: 'wp',
			filename: `[name]${ suffix }.js`,
			path: normalizeJoin(baseDir, `${ buildTarget }/js/dist` ),
		},
		plugins: [
			new DefinePlugin( {
				// Inject the `IS_GUTENBERG_PLUGIN` global, used for feature flagging.
				'process.env.IS_GUTENBERG_PLUGIN': false,
				'process.env.FORCE_REDUCED_MOTION': JSON.stringify(
					process.env.FORCE_REDUCED_MOTION
				),
			} ),
			new DependencyExtractionPlugin( {
				injectPolyfill: true,
				combineAssets: true,
				combinedOutputFile: `../../assets/script-loader-packages${ suffix }.php`,
			} ),
			new CopyWebpackPlugin( {
				patterns: [
					...vendorCopies,
					...cssCopies,
					...phpCopies,
				],
			} ),
		],
	};

	if ( config.mode === 'development' ) {
		config.plugins.push( new LiveReloadPlugin( { port: process.env.WORDPRESS_LIVE_RELOAD_PORT || 35729 } ) );
	}

	return config;
};
