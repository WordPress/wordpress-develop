<?php

ob_start(
	static function ( string $output, ?int $phase ): string {
		// When the output is being cleaned (e.g. pending template is replaced with error page), do not send it through the filter.
		if ( ( $phase & PHP_OUTPUT_HANDLER_CLEAN ) !== 0 ) {
			return $output;
		}

		// Detect if the response is an HTML content type.
		$is_html_content_type = false;
		$headers_list         = array_merge(
			array( 'Content-Type: ' . ini_get( 'default_mimetype' ) ),
			headers_list()
		);
		foreach ( $headers_list as $header ) {
			$header_parts = preg_split( '/\s*[:;]\s*/', strtolower( $header ) );
			if ( is_array( $header_parts ) && count( $header_parts ) >= 2 && 'content-type' === $header_parts[0] ) {
				$is_html_content_type = in_array( $header_parts[1], array( 'text/html', 'application/xhtml+xml' ), true );
			}
		}
		// TODO: Also check if str_starts_with( ltrim( $buffer ), '<' )? Or check if str_contains( '<html' ) in case a PHP warning output an error before the HTML tag.

		if ( $is_html_content_type ) {
			/**
			 * Filters the HTML output buffer prior to sending to the client.
			 *
			 * The output buffer is started before the `template_redirect` action is triggered, allowing templates rendered
			 * at that action to also have their output filtered.
			 *
			 * @since n.e.x.t
			 *
			 * @param string $output Output buffer HTML.
			 * @return string Filtered output buffer HTML.
			 */
			$output = (string) apply_filters( 'wp_output_buffer_html', $output );
		}

		/**
		 * Fires after the output buffer has been filtered prior to sending to the client.
		 *
		 * This is useful for caching plugins to capture the page output for storage.
		 *
		 * @since n.e.x.t
		 *
		 * @param string $output Output buffer.
		 */
		do_action( 'wp_final_output_buffer', $output );

		return $output;
	},
	0, // Unlimited buffer size so that entire output is passed to the filter.
	/*
	 * Instead of the default PHP_OUTPUT_HANDLER_STDFLAGS (cleanable, flushable, and removable) being used for flags,
	 * the PHP_OUTPUT_HANDLER_FLUSHABLE flag must be omitted. If the buffer were flushable, then each time that
	 * ob_flush() is called, it would send a fragment of the output into the output buffer callback. When buffering the
	 * entire response as an HTML document, this would result in broken HTML processing.
	 *
	 * If this ends up being problematic, then PHP_OUTPUT_HANDLER_FLUSHABLE could be added to the $flags and the
	 * output buffer callback could check if the phase is PHP_OUTPUT_HANDLER_FLUSH and abort any subsequent
	 * processing while also emitting a _doing_it_wrong().
	 *
	 * The output buffer needs to be removable because WordPress calls wp_ob_end_flush_all() and then calls
	 * wp_cache_close(). If the buffers are not all flushed before wp_cache_close() is closed, then some output buffer
	 * handlers (e.g. for caching plugins) may fail to be able to store the page output in the object cache.
	 * See <https://github.com/WordPress/performance/pull/1317#issuecomment-2271955356>.
	 */
	PHP_OUTPUT_HANDLER_STDFLAGS ^ PHP_OUTPUT_HANDLER_FLUSHABLE
);

/**
 * Loads the correct template based on the visitor's URL
 *
 * @package WordPress
 */
if ( wp_using_themes() ) {
	/**
	 * Fires before determining which template to load.
	 *
	 * This action hook executes just before WordPress determines which template page to load.
	 * It is a good hook to use if you need to do a redirect with full knowledge of the content
	 * that has been queried.
	 *
	 * Note: Loading a different template is not a good use of this hook. If you include another template
	 * and then use `exit()` or `die()`, no subsequent `template_redirect` hooks will be run, which could
	 * break the site’s functionality. Instead, use the {@see 'template_include'} filter hook to return
	 * the path to the new template you want to use. This will allow an alternative template to be used
	 * without interfering with the WordPress loading process.
	 *
	 * @since 1.5.0
	 */
	do_action( 'template_redirect' );
}

/**
 * Filters whether to allow 'HEAD' requests to generate content.
 *
 * Provides a significant performance bump by exiting before the page
 * content loads for 'HEAD' requests. See #14348.
 *
 * @since 3.5.0
 *
 * @param bool $exit Whether to exit without generating any content for 'HEAD' requests. Default true.
 */
if ( 'HEAD' === $_SERVER['REQUEST_METHOD'] && apply_filters( 'exit_on_http_head', true ) ) {
	exit;
}

// Process feeds and trackbacks even if not using themes.
if ( is_robots() ) {
	/**
	 * Fired when the template loader determines a robots.txt request.
	 *
	 * @since 2.1.0
	 */
	do_action( 'do_robots' );
	return;
} elseif ( is_favicon() ) {
	/**
	 * Fired when the template loader determines a favicon.ico request.
	 *
	 * @since 5.4.0
	 */
	do_action( 'do_favicon' );
	return;
} elseif ( is_feed() ) {
	do_feed();
	return;
} elseif ( is_trackback() ) {
	require ABSPATH . 'wp-trackback.php';
	return;
}

if ( wp_using_themes() ) {

	$tag_templates = array(
		'is_embed'             => 'get_embed_template',
		'is_404'               => 'get_404_template',
		'is_search'            => 'get_search_template',
		'is_front_page'        => 'get_front_page_template',
		'is_home'              => 'get_home_template',
		'is_privacy_policy'    => 'get_privacy_policy_template',
		'is_post_type_archive' => 'get_post_type_archive_template',
		'is_tax'               => 'get_taxonomy_template',
		'is_attachment'        => 'get_attachment_template',
		'is_single'            => 'get_single_template',
		'is_page'              => 'get_page_template',
		'is_singular'          => 'get_singular_template',
		'is_category'          => 'get_category_template',
		'is_tag'               => 'get_tag_template',
		'is_author'            => 'get_author_template',
		'is_date'              => 'get_date_template',
		'is_archive'           => 'get_archive_template',
	);
	$template      = false;

	// Loop through each of the template conditionals, and find the appropriate template file.
	foreach ( $tag_templates as $tag => $template_getter ) {
		if ( call_user_func( $tag ) ) {
			$template = call_user_func( $template_getter );
		}

		if ( $template ) {
			if ( 'is_attachment' === $tag ) {
				remove_filter( 'the_content', 'prepend_attachment' );
			}

			break;
		}
	}

	if ( ! $template ) {
		$template = get_index_template();
	}

	/**
	 * Filters the path of the current template before including it.
	 *
	 * @since 3.0.0
	 *
	 * @param string $template The path of the template to include.
	 */
	$template = apply_filters( 'template_include', $template );
	if ( $template ) {
		include $template;
	} elseif ( current_user_can( 'switch_themes' ) ) {
		$theme = wp_get_theme();
		if ( $theme->errors() ) {
			wp_die( $theme->errors() );
		}
	}
	return;
}
