/**
 * External dependencies
 */
const { DefinePlugin } = require( 'webpack' );
const CopyWebpackPlugin = require( 'copy-webpack-plugin' );
const postcss = require( 'postcss' );
const UglifyJS = require( 'uglify-js' );

const { join, basename } = require( 'path' );
const { get } = require( 'lodash' );

/**
 * WordPress dependencies
 */
const DependencyExtractionPlugin = require( '@wordpress/dependency-extraction-webpack-plugin' );

const baseDir = join( __dirname, '../../' );

module.exports = function( env = { environment: 'production', watch: false, buildTarget: false } ) {
	const mode = env.environment;
	const suffix = mode === 'production' ? '.min' : '';
	let buildTarget = env.buildTarget ? env.buildTarget : ( mode === 'production' ? 'build' : 'src' );
	buildTarget = buildTarget  + '/wp-includes';

	const dynamicBlockFolders = [
		'archives',
		'block',
		'calendar',
		'categories',
		'file',
		'latest-comments',
		'latest-posts',
		'loginout',
		'page-list',
		'post-content',
		'post-date',
		'post-excerpt',
		'post-featured-image',
		'post-terms',
		'post-title',
		'post-template',
		'query',
		'query-pagination',
		'query-pagination-next',
		'query-pagination-numbers',
		'query-pagination-previous',
		'query-title',
		'rss',
		'search',
		'shortcode',
		'site-logo',
		'site-tagline',
		'site-title',
		'social-link',
		'tag-cloud',
	];
	const blockFolders = [
		'audio',
		'button',
		'buttons',
		'code',
		'column',
		'columns',
		'cover',
		'embed',
		'freeform',
		'gallery',
		'group',
		'heading',
		'html',
		'image',
		'list',
		'media-text',
		'missing',
		'more',
		'nextpage',
		'paragraph',
		'preformatted',
		'pullquote',
		'quote',
		'separator',
		'social-links',
		'spacer',
		'table',
		'text-columns',
		'verse',
		'video',
		...dynamicBlockFolders,
	];
	const blockPHPFiles = {
		'widgets/src/blocks/legacy-widget/index.php': 'wp-includes/blocks/legacy-widget.php',
		...dynamicBlockFolders.reduce( ( files, blockName ) => {
			files[ `block-library/src/${ blockName }/index.php` ] = `wp-includes/blocks/${ blockName }.php`;
			return files;
		} , {} ),
	};
	const blockMetadataFiles = {
		'widgets/src/blocks/legacy-widget/block.json': 'wp-includes/blocks/legacy-widget/block.json',
		...blockFolders.reduce( ( files, blockName ) => {
			files[ `block-library/src/${ blockName }/block.json` ] = `wp-includes/blocks/${ blockName }/block.json`;
			return files;
		} , {} ),
	};

	const blockPHPCopies = Object.keys( blockPHPFiles ).map( ( filename ) => ( {
		from: join( baseDir, `node_modules/@wordpress/${ filename }` ),
		to: join( baseDir, `src/${ blockPHPFiles[ filename ] }` ),
	} ) );

	const blockMetadataCopies = Object.keys( blockMetadataFiles ).map( ( filename ) => ( {
		from: join( baseDir, `node_modules/@wordpress/${ filename }` ),
		to: join( baseDir, `src/${ blockMetadataFiles[ filename ] }` ),
	} ) );

	const blockStylesheetCopies = blockFolders.map( ( blockName ) => ( {
		from: join( baseDir, `node_modules/@wordpress/block-library/build-style/${ blockName }/*.css` ),
		to: join( baseDir, `${ buildTarget }/blocks/${ blockName }/` ),
		flatten: true,
		transform: ( content ) => {
			if ( mode === 'production' ) {
				return postcss( [
					require( 'cssnano' )( {
						preset: 'default',
					} ),
				] )
					.process( content, { from: 'src/app.css', to: 'dest/app.css' } )
					.then( ( result ) => result.css );
			}

			return content;
		},
		transformPath: ( targetPath, sourcePath ) => {
			if ( mode === 'production' ) {
				return targetPath.replace( /\.css$/, '.min.css' );
			}

			return targetPath;
		}
	} ) );

	const config = {
		mode,
		entry: {
			'file/view': join( baseDir, `node_modules/@wordpress/block-library/build-module/file/view` ),
		},
		output: {
			devtoolNamespace: 'wp',
			filename: `[name]${ suffix }.js`,
			path: join( baseDir, `${ buildTarget }/blocks` ),
		},
		resolve: {
			modules: [
				baseDir,
				'node_modules',
			],
			alias: {
				'lodash-es': 'lodash',
			},
		},
		module: {
			rules: [
				{
					test: /\.js$/,
					use: [ 'source-map-loader' ],
					enforce: 'pre',
				},
			],
		},
		optimization: {
			moduleIds: mode === 'production' ? 'hashed' : 'named',
		},
		plugins: [
			new DefinePlugin( {
				// Inject the `GUTENBERG_PHASE` global, used for feature flagging.
				'process.env.GUTENBERG_PHASE': 1,
				'process.env.FORCE_REDUCED_MOTION': JSON.stringify(
					process.env.FORCE_REDUCED_MOTION
				),
			} ),
			new DependencyExtractionPlugin( {
				injectPolyfill: true,
			} ),
			new CopyWebpackPlugin(
				[
					...blockPHPCopies,
					...blockMetadataCopies,
					...blockStylesheetCopies,
				],
			),
		],
		stats: {
			children: false,
		},

		watch: env.watch,
	};

	if ( config.mode !== 'production' ) {
		config.devtool = process.env.SOURCEMAP || 'source-map';
	}

	if ( mode === 'development' && env.buildTarget === 'build/' ) {
		delete config.devtool;
		config.mode = 'production';
		config.optimization = {
			minimize: false,
			moduleIds: 'hashed',
		};
	}

	return config;
};
