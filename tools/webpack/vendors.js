/**
 * External dependencies
 */
const { join } = require( 'path' );

const importedVendors = {
	'react-jsx-runtime': {
		import: 'react/jsx-runtime',
		global: 'ReactJSXRuntime',
	},
};

module.exports = function (
	env = { environment: 'production', watch: false, buildTarget: false }
) {
	const mode = env.environment;
	let buildTarget = env.buildTarget
		? env.buildTarget
		: mode === 'production'
		? 'build'
		: 'src';
	buildTarget = buildTarget + '/wp-includes/js/dist/vendor/';
	return [
		...Object.entries( importedVendors ).flatMap( ( [ name, config ] ) => {
			return [ 'production', 'development' ].map( ( currentMode ) => {
				return {
					mode: currentMode,
					target: 'browserslist',
					output: {
						filename:
							currentMode === 'development'
								? `[name].js`
								: `[name].min.js`,
						path: join( __dirname, '..', '..', buildTarget ),
					},
					entry: {
						[ name ]: {
							import: config.import,
							library: {
								name: config.global,
								type: 'window',
							},
						},
					},

					externals: {
						react: 'React',
					},
				};
			} );
		} ),
	];
};
