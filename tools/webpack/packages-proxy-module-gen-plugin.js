/**
 * External dependencies
 */
const path = require( 'path' );
const webpack = require( 'webpack' );
const json2php = require( 'json2php' );

const { createHash } = webpack.util;
const { RawSource } = webpack.sources;

class WpScriptsPackageProxyModuleWebpackPlugin {
	/**
	 * @param {{ combinedOutputFile: string }} options
	 */
	constructor( options ) {
		if ( ! options || ! Object.hasOwn( options, 'combinedOutputFile' ) ) {
			throw new Error( 'Must provide combinedOutputFile option' );
		}

		this.options = options;
	}

	/**
	 * @param {any} asset Asset Data
	 * @return {string} Stringified asset data suitable for output
	 */
	stringify( asset ) {
		return `<?php return ${ json2php(
			JSON.parse( JSON.stringify( asset ) )
		) };\n`;
	}

	/** @type {webpack.WebpackPluginInstance['apply']} */
	apply( compiler ) {
		if ( compiler.options.output?.module ) {
			throw new Error(
				`${ this.constructor.name } is not compatible with a module build.`
			);
		}

		compiler.hooks.thisCompilation.tap(
			this.constructor.name,
			( compilation ) => {
				compilation.hooks.processAssets.tap(
					{
						name: this.constructor.name,
						stage: compiler.webpack.Compilation
							.PROCESS_ASSETS_STAGE_ANALYSE,
					},
					() => this.addAssets( compilation )
				);
			}
		);
	}

	/** @param {webpack.Compilation} compilation */
	addAssets( compilation ) {
		const { combinedOutputFile } = this.options;

		const combinedAssetsData = {};

		// Accumulate all entrypoint chunks, some of them shared
		const entrypointChunks = new Set();

		/**
		 * @type Map<string, {exportsInfo: ReturnType<webpack.ModuleGraph['getExportInfo']>, libOpts: {name:string[];type:string}}>
		 */
		const entrypointProxyInfo = new Map();

		for ( const entrypoint of compilation.entrypoints.values() ) {
			for ( const chunk of entrypoint.chunks ) {
				entrypointChunks.add( chunk );
			}

			const library = entrypoint.options.library;
			if ( ! library || ! Array.isArray( library.name ) ) {
				continue;
			}

			if (
				1 !==
				compilation.chunkGraph.getNumberOfEntryModules(
					entrypoint.getEntrypointChunk()
				)
			) {
				continue;
			}

			/** @type {webpack.Module} */
			const entryModule = compilation.chunkGraph
				.getChunkEntryModulesIterable( entrypoint.getEntrypointChunk() )
				.next().value;

			const exportsInfo =
				compilation.moduleGraph.getExportsInfo( entryModule );

			if ( Array.isArray( exportsInfo.getProvidedExports() ) ) {
				entrypointProxyInfo.set( entrypoint.options.name, {
					exportsInfo,
					libOpts: library,
				} );
			}
		}

		// Process each entrypoint chunk independently
		for ( const chunk of entrypointChunks ) {
			const chunkFiles = Array.from( chunk.files );

			const jsExtensionRegExp = this.useModules ? /\.m?js$/i : /\.js$/i;

			const chunkJSFile = chunkFiles.find( ( f ) =>
				jsExtensionRegExp.test( f )
			);
			if ( ! chunkJSFile ) {
				// There's no JS file in this chunk, no work for us. Typically a `style.css` from cache group.
				continue;
			}

			// Go through the assets and hash the sources. We can't just use
			// `chunk.contentHash` because that's not updated when
			// assets are minified. In practice the hash is updated by
			// `RealContentHashPlugin` after minification, but it only modifies
			// already-produced asset filenames and the updated hash is not
			// available to plugins.
			const { hashFunction, hashDigest, hashDigestLength } =
				compilation.outputOptions;

			if ( entrypointProxyInfo.has( chunk.name ) ) {
				const { exportsInfo, libOpts } = entrypointProxyInfo.get(
					chunk.name
				);
				const generatedProxyModuleFilename = compilation
					.getPath( '[file]', {
						filename: chunkJSFile,
					} )
					.replace( /\.m?js$/i, '-esm-proxy.js' );
				const libraryPath = libOpts.name.map(
					( n ) => `[${ JSON.stringify( n ) }]`
				);

				// let sourceString = `if ( 'undefined' === typeof ${
				// 	libOpts.type
				// }?.${ libraryPath.join(
				// 	'?.'
				// ) } ) {\n\tthrow new Error( 'Undefined dependency: ${
				// 	libOpts.type
				// }${ libraryPath.join( '' ) }' );\n}\n`;

				let sourceString = `const __library__ = ${
					libOpts.type
				}?.${ libraryPath.join(
					'?.'
				) } ?? await import( '@wordpress-esm/${ chunk.name }' )${
					'' //libOpts.export === 'default' ? '.default' : ''
				};\n`;

				console.log( {
					n: chunk.name,
					es: exportsInfo.getProvidedExports(),
				} );
				for ( const exportName of exportsInfo.getProvidedExports() ) {
					if ( exportName === 'default' ) {
						sourceString += `export default __library__.default;\n`;
					} else {
						sourceString += `export const ${ exportName } = __library__.${ exportName };\n`;
					}
				}

				const contentHash = createHash( hashFunction );
				contentHash.update( sourceString );

				compilation.assets[ generatedProxyModuleFilename ] =
					new RawSource( sourceString );

				chunk.files.add( generatedProxyModuleFilename );

				const assetData = {
					dependencies: [
						// { id: `wp-${ chunk.name }`, import: 'wp-script' },
						{
							id: `@wordpress-esm/${ chunk.name }`,
							import: 'dynamic',
						},
					],
					version: contentHash
						.digest( hashDigest )
						.slice( 0, hashDigestLength ),
				};
				combinedAssetsData[ generatedProxyModuleFilename ] = assetData;
			}
		}

		const outputFolder = compilation.outputOptions.path;

		const assetsFilePath = path.resolve( outputFolder, combinedOutputFile );
		const assetsFilename = path.relative( outputFolder, assetsFilePath );

		// Add source into compilation for webpack to output.
		compilation.assets[ assetsFilename ] = new RawSource(
			this.stringify( combinedAssetsData )
		);
	}
}

module.exports = WpScriptsPackageProxyModuleWebpackPlugin;
