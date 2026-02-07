/* jshint node:true */
const path = require( 'path' );
const webpack = require( 'webpack' );
const TerserPlugin = require( 'terser-webpack-plugin' );
const codemirrorBanner = require( './codemirror-banner' );

module.exports = ( env = { buildTarget: 'src/' } ) => {
	const buildTarget = env.buildTarget || 'src/';
	const outputPath = path.resolve( __dirname, '../../', buildTarget, 'wp-includes/js/codemirror' );

	const optimization = {
		minimize: true,
		minimizer: [
			new TerserPlugin( {
				terserOptions: {
					format: {
						comments: /^!/,
					},
				},
				extractComments: false,
			} ),
		],
	};

	const codemirrorConfig = {
		target: 'browserslist',
		mode: 'production',
		entry: {
			'codemirror.min': './tools/vendors/codemirror-entry.js',
		},
		output: {
			path: outputPath,
			filename: '[name].js',
		},
		optimization,
		externals: {
			'csslint': 'window.CSSLint',
			'htmlhint': 'window.HTMLHint',
			'jsonlint': 'window.jsonlint',
		},
		plugins: [
			new webpack.BannerPlugin( {
				banner: codemirrorBanner,
				raw: true,
				entryOnly: true,
			} ),
		],
	};

	const espreeConfig = {
		target: 'browserslist',
		mode: 'production',
		entry: {
			'espree.min': 'espree',
		},
		output: {
			path: outputPath,
			filename: '[name].js',
			library: {
				type: 'module',
			},
		},
		experiments: {
			outputModule: true,
		},
		optimization,
	};

	return [ codemirrorConfig, espreeConfig ];
};
