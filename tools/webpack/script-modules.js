/**
 * External dependencies
 */
const { createRequire } = require( 'node:module' );
const { dirname } = require( 'node:path' );

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
	SCRIPT_AND_MODULE_DUAL_PACKAGES,
	WORDPRESS_NAMESPACE,
} = require( './shared' );

/** @type {Map<string, string>} */
const scriptModules = new Map();
for ( const packageName of MODULES.concat( SCRIPT_AND_MODULE_DUAL_PACKAGES ) ) {
	const packageRequire = createRequire(
		`${ dirname( require.resolve( `${ packageName }/package.json` ) ) }/`
	);

	const depPackageJson = packageRequire( './package.json' );
	if ( ! Object.hasOwn( depPackageJson, 'wpScriptModuleExports' ) ) {
		continue;
	}

	const moduleName = packageName.substring( WORDPRESS_NAMESPACE.length );
	let { wpScriptModuleExports } = depPackageJson;

	// Special handling for { "wpScriptModuleExports": "./build-module/index.js" }.
	if ( typeof wpScriptModuleExports === 'string' ) {
		wpScriptModuleExports = { '.': wpScriptModuleExports };
	}

	if ( Object.getPrototypeOf( wpScriptModuleExports ) !== Object.prototype ) {
		throw new Error( 'wpScriptModuleExports must be an object' );
	}

	for ( const [ exportName, exportPath ] of Object.entries(
		wpScriptModuleExports
	) ) {
		if ( typeof exportPath !== 'string' ) {
			throw new Error( 'wpScriptModuleExports paths must be strings' );
		}

		if ( ! exportPath.startsWith( './' ) ) {
			throw new Error(
				'wpScriptModuleExports paths must start with "./"'
			);
		}

		const name =
			exportName === '.' ? 'index' : exportName.replace( /^\.\/?/, '' );

		scriptModules.set(
			`${ moduleName }/${ name }`,
			packageRequire.resolve( exportPath )
		);
	}
}

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
		entry: Object.fromEntries( scriptModules.entries() ),
		experiments: {
			outputModule: true,
		},
		output: {
			devtoolNamespace: 'wp',
			filename: `[name]${ suffix }.js`,
			path: normalizeJoin(
				baseDir,
				`${ buildTarget }/js/dist/script-modules`
			),
			library: {
				type: 'module',
			},
			environment: { module: true },
			module: true,
			chunkFormat: 'module',
			asyncChunks: false,
		},
		plugins: [
			...baseConfig.plugins,
			new DependencyExtractionPlugin( {
				injectPolyfill: false,
				combineAssets: true,
				combinedOutputFile: normalizeJoin(
					baseDir,
					`${ buildTarget }/assets/script-modules-packages${ suffix }.php`
				),
			} ),
		],
	};

	return config;
};
