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

	$to_register = array();

	$file_chunk_callback = function ( $file_path, $event, $chunk = null ) use ( &$to_register, $default_headers, $theme ) {
		switch ( $event ) {
			case 'queued':
				return 8 * KB_IN_BYTES;

			case 'read':
				$pattern_data = get_file_data_from_string( $chunk, $default_headers );
				$pattern_data = _register_theme_block_patterns_process_metadata( $theme, $file_path, $pattern_data );
				if ( false !== $pattern_data ) {
					$to_register[ $file_path ] = $pattern_data;
				}
				return 'stop';
		}
	};

	wp_concurrently_read_files_in_chunks( $pattern_files, $file_chunk_callback );

	// Once all the patterns have been recognized, register them.
	foreach ( $to_register as $file_path => $data ) {
		ob_start();
		include $file_path;
		$data['content'] = ob_get_clean();

		if ( ! empty( $data['content'] ) ) {
			register_block_pattern( $data['slug'], $data );
		}
		unset( $to_register[ $file_path ] );
	}
}
add_action( 'init', '_register_theme_block_patterns' );

/**
 * Prepares metadata for pattern registration.
 *
 * @since 6.4.0
 *
 * @param WP_Theme $theme     Active theme.
 * @param string   $file_path Absolute path from where pattern was read, for error messages.
 * @param array    $data      Read pattern metadata from top of file.
 * @return array|false Transformed pattern metadata, or `false` if invalid.
 */
function _register_theme_block_patterns_process_metadata( $theme, $file_path, $data ) {
	if ( empty( $data['slug'] ) ) {
		_doing_it_wrong(
			'_register_theme_block_patterns',
			sprintf(
			/* translators: %s: file name. */
				__( 'Could not register file "%s" as a block pattern ("Slug" field missing)' ),
				$file_path
			),
			'6.0.0'
		);
		return false;
	}

	if ( ! preg_match( '/^[A-z0-9\/_-]+$/', $data['slug'] ) ) {
		_doing_it_wrong(
			'_register_theme_block_patterns',
			sprintf(
			/* translators: %1s: file name; %2s: slug value found. */
				__( 'Could not register file "%1$s" as a block pattern (invalid slug "%2$s")' ),
				$file_path,
				$data['slug']
			),
			'6.0.0'
		);
	}

	if ( WP_Block_Patterns_Registry::get_instance()->is_registered( $data['slug'] ) ) {
		return false;
	}

	// Title is a required property.
	if ( ! $data['title'] ) {
		_doing_it_wrong(
			'_register_theme_block_patterns',
			sprintf(
			/* translators: %1s: file name; %2s: slug value found. */
				__( 'Could not register file "%s" as a block pattern ("Title" field missing)' ),
				$file_path
			),
			'6.0.0'
		);
		return false;
	}

	// For properties of type array, parse data as comma-separated.
	foreach ( array( 'categories', 'keywords', 'blockTypes', 'postTypes', 'templateTypes' ) as $property ) {
		if ( ! empty( $data[ $property ] ) ) {
			$data[ $property ] = array_filter(
				preg_split(
					'/[\s,]+/',
					(string) $data[ $property ]
				)
			);
		} else {
			unset( $data[ $property ] );
		}
	}

	// Parse properties of type int.
	foreach ( array( 'viewportWidth' ) as $property ) {
		if ( ! empty( $data[ $property ] ) ) {
			$data[ $property ] = (int) $data[ $property ];
		} else {
			unset( $data[ $property ] );
		}
	}

	// Parse properties of type bool.
	foreach ( array( 'inserter' ) as $property ) {
		if ( ! empty( $data[ $property ] ) ) {
			$data[ $property ] = in_array(
				strtolower( $data[ $property ] ),
				array( 'yes', 'true' ),
				true
			);
		} else {
			unset( $data[ $property ] );
		}
	}

	// Translate the pattern metadata.
	$text_domain = $theme->get( 'TextDomain' );
	// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralContext,WordPress.WP.I18n.NonSingularStringLiteralDomain,WordPress.WP.I18n.LowLevelTranslationFunction
	$data['title'] = translate_with_gettext_context( $data['title'], 'Pattern title', $text_domain );
	if ( ! empty( $data['description'] ) ) {
		// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralContext,WordPress.WP.I18n.NonSingularStringLiteralDomain,WordPress.WP.I18n.LowLevelTranslationFunction
		$data['description'] = translate_with_gettext_context( $data['description'], 'Pattern description', $text_domain );
	}

	return $data;
}

/**
 * Performs concurrent reads on provided files and dispatches reads to state-machine callback.
 *
 * @TODO: Move this into a separate file.
 *
 * @since 6.4.0
 *
 * Example:
 *
 *     $data = array()
 *     $file_processor = function ( $file_path, $event, $chunk = null ) use ( &$data ) {
 *         switch ( $event ) {
 *             case 'not-a-regular-file':
 *             case 'cannot-open-file':
 *             case 'cannot-read':
 *                 report_error_for( $file_path );
 *                 return;
 *
 *             case 'queued':
 *                 // Return a spcific minimum number of bytes if there's a reason to.
 *                 if ( is_header_kind_of_file( $file_path ) ) {
 *                     return $minimum_file_header_size;
 *                 }
 *                 // Otherwise accept how every much data arrives on the first read.
 *                 return;
 *
 *             case 'read':
 *                 $data[ $file_path ] .= $chunk;
 *
 *                 // "stop" closes the file and stops streaming it.
 *                 if ( has_enough( $file_path, $data[ $file_path ] ) ) {
 *                     return 'stop';
 *                 }
 *
 *                 // Can specify the next required chunk size.
 *                 if ( needs_large_data( $file_path, $data[ $file_path ] ) ) {
 *                      return ( 4 * MB_IN_BYTES ) - strlen( $data[ $file_path ] );
 *                 }
 *
 *                 // Or accept the next one as it arrives.
 *                 return;
 *
 *             case 'done':
 *                 process_file( $file_path, $data[ $file_path ] );
 *                 unset( $data[ $file_path ] ); // no sense keeping this in memory while the other files continue.
 *                 return;
 *
 *             case 'idle':
 *                 // This is an opportunity to spend some CPU cycles while waiting for I/O.
 *                 // No more file reads occur while this is processing, but the OS will still
 *                 // fill buffers, making the next read after finishing this task faster.
 *                 process_next_task_while_waiting();
 *                 return;
 *         }
 *     }
 *     wp_concurrently_read_files_in_chunks( $files, $file_processor );
 *
 * @param string[] $file_paths Absolute paths of files to read.
 * @param callable $callback   Takes ( $file_path or null, $event, $data = null ).
 * @param array    $options    Low-level performance controls; only adjust in response to performance measurements.
 */
function wp_concurrently_read_files_in_chunks( $file_paths, $callback, $options = null ) {
	$file_queue        = array();
	$file_buffers      = array();
	$wanted_bytes      = array();
	$fds_being_read    = array();
	$max_concurrency   = isset( $options['max_concurrency'] ) ? $options['max_concurrency'] : 32;
	$max_chunk_size    = isset( $options['max_chunk_size'] ) ? $options['max_chunk_size'] : 8 * KB_IN_BYTES;
	$stream_wait_ms    = isset( $options['stream_wait_ms'] ) ? $options['stream_wait_ms'] : 50;
	$stream_wait_s     = floor( $stream_wait_ms / 1000 );
	$stream_wait_us    = ( $stream_wait_ms % 1000 ) * 1000;

	// Only attempt to read regular files.
	foreach ( $file_paths as $file_path ) {
		if ( is_file( $file_path ) ) {
			$file_queue[]               = $file_path;
			$chunk_size                 = call_user_func( $callback, $file_path, 'queued', null );
			$wanted_bytes[ $file_path ] = is_int( $chunk_size ) && $chunk_size > 0 ? $chunk_size : $max_chunk_size;
		} else {
			call_user_func( $callback, $file_path, 'not-a-regular-file', null );
		}
	}

	// Start streaming data.
	while ( count( $file_queue ) > 0 ) {
		/*
		 * Streaming runs in rounds to fill a maximum level of concurrency. It might
		 * be possible that by requesting every file at once too much data could be
		 * read into memory and overload the system. Limiting the level of concurrency
		 * allow for a balance between parallelism and overhead.
		 */
		$available_slots = $max_concurrency - count( $fds_being_read );
		foreach ( array_splice( $file_queue, 0, $available_slots ) as $file_path ) {
			$fd = fopen( $file_path, 'r' );
			if ( false === $fd ) {
				call_user_func( $callback, $file_path, 'cannot-open-file', null );
			} else {
				stream_set_blocking( $fd, false );
				$fds_being_read[ $file_path ] = $fd;
				$file_buffers[ $file_path ]   = '';
			}
		}

		// Setup next call to read data. These are passed by reference so the null values are required.
		$read_fds     = $fds_being_read;
		$write_fds    = null;
		$except_fds   = null;
		$stream_count = stream_select(
			$read_fds,
			$write_fds,
			$except_fds,
			$stream_wait_s,
			$stream_wait_us
		);

		// If something interrupted the request, try again.
		if ( false === $stream_count ) {
			continue;
		}

		// Provide a way to run compute while waiting for more file data.
		if ( 0 === $stream_count ) {
			call_user_func( $callback, null, 'idle', null );
		}

		foreach ( $read_fds as $file_path => $fd ) {
			$chunk  = fread( $fd, $max_chunk_size );
			$is_eof = feof( $fd );

			if ( false === $chunk ) {
				call_user_func( $callback, $file_path, 'cannot-read', null );
				fclose( $fd );
				unset( $fds_being_read[ $file_path ] );
				unset( $file_buffers[ $file_path ] );
				continue;
			}

			$file_buffers[ $file_path ] .= $chunk;

			if ( $is_eof || strlen( $file_buffers[ $file_path ] ) >= $wanted_bytes[ $file_path ] ) {
				$chunk_size = call_user_func( $callback, $file_path, 'read', $file_buffers[ $file_path ] );
				if ( 'stop' === $chunk_size ) {
					fclose( $fd );
					unset( $fds_being_read[ $file_path ] );
					unset( $file_buffers[ $file_path ] );
					continue;
				}

				$wanted_bytes = is_int( $chunk_size ) && $chunk_size > 0 ? $chunk_size : $max_chunk_size;
				$file_buffers[ $file_path ] = '';
			}

			if ( $is_eof ) {
				fclose( $fd );
				unset( $fds_being_read[ $file_path ] );
				unset( $file_buffers[ $file_path ] );
				call_user_func( $callback, $file_path, 'done', null );
			}
		}
	}
}
