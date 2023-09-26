/**
 * External dependencies
 */
const CopyWebpackPlugin = require( 'copy-webpack-plugin' );

/**
 * WordPress dependencies
 */
const DependencyExtractionPlugin = require( '@wordpress/dependency-extraction-webpack-plugin' );

/**
 * Internal dependencies
 */
const { baseDir, getBaseConfig, normalizeJoin, stylesTransform } = require( './shared' );
const {
	isDynamic,
	toDirectoryName,
	getStableBlocksMetadata,
} = require( '../release/sync-stable-blocks' );

module.exports = function( env = { environment: 'production', watch: false, buildTarget: false } ) {
	const mode = env.environment;
	const suffix = mode === 'production' ? '.min' : '';
	let buildTarget = env.buildTarget ? env.buildTarget : ( mode === 'production' ? 'build' : 'src' );
	buildTarget = buildTarget + '/wp-includes';

	const blocks = getStableBlocksMetadata();
	const dynamicBlockFolders = blocks.filter( isDynamic ).map( toDirectoryName );
	const blockFolders = blocks.map( toDirectoryName );
	const blockPHPFiles = {
		'widgets/src/blocks/legacy-widget/index.php': 'wp-includes/blocks/legacy-widget.php',
		'widgets/src/blocks/widget-group/index.php': 'wp-includes/blocks/widget-group.php',
		...dynamicBlockFolders.reduce( ( files, blockName ) => {
			files[ `block-library/src/${ blockName }/index.php` ] = `wp-includes/blocks/${ blockName }.php`;
			return files;
		}, {} ),
	};
	const blockMetadataFiles = {
		'widgets/src/blocks/legacy-widget/block.json': 'wp-includes/blocks/legacy-widget/block.json',
		'widgets/src/blocks/widget-group/block.json': 'wp-includes/blocks/widget-group/block.json',
		...blockFolders.reduce( ( files, blockName ) => {
			files[ `block-library/src/${ blockName }/block.json` ] = `wp-includes/blocks/${ blockName }/block.json`;
			return files;
		}, {} ),
	};

	const blockPHPCopies = Object.keys( blockPHPFiles ).map( ( filename ) => ( {
		from: normalizeJoin(baseDir, `node_modules/@wordpress/${ filename }` ),
		to: normalizeJoin(baseDir, `src/${ blockPHPFiles[ filename ] }` ),
	} ) );

	const blockMetadataCopies = Object.keys( blockMetadataFiles ).map( ( filename ) => ( {
		from: normalizeJoin(baseDir, `node_modules/@wordpress/${ filename }` ),
		to: normalizeJoin(baseDir, `src/${ blockMetadataFiles[ filename ] }` ),
	} ) );

	const blockStylesheetCopies = blockFolders.map( ( blockName ) => ( {
		from: normalizeJoin(baseDir, `node_modules/@wordpress/block-library/build-style/${ blockName }/*.css` ),
		to: normalizeJoin(baseDir, `${ buildTarget }/blocks/${ blockName }/[name]${ suffix }.css` ),
		transform: stylesTransform( mode ),
		noErrorOnMissing: true,
	} ) );

	const baseConfig = getBaseConfig( env );
	const config = {
		...baseConfig,
		entry: {
			'file/view': normalizeJoin(baseDir, `node_modules/@wordpress/block-library/build-module/file/view` ),
			'navigation/view': normalizeJoin(baseDir, `node_modules/@wordpress/block-library/build-module/navigation/view` ),
			'navigation/view-modal': normalizeJoin(baseDir, `node_modules/@wordpress/block-library/build-module/navigation/view-modal` ),
			'search/view': normalizeJoin(baseDir, `node_modules/@wordpress/block-library/build-module/search/view` ),
		},
		output: {
			devtoolNamespace: 'wp',
			filename: `[name]${ suffix }.js`,
			path: normalizeJoin(baseDir, `${ buildTarget }/blocks` ),
		},
		plugins: [
			...baseConfig.plugins,
			new DependencyExtractionPlugin( {
				injectPolyfill: false,
			} ),
			new CopyWebpackPlugin( {
				patterns: [
					...blockPHPCopies,
					...blockMetadataCopies,
					...blockStylesheetCopies,
				],
			} ),
		],
	};

	return config;
};
