/**
 * External dependencies
 */
const CopyWebpackPlugin = require( 'copy-webpack-plugin' );

/**
 * Internal dependencies
 */
const {
	baseDir,
	getBaseConfig,
	normalizeJoin,
	stylesTransform,
} = require( './shared' );
const {
	isDynamic,
	toDirectoryName,
	getStableBlocksMetadata,
} = require( '../release/sync-stable-blocks' );

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

	const blocks = getStableBlocksMetadata();
	const dynamicBlockFolders = blocks
		.filter( isDynamic )
		.map( toDirectoryName );
	const blockFolders = blocks.map( toDirectoryName );
	const blockPHPFiles = {
		'widgets/src/blocks/legacy-widget/index.php':
			'wp-includes/blocks/legacy-widget.php',
		'widgets/src/blocks/widget-group/index.php':
			'wp-includes/blocks/widget-group.php',
		...dynamicBlockFolders.reduce( ( files, blockName ) => {
			files[
				`block-library/src/${ blockName }/index.php`
			] = `wp-includes/blocks/${ blockName }.php`;
			return files;
		}, {} ),
	};
	const blockMetadataFiles = {
		'widgets/src/blocks/legacy-widget/block.json':
			'wp-includes/blocks/legacy-widget/block.json',
		'widgets/src/blocks/widget-group/block.json':
			'wp-includes/blocks/widget-group/block.json',
		...blockFolders.reduce( ( files, blockName ) => {
			files[
				`block-library/src/${ blockName }/block.json`
			] = `wp-includes/blocks/${ blockName }/block.json`;
			return files;
		}, {} ),
	};

	const blockPHPCopies = Object.keys( blockPHPFiles ).map( ( filename ) => ( {
		from: normalizeJoin( baseDir, `node_modules/@wordpress/${ filename }` ),
		to: normalizeJoin( baseDir, `src/${ blockPHPFiles[ filename ] }` ),
	} ) );

	const blockMetadataCopies = Object.keys( blockMetadataFiles ).map(
		( filename ) => ( {
			from: normalizeJoin(
				baseDir,
				`node_modules/@wordpress/${ filename }`
			),
			to: normalizeJoin(
				baseDir,
				`src/${ blockMetadataFiles[ filename ] }`
			),
		} )
	);

	const blockStylesheetCopies = blockFolders.map( ( blockName ) => ( {
		from: normalizeJoin(
			baseDir,
			`node_modules/@wordpress/block-library/build-style/${ blockName }/*.css`
		),
		to: normalizeJoin(
			baseDir,
			`${ buildTarget }/blocks/${ blockName }/[name]${ suffix }.css`
		),
		transform: stylesTransform( mode ),
		noErrorOnMissing: true,
	} ) );

	// Todo: This list need of entry points need to be automatically fetched from the package
	// We shouldn't have to maintain it manually.
	const interactiveBlocks = [
		'navigation',
		'image',
		'query',
		'file',
		'search',
	];

	const baseConfig = getBaseConfig( env );
	const config = {
		...baseConfig,
		entry: interactiveBlocks.reduce(( memo, blockName ) => {
			memo[ blockName ] = {
				import: normalizeJoin(
					baseDir,
					`node_modules/@wordpress/block-library/build-module/${ blockName }/view`
				),
			};
			return memo;
		}, {}),
		experiments: {
			outputModule: true,
		},
		output: {
			devtoolNamespace: 'wp',
			filename: `./blocks/[name]/view${ suffix }.js`,
			path: normalizeJoin( baseDir, buildTarget ),
			library: {
				type: 'module',
			},
			environment: { module: true },
		},
		externalsType: 'module',
		externals: {
			'@wordpress/interactivity': '@wordpress/interactivity',
			'@wordpress/interactivity-router': 'import @wordpress/interactivity-router',
		},
		plugins: [
			...baseConfig.plugins,
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
