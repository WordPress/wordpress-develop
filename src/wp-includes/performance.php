<?php
/**
 * Performance optimization functions.
 *
 * @package WordPress
 */

function wp_get_performance_optimization_defaults() {
	return array(
		'page_cache'         => false,
		'minify_assets'      => false,
		'critical_css'       => false,
		'lazy_loading'       => true,
		'image_optimization' => false,
		'database_cleanup'   => false,
	);
}

function wp_sanitize_performance_optimization_settings( $settings ) {
	$defaults = wp_get_performance_optimization_defaults();

	if ( ! is_array( $settings ) ) {
		$settings = array();
	}

	$sanitized = array();
	foreach ( $defaults as $feature => $default ) {
		$sanitized[ $feature ] = isset( $settings[ $feature ] ) ? wp_validate_boolean( $settings[ $feature ] ) : $default;
	}

	return $sanitized;
}

function wp_get_performance_optimization_settings() {
	$settings = get_option( 'performance_optimization', array() );

	return wp_parse_args(
		wp_sanitize_performance_optimization_settings( $settings ),
		wp_get_performance_optimization_defaults()
	);
}

function wp_performance_optimization_enabled( $feature ) {
	$settings = wp_get_performance_optimization_settings();

	return ! empty( $settings[ $feature ] );
}

function wp_register_performance_optimization_settings() {
	global $wp_registered_settings;

	if ( isset( $wp_registered_settings['performance_optimization'] ) ) {
		return;
	}

	register_setting(
		'performance',
		'performance_optimization',
		array(
			'type'              => 'object',
			'description'       => __( 'Native performance optimization settings.' ),
			'sanitize_callback' => 'wp_sanitize_performance_optimization_settings',
			'default'           => wp_get_performance_optimization_defaults(),
			'show_in_rest'      => array(
				'schema' => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => array(
						'page_cache'         => array(
							'type'        => 'boolean',
							'description' => __( 'Enable built-in page caching for anonymous front-end requests.' ),
						),
						'minify_assets'      => array(
							'type'        => 'boolean',
							'description' => __( 'Minify front-end HTML and inline CSS and JavaScript.' ),
						),
						'critical_css'       => array(
							'type'        => 'boolean',
							'description' => __( 'Generate a small cached critical CSS block from early inline styles.' ),
						),
						'lazy_loading'       => array(
							'type'        => 'boolean',
							'description' => __( 'Add lazy-loading attributes to eligible images and iframes.' ),
						),
						'image_optimization' => array(
							'type'        => 'boolean',
							'description' => __( 'Prefer modern image output formats when supported by the server.' ),
						),
						'database_cleanup'   => array(
							'type'        => 'boolean',
							'description' => __( 'Show database cleanup tools on the Performance settings screen.' ),
						),
					),
				),
			),
		)
	);
}

function wp_maybe_register_performance_optimization_settings() {
	global $pagenow;

	$is_performance_screen = 'options-performance.php' === $pagenow;
	$is_options_screen     = 'options.php' === $pagenow;

	if ( $is_performance_screen || $is_options_screen ) {
		wp_register_performance_optimization_settings();
	}
}

function wp_performance_filter_loading_optimization_attributes( $loading_attrs, $tag_name ) {
	if ( wp_performance_optimization_enabled( 'lazy_loading' ) || ( 'img' !== $tag_name && 'iframe' !== $tag_name ) ) {
		return $loading_attrs;
	}

	if ( isset( $loading_attrs['loading'] ) && 'lazy' === $loading_attrs['loading'] ) {
		unset( $loading_attrs['loading'] );
	}

	return $loading_attrs;
}

function wp_performance_filter_image_editor_output_format( $output_format ) {
	if ( ! wp_performance_optimization_enabled( 'image_optimization' ) ) {
		return $output_format;
	}

	$modern_format = '';
	if ( wp_image_editor_supports( array( 'mime_type' => 'image/avif' ) ) ) {
		$modern_format = 'image/avif';
	} elseif ( wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) ) {
		$modern_format = 'image/webp';
	}

	if ( ! $modern_format ) {
		return $output_format;
	}

	foreach ( array( 'image/jpeg', 'image/png' ) as $mime_type ) {
		$output_format[ $mime_type ] = $modern_format;
	}

	return $output_format;
}

function wp_get_performance_cache_dir( $type = '' ) {
	$dir = WP_CONTENT_DIR . '/cache/performance';

	if ( $type ) {
		$dir .= '/' . sanitize_key( $type );
	}

	return $dir;
}

function wp_delete_performance_cache( $type = '' ) {
	$dir = wp_get_performance_cache_dir( $type );

	if ( ! is_dir( $dir ) ) {
		return true;
	}

	$items = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ( $items as $item ) {
		if ( $item->isDir() ) {
			rmdir( $item->getPathname() );
		} else {
			unlink( $item->getPathname() );
		}
	}

	return rmdir( $dir );
}

function wp_clean_performance_page_cache() {
	wp_delete_performance_cache( 'page' );
}

function wp_can_optimize_performance_output() {
	if (
		(
			! wp_performance_optimization_enabled( 'page_cache' ) &&
			! wp_performance_optimization_enabled( 'minify_assets' ) &&
			! wp_performance_optimization_enabled( 'critical_css' )
		) ||
		is_admin() ||
		is_user_logged_in() ||
		wp_doing_ajax() ||
		wp_is_json_request() ||
		is_feed() ||
		is_robots() ||
		is_trackback() ||
		( defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE )
	) {
		return false;
	}

	if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'GET' !== $_SERVER['REQUEST_METHOD'] ) {
		return false;
	}

	$has_logged_in_cookie      = ! empty( $_COOKIE[LOGGED_IN_COOKIE] );
	$has_comment_author_cookie = ! empty( $_COOKIE[ 'comment_author_' . COOKIEHASH ] );
	$has_preview              = ! empty( $_GET['preview'] );

	if ( $has_logged_in_cookie || $has_comment_author_cookie || $has_preview ) {
		return false;
	}

	return (bool) apply_filters( 'wp_can_optimize_performance_output', true );
}

function wp_can_use_performance_page_cache() {
	if ( ! wp_performance_optimization_enabled( 'page_cache' ) || ! wp_can_optimize_performance_output() ) {
		return false;
	}

	return (bool) apply_filters( 'wp_can_use_performance_page_cache', true );
}

function wp_get_performance_page_cache_file() {
	$url = home_url( add_query_arg( null, null ) );

	return wp_get_performance_cache_dir( 'page' ) . '/' . md5( $url ) . '.html';
}

function wp_start_performance_page_cache() {
	if ( ! wp_can_optimize_performance_output() ) {
		return;
	}

	$cache_file = wp_get_performance_page_cache_file();
	$ttl        = (int) apply_filters( 'wp_performance_page_cache_ttl', HOUR_IN_SECONDS );

	if ( wp_can_use_performance_page_cache() && is_readable( $cache_file ) && filemtime( $cache_file ) > time() - $ttl ) {
		header( 'X-WP-Performance-Cache: HIT' );
		readfile( $cache_file );
		exit;
	}

	if ( wp_performance_optimization_enabled( 'page_cache' ) ) {
		header( 'X-WP-Performance-Cache: MISS' );
	}

	ob_start( 'wp_capture_performance_page_cache' );
}

function wp_capture_performance_page_cache( $output ) {
	if ( ! wp_can_optimize_performance_output() || 200 !== http_response_code() || false === stripos( $output, '<html' ) ) {
		return $output;
	}

	if ( wp_performance_optimization_enabled( 'critical_css' ) ) {
		$output = wp_add_performance_critical_css( $output );
	}

	if ( wp_performance_optimization_enabled( 'minify_assets' ) ) {
		$output = wp_minify_performance_output( $output );
	}

	if ( ! wp_can_use_performance_page_cache() ) {
		return $output;
	}

	$cache_dir = wp_get_performance_cache_dir( 'page' );
	if ( wp_mkdir_p( $cache_dir ) ) {
		$cache_file = wp_get_performance_page_cache_file();
		$tmp_file   = tempnam( $cache_dir, 'wp-performance-' );

		if ( $tmp_file && file_put_contents( $tmp_file, $output, LOCK_EX ) ) {
			rename( $tmp_file, $cache_file );
		}
	}

	return $output;
}

function wp_add_performance_critical_css( $html ) {
	if ( false !== strpos( $html, 'id="wp-critical-css"' ) ) {
		return $html;
	}

	if ( ! preg_match_all( '#<style\b[^>]*>(.*?)</style>#is', $html, $matches ) ) {
		return $html;
	}

	$critical_css = '';
	foreach ( $matches[1] as $css ) {
		$critical_css .= "\n" . trim( wp_minify_performance_css( $css ) );
		if ( strlen( $critical_css ) >= 12000 ) {
			break;
		}
	}

	if ( '' === trim( $critical_css ) ) {
		return $html;
	}

	$style = '<style id="wp-critical-css">' . substr( $critical_css, 0, 12000 ) . '</style>';

	return preg_replace( '#</head>#i', $style . '</head>', $html, 1 );
}

function wp_minify_performance_output( $html ) {
	$html = preg_replace_callback(
		'#<style\b([^>]*)>(.*?)</style>#is',
		static function ( $matches ) {
			return '<style' . $matches[1] . '>' . wp_minify_performance_css( $matches[2] ) . '</style>';
		},
		$html
	);

	$html = preg_replace_callback(
		'#<script\b([^>]*)>(.*?)</script>#is',
		static function ( $matches ) {
			if ( preg_match( '/\btype=(["\'])(?!text\/javascript|application\/javascript|module)/i', $matches[1] ) ) {
				return $matches[0];
			}

			return '<script' . $matches[1] . '>' . wp_minify_performance_js( $matches[2] ) . '</script>';
		},
		$html
	);

	$html = preg_replace( '/<!--(?!\[if|\s*wp:).*?-->/s', '', $html );
	$html = preg_replace( '/>\s+</', '><', $html );

	return trim( $html );
}

function wp_minify_performance_css( $css ) {
	$css = preg_replace( '#/\*.*?\*/#s', '', $css );
	$css = preg_replace( '/\s+/', ' ', $css );
	$css = preg_replace( '/\s*([{}:;,>])\s*/', '$1', $css );

	return trim( $css );
}

function wp_minify_performance_js( $js ) {
	$js = preg_replace( '#/\*.*?\*/#s', '', $js );
	$js = preg_replace( '/\s+/', ' ', $js );

	return trim( $js );
}
