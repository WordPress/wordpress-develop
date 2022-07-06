/**
 * External dependencies
 */
const { DefinePlugin } = require( 'webpack' );
const CopyWebpackPlugin = require( 'copy-webpack-plugin' );
const { join } = require( 'path' );

/**
 * WordPress dependencies
 */
const DependencyExtractionPlugin = require( '@wordpress/dependency-extraction-webpack-plugin' );

/**
 * Internal dependencies
 */
const { stylesTransform, baseConfig, baseDir } = require( './shared' );

module.exports = function( env = { environment: 'production', watch: false, buildTarget: false } ) {
	const mode = env.environment;
	const suffix = mode === 'production' ? '.min' : '';
	let buildTarget = env.buildTarget ? env.buildTarget : ( mode === 'production' ? 'build' : 'src' );
	buildTarget = buildTarget  + '/wp-includes';

	const dynamicBlockFolders = [
		'archives',
		'avatar',
		'block',
		'calendar',
		'categories',
		'comment-author-name',
		'comment-content',
		'comment-date',
		'comment-edit-link',
		'comment-reply-link',
		'comment-template',
		'comments-pagination',
		'comments-pagination-next',
		'comments-pagination-numbers',
		'comments-pagination-previous',
		'comments-title',
		'cover',
		'file',
		'gallery',
		'home-link',
		'image',
		'latest-comments',
		'latest-posts',
		'loginout',
		'navigation',
		'navigation-link',
		'navigation-submenu',
		'page-list',
		'pattern',
		'post-author',
		'post-author-biography',
		'post-comments',
		'post-comments-form',
		'post-content',
		'post-date',
		'post-excerpt',
		'post-featured-image',
		'post-navigation-link',
		'post-template',
		'post-terms',
		'post-title',
		'query',
		'query-no-results',
		'query-pagination',
		'query-pagination-next',
		'query-pagination-numbers',
		'query-pagination-previous',
		'query-title',
		'read-more',
		'rss',
		'search',
		'shortcode',
		'site-logo',
		'site-tagline',
		'site-title',
		'social-link',
		'tag-cloud',
		'template-part',
		'term-description',
	];
	const blockFolders = [
		'audio',
		'button',
		'buttons',
		'code',
		'column',
		'columns',
		'comments-query-loop',
		'embed',
		'freeform',
		'group',
		'heading',
		'html',
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
		'widgets/src/blocks/widget-group/index.php': 'wp-includes/blocks/widget-group.php',
		...dynamicBlockFolders.reduce( ( files, blockName ) => {
			files[ `block-library/src/${ blockName }/index.php` ] = `wp-includes/blocks/${ blockName }.php`;
			return files;
		} , {} ),
	};
	const blockMetadataFiles = {
		'widgets/src/blocks/legacy-widget/block.json': 'wp-includes/blocks/legacy-widget/block.json',
		'widgets/src/blocks/widget-group/block.json': 'wp-includes/blocks/widget-group/block.json',
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
		to: join( baseDir, `${ buildTarget }/blocks/${ blockName }/[name]${ suffix }.css` ),
		transform: stylesTransform( mode ),
		noErrorOnMissing: true,
	} ) );

	const config = {
		...baseConfig( env ),
		entry: {
			'file/view': join( baseDir, `node_modules/@wordpress/block-library/build-module/file/view` ),
			'navigation/view': join( baseDir, `node_modules/@wordpress/block-library/build-module/navigation/view` ),
		},
		output: {
			devtoolNamespace: 'wp',
			filename: `[name]${ suffix }.js`,
			path: join( baseDir, `${ buildTarget }/blocks` ),
		},
		plugins: [
			new DefinePlugin( {
				// Inject the `IS_GUTENBERG_PLUGIN` global, used for feature flagging.
				'process.env.IS_GUTENBERG_PLUGIN': false,
				'process.env.FORCE_REDUCED_MOTION': JSON.stringify(
					process.env.FORCE_REDUCED_MOTION
				),
			} ),
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
