<?php
/**
 * Register the block patterns and block patterns categories
 *
 * @package WordPress
 * @since 5.5.0
 */

add_theme_support( 'core-block-patterns' );

/**
 * Registers the core block patterns and categories.
 *
 * @since 5.5.0
 * @since 6.3.0 Added source to core block patterns.
 * @access private
 */
function _register_core_block_patterns_and_categories() {
	$should_register_core_patterns = get_theme_support( 'core-block-patterns' );

	if ( $should_register_core_patterns ) {
		$core_block_patterns = array(
			'query-standard-posts',
			'query-medium-posts',
			'query-small-posts',
			'query-grid-posts',
			'query-large-title-posts',
			'query-offset-posts',
			'social-links-shared-background-color',
		);

		foreach ( $core_block_patterns as $core_block_pattern ) {
			$pattern           = require __DIR__ . '/block-patterns/' . $core_block_pattern . '.php';
			$pattern['source'] = 'core';
			register_block_pattern( 'core/' . $core_block_pattern, $pattern );
		}
	}

	register_block_pattern_category( 'banner', array( 'label' => _x( 'Banners', 'Block pattern category' ) ) );
	register_block_pattern_category(
		'buttons',
		array(
			'label'       => _x( 'Buttons', 'Block pattern category' ),
			'description' => __( 'Patterns that contain buttons and call to actions.' ),
		)
	);
	register_block_pattern_category(
		'columns',
		array(
			'label'       => _x( 'Columns', 'Block pattern category' ),
			'description' => __( 'Multi-column patterns with more complex layouts.' ),
		)
	);
	register_block_pattern_category(
		'text',
		array(
			'label'       => _x( 'Text', 'Block pattern category' ),
			'description' => __( 'Patterns containing mostly text.' ),
		)
	);
	register_block_pattern_category(
		'query',
		array(
			'label'       => _x( 'Posts', 'Block pattern category' ),
			'description' => __( 'Display your latest posts in lists, grids or other layouts.' ),
		)
	);
	register_block_pattern_category(
		'featured',
		array(
			'label'       => _x( 'Featured', 'Block pattern category' ),
			'description' => __( 'A set of high quality curated patterns.' ),
		)
	);
	register_block_pattern_category(
		'call-to-action',
		array(
			'label'       => _x( 'Call to Action', 'Block pattern category' ),
			'description' => __( 'Sections whose purpose is to trigger a specific action.' ),
		)
	);
	register_block_pattern_category(
		'team',
		array(
			'label'       => _x( 'Team', 'Block pattern category' ),
			'description' => __( 'A variety of designs to display your team members.' ),
		)
	);
	register_block_pattern_category(
		'testimonials',
		array(
			'label'       => _x( 'Testimonials', 'Block pattern category' ),
			'description' => __( 'Share reviews and feedback about your brand/business.' ),
		)
	);
	register_block_pattern_category(
		'services',
		array(
			'label'       => _x( 'Services', 'Block pattern category' ),
			'description' => __( 'Briefly describe what your business does and how you can help.' ),
		)
	);
	register_block_pattern_category(
		'contact',
		array(
			'label'       => _x( 'Contact', 'Block pattern category' ),
			'description' => __( 'Display your contact information.' ),
		)
	);
	register_block_pattern_category(
		'about',
		array(
			'label'       => _x( 'About', 'Block pattern category' ),
			'description' => __( 'Introduce yourself.' ),
		)
	);
	register_block_pattern_category(
		'portfolio',
		array(
			'label'       => _x( 'Portfolio', 'Block pattern category' ),
			'description' => __( 'Showcase your latest work.' ),
		)
	);
	register_block_pattern_category(
		'gallery',
		array(
			'label'       => _x( 'Gallery', 'Block pattern category' ),
			'description' => __( 'Different layouts for displaying images.' ),
		)
	);
	register_block_pattern_category(
		'media',
		array(
			'label'       => _x( 'Media', 'Block pattern category' ),
			'description' => __( 'Different layouts containing video or audio.' ),
		)
	);
	register_block_pattern_category(
		'posts',
		array(
			'label'       => _x( 'Posts', 'Block pattern category' ),
			'description' => __( 'Display your latest posts in lists, grids or other layouts.' ),
		)
	);
	register_block_pattern_category(
		'footer',
		array(
			'label'       => _x( 'Footers', 'Block pattern category' ),
			'description' => __( 'A variety of footer designs displaying information and site navigation.' ),
		)
	);
	register_block_pattern_category(
		'header',
		array(
			'label'       => _x( 'Headers', 'Block pattern category' ),
			'description' => __( 'A variety of header designs displaying your site title and navigation.' ),
		)
	);
}

/**
 * Normalize the pattern properties to camelCase.
 *
 * The API's format is snake_case, `register_block_pattern()` expects camelCase.
 *
 * @since 6.2.0
 * @access private
 *
 * @param array $pattern Pattern as returned from the Pattern Directory API.
 * @return array Normalized pattern.
 */
function wp_normalize_remote_block_pattern( $pattern ) {
	if ( isset( $pattern['block_types'] ) ) {
		$pattern['blockTypes'] = $pattern['block_types'];
		unset( $pattern['block_types'] );
	}

	if ( isset( $pattern['viewport_width'] ) ) {
		$pattern['viewportWidth'] = $pattern['viewport_width'];
		unset( $pattern['viewport_width'] );
	}

	return (array) $pattern;
}

/**
 * Register Core's official patterns from wordpress.org/patterns.
 *
 * @since 5.8.0
 * @since 5.9.0 The $current_screen argument was removed.
 * @since 6.2.0 Normalize the pattern from the API (snake_case) to the
 *              format expected by `register_block_pattern` (camelCase).
 * @since 6.3.0 Add 'pattern-directory/core' to the pattern's 'source'.
 *
 * @param WP_Screen $deprecated Unused. Formerly the screen that the current request was triggered from.
 */
function _load_remote_block_patterns( $deprecated = null ) {
	if ( ! empty( $deprecated ) ) {
		_deprecated_argument( __FUNCTION__, '5.9.0' );
		$current_screen = $deprecated;
		if ( ! $current_screen->is_block_editor ) {
			return;
		}
	}

	$supports_core_patterns = get_theme_support( 'core-block-patterns' );

	/**
	 * Filter to disable remote block patterns.
	 *
	 * @since 5.8.0
	 *
	 * @param bool $should_load_remote
	 */
	$should_load_remote = apply_filters( 'should_load_remote_block_patterns', true );

	if ( $supports_core_patterns && $should_load_remote ) {
		$request         = new WP_REST_Request( 'GET', '/wp/v2/pattern-directory/patterns' );
		$core_keyword_id = 11; // 11 is the ID for "core".
		$request->set_param( 'keyword', $core_keyword_id );
		$response = rest_do_request( $request );
		if ( $response->is_error() ) {
			return;
		}
		$patterns = $response->get_data();

		foreach ( $patterns as $pattern ) {
			$pattern['source']  = 'pattern-directory/core';
			$normalized_pattern = wp_normalize_remote_block_pattern( $pattern );
			$pattern_name       = 'core/' . sanitize_title( $normalized_pattern['title'] );
			register_block_pattern( $pattern_name, $normalized_pattern );
		}
	}
}

/**
 * Register `Featured` (category) patterns from wordpress.org/patterns.
 *
 * @since 5.9.0
 * @since 6.2.0 Normalized the pattern from the API (snake_case) to the
 *              format expected by `register_block_pattern()` (camelCase).
 * @since 6.3.0 Add 'pattern-directory/featured' to the pattern's 'source'.
 */
function _load_remote_featured_patterns() {
	$supports_core_patterns = get_theme_support( 'core-block-patterns' );

	/** This filter is documented in wp-includes/block-patterns.php */
	$should_load_remote = apply_filters( 'should_load_remote_block_patterns', true );

	if ( ! $should_load_remote || ! $supports_core_patterns ) {
		return;
	}

	$request         = new WP_REST_Request( 'GET', '/wp/v2/pattern-directory/patterns' );
	$featured_cat_id = 26; // This is the `Featured` category id from pattern directory.
	$request->set_param( 'category', $featured_cat_id );
	$response = rest_do_request( $request );
	if ( $response->is_error() ) {
		return;
	}
	$patterns = $response->get_data();
	$registry = WP_Block_Patterns_Registry::get_instance();
	foreach ( $patterns as $pattern ) {
		$pattern['source']  = 'pattern-directory/featured';
		$normalized_pattern = wp_normalize_remote_block_pattern( $pattern );
		$pattern_name       = sanitize_title( $normalized_pattern['title'] );
		// Some patterns might be already registered as core patterns with the `core` prefix.
		$is_registered = $registry->is_registered( $pattern_name ) || $registry->is_registered( "core/$pattern_name" );
		if ( ! $is_registered ) {
			register_block_pattern( $pattern_name, $normalized_pattern );
		}
	}
}

/**
 * Registers patterns from Pattern Directory provided by a theme's
 * `theme.json` file.
 *
 * @since 6.0.0
 * @since 6.2.0 Normalized the pattern from the API (snake_case) to the
 *              format expected by `register_block_pattern()` (camelCase).
 * @since 6.3.0 Add 'pattern-directory/theme' to the pattern's 'source'.
 * @access private
 */
function _register_remote_theme_patterns() {
	/** This filter is documented in wp-includes/block-patterns.php */
	if ( ! apply_filters( 'should_load_remote_block_patterns', true ) ) {
		return;
	}

	if ( ! wp_theme_has_theme_json() ) {
		return;
	}

	$pattern_settings = wp_get_theme_directory_pattern_slugs();
	if ( empty( $pattern_settings ) ) {
		return;
	}

	$request         = new WP_REST_Request( 'GET', '/wp/v2/pattern-directory/patterns' );
	$request['slug'] = $pattern_settings;
	$response        = rest_do_request( $request );
	if ( $response->is_error() ) {
		return;
	}
	$patterns          = $response->get_data();
	$patterns_registry = WP_Block_Patterns_Registry::get_instance();
	foreach ( $patterns as $pattern ) {
		$pattern['source']  = 'pattern-directory/theme';
		$normalized_pattern = wp_normalize_remote_block_pattern( $pattern );
		$pattern_name       = sanitize_title( $normalized_pattern['title'] );
		// Some patterns might be already registered as core patterns with the `core` prefix.
		$is_registered = $patterns_registry->is_registered( $pattern_name ) || $patterns_registry->is_registered( "core/$pattern_name" );
		if ( ! $is_registered ) {
			register_block_pattern( $pattern_name, $normalized_pattern );
		}
	}
}

/**
 * Register any patterns that the active theme may provide under its
 * `./patterns/` directory. Each pattern is defined as a PHP file and defines
 * its metadata using plugin-style headers. The minimum required definition is:
 *
 *     /**
 *      * Title: My Pattern
 *      * Slug: my-theme/my-pattern
 *      *
 *
 * The output of the PHP source corresponds to the content of the pattern, e.g.:
 *
 *     <main><p><?php echo "Hello"; ?></p></main>
 *
 * If applicable, this will collect from both parent and child theme.
 *
 * Other settable fields include:
 *
 *   - Description
 *   - Viewport Width
 *   - Inserter         (yes/no)
 *   - Categories       (comma-separated values)
 *   - Keywords         (comma-separated values)
 *   - Block Types      (comma-separated values)
 *   - Post Types       (comma-separated values)
 *   - Template Types   (comma-separated values)
 *
 * @since 6.0.0
 * @since 6.1.0 The `postTypes` property was added.
 * @since 6.2.0 The `templateTypes` property was added.
 * @access private
 */
function _register_theme_block_patterns() {
	$default_headers = array(
		'title'         => 'Title',
		'slug'          => 'Slug',
		'description'   => 'Description',
		'viewportWidth' => 'Viewport Width',
		'inserter'      => 'Inserter',
		'categories'    => 'Categories',
		'keywords'      => 'Keywords',
		'blockTypes'    => 'Block Types',
		'postTypes'     => 'Post Types',
		'templateTypes' => 'Template Types',
	);

	/*
	 * Register patterns for the active theme. If the theme is a child theme,
	 * let it override any patterns from the parent theme that shares the same slug.
	 */
	$themes     = array();
	$stylesheet = get_stylesheet();
	$template   = get_template();
	if ( $stylesheet !== $template ) {
		$themes[] = wp_get_theme( $stylesheet );
	}
	$themes[] = wp_get_theme( $template );

	$pattern_files = array();

	// Fetch a list of files to examine as potential pattern-files.
	foreach ( $themes as $theme ) {
		/** @var string $pattern_directory_path Where on the filesystem the theme's pattern files are stored. */
		$pattern_directory_path = $theme->get_stylesheet_directory() . '/patterns/';

		// Suppress the E_WARNING from opendir() if it fails to open the pattern directory.
		set_error_handler( '__return_true', E_WARNING );

		/** $pattern_directory_handle Use this to list files in the pattern directory. */
		$pattern_directory_handle = opendir( $pattern_directory_path );

		restore_error_handler();

		// This might fail if the directory doesn't exist or if WordPress can't read it.
		if ( false === $pattern_directory_handle ) {
			continue;
		}

		while ( false !== ( $pattern_file = readdir( $pattern_directory_handle ) ) ) {
			$file_path = "{$pattern_directory_path}/{$pattern_file}";
			if (
				4 >= strlen( $pattern_file ) ||
				'.php' !== substr( $pattern_file, -4 ) ||
				! is_file( $file_path )
			) {
				continue;
			}

			$pattern_files[] = $file_path;
		}
	}

	$queue                 = $pattern_files;
	/** Tune this to an optimum value as determined by profiling and measuring. */
	$flush_read_chunk_size = 16 * KB_IN_BYTES;
	/** Tune this to an optimimum value as determined by profiling and measuring. */
	$max_concurrency       = 32;
	$fds                   = array();
	$pattern_file_contents = array();
	$patterns_data         = array();
	$initial_bytes         = 8 * KB_IN_BYTES;

	while ( count( $queue ) > 0 ) {
		// Grab the next batch.
		foreach ( array_splice( $queue, 0, $max_concurrency - count( $fds ) ) as $file_path ) {
			$fd = fopen( $file_path, 'r' );
			// If the file can't be opened, skip it.
			if ( false === $fd ) {
				continue;
			}
			stream_set_blocking( $fd, false );

			$fds[ $file_path ] = $fd;
		}

		$to_read   = $fds;
		$to_write  = null;
		$to_except = null;

		$streams = stream_select( $to_read, $to_write, $to_except, 0, 10000 );

		if ( false === $streams ) {
			throw new Error( 'Should not have died.' );
		}

		if ( 0 === $streams ) {
			continue;
		}

		foreach ( $to_read as $file_path => $fd ) {
			if ( ! array_key_exists( $file_path, $pattern_file_contents ) ) {
				$pattern_file_contents[ $file_path ] = '';
			}

			$is_confirmed_pattern = array_key_exists( $file_path, $patterns_data );
			$bytes_wanted         = $is_confirmed_pattern
				? $flush_read_chunk_size
				: $initial_bytes - strlen( $pattern_file_contents[ $file_path ] );

			$chunk = fread( $fd, $bytes_wanted );

			// If there was an error, remove the file from the processing chain.
			if ( false === $chunk ) {
				fclose( $fd );
				unset( $fds[ $file_path ] );
				unset( $pattern_file_contents[ $file_path ] );
				continue;
			}

			$pattern_file_contents[ $file_path ] .= $chunk;
			if (
				! $is_confirmed_pattern &&
				(
					strlen( $pattern_file_contents[ $file_path ] ) >= $initial_bytes ||
					feof( $fd )
				)
			) {
				$file_data_chunk = substr( $pattern_file_contents[ $file_path ], 0, $initial_bytes );
				unset( $pattern_file_contents[ $file_path ] );

				$pattern_data = get_file_data_from_string( $file_data_chunk, $default_headers );

				if ( empty( $pattern_data['slug'] ) ) {
					_doing_it_wrong(
						'_register_theme_block_patterns',
						sprintf(
						/* translators: %s: file name. */
							__( 'Could not register file "%s" as a block pattern ("Slug" field missing)' ),
							$file_path
						),
						'6.0.0'
					);
					fclose( $fd );
					unset( $fds[ $file_path ] );
					continue;
				}

				if ( ! preg_match( '/^[A-z0-9\/_-]+$/', $pattern_data['slug'] ) ) {
					_doing_it_wrong(
						'_register_theme_block_patterns',
						sprintf(
						/* translators: %1s: file name; %2s: slug value found. */
							__( 'Could not register file "%1$s" as a block pattern (invalid slug "%2$s")' ),
							$file_path,
							$pattern_data['slug']
						),
						'6.0.0'
					);
				}

				if ( WP_Block_Patterns_Registry::get_instance()->is_registered( $pattern_data['slug'] ) ) {
					fclose( $fd );
					unset( $fds[ $file_path ] );
					continue;
				}

				// Title is a required property.
				if ( ! $pattern_data['title'] ) {
					_doing_it_wrong(
						'_register_theme_block_patterns',
						sprintf(
						/* translators: %1s: file name; %2s: slug value found. */
							__( 'Could not register file "%s" as a block pattern ("Title" field missing)' ),
							$file_path
						),
						'6.0.0'
					);
					fclose( $fd );
					unset( $fds[ $file_path ] );
					continue;
				}

				// For properties of type array, parse data as comma-separated.
				foreach ( array( 'categories', 'keywords', 'blockTypes', 'postTypes', 'templateTypes' ) as $property ) {
					if ( ! empty( $pattern_data[ $property ] ) ) {
						$pattern_data[ $property ] = array_filter(
							preg_split(
								'/[\s,]+/',
								(string) $pattern_data[ $property ]
							)
						);
					} else {
						unset( $pattern_data[ $property ] );
					}
				}

				// Parse properties of type int.
				foreach ( array( 'viewportWidth' ) as $property ) {
					if ( ! empty( $pattern_data[ $property ] ) ) {
						$pattern_data[ $property ] = (int) $pattern_data[ $property ];
					} else {
						unset( $pattern_data[ $property ] );
					}
				}

				// Parse properties of type bool.
				foreach ( array( 'inserter' ) as $property ) {
					if ( ! empty( $pattern_data[ $property ] ) ) {
						$pattern_data[ $property ] = in_array(
							strtolower( $pattern_data[ $property ] ),
							array( 'yes', 'true' ),
							true
						);
					} else {
						unset( $pattern_data[ $property ] );
					}
				}

				// Translate the pattern metadata.
				$text_domain = $theme->get( 'TextDomain' );
				// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralContext,WordPress.WP.I18n.NonSingularStringLiteralDomain,WordPress.WP.I18n.LowLevelTranslationFunction
				$pattern_data['title'] = translate_with_gettext_context( $pattern_data['title'], 'Pattern title', $text_domain );
				if ( ! empty( $pattern_data['description'] ) ) {
					// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralContext,WordPress.WP.I18n.NonSingularStringLiteralDomain,WordPress.WP.I18n.LowLevelTranslationFunction
					$pattern_data['description'] = translate_with_gettext_context( $pattern_data['description'], 'Pattern description', $text_domain );
				}

				$patterns_data[ $file_path ] = $pattern_data;
				fclose( $fd );
				unset( $fds[ $file_path ] );
			}
		}
	}

	foreach ( $patterns_data as $file_path => $data ) {
		ob_start();
		include $file_path;
		$data['content'] = ob_get_clean();

		if ( ! empty( $data['content'] ) ) {
			register_block_pattern( $data['slug'], $data );
		}
		unset( $patterns_data[ $file_path ] );
	}
}
add_action( 'init', '_register_theme_block_patterns' );
