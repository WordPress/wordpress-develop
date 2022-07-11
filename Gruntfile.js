/* jshint node:true */
/* jshint esversion: 6 */
/* globals Set */
var webpackConfig = require( './webpack.config' );
var installChanged = require( 'install-changed' );

module.exports = function(grunt) {
	var path = require('path'),
		fs = require( 'fs' ),
		glob = require( 'glob' ),
		assert = require( 'assert' ).strict,
		spawn = require( 'child_process' ).spawnSync,
		SOURCE_DIR = 'src/',
		BUILD_DIR = 'build/',
		WORKING_DIR = grunt.option( 'dev' ) ? SOURCE_DIR : BUILD_DIR,
		BANNER_TEXT = '/*! This file is auto-generated */',
		autoprefixer = require( 'autoprefixer' ),
		sass = require( 'sass' ),
		phpUnitWatchGroup = grunt.option( 'group' ),
		buildFiles = [
			'*.php',
			'*.txt',
			'*.html',
			'wp-includes/**', // Include everything in wp-includes.
			'wp-admin/**', // Include everything in wp-admin.
			'wp-content/index.php',
			'wp-content/themes/index.php',
			'wp-content/themes/twenty*/**',
			'wp-content/plugins/index.php',
			'wp-content/plugins/hello.php',
			'wp-content/plugins/akismet/**',
			'!wp-content/themes/twenty*/node_modules/**',
		],
		changedFiles = {
			php: []
		};

	if ( 'watch:phpunit' === grunt.cli.tasks[ 0 ] && ! phpUnitWatchGroup ) {
		grunt.log.writeln();
		grunt.fail.fatal(
			'Missing required parameters. Example usage: ' + '\n\n' +
			'grunt watch:phpunit --group=community-events' + '\n' +
			'grunt watch:phpunit --group=multisite,mail'
		);
	}

	// First do `npm install` if package.json has changed.
	installChanged.watchPackage();

	// Load tasks.
	require('matchdep').filterDev(['grunt-*', '!grunt-legacy-util']).forEach( grunt.loadNpmTasks );
	// Load legacy utils.
	grunt.util = require('grunt-legacy-util');

	// Project configuration.
	grunt.initConfig({
		postcss: {
			options: {
				processors: [
					autoprefixer({
						cascade: false
					})
				]
			},
			core: {
				expand: true,
				cwd: SOURCE_DIR,
				dest: SOURCE_DIR,
				src: [
					'wp-admin/css/*.css',
					'wp-includes/css/*.css'
				]
			},
			colors: {
				expand: true,
				cwd: WORKING_DIR,
				dest: WORKING_DIR,
				src: [
					'wp-admin/css/colors/*/colors.css'
				]
			}
		},
		usebanner: {
			options: {
				position: 'top',
				banner: BANNER_TEXT,
				linebreak: true
			},
			files: {
				src: [
					WORKING_DIR + 'wp-admin/css/*.min.css',
					WORKING_DIR + 'wp-admin/css/*-rtl*.css',
					WORKING_DIR + 'wp-admin/js/**/*.min.js',
					WORKING_DIR + 'wp-includes/css/*.min.css',
					WORKING_DIR + 'wp-includes/css/*-rtl*.css',
					WORKING_DIR + 'wp-includes/js/*.min.js',
					WORKING_DIR + 'wp-includes/js/dist/*.min.js',
					WORKING_DIR + 'wp-admin/css/colors/*/*.css'
				]
			}
		},
		clean: {
			plugins: [BUILD_DIR + 'wp-content/plugins'],
			themes: [BUILD_DIR + 'wp-content/themes'],
			files: buildFiles.concat( [
				'!wp-config.php',
			] ).map( function( file ) {
				if ( '!' === file.charAt( 0 ) ) {
					return '!' + BUILD_DIR + file.substring( 1 );
				}
				return BUILD_DIR + file;
			} ),
			css: [
				WORKING_DIR + 'wp-admin/css/*.min.css',
				WORKING_DIR + 'wp-admin/css/*-rtl*.css',
				WORKING_DIR + 'wp-includes/css/*.min.css',
				WORKING_DIR + 'wp-includes/css/*-rtl*.css',
				WORKING_DIR + 'wp-admin/css/colors/**/*.css'
			],
			js: [
				WORKING_DIR + 'wp-admin/js/',
				WORKING_DIR + 'wp-includes/js/'
			],
			'webpack-assets': [
				WORKING_DIR + 'wp-includes/assets/*',
				WORKING_DIR + 'wp-includes/css/dist/',
				'!' + WORKING_DIR + 'wp-includes/assets/script-loader-*.php'
			],
			dynamic: {
				dot: true,
				expand: true,
				cwd: WORKING_DIR,
				src: []
			},
			qunit: ['tests/qunit/compiled.html']
		},
		file_append: {
			// grunt-file-append supports only strings for input and output.
			default_options: {
				files: [
					{
						append: 'jQuery.noConflict();',
						input: WORKING_DIR + 'wp-includes/js/jquery/jquery.js',
						output: WORKING_DIR + 'wp-includes/js/jquery/jquery.js'
					},
					{
						append: 'jQuery.noConflict();',
						input: WORKING_DIR + 'wp-includes/js/jquery/jquery.min.js',
						output: WORKING_DIR + 'wp-includes/js/jquery/jquery.min.js'
					}
				]
			}
		},
		copy: {
			files: {
				files: [
					{
						dot: true,
						expand: true,
						cwd: SOURCE_DIR,
						src: buildFiles.concat( [
							'!wp-includes/assets/**', // Assets is extracted into separate copy tasks.
							'!js/**', // JavaScript is extracted into separate copy tasks.
							'!.{svn,git}', // Exclude version control folders.
							'!wp-includes/version.php', // Exclude version.php.
							'!**/*.map', // The build doesn't need .map files.
							'!index.php', '!wp-admin/index.php',
							'!_index.php', '!wp-admin/_index.php'
						] ),
						dest: BUILD_DIR
					},
					{
						src: 'wp-config-sample.php',
						dest: BUILD_DIR
					},
					{
						[BUILD_DIR + 'index.php']: ['src/_index.php'],
						[BUILD_DIR + 'wp-admin/index.php']: ['src/wp-admin/_index.php']
					}
				]
			},
			'npm-packages': {
				files: [
					{
						[ WORKING_DIR + 'wp-includes/js/backbone.js' ]: [ './node_modules/backbone/backbone.js' ],
						[ WORKING_DIR + 'wp-includes/js/clipboard.js' ]: [ './node_modules/clipboard/dist/clipboard.js' ],
						[ WORKING_DIR + 'wp-includes/js/hoverIntent.js' ]: [ './node_modules/jquery-hoverintent/jquery.hoverIntent.js' ],

						// Renamed to avoid conflict with jQuery hoverIntent.min.js (after minifying).
						[ WORKING_DIR + 'wp-includes/js/hoverintent-js.min.js' ]: [ './node_modules/hoverintent/dist/hoverintent.min.js' ],
						[ WORKING_DIR + 'wp-includes/js/imagesloaded.min.js' ]: [ './node_modules/imagesloaded/imagesloaded.pkgd.min.js' ],
						[ WORKING_DIR + 'wp-includes/js/jquery/jquery.js' ]: [ './node_modules/jquery/dist/jquery.js' ],
						[ WORKING_DIR + 'wp-includes/js/jquery/jquery.min.js' ]: [ './node_modules/jquery/dist/jquery.min.js' ],
						[ WORKING_DIR + 'wp-includes/js/jquery/jquery.form.js' ]: [ './node_modules/jquery-form/src/jquery.form.js' ],
						[ WORKING_DIR + 'wp-includes/js/jquery/jquery.color.min.js' ]: [ './node_modules/jquery-color/dist/jquery.color.min.js' ],
						[ WORKING_DIR + 'wp-includes/js/masonry.min.js' ]: [ './node_modules/masonry-layout/dist/masonry.pkgd.min.js' ],
						[ WORKING_DIR + 'wp-includes/js/twemoji.js' ]: [ './node_modules/twemoji/dist/twemoji.js' ],
						[ WORKING_DIR + 'wp-includes/js/underscore.js' ]: [ './node_modules/underscore/underscore.js' ],
					}
				]
			},
			'vendor-js': {
				files: [
					{
						expand: true,
						cwd: SOURCE_DIR + 'js/_enqueues/vendor/',
						src: [
							'**/*',
							'!farbtastic.js',
							'!iris.min.js',
							'!deprecated/**',
							'!README.md',
							// Ignore unminified version of vendor lib we don't ship.
							'!jquery/jquery.masonry.js',
							'!tinymce/tinymce.js'
						],
						dest: WORKING_DIR + 'wp-includes/js/'
					},
					{
						expand: true,
						cwd: SOURCE_DIR + 'js/_enqueues/vendor/',
						src: [
							'farbtastic.js',
							'iris.min.js'
						],
						dest: WORKING_DIR + 'wp-admin/js/'
					},
					{
						expand: true,
						cwd: SOURCE_DIR + 'js/_enqueues/vendor/deprecated',
						src: [
							'suggest*'
						],
						dest: WORKING_DIR + 'wp-includes/js/jquery/'
					},
					{
						expand: true,
						cwd: SOURCE_DIR + 'js/_enqueues/vendor/tinymce/',
						src: 'tinymce.js',
						dest: SOURCE_DIR + 'wp-includes/js/tinymce/'
					},

				]
			},
			'admin-js': {
				files: {
					[ WORKING_DIR + 'wp-admin/js/accordion.js' ]: [ './src/js/_enqueues/lib/accordion.js' ],
					[ WORKING_DIR + 'wp-admin/js/application-passwords.js' ]: [ './src/js/_enqueues/admin/application-passwords.js' ],
					[ WORKING_DIR + 'wp-admin/js/auth-app.js' ]: [ './src/js/_enqueues/admin/auth-app.js' ],
					[ WORKING_DIR + 'wp-admin/js/code-editor.js' ]: [ './src/js/_enqueues/wp/code-editor.js' ],
					[ WORKING_DIR + 'wp-admin/js/color-picker.js' ]: [ './src/js/_enqueues/lib/color-picker.js' ],
					[ WORKING_DIR + 'wp-admin/js/comment.js' ]: [ './src/js/_enqueues/admin/comment.js' ],
					[ WORKING_DIR + 'wp-admin/js/common.js' ]: [ './src/js/_enqueues/admin/common.js' ],
					[ WORKING_DIR + 'wp-admin/js/custom-background.js' ]: [ './src/js/_enqueues/admin/custom-background.js' ],
					[ WORKING_DIR + 'wp-admin/js/custom-header.js' ]: [ './src/js/_enqueues/admin/custom-header.js' ],
					[ WORKING_DIR + 'wp-admin/js/customize-controls.js' ]: [ './src/js/_enqueues/wp/customize/controls.js' ],
					[ WORKING_DIR + 'wp-admin/js/customize-nav-menus.js' ]: [ './src/js/_enqueues/wp/customize/nav-menus.js' ],
					[ WORKING_DIR + 'wp-admin/js/customize-widgets.js' ]: [ './src/js/_enqueues/wp/customize/widgets.js' ],
					[ WORKING_DIR + 'wp-admin/js/dashboard.js' ]: [ './src/js/_enqueues/wp/dashboard.js' ],
					[ WORKING_DIR + 'wp-admin/js/edit-comments.js' ]: [ './src/js/_enqueues/admin/edit-comments.js' ],
					[ WORKING_DIR + 'wp-admin/js/editor-expand.js' ]: [ './src/js/_enqueues/wp/editor/dfw.js' ],
					[ WORKING_DIR + 'wp-admin/js/editor.js' ]: [ './src/js/_enqueues/wp/editor/base.js' ],
					[ WORKING_DIR + 'wp-admin/js/gallery.js' ]: [ './src/js/_enqueues/lib/gallery.js' ],
					[ WORKING_DIR + 'wp-admin/js/image-edit.js' ]: [ './src/js/_enqueues/lib/image-edit.js' ],
					[ WORKING_DIR + 'wp-admin/js/inline-edit-post.js' ]: [ './src/js/_enqueues/admin/inline-edit-post.js' ],
					[ WORKING_DIR + 'wp-admin/js/inline-edit-tax.js' ]: [ './src/js/_enqueues/admin/inline-edit-tax.js' ],
					[ WORKING_DIR + 'wp-admin/js/language-chooser.js' ]: [ './src/js/_enqueues/lib/language-chooser.js' ],
					[ WORKING_DIR + 'wp-admin/js/link.js' ]: [ './src/js/_enqueues/admin/link.js' ],
					[ WORKING_DIR + 'wp-admin/js/media-gallery.js' ]: [ './src/js/_enqueues/deprecated/media-gallery.js' ],
					[ WORKING_DIR + 'wp-admin/js/media-upload.js' ]: [ './src/js/_enqueues/admin/media-upload.js' ],
					[ WORKING_DIR + 'wp-admin/js/media.js' ]: [ './src/js/_enqueues/admin/media.js' ],
					[ WORKING_DIR + 'wp-admin/js/nav-menu.js' ]: [ './src/js/_enqueues/lib/nav-menu.js' ],
					[ WORKING_DIR + 'wp-admin/js/password-strength-meter.js' ]: [ './src/js/_enqueues/wp/password-strength-meter.js' ],
					[ WORKING_DIR + 'wp-admin/js/plugin-install.js' ]: [ './src/js/_enqueues/admin/plugin-install.js' ],
					[ WORKING_DIR + 'wp-admin/js/post.js' ]: [ './src/js/_enqueues/admin/post.js' ],
					[ WORKING_DIR + 'wp-admin/js/postbox.js' ]: [ './src/js/_enqueues/admin/postbox.js' ],
					[ WORKING_DIR + 'wp-admin/js/revisions.js' ]: [ './src/js/_enqueues/wp/revisions.js' ],
					[ WORKING_DIR + 'wp-admin/js/set-post-thumbnail.js' ]: [ './src/js/_enqueues/admin/set-post-thumbnail.js' ],
					[ WORKING_DIR + 'wp-admin/js/svg-painter.js' ]: [ './src/js/_enqueues/wp/svg-painter.js' ],
					[ WORKING_DIR + 'wp-admin/js/tags-box.js' ]: [ './src/js/_enqueues/admin/tags-box.js' ],
					[ WORKING_DIR + 'wp-admin/js/tags-suggest.js' ]: [ './src/js/_enqueues/admin/tags-suggest.js' ],
					[ WORKING_DIR + 'wp-admin/js/tags.js' ]: [ './src/js/_enqueues/admin/tags.js' ],
					[ WORKING_DIR + 'wp-admin/js/site-health.js' ]: [ './src/js/_enqueues/admin/site-health.js' ],
					[ WORKING_DIR + 'wp-admin/js/privacy-tools.js' ]: [ './src/js/_enqueues/admin/privacy-tools.js' ],
					[ WORKING_DIR + 'wp-admin/js/theme-plugin-editor.js' ]: [ './src/js/_enqueues/wp/theme-plugin-editor.js' ],
					[ WORKING_DIR + 'wp-admin/js/theme.js' ]: [ './src/js/_enqueues/wp/theme.js' ],
					[ WORKING_DIR + 'wp-admin/js/updates.js' ]: [ './src/js/_enqueues/wp/updates.js' ],
					[ WORKING_DIR + 'wp-admin/js/user-profile.js' ]: [ './src/js/_enqueues/admin/user-profile.js' ],
					[ WORKING_DIR + 'wp-admin/js/user-suggest.js' ]: [ './src/js/_enqueues/lib/user-suggest.js' ],
					[ WORKING_DIR + 'wp-admin/js/widgets/custom-html-widgets.js' ]: [ './src/js/_enqueues/wp/widgets/custom-html.js' ],
					[ WORKING_DIR + 'wp-admin/js/widgets/media-audio-widget.js' ]: [ './src/js/_enqueues/wp/widgets/media-audio.js' ],
					[ WORKING_DIR + 'wp-admin/js/widgets/media-gallery-widget.js' ]: [ './src/js/_enqueues/wp/widgets/media-gallery.js' ],
					[ WORKING_DIR + 'wp-admin/js/widgets/media-image-widget.js' ]: [ './src/js/_enqueues/wp/widgets/media-image.js' ],
					[ WORKING_DIR + 'wp-admin/js/widgets/media-video-widget.js' ]: [ './src/js/_enqueues/wp/widgets/media-video.js' ],
					[ WORKING_DIR + 'wp-admin/js/widgets/media-widgets.js' ]: [ './src/js/_enqueues/wp/widgets/media.js' ],
					[ WORKING_DIR + 'wp-admin/js/widgets/text-widgets.js' ]: [ './src/js/_enqueues/wp/widgets/text.js' ],
					[ WORKING_DIR + 'wp-admin/js/widgets.js' ]: [ './src/js/_enqueues/admin/widgets.js' ],
					[ WORKING_DIR + 'wp-admin/js/word-count.js' ]: [ './src/js/_enqueues/wp/utils/word-count.js' ],
					[ WORKING_DIR + 'wp-admin/js/wp-fullscreen-stub.js' ]: [ './src/js/_enqueues/deprecated/fullscreen-stub.js' ],
					[ WORKING_DIR + 'wp-admin/js/xfn.js' ]: [ './src/js/_enqueues/admin/xfn.js' ]
				}
			},
			'includes-js': {
				files: {
					[ WORKING_DIR + 'wp-includes/js/admin-bar.js' ]: [ './src/js/_enqueues/lib/admin-bar.js' ],
					[ WORKING_DIR + 'wp-includes/js/api-request.js' ]: [ './src/js/_enqueues/wp/api-request.js' ],
					[ WORKING_DIR + 'wp-includes/js/autosave.js' ]: [ './src/js/_enqueues/wp/autosave.js' ],
					[ WORKING_DIR + 'wp-includes/js/comment-reply.js' ]: [ './src/js/_enqueues/lib/comment-reply.js' ],
					[ WORKING_DIR + 'wp-includes/js/customize-base.js' ]: [ './src/js/_enqueues/wp/customize/base.js' ],
					[ WORKING_DIR + 'wp-includes/js/customize-loader.js' ]: [ './src/js/_enqueues/wp/customize/loader.js' ],
					[ WORKING_DIR + 'wp-includes/js/customize-models.js' ]: [ './src/js/_enqueues/wp/customize/models.js' ],
					[ WORKING_DIR + 'wp-includes/js/customize-preview-nav-menus.js' ]: [ './src/js/_enqueues/wp/customize/preview-nav-menus.js' ],
					[ WORKING_DIR + 'wp-includes/js/customize-preview-widgets.js' ]: [ './src/js/_enqueues/wp/customize/preview-widgets.js' ],
					[ WORKING_DIR + 'wp-includes/js/customize-preview.js' ]: [ './src/js/_enqueues/wp/customize/preview.js' ],
					[ WORKING_DIR + 'wp-includes/js/customize-selective-refresh.js' ]: [ './src/js/_enqueues/wp/customize/selective-refresh.js' ],
					[ WORKING_DIR + 'wp-includes/js/customize-views.js' ]: [ './src/js/_enqueues/wp/customize/views.js' ],
					[ WORKING_DIR + 'wp-includes/js/heartbeat.js' ]: [ './src/js/_enqueues/wp/heartbeat.js' ],
					[ WORKING_DIR + 'wp-includes/js/mce-view.js' ]: [ './src/js/_enqueues/wp/mce-view.js' ],
					[ WORKING_DIR + 'wp-includes/js/media-editor.js' ]: [ './src/js/_enqueues/wp/media/editor.js' ],
					[ WORKING_DIR + 'wp-includes/js/quicktags.js' ]: [ './src/js/_enqueues/lib/quicktags.js' ],
					[ WORKING_DIR + 'wp-includes/js/shortcode.js' ]: [ './src/js/_enqueues/wp/shortcode.js' ],
					[ WORKING_DIR + 'wp-includes/js/utils.js' ]: [ './src/js/_enqueues/lib/cookies.js' ],
					[ WORKING_DIR + 'wp-includes/js/wp-ajax-response.js' ]: [ './src/js/_enqueues/lib/ajax-response.js' ],
					[ WORKING_DIR + 'wp-includes/js/wp-api.js' ]: [ './src/js/_enqueues/wp/api.js' ],
					[ WORKING_DIR + 'wp-includes/js/wp-auth-check.js' ]: [ './src/js/_enqueues/lib/auth-check.js' ],
					[ WORKING_DIR + 'wp-includes/js/wp-backbone.js' ]: [ './src/js/_enqueues/wp/backbone.js' ],
					[ WORKING_DIR + 'wp-includes/js/wp-custom-header.js' ]: [ './src/js/_enqueues/wp/custom-header.js' ],
					[ WORKING_DIR + 'wp-includes/js/wp-embed-template.js' ]: [ './src/js/_enqueues/lib/embed-template.js' ],
					[ WORKING_DIR + 'wp-includes/js/wp-embed.js' ]: [ './src/js/_enqueues/wp/embed.js' ],
					[ WORKING_DIR + 'wp-includes/js/wp-emoji-loader.js' ]: [ './src/js/_enqueues/lib/emoji-loader.js' ],
					[ WORKING_DIR + 'wp-includes/js/wp-emoji.js' ]: [ './src/js/_enqueues/wp/emoji.js' ],
					[ WORKING_DIR + 'wp-includes/js/wp-list-revisions.js' ]: [ './src/js/_enqueues/lib/list-revisions.js' ],
					[ WORKING_DIR + 'wp-includes/js/wp-lists.js' ]: [ './src/js/_enqueues/lib/lists.js' ],
					[ WORKING_DIR + 'wp-includes/js/wp-pointer.js' ]: [ './src/js/_enqueues/lib/pointer.js' ],
					[ WORKING_DIR + 'wp-includes/js/wp-sanitize.js' ]: [ './src/js/_enqueues/wp/sanitize.js' ],
					[ WORKING_DIR + 'wp-includes/js/wp-util.js' ]: [ './src/js/_enqueues/wp/util.js' ],
					[ WORKING_DIR + 'wp-includes/js/wpdialog.js' ]: [ './src/js/_enqueues/lib/dialog.js' ],
					[ WORKING_DIR + 'wp-includes/js/wplink.js' ]: [ './src/js/_enqueues/lib/link.js' ],
					[ WORKING_DIR + 'wp-includes/js/zxcvbn-async.js' ]: [ './src/js/_enqueues/lib/zxcvbn-async.js' ]
				}
			},
			'wp-admin-css-compat-rtl': {
				options: {
					processContent: function( src ) {
						return src.replace( /\.css/g, '-rtl.css' );
					}
				},
				src: SOURCE_DIR + 'wp-admin/css/wp-admin.css',
				dest: WORKING_DIR + 'wp-admin/css/wp-admin-rtl.css'
			},
			'wp-admin-css-compat-min': {
				options: {
					processContent: function( src ) {
						return src.replace( /\.css/g, '.min.css' );
					}
				},
				files: [
					{
						src: SOURCE_DIR + 'wp-admin/css/wp-admin.css',
						dest: WORKING_DIR + 'wp-admin/css/wp-admin.min.css'
					},
					{
						src:  WORKING_DIR + 'wp-admin/css/wp-admin-rtl.css',
						dest: WORKING_DIR + 'wp-admin/css/wp-admin-rtl.min.css'
					}
				]
			},
			version: {
				options: {
					processContent: function( src ) {
						return src.replace( /^\$wp_version = '(.+?)';/m, function( str, version ) {
							version = version.replace( /-src$/, '' );

							// If the version includes an SVN commit (-12345), it's not a released alpha/beta. Append a timestamp.
							version = version.replace( /-[\d]{5}$/, '-' + grunt.template.today( 'yyyymmdd.HHMMss' ) );

							/* jshint quotmark: true */
							return "$wp_version = '" + version + "';";
						});
					}
				},
				src: SOURCE_DIR + 'wp-includes/version.php',
				dest: BUILD_DIR + 'wp-includes/version.php'
			},
			dynamic: {
				dot: true,
				expand: true,
				cwd: SOURCE_DIR,
				dest: WORKING_DIR,
				src: []
			},
			'dynamic-js': {
				files: {}
			},
			qunit: {
				src: 'tests/qunit/index.html',
				dest: 'tests/qunit/compiled.html',
				options: {
					processContent: function( src ) {
						return src.replace( /(\".+?\/)build(\/.+?)(?:.min)?(.js\")/g , function( match, $1, $2, $3 ) {
							// Don't add `.min` to files that don't have it.
							return $1 + 'build' + $2 + ( /jquery$/.test( $2 ) ? '' : '.min' ) + $3;
						} );
					}
				}
			}
		},
		sass: {
			colors: {
				expand: true,
				cwd: SOURCE_DIR,
				dest: WORKING_DIR,
				ext: '.css',
				src: ['wp-admin/css/colors/*/colors.scss'],
				options: {
					implementation: sass
				}
			}
		},
		cssmin: {
			options: {
				compatibility: 'ie11'
			},
			core: {
				expand: true,
				cwd: WORKING_DIR,
				dest: WORKING_DIR,
				ext: '.min.css',
				src: [
					'wp-admin/css/*.css',
					'!wp-admin/css/wp-admin*.css',
					'wp-includes/css/*.css',
					'wp-includes/js/mediaelement/wp-mediaelement.css'
				]
			},
			rtl: {
				expand: true,
				cwd: WORKING_DIR,
				dest: WORKING_DIR,
				ext: '.min.css',
				src: [
					'wp-admin/css/*-rtl.css',
					'!wp-admin/css/wp-admin*.css',
					'wp-includes/css/*-rtl.css'
				]
			},
			colors: {
				expand: true,
				cwd: WORKING_DIR,
				dest: WORKING_DIR,
				ext: '.min.css',
				src: [
					'wp-admin/css/colors/*/*.css'
				]
			}
		},
		rtlcss: {
			options: {
				// rtlcss options.
				opts: {
					clean: false,
					processUrls: { atrule: true, decl: false },
					stringMap: [
						{
							name: 'import-rtl-stylesheet',
							priority: 10,
							exclusive: true,
							search: [ '.css' ],
							replace: [ '-rtl.css' ],
							options: {
								scope: 'url',
								ignoreCase: false
							}
						}
					]
				},
				saveUnmodified: false,
				plugins: [
					{
						name: 'swap-dashicons-left-right-arrows',
						priority: 10,
						directives: {
							control: {},
							value: []
						},
						processors: [
							{
								expr: /content/im,
								action: function( prop, value ) {
									if ( value === '"\\f141"' ) { // dashicons-arrow-left
										value = '"\\f139"';
									} else if ( value === '"\\f340"' ) { // dashicons-arrow-left-alt
										value = '"\\f344"';
									} else if ( value === '"\\f341"' ) { // dashicons-arrow-left-alt2
										value = '"\\f345"';
									} else if ( value === '"\\f139"' ) { // dashicons-arrow-right
										value = '"\\f141"';
									} else if ( value === '"\\f344"' ) { // dashicons-arrow-right-alt
										value = '"\\f340"';
									} else if ( value === '"\\f345"' ) { // dashicons-arrow-right-alt2
										value = '"\\f341"';
									}
									return { prop: prop, value: value };
								}
							}
						]
					}
				]
			},
			core: {
				expand: true,
				cwd: SOURCE_DIR,
				dest: WORKING_DIR,
				ext: '-rtl.css',
				src: [
					'wp-admin/css/*.css',
					'wp-includes/css/*.css',

					/*
					 * Exclude minified and already processed files, and files from external packages.
					 * These are present when running `grunt build` after `grunt --dev`.
					 */
					'!wp-admin/css/*-rtl.css',
					'!wp-includes/css/*-rtl.css',
					'!wp-admin/css/*.min.css',
					'!wp-includes/css/*.min.css',
					'!wp-includes/css/dist',

					// Exceptions.
					'!wp-includes/css/dashicons.css',
					'!wp-includes/css/wp-embed-template.css',
					'!wp-includes/css/wp-embed-template-ie.css'
				]
			},
			colors: {
				expand: true,
				cwd: WORKING_DIR,
				dest: WORKING_DIR,
				ext: '-rtl.css',
				src: [
					'wp-admin/css/colors/*/colors.css'
				]
			},
			dynamic: {
				expand: true,
				cwd: SOURCE_DIR,
				dest: WORKING_DIR,
				ext: '-rtl.css',
				src: []
			}
		},
		jshint: {
			options: grunt.file.readJSON( '.jshintrc' ),
			grunt: {
				src: ['Gruntfile.js']
			},
			tests: {
				src: [
					'tests/qunit/**/*.js',
					'!tests/qunit/vendor/*',
					'!tests/qunit/editor/**'
				],
				options: grunt.file.readJSON( 'tests/qunit/.jshintrc' )
			},
			themes: {
				expand: true,
				cwd: SOURCE_DIR + 'wp-content/themes',
				src: [
					'twenty*/**/*.js',
					'!twenty{eleven,twelve,thirteen}/**',
					// Third party scripts.
					'!twenty*/node_modules/**',
					'!twenty{fourteen,fifteen,sixteen}/js/html5.js',
					'!twentyseventeen/assets/js/html5.js',
					'!twentyseventeen/assets/js/jquery.scrollTo.js'
				]
			},
			media: {
				src: [
					SOURCE_DIR + 'js/media/**/*.js'
				]
			},
			core: {
				expand: true,
				cwd: SOURCE_DIR,
				src: [
					'js/_enqueues/**/*.js',
					// Third party scripts.
					'!js/_enqueues/vendor/**/*.js'
				],
				// Remove once other JSHint errors are resolved.
				options: {
					curly: false,
					eqeqeq: false
				},
				/*
				 * Limit JSHint's run to a single specified file:
				 *
				 *    grunt jshint:core --file=filename.js
				 *
				 * Optionally, include the file path:
				 *
				 *    grunt jshint:core --file=path/to/filename.js
				 */
				filter: function( filepath ) {
					var index, file = grunt.option( 'file' );

					// Don't filter when no target file is specified.
					if ( ! file ) {
						return true;
					}

					// Normalize filepath for Windows.
					filepath = filepath.replace( /\\/g, '/' );
					index = filepath.lastIndexOf( '/' + file );

					// Match only the filename passed from cli.
					if ( filepath === file || ( -1 !== index && index === filepath.length - ( file.length + 1 ) ) ) {
						return true;
					}

					return false;
				}
			},
			plugins: {
				expand: true,
				cwd: SOURCE_DIR + 'wp-content/plugins',
				src: [
					'**/*.js',
					'!**/*.min.js'
				],
				/*
				 * Limit JSHint's run to a single specified plugin directory:
				 *
				 *    grunt jshint:plugins --dir=foldername
				 */
				filter: function( dirpath ) {
					var index, dir = grunt.option( 'dir' );

					// Don't filter when no target folder is specified.
					if ( ! dir ) {
						return true;
					}

					dirpath = dirpath.replace( /\\/g, '/' );
					index = dirpath.lastIndexOf( '/' + dir );

					// Match only the folder name passed from cli.
					if ( -1 !== index ) {
						return true;
					}

					return false;
				}
			}
		},
		jsdoc : {
			dist : {
				dest: 'jsdoc',
				options: {
					configure : 'jsdoc.conf.json'
				}
			}
		},
		qunit: {
			files: [
				'tests/qunit/**/*.html',
				'!tests/qunit/editor/**'
			]
		},
		phpunit: {
			'default': {
				args: ['--verbose', '-c', 'phpunit.xml.dist']
			},
			ajax: {
				args: ['--verbose', '-c', 'phpunit.xml.dist', '--group', 'ajax']
			},
			multisite: {
				args: ['--verbose', '-c', 'tests/phpunit/multisite.xml']
			},
			'ms-ajax': {
				args: ['--verbose', '-c', 'tests/phpunit/multisite.xml', '--group', 'ajax']
			},
			'ms-files': {
				args: ['--verbose', '-c', 'tests/phpunit/multisite.xml', '--group', 'ms-files']
			},
			'external-http': {
				args: ['--verbose', '-c', 'phpunit.xml.dist', '--group', 'external-http']
			},
			'restapi-jsclient': {
				args: ['--verbose', '-c', 'phpunit.xml.dist', '--group', 'restapi-jsclient']
			}
		},
		uglify: {
			options: {
				output: {
					ascii_only: true
				}
			},
			core: {
				expand: true,
				cwd: WORKING_DIR,
				dest: WORKING_DIR,
				ext: '.min.js',
				src: [
					'wp-admin/js/**/*.js',
					'wp-includes/js/*.js',
					'wp-includes/js/plupload/*.js',
					'wp-includes/js/mediaelement/wp-mediaelement.js',
					'wp-includes/js/mediaelement/wp-playlist.js',
					'wp-includes/js/mediaelement/mediaelement-migrate.js',
					'wp-includes/js/tinymce/plugins/wordpress/plugin.js',
					'wp-includes/js/tinymce/plugins/wp*/plugin.js',

					// Exceptions.
					'!**/*.min.js',
					'!wp-admin/js/custom-header.js', // Why? We should minify this.
					'!wp-admin/js/farbtastic.js',
					'!wp-includes/js/swfobject.js',
					'!wp-includes/js/wp-embed.js' // We have extra options for this, see uglify:embed.
				]
			},
			embed: {
				options: {
					compress: {
						conditionals: false
					}
				},
				expand: true,
				cwd: WORKING_DIR,
				dest: WORKING_DIR,
				ext: '.min.js',
				src: ['wp-includes/js/wp-embed.js']
			},
			'jquery-ui': {
				options: {
					// Preserve comments that start with a bang.
					output: {
						comments: /^!/
					}
				},
				expand: true,
				cwd: WORKING_DIR + 'wp-includes/js/jquery/ui/',
				dest: WORKING_DIR + 'wp-includes/js/jquery/ui/',
				ext: '.min.js',
				src: ['*.js']
			},
			imgareaselect: {
				src: WORKING_DIR + 'wp-includes/js/imgareaselect/jquery.imgareaselect.js',
				dest: WORKING_DIR + 'wp-includes/js/imgareaselect/jquery.imgareaselect.min.js'
			},
			jqueryform: {
				src: WORKING_DIR + 'wp-includes/js/jquery/jquery.form.js',
				dest: WORKING_DIR + 'wp-includes/js/jquery/jquery.form.min.js'
			},
			moment: {
				src: WORKING_DIR + 'wp-includes/js/dist/vendor/moment.js',
				dest: WORKING_DIR + 'wp-includes/js/dist/vendor/moment.min.js'
			},
			dynamic: {
				expand: true,
				cwd: WORKING_DIR,
				dest: WORKING_DIR,
				ext: '.min.js',
				src: []
			}
		},
		webpack: {
			prod: webpackConfig( { environment: 'production', buildTarget: WORKING_DIR } ),
			dev: webpackConfig( { environment: 'development', buildTarget: WORKING_DIR } ),
			watch: webpackConfig( { environment: 'development', watch: true } )
		},
		concat: {
			tinymce: {
				options: {
					separator: '\n',
					process: function( src, filepath ) {
						return '// Source: ' + filepath.replace( WORKING_DIR, '' ) + '\n' + src;
					}
				},
				src: [
					WORKING_DIR + 'wp-includes/js/tinymce/tinymce.min.js',
					WORKING_DIR + 'wp-includes/js/tinymce/themes/modern/theme.min.js',
					WORKING_DIR + 'wp-includes/js/tinymce/plugins/*/plugin.min.js'
				],
				dest: WORKING_DIR + 'wp-includes/js/tinymce/wp-tinymce.js'
			},
			emoji: {
				options: {
					separator: '\n',
					process: function( src, filepath ) {
						return '// Source: ' + filepath.replace( WORKING_DIR, '' ) + '\n' + src;
					}
				},
				src: [
					WORKING_DIR + 'wp-includes/js/twemoji.min.js',
					WORKING_DIR + 'wp-includes/js/wp-emoji.min.js'
				],
				dest: WORKING_DIR + 'wp-includes/js/wp-emoji-release.min.js'
			}
		},
		patch:{
			options: {
				file_mappings: {
					'src/wp-admin/js/accordion.js': 'src/js/_enqueues/lib/accordion.js',
					'src/wp-admin/js/application-passwords.js': 'src/js/_enqueues/admin/application-passwords.js',
					'src/wp-admin/js/auth-app.js': 'src/js/_enqueues/admin/auth-app.js',
					'src/wp-admin/js/code-editor.js': 'src/js/_enqueues/wp/code-editor.js',
					'src/wp-admin/js/color-picker.js': 'src/js/_enqueues/lib/color-picker.js',
					'src/wp-admin/js/comment.js': 'src/js/_enqueues/admin/comment.js',
					'src/wp-admin/js/common.js': 'src/js/_enqueues/admin/common.js',
					'src/wp-admin/js/custom-background.js': 'src/js/_enqueues/admin/custom-background.js',
					'src/wp-admin/js/custom-header.js': 'src/js/_enqueues/admin/custom-header.js',
					'src/wp-admin/js/customize-controls.js': 'src/js/_enqueues/wp/customize/controls.js',
					'src/wp-admin/js/customize-nav-menus.js': 'src/js/_enqueues/wp/customize/nav-menus.js',
					'src/wp-admin/js/customize-widgets.js': 'src/js/_enqueues/wp/customize/widgets.js',
					'src/wp-admin/js/dashboard.js': 'src/js/_enqueues/wp/dashboard.js',
					'src/wp-admin/js/edit-comments.js': 'src/js/_enqueues/admin/edit-comments.js',
					'src/wp-admin/js/editor-expand.js': 'src/js/_enqueues/wp/editor/dfw.js',
					'src/wp-admin/js/editor.js': 'src/js/_enqueues/wp/editor/base.js',
					'src/wp-admin/js/gallery.js': 'src/js/_enqueues/lib/gallery.js',
					'src/wp-admin/js/image-edit.js': 'src/js/_enqueues/lib/image-edit.js',
					'src/wp-admin/js/inline-edit-post.js': 'src/js/_enqueues/admin/inline-edit-post.js',
					'src/wp-admin/js/inline-edit-tax.js': 'src/js/_enqueues/admin/inline-edit-tax.js',
					'src/wp-admin/js/language-chooser.js': 'src/js/_enqueues/lib/language-chooser.js',
					'src/wp-admin/js/link.js': 'src/js/_enqueues/admin/link.js',
					'src/wp-admin/js/media-gallery.js': 'src/js/_enqueues/deprecated/media-gallery.js',
					'src/wp-admin/js/media-upload.js': 'src/js/_enqueues/admin/media-upload.js',
					'src/wp-admin/js/media.js': 'src/js/_enqueues/admin/media.js',
					'src/wp-admin/js/nav-menu.js': 'src/js/_enqueues/lib/nav-menu.js',
					'src/wp-admin/js/password-strength-meter.js': 'src/js/_enqueues/wp/password-strength-meter.js',
					'src/wp-admin/js/plugin-install.js': 'src/js/_enqueues/admin/plugin-install.js',
					'src/wp-admin/js/post.js': 'src/js/_enqueues/admin/post.js',
					'src/wp-admin/js/postbox.js': 'src/js/_enqueues/admin/postbox.js',
					'src/wp-admin/js/revisions.js': 'src/js/_enqueues/wp/revisions.js',
					'src/wp-admin/js/set-post-thumbnail.js': 'src/js/_enqueues/admin/set-post-thumbnail.js',
					'src/wp-admin/js/svg-painter.js': 'src/js/_enqueues/wp/svg-painter.js',
					'src/wp-admin/js/tags-box.js': 'src/js/_enqueues/admin/tags-box.js',
					'src/wp-admin/js/tags-suggest.js': 'src/js/_enqueues/admin/tags-suggest.js',
					'src/wp-admin/js/tags.js': 'src/js/_enqueues/admin/tags.js',
					'src/wp-admin/js/theme-plugin-editor.js': 'src/js/_enqueues/wp/theme-plugin-editor.js',
					'src/wp-admin/js/theme.js': 'src/js/_enqueues/wp/theme.js',
					'src/wp-admin/js/updates.js': 'src/js/_enqueues/wp/updates.js',
					'src/wp-admin/js/user-profile.js': 'src/js/_enqueues/admin/user-profile.js',
					'src/wp-admin/js/user-suggest.js': 'src/js/_enqueues/lib/user-suggest.js',
					'src/wp-admin/js/widgets/custom-html-widgets.js': 'src/js/_enqueues/wp/widgets/custom-html.js',
					'src/wp-admin/js/widgets/media-audio-widget.js': 'src/js/_enqueues/wp/widgets/media-audio.js',
					'src/wp-admin/js/widgets/media-gallery-widget.js': 'src/js/_enqueues/wp/widgets/media-gallery.js',
					'src/wp-admin/js/widgets/media-image-widget.js': 'src/js/_enqueues/wp/widgets/media-image.js',
					'src/wp-admin/js/widgets/media-video-widget.js': 'src/js/_enqueues/wp/widgets/media-video.js',
					'src/wp-admin/js/widgets/media-widgets.js': 'src/js/_enqueues/wp/widgets/media.js',
					'src/wp-admin/js/widgets/text-widgets.js': 'src/js/_enqueues/wp/widgets/text.js',
					'src/wp-admin/js/widgets.js': 'src/js/_enqueues/admin/widgets.js',
					'src/wp-admin/js/word-count.js': 'src/js/_enqueues/wp/utils/word-count.js',
					'src/wp-admin/js/wp-fullscreen-stub.js': 'src/js/_enqueues/deprecated/fullscreen-stub.js',
					'src/wp-admin/js/xfn.js': 'src/js/_enqueues/admin/xfn.js',
					'src/wp-includes/js/admin-bar.js': 'src/js/_enqueues/lib/admin-bar.js',
					'src/wp-includes/js/api-request.js': 'src/js/_enqueues/wp/api-request.js',
					'src/wp-includes/js/autosave.js': 'src/js/_enqueues/wp/autosave.js',
					'src/wp-includes/js/comment-reply.js': 'src/js/_enqueues/lib/comment-reply.js',
					'src/wp-includes/js/customize-base.js': 'src/js/_enqueues/wp/customize/base.js',
					'src/wp-includes/js/customize-loader.js': 'src/js/_enqueues/wp/customize/loader.js',
					'src/wp-includes/js/customize-models.js': 'src/js/_enqueues/wp/customize/models.js',
					'src/wp-includes/js/customize-preview-nav-menus.js': 'src/js/_enqueues/wp/customize/preview-nav-menus.js',
					'src/wp-includes/js/customize-preview-widgets.js': 'src/js/_enqueues/wp/customize/preview-widgets.js',
					'src/wp-includes/js/customize-preview.js': 'src/js/_enqueues/wp/customize/preview.js',
					'src/wp-includes/js/customize-selective-refresh.js': 'src/js/_enqueues/wp/customize/selective-refresh.js',
					'src/wp-includes/js/customize-views.js': 'src/js/_enqueues/wp/customize/views.js',
					'src/wp-includes/js/heartbeat.js': 'src/js/_enqueues/wp/heartbeat.js',
					'src/wp-includes/js/mce-view.js': 'src/js/_enqueues/wp/mce-view.js',
					'src/wp-includes/js/media-editor.js': 'src/js/_enqueues/wp/media/editor.js',
					'src/wp-includes/js/quicktags.js': 'src/js/_enqueues/lib/quicktags.js',
					'src/wp-includes/js/shortcode.js': 'src/js/_enqueues/wp/shortcode.js',
					'src/wp-includes/js/utils.js': 'src/js/_enqueues/lib/cookies.js',
					'src/wp-includes/js/wp-ajax-response.js': 'src/js/_enqueues/lib/ajax-response.js',
					'src/wp-includes/js/wp-api.js': 'src/js/_enqueues/wp/api.js',
					'src/wp-includes/js/wp-auth-check.js': 'src/js/_enqueues/lib/auth-check.js',
					'src/wp-includes/js/wp-backbone.js': 'src/js/_enqueues/wp/backbone.js',
					'src/wp-includes/js/wp-custom-header.js': 'src/js/_enqueues/wp/custom-header.js',
					'src/wp-includes/js/wp-embed-template.js': 'src/js/_enqueues/lib/embed-template.js',
					'src/wp-includes/js/wp-embed.js': 'src/js/_enqueues/wp/embed.js',
					'src/wp-includes/js/wp-emoji-loader.js': 'src/js/_enqueues/lib/emoji-loader.js',
					'src/wp-includes/js/wp-emoji.js': 'src/js/_enqueues/wp/emoji.js',
					'src/wp-includes/js/wp-list-revisions.js': 'src/js/_enqueues/lib/list-revisions.js',
					'src/wp-includes/js/wp-lists.js': 'src/js/_enqueues/lib/lists.js',
					'src/wp-includes/js/wp-pointer.js': 'src/js/_enqueues/lib/pointer.js',
					'src/wp-includes/js/wp-sanitize.js': 'src/js/_enqueues/wp/sanitize.js',
					'src/wp-includes/js/wp-util.js': 'src/js/_enqueues/wp/util.js',
					'src/wp-includes/js/wpdialog.js': 'src/js/_enqueues/lib/dialog.js',
					'src/wp-includes/js/wplink.js': 'src/js/_enqueues/lib/link.js',
					'src/wp-includes/js/zxcvbn-async.js': 'src/js/_enqueues/lib/zxcvbn-async.js',
					'src/wp-includes/js/media/controllers/audio-details.js' : 'src/js/media/controllers/audio-details.js',
					'src/wp-includes/js/media/controllers/collection-add.js' : 'src/js/media/controllers/collection-add.js',
					'src/wp-includes/js/media/controllers/collection-edit.js' : 'src/js/media/controllers/collection-edit.js',
					'src/wp-includes/js/media/controllers/cropper.js' : 'src/js/media/controllers/cropper.js',
					'src/wp-includes/js/media/controllers/customize-image-cropper.js' : 'src/js/media/controllers/customize-image-cropper.js',
					'src/wp-includes/js/media/controllers/edit-attachment-metadata.js' : 'src/js/media/controllers/edit-attachment-metadata.js',
					'src/wp-includes/js/media/controllers/edit-image.js' : 'src/js/media/controllers/edit-image.js',
					'src/wp-includes/js/media/controllers/embed.js' : 'src/js/media/controllers/embed.js',
					'src/wp-includes/js/media/controllers/featured-image.js' : 'src/js/media/controllers/featured-image.js',
					'src/wp-includes/js/media/controllers/gallery-add.js' : 'src/js/media/controllers/gallery-add.js',
					'src/wp-includes/js/media/controllers/gallery-edit.js' : 'src/js/media/controllers/gallery-edit.js',
					'src/wp-includes/js/media/controllers/image-details.js' : 'src/js/media/controllers/image-details.js',
					'src/wp-includes/js/media/controllers/library.js' : 'src/js/media/controllers/library.js',
					'src/wp-includes/js/media/controllers/media-library.js' : 'src/js/media/controllers/media-library.js',
					'src/wp-includes/js/media/controllers/region.js' : 'src/js/media/controllers/region.js',
					'src/wp-includes/js/media/controllers/replace-image.js' : 'src/js/media/controllers/replace-image.js',
					'src/wp-includes/js/media/controllers/site-icon-cropper.js' : 'src/js/media/controllers/site-icon-cropper.js',
					'src/wp-includes/js/media/controllers/state-machine.js' : 'src/js/media/controllers/state-machine.js',
					'src/wp-includes/js/media/controllers/state.js' : 'src/js/media/controllers/state.js',
					'src/wp-includes/js/media/controllers/video-details.js' : 'src/js/media/controllers/video-details.js',
					'src/wp-includes/js/media/models/attachment.js' : 'src/js/media/models/attachment.js',
					'src/wp-includes/js/media/models/attachments.js' : 'src/js/media/models/attachments.js',
					'src/wp-includes/js/media/models/post-image.js' : 'src/js/media/models/post-image.js',
					'src/wp-includes/js/media/models/post-media.js' : 'src/js/media/models/post-media.js',
					'src/wp-includes/js/media/models/query.js' : 'src/js/media/models/query.js',
					'src/wp-includes/js/media/models/selection.js' : 'src/js/media/models/selection.js',
					'src/wp-includes/js/media/routers/manage.js' : 'src/js/media/routers/manage.js',
					'src/wp-includes/js/media/utils/selection-sync.js' : 'src/js/media/utils/selection-sync.js',
					'src/wp-includes/js/media/views/attachment-compat.js' : 'src/js/media/views/attachment-compat.js',
					'src/wp-includes/js/media/views/attachment-filters.js' : 'src/js/media/views/attachment-filters.js',
					'src/wp-includes/js/media/views/attachment-filters/all.js' : 'src/js/media/views/attachment-filters/all.js',
					'src/wp-includes/js/media/views/attachment-filters/date.js' : 'src/js/media/views/attachment-filters/date.js',
					'src/wp-includes/js/media/views/attachment-filters/uploaded.js' : 'src/js/media/views/attachment-filters/uploaded.js',
					'src/wp-includes/js/media/views/attachment.js' : 'src/js/media/views/attachment.js',
					'src/wp-includes/js/media/views/attachment/details-two-column.js' : 'src/js/media/views/details-two-column.js',
					'src/wp-includes/js/media/views/attachment/details.js' : 'src/js/media/views/details.js',
					'src/wp-includes/js/media/views/attachment/edit-library.js' : 'src/js/media/views/edit-library.js',
					'src/wp-includes/js/media/views/attachment/edit-selection.js' : 'src/js/media/views/edit-selection.js',
					'src/wp-includes/js/media/views/attachment/library.js' : 'src/js/media/views/library.js',
					'src/wp-includes/js/media/views/attachment/selection.js' : 'src/js/media/views/selection.js',
					'src/wp-includes/js/media/views/attachment/attachments.js' : 'src/js/media/views/attachments.js',
					'src/wp-includes/js/media/views/attachments/browser.js' : 'src/js/media/views/attachments/browser.js',
					'src/wp-includes/js/media/views/attachments/selection.js' : 'src/js/media/views/attachments/selection.js',
					'src/wp-includes/js/media/views/attachments/audio-details.js' : 'src/js/media/views/attachments/audio-details.js',
					'src/wp-includes/js/media/views/attachments/button-group.js' : 'src/js/media/views/attachments/button-group.js',
					'src/wp-includes/js/media/views/attachments/button.js' : 'src/js/media/views/attachments/button.js',
					'src/wp-includes/js/media/views/button/delete-selected-permanently.js' : 'src/js/media/views/button/delete-selected-permanently.js',
					'src/wp-includes/js/media/views/button/delete-selected.js' : 'src/js/media/views/button/delete-selected.js',
					'src/wp-includes/js/media/views/button/select-mode-toggle.js' : 'src/js/media/views/button/select-mode-toggle.js',
					'src/wp-includes/js/media/views/cropper.js' : 'src/js/media/views/cropper.js',
					'src/wp-includes/js/media/views/edit-image-details.js' : 'src/js/media/views/edit-image-details.js',
					'src/wp-includes/js/media/views/edit-image.js' : 'src/js/media/views/edit-image.js',
					'src/wp-includes/js/media/views/embed.js' : 'src/js/media/views/embed.js',
					'src/wp-includes/js/media/views/embed/image.js' : 'src/js/media/views/embed/image.js',
					'src/wp-includes/js/media/views/embed/link.js' : 'src/js/media/views/embed/link.js',
					'src/wp-includes/js/media/views/embed/url.js' : 'src/js/media/views/embed/url.js',
					'src/wp-includes/js/media/views/focus-manager.js' : 'src/js/media/views/focus-manager.js',
					'src/wp-includes/js/media/views/frame.js' : 'src/js/media/views/frame.js',
					'src/wp-includes/js/media/views/frame/audio-details.js' : 'src/js/media/views/frame/audio-details.js',
					'src/wp-includes/js/media/views/frame/edit-attachments.js' : 'src/js/media/views/frame/edit-attachments.js',
					'src/wp-includes/js/media/views/frame/image-details.js' : 'src/js/media/views/frame/image-details.js',
					'src/wp-includes/js/media/views/frame/manage.js' : 'src/js/media/views/frame/manage.js',
					'src/wp-includes/js/media/views/frame/media-details.js' : 'src/js/media/views/frame/media-details.js',
					'src/wp-includes/js/media/views/frame/post.js' : 'src/js/media/views/frame/post.js',
					'src/wp-includes/js/media/views/frame/select.js' : 'src/js/media/views/frame/select.js',
					'src/wp-includes/js/media/views/frame/video-details.js' : 'src/js/media/views/frame/video-details.js',
					'src/wp-includes/js/media/views/iframe.js' : 'src/js/media/views/iframe.js',
					'src/wp-includes/js/media/views/image-details.js' : 'src/js/media/views/image-details.js',
					'src/wp-includes/js/media/views/label.js' : 'src/js/media/views/label.js',
					'src/wp-includes/js/media/views/media-details.js' : 'src/js/media/views/media-details.js',
					'src/wp-includes/js/media/views/media-frame.js' : 'src/js/media/views/media-frame.js',
					'src/wp-includes/js/media/views/menu-item.js' : 'src/js/media/views/menu-item.js',
					'src/wp-includes/js/media/views/menu.js' : 'src/js/media/views/menu.js',
					'src/wp-includes/js/media/views/modal.js' : 'src/js/media/views/modal.js',
					'src/wp-includes/js/media/views/priority-list.js' : 'src/js/media/views/priority-list.js',
					'src/wp-includes/js/media/views/router-item.js' : 'src/js/media/views/router-item.js',
					'src/wp-includes/js/media/views/router.js' : 'src/js/media/views/router.js',
					'src/wp-includes/js/media/views/search.js' : 'src/js/media/views/search.js',
					'src/wp-includes/js/media/views/selection.js' : 'src/js/media/views/selection.js',
					'src/wp-includes/js/media/views/settings.js' : 'src/js/media/views/settings.js',
					'src/wp-includes/js/media/views/settings/attachment-display.js' : 'src/js/media/views/settings/attachment-display.js',
					'src/wp-includes/js/media/views/settings/gallery.js' : 'src/js/media/views/settings/gallery.js',
					'src/wp-includes/js/media/views/settings/playlist.js' : 'src/js/media/views/settings/playlist.js',
					'src/wp-includes/js/media/views/sidebar.js' : 'src/js/media/views/sidebar.js',
					'src/wp-includes/js/media/views/site-icon-cropper.js' : 'src/js/media/views/site-icon-cropper.js',
					'src/wp-includes/js/media/views/site-icon-preview.js' : 'src/js/media/views/site-icon-preview.js',
					'src/wp-includes/js/media/views/spinner.js' : 'src/js/media/views/spinner.js',
					'src/wp-includes/js/media/views/toolbar.js' : 'src/js/media/views/toolbar.js',
					'src/wp-includes/js/media/views/toolbar/embed.js' : 'src/js/media/views/toolbar/embed.js',
					'src/wp-includes/js/media/views/toolbar/select.js' : 'src/js/media/views/toolbar/select.js',
					'src/wp-includes/js/media/views/uploader/editor.js' : 'src/js/media/views/uploader/editor.js',
					'src/wp-includes/js/media/views/uploader/inline.js' : 'src/js/media/views/uploader/inline.js',
					'src/wp-includes/js/media/views/uploader/status-error.js' : 'src/js/media/views/uploader/status-error.js',
					'src/wp-includes/js/media/views/uploader/status.js' : 'src/js/media/views/uploader/status.js',
					'src/wp-includes/js/media/views/uploader/window.js' : 'src/js/media/views/uploader/window.js',
					'src/wp-includes/js/media/views/video-details.js' : 'src/js/media/views/video-details.js',
					'src/wp-includes/js/media/views/view.js' : 'src/js/media/views/view.js'
				}
			}
		},
		jsvalidate:{
			options: {
				globals: {},
				esprimaOptions:{},
				verbose: false
			},
			build: {
				files: {
					src: [
						WORKING_DIR + 'wp-{admin,includes}/**/*.js',
						WORKING_DIR + 'wp-content/themes/twenty*/**/*.js',
						'!' + WORKING_DIR + 'wp-content/themes/twenty*/node_modules/**/*.js',
						'!' + WORKING_DIR + 'wp-includes/blocks/**/*.js',
						'!' + WORKING_DIR + 'wp-includes/js/dist/**/*.js',
					]
				}
			},
			dynamic: {
				files: {
					src: []
				}
			}
		},
		imagemin: {
			core: {
				expand: true,
				cwd: SOURCE_DIR,
				src: [
					'wp-{admin,includes}/images/**/*.{png,jpg,gif,jpeg}',
					'wp-includes/js/tinymce/skins/wordpress/images/*.{png,jpg,gif,jpeg}'
				],
				dest: SOURCE_DIR
			}
		},
		replace: {
			'emoji-regex': {
				options: {
					patterns: [
						{
							match: /\/\/ START: emoji arrays[\S\s]*\/\/ END: emoji arrays/g,
							replacement: function() {
								var regex, files,
									partials, partialsSet,
									entities, emojiArray;

								grunt.log.writeln( 'Fetching list of Twemoji files...' );

								// Fetch a list of the files that Twemoji supplies.
								files = spawn( 'svn', [ 'ls', 'https://github.com/twitter/twemoji.git/trunk/assets/svg' ] );
								if ( 0 !== files.status ) {
									grunt.fatal( 'Unable to fetch Twemoji file list' );
								}

								entities = files.stdout.toString();

								// Tidy up the file list.
								entities = entities.replace( /\.svg/g, '' );
								entities = entities.replace( /^$/g, '' );

								// Convert the emoji entities to HTML entities.
								partials = entities = entities.replace( /([a-z0-9]+)/g, '&#x$1;' );

								// Remove the hyphens between the HTML entities.
								entities = entities.replace( /-/g, '' );

								// Sort the entities list by length, so the longest emoji will be found first.
								emojiArray = entities.split( '\n' ).sort( function( a, b ) {
									return b.length - a.length;
								} );

								// Convert the entities list to PHP array syntax.
								entities = '\'' + emojiArray.filter( function( val ) {
									return val.length >= 8 ? val : false ;
								} ).join( '\', \'' ) + '\'';

								// Create a list of all characters used by the emoji list.
								partials = partials.replace( /-/g, '\n' );

								// Set automatically removes duplicates.
								partialsSet = new Set( partials.split( '\n' ) );

								// Convert the partials list to PHP array syntax.
								partials = '\'' + Array.from( partialsSet ).filter( function( val ) {
									return val.length >= 8 ? val : false ;
								} ).join( '\', \'' ) + '\'';

								regex = '// START: emoji arrays\n';
								regex += '\t$entities = array( ' + entities + ' );\n';
								regex += '\t$partials = array( ' + partials + ' );\n';
								regex += '\t// END: emoji arrays';

								return regex;
							}
						}
					]
				},
				files: [
					{
						expand: true,
						flatten: true,
						src: [
							SOURCE_DIR + 'wp-includes/formatting.php'
						],
						dest: SOURCE_DIR + 'wp-includes/'
					}
				]
			},
			'source-maps': {
				options: {
					patterns: [
						{
							match: new RegExp( '//# sourceMappingURL=.*\\s*' ),
							replacement: ''
						}
					]
				},
				files: [
					{
						expand: true,
						flatten: true,
						src: [
							BUILD_DIR + 'wp-includes/js/underscore.js'
						],
						dest: BUILD_DIR + 'wp-includes/js/'
					}
				]
			}
		},
		_watch: {
			options: {
				interval: 2000
			},
			all: {
				files: [
					SOURCE_DIR + '**',
					'!' + SOURCE_DIR + 'js/**/*.js',
					// Ignore version control directories.
					'!' + SOURCE_DIR + '**/.{svn,git}/**'
				],
				tasks: ['clean:dynamic', 'copy:dynamic'],
				options: {
					dot: true,
					spawn: false
				}
			},
			'js-enqueues': {
				files: [SOURCE_DIR + 'js/_enqueues/**/*.js'],
				tasks: ['clean:dynamic', 'copy:dynamic-js', 'uglify:dynamic', 'jsvalidate:dynamic'],
				options: {
					dot: true,
					spawn: false
				}
			},
			'js-webpack': {
				files: [
					SOURCE_DIR + 'js/**/*.js',
					'!' + SOURCE_DIR + 'js/_enqueues/**/*.js',
					'webpack-dev.config.js'
				],
				tasks: ['clean:dynamic', 'webpack:dev', 'uglify:dynamic', 'jsvalidate:dynamic'],
				options: {
					dot: true,
					spawn: false
				}
			},
			config: {
				files: [
					'Gruntfile.js',
					'webpack.config.js'
				]
			},
			colors: {
				files: [SOURCE_DIR + 'wp-admin/css/colors/**'],
				tasks: ['sass:colors']
			},
			rtl: {
				files: [
					SOURCE_DIR + 'wp-admin/css/*.css',
					SOURCE_DIR + 'wp-includes/css/*.css'
				],
				tasks: ['rtlcss:dynamic'],
				options: {
					spawn: false
				}
			},
			test: {
				files: [
					'tests/qunit/**',
					'!tests/qunit/editor/**'
				],
				tasks: ['qunit']
			}
		}
	});

	// Allow builds to be minimal.
	if( grunt.option( 'minimal-copy' ) ) {
		var copyFilesOptions = grunt.config.get( 'copy.files.files' );
		copyFilesOptions[0].src.push( '!wp-content/plugins/**' );
		copyFilesOptions[0].src.push( '!wp-content/themes/!(twenty*)/**' );
		grunt.config.set( 'copy.files.files', copyFilesOptions );
	}


	// Register tasks.

	// Webpack task.
	grunt.loadNpmTasks( 'grunt-webpack' );

	// RTL task.
	grunt.registerTask('rtl', ['rtlcss:core', 'rtlcss:colors']);

	// Color schemes task.
	grunt.registerTask('colors', ['sass:colors', 'postcss:colors']);

	// JSHint task.
	grunt.registerTask( 'jshint:corejs', [
		'jshint:grunt',
		'jshint:tests',
		'jshint:themes',
		'jshint:core',
		'jshint:media'
	] );

	grunt.registerTask( 'restapi-jsclient', [
		'phpunit:restapi-jsclient',
		'qunit:compiled'
	] );

	grunt.registerTask( 'sync-gutenberg-packages', function() {
		if ( grunt.option( 'update-browserlist' ) ) {
			/*
			 * Updating the browserlist database is opt-in and up to the release lead.
			 *
			 * Browserlist database should be updated:
			 * - In each release cycle up until RC1
			 * - If Webpack throws a warning about an outdated database
			 *
			 * It should not be updated:
			 * - After the RC1
			 * - When backporting fixes to older WordPress releases.
			 *
			 * For more context, see:
			 * https://github.com/WordPress/wordpress-develop/pull/2621#discussion_r859840515
			 * https://core.trac.wordpress.org/ticket/55559
			 */
			grunt.task.run( 'browserslist:update' );
		}

		// Install the latest version of the packages already listed in package.json.
		grunt.task.run( 'wp-packages:update' );

		/*
		 * Install any new @wordpress packages that are now required.
		 * Update any non-@wordpress deps to the same version as required in the @wordpress packages (e.g. react 16 -> 17).
		 */
		grunt.task.run( 'wp-packages:refresh-deps' );
	} );

	grunt.renameTask( 'watch', '_watch' );

	grunt.registerTask( 'watch', function() {
		if ( ! this.args.length || this.args.indexOf( 'webpack' ) > -1 ) {
			grunt.task.run( 'build' );
		}

		if ( 'watch:phpunit' === grunt.cli.tasks[ 0 ] || 'undefined' !== typeof grunt.option( 'phpunit' ) ) {
			grunt.config.data._watch.phpunit = {
				files: [ '**/*.php' ],
				tasks: [ 'phpunit:default' ]
			};
		}

		grunt.task.run( '_' + this.nameArgs );
	} );

	grunt.registerTask( 'precommit:image', [
		'imagemin:core'
	] );

	grunt.registerTask( 'precommit:js', [
		'webpack:prod',
		'jshint:corejs',
		'uglify:imgareaselect',
		'uglify:jqueryform',
		'uglify:moment',
		'qunit:compiled'
	] );

	grunt.registerTask( 'precommit:css', [
		'postcss:core'
	] );

	grunt.registerTask( 'precommit:php', [
		'phpunit'
	] );

	grunt.registerTask( 'precommit:emoji', [
		'replace:emoji-regex'
	] );

	grunt.registerTask( 'precommit', 'Runs test and build tasks in preparation for a commit', function() {
		var done = this.async();
		var map = {
			svn: 'svn status --ignore-externals',
			git: 'git status --short'
		};

		find( [
			__dirname + '/.svn',
			__dirname + '/.git',
			path.dirname( __dirname ) + '/.svn'
		] );

		function find( set ) {
			var dir;

			if ( set.length ) {
				fs.stat( dir = set.shift(), function( error ) {
					error ? find( set ) : run( path.basename( dir ).substr( 1 ) );
				} );
			} else {
				runAllTasks();
			}
		}

		function runAllTasks() {
			grunt.log.writeln( 'Cannot determine which files are modified as SVN and GIT are not available.' );
			grunt.log.writeln( 'Running all tasks and all tests.' );
			grunt.task.run([
				'format:php',
				'precommit:js',
				'precommit:css',
				'precommit:image',
				'precommit:emoji',
				'precommit:php'
			]);

			done();
		}

		function run( type ) {
			var command = map[ type ].split( ' ' );

			grunt.util.spawn( {
				cmd: command.shift(),
				args: command
			}, function( error, result, code ) {
				var taskList = [];

				// Callback for finding modified paths.
				function testPath( path ) {
					var regex = new RegExp( ' ' + path + '$', 'm' );
					return regex.test( result.stdout );
				}

				// Callback for finding modified files by extension.
				function testExtension( extension ) {
					var regex = new RegExp( '\.' + extension + '$', 'm' );
					return regex.test( result.stdout );
				}

				if ( code === 0 ) {
					if ( [ 'package.json', 'Gruntfile.js', 'composer.json' ].some( testPath ) ) {
						grunt.log.writeln( 'Configuration files modified. Running `prerelease`.' );
						taskList.push( 'prerelease' );
					} else {
						if ( [ 'png', 'jpg', 'gif', 'jpeg' ].some( testExtension ) ) {
							grunt.log.writeln( 'Image files modified. Minifying.' );
							taskList.push( 'precommit:image' );
						}

						[ 'js', 'css', 'php' ].forEach( function( extension ) {
							if ( testExtension( extension ) ) {
								grunt.log.writeln( extension.toUpperCase() + ' files modified. ' + extension.toUpperCase() + ' tests will be run.' );
								taskList.push( 'precommit:' + extension );
							}
						} );

						if ( [ 'twemoji.js' ].some( testPath ) ) {
							grunt.log.writeln( 'twemoji.js has updated. Running `precommit:emoji.' );
							taskList.push( 'precommit:emoji' );
						}

						if ( testExtension( 'php' ) ) {
							grunt.log.writeln( 'PHP files modified. Code formatting will be run.' );
							var PHPfiles = result.stdout.split( '\n' );

							// Find .php files that have been modified or added.
							PHPfiles = PHPfiles.filter( function( file ) {
								return /^\s*[MA]\s*.*\.php$/.test( file );
							} );

							PHPfiles = PHPfiles.map( function( file ) {
								return file.replace( /^\s*[MA]\s*/, '' );
							} );

							changedFiles = {
								php: PHPfiles
							};

							taskList.push( 'format:php' );
						}
					}

					grunt.task.run( taskList );
					done();
				} else {
					runAllTasks();
				}
			} );
		}
	} );

	grunt.registerTask( 'copy:js', [
		'copy:npm-packages',
		'copy:vendor-js',
		'copy:admin-js',
		'copy:includes-js'
	] );

	grunt.registerTask( 'uglify:all', [
		'uglify:core',
		'uglify:embed',
		'uglify:jquery-ui',
		'uglify:imgareaselect',
		'uglify:jqueryform',
		'uglify:moment'
	] );

	grunt.registerTask( 'build:webpack', [
		'clean:webpack-assets',
		'webpack:prod',
		'webpack:dev',
	] );

	grunt.registerTask( 'build:js', [
		'clean:js',
		'build:webpack',
		'copy:js',
		'file_append',
		'uglify:all',
		'concat:tinymce',
		'concat:emoji',
		'jsvalidate:build'
	] );

	grunt.registerTask( 'build:css', [
		'clean:css',
		'copy:wp-admin-css-compat-rtl',
		'copy:wp-admin-css-compat-min',
		'cssmin:core',
		'colors',
		'rtl',
		'cssmin:rtl',
		'cssmin:colors',
		'usebanner'
	] );

	grunt.registerTask( 'build:files', [
		'clean:files',
		'copy:files',
		'copy:version',
	] );

	/**
	 * Build verification tasks.
	 */
	grunt.registerTask( 'verify:build', [
		'verify:wp-embed',
		'verify:old-files',
		'verify:source-maps',
	] );

	/**
	 * Build assertions for wp-embed.min.js.
	 *
	 * @ticket 34698
	 */
	grunt.registerTask( 'verify:wp-embed', function() {
		const file = `${ BUILD_DIR }/wp-includes/js/wp-embed.min.js`;

		assert(
			fs.existsSync( file ),
			'The build/wp-includes/js/wp-embed.min.js file does not exist.'
		);

		const contents = fs.readFileSync( file, {
			encoding: 'utf8',
		} );

		assert(
			contents.length > 0,
			'The build/wp-includes/js/wp-embed.min.js file must not be empty.'
		);
		assert(
			false === contents.includes( '&' ),
			'The build/wp-includes/js/wp-embed.min.js file must not contain ampersands.'
		);
	} );

	/**
	 * Build assertions to ensure no project files are inside `$_old_files` in the build directory.
	 *
	 * @ticket 36083
	 */
	grunt.registerTask( 'verify:old-files', function() {
		const file = `${ BUILD_DIR }wp-admin/includes/update-core.php`;

		assert(
			fs.existsSync( file ),
			'The build/wp-admin/includes/update-core.php file does not exist.'
		);

		const contents = fs.readFileSync( file, {
			encoding: 'utf8',
		} );

		assert(
			contents.length > 0,
			'The build/wp-admin/includes/update-core.php file must not be empty.'
		);

		const match = contents.match( /\$_old_files = array\(([^\)]+)\);/ );

		assert(
			match.length > 0,
			'The build/wp-admin/includes/update-core.php file does not include an `$_old_files` array.'
		);

		const files = match[1].split( '\n\t' ).filter( function( file ) {
			// Filter out empty lines.
			if ( '' === file ) {
				return false;
			}

			// Filter out commented out lines.
			if ( 0 === file.indexOf( '/' ) ) {
				return false;
			}

			return true;
		} ).map( function( file ) {
			// Strip leading and trailing single quotes and commas.
			return file.replace( /^\'|\',$/g, '' );
		} );

		files.forEach(function( file ){
			const search = `${ BUILD_DIR }${ file }`;
			assert(
				false === fs.existsSync( search ),
				`${ search } should not be present in the $_old_files array.`
			);
		});
	} );

	/**
	 * Build assertions for the lack of source maps in JavaScript files.
	 *
	 * @ticket 24994
	 * @ticket 46218
	 */
	grunt.registerTask( 'verify:source-maps', function() {
		const ignoredFiles = [
			'build/wp-includes/js/dist/components.js'
		];
		const files = buildFiles.reduce( ( acc, path ) => {
			// Skip excluded paths and any path that isn't a file.
			if ( '!' === path[0] || '**' !== path.substr( -2 ) ) {
				return acc;
			}
			acc.push( ...glob.sync( `${ BUILD_DIR }/${ path }/*.js` ) );
			return acc;
		}, [] );

		assert(
			files.length > 0,
			'No JavaScript files found in the build directory.'
		);

		files
			.filter(file => ! ignoredFiles.includes( file) )
			.forEach( function( file ) {
				const contents = fs.readFileSync( file, {
					encoding: 'utf8',
				} );
				// `data:` URLs are allowed:
				const match = contents.match( /sourceMappingURL=((?!data:).)/ );

				assert(
					match === null,
					`The ${ file } file must not contain a sourceMappingURL.`
				);
			} );
	} );

	grunt.registerTask( 'build', function() {
		if ( grunt.option( 'dev' ) ) {
			grunt.task.run( [
				'build:js',
				'build:css',
			] );
		} else {
			grunt.task.run( [
				'build:files',
				'build:js',
				'build:css',
				'replace:source-maps',
				'verify:build'
			] );
		}
	} );

	grunt.registerTask( 'prerelease', [
		'format:php:error',
		'precommit:php',
		'precommit:js',
		'precommit:css',
		'precommit:image'
	] );

	// Testing tasks.
	grunt.registerMultiTask( 'phpunit', 'Runs PHPUnit tests, including the ajax, external-http, and multisite tests.', function() {
		var args = phpUnitWatchGroup ? this.data.args.concat( [ '--group', phpUnitWatchGroup ] ) : this.data.args;

		args.unshift( 'test', '--' );

		grunt.util.spawn({
			cmd: 'composer',
			args: args,
			opts: { stdio: 'inherit' }
		}, this.async());
	});

	grunt.registerTask( 'qunit:compiled', 'Runs QUnit tests on compiled as well as uncompiled scripts.',
		['build', 'copy:qunit', 'qunit']
	);

	grunt.registerTask( 'test', 'Runs all QUnit and PHPUnit tasks.', ['qunit:compiled', 'phpunit'] );

	grunt.registerTask( 'format:php', 'Runs the code formatter on changed files.', function() {
		var done = this.async();
		var flags = this.flags;
		var args = changedFiles.php;

		args.unshift( 'format' );

		grunt.util.spawn( {
			cmd: 'composer',
			args: args,
			opts: { stdio: 'inherit' }
		}, function( error ) {
			if ( flags.error && error ) {
				done( false );
			} else {
				done( true );
			}
		} );
	} );

	grunt.registerTask( 'lint:php', 'Runs the code linter on changed files.', function() {
		var done = this.async();
		var flags = this.flags;
		var args = changedFiles.php;

		args.unshift( 'lint' );

		grunt.util.spawn( {
			cmd: 'composer',
			args: args,
			opts: { stdio: 'inherit' }
		}, function( error ) {
			if ( flags.error && error ) {
				done( false );
			} else {
				done( true );
			}
		} );
	} );

	grunt.registerTask( 'wp-packages:update', 'Update WordPress packages', function() {
		const distTag = grunt.option('dist-tag') || 'latest';
		grunt.log.writeln( `Updating WordPress packages (--dist-tag=${distTag})` );
		spawn( 'npx', [ 'wp-scripts', 'packages-update', `--dist-tag=${distTag}` ], {
			cwd: __dirname,
			stdio: 'inherit',
		} );
	} );

	grunt.registerTask( 'browserslist:update', 'Update the local database of browser supports', function() {
		grunt.log.writeln( `Updating browsers list` );
		spawn( 'npx', [ 'browserslist@latest', '--update-db' ], {
			cwd: __dirname,
			stdio: 'inherit',
		} );
	} );

	grunt.registerTask( 'wp-packages:refresh-deps', 'Update version of dependencies in package.json to match the ones listed in the latest WordPress packages', function() {
		const distTag = grunt.option('dist-tag') || 'latest';
		grunt.log.writeln( `Updating versions of dependencies listed in package.json (--dist-tag=${distTag})` );
		spawn( 'node', [ 'tools/release/sync-gutenberg-packages.js', `--dist-tag=${distTag}` ], {
			cwd: __dirname,
			stdio: 'inherit',
		} );
	} );

	grunt.registerTask( 'wp-packages:sync-stable-blocks', 'Refresh the PHP files referring to stable @wordpress/block-library blocks.', function() {
		grunt.log.writeln( `Syncing stable blocks from @wordpress/block-library to src/` );
		const { main } = require( './tools/release/sync-stable-blocks' );
		main();
	} );

	// Patch task.
	grunt.renameTask('patch_wordpress', 'patch');

	// Add an alias `apply` of the `patch` task name.
	grunt.registerTask('apply', 'patch');

	// Default task.
	grunt.registerTask('default', ['build']);

	/*
	 * Automatically updates the `:dynamic` configurations
	 * so that only the changed files are updated.
	 */
	grunt.event.on( 'watch', function( action, filepath, target ) {
		var src;

		// Only configure the dynamic tasks based on known targets.
		if ( [ 'all', 'rtl', 'webpack', 'js-enqueues', 'js-webpack' ].indexOf( target ) === -1 ) {
			return;
		}

		// Normalize filepath for Windows.
		filepath = filepath.replace( /\\/g, '/' );

		// If the target is a file in the restructured js src.
		if ( target === 'js-enqueues' ) {
			var files = {};
			var configs, dest;

			// If it's a vendor file which are configured with glob matchers.
			if ( filepath.indexOf( SOURCE_DIR + 'js/_enqueues/vendor/' ) === 0 ) {
				// Grab the glob matchers from the copy task.
				configs = grunt.config( [ 'copy', 'vendor-js', 'files' ] );

				// For each glob matcher check if it matches and if so set the variables for our dynamic tasks.
				for ( var i = 0; i < configs.length; i++ ) {
					var config = configs[ i ];
					var relative = path.relative( config.cwd, filepath );
					var minimatch = require('minimatch');

					if ( minimatch.match( config.src, relative, {} ) ) {
						dest = config.dest + relative;
						src = [ path.relative( WORKING_DIR, dest ) ];
						files[ dest ] = [ filepath ];
						break;
					}
				}
			// Or if it's another file which has a straight mapping.
			} else {
				configs = Object.assign( {},
					grunt.config( [ 'copy', 'admin-js', 'files' ] ),
					grunt.config( [ 'copy', 'includes-js', 'files' ] )
				);

				for ( dest in configs ) {
					// If a file in the mapping matches then set the variables for our dynamic tasks.
					if ( dest && configs.hasOwnProperty( dest ) && configs[ dest ][0] === './' + filepath ) {
						files[ dest ] = configs[ dest ];
						src = [ path.relative( WORKING_DIR, dest ) ];
						break;
					}
				}
			}

			// Configure our dynamic-js copy task which uses a file mapping rather than simply copying from src to build.
			if ( action !== 'deleted' ) {
				grunt.config( [ 'copy', 'dynamic-js', 'files' ], files );
			}
		// For the webpack builds configure the jsvalidate task to only check those files build by webpack.
		} else if ( target === 'js-webpack' ) {
			src = [
				'wp-includes/js/media-audiovideo.js',
				'wp-includes/js/media-grid.js',
				'wp-includes/js/media-models.js',
				'wp-includes/js/media-views.js'
			];
		// Else simply use the path relative to the source directory.
		} else {
			src = [ path.relative( SOURCE_DIR, filepath ) ];
		}

		if ( ! src ) {
			grunt.warn( 'Failed to determine the destination file.' );
			return;
		}

		if ( action === 'deleted' ) {
			// Clean up only those files that were deleted.
			grunt.config( [ 'clean', 'dynamic', 'src' ], src );
		} else {
			// Otherwise copy over only the changed file.
			grunt.config( [ 'copy', 'dynamic', 'src' ], src );

			// For javascript also minify and validate the changed file.
			if ( target === 'js-enqueues' ) {
				grunt.config( [ 'uglify', 'dynamic', 'src' ], src );
				grunt.config( [ 'jsvalidate', 'dynamic', 'files', 'src' ], src.map( function( dir ) { return  WORKING_DIR + dir; } ) );
			}
			// For webpack only validate the file, minification is handled by webpack itself.
			if ( target === 'js-webpack' ) {
				grunt.config( [ 'jsvalidate', 'dynamic', 'files', 'src' ], src.map( function( dir ) { return  WORKING_DIR + dir; } ) );
			}
			// For css run the rtl task on just the changed file.
			if ( target === 'rtl' ) {
				grunt.config( [ 'rtlcss', 'dynamic', 'src' ], src );
			}
		}
	});
};
