/* jshint node:true */
/* jshint esversion: 6 */
const path = require( 'path' );
const webpack = require( 'webpack' );
const TerserPlugin = require( 'terser-webpack-plugin' );
const codemirrorBanner = require( './codemirror-banner' );

module.exports = ( env = { buildTarget: 'src/' } ) => {
	const buildTarget = env.buildTarget || 'src/';

	return {
		target: 'browserslist',
		mode: 'production',
		entry: './tools/vendors/codemirror-entry.js',
		output: {
			path: path.resolve( __dirname, '../../', buildTarget, 'wp-includes/js/codemirror' ),
			filename: 'codemirror.min.js',
		},
		optimization: {
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
		},
		externals: {
			'csslint': 'window.CSSLint',
			'htmlhint': 'window.HTMLHint',
			'jshint': 'window.JSHINT',
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
};
