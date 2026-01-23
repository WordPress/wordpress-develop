/* jshint node:true */
/* jshint esversion: 6 */
const path = require( 'path' );
const webpack = require( 'webpack' );
const TerserPlugin = require( 'terser-webpack-plugin' );
const pkg = require( '../../package.json' );

const config = {
	mode: 'production',
	entry: './tools/vendors/codemirror-entry.js',
	output: {
		path: path.resolve( __dirname, '../../src/wp-includes/js/codemirror' ),
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
			banner: `/*! This file is auto-generated from CodeMirror - v${ pkg.dependencies.codemirror }\n` +
				`\n` +
				`CodeMirror, copyright (c) by Marijn Haverbeke and others\n` +
				`Distributed under an MIT license: http://codemirror.net/LICENSE\n` +
				`\n` +
				`This is CodeMirror (http://codemirror.net), a code editor\n` +
				`implemented in JavaScript on top of the browser's DOM.\n` +
				`\n` +
				`You can find some technical background for some of the code below\n` +
				`at http://marijnhaverbeke.nl/blog/#cm-internals .\n` +
				`*/\n`,
			raw: true,
			entryOnly: true,
		} ),
	],
};

module.exports = config;
