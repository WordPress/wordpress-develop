const path = require( 'path' );
const TerserPlugin = require( 'terser-webpack-plugin' );

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
						comments: false,
					},
				},
				extractComments: false,
			} ),
		],
	},
};

module.exports = config;
