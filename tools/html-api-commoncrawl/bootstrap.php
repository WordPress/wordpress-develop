<?php
/**
 * Loads the HTML API and `wp_kses()` from a WordPress source tree without loading WordPress.
 *
 * The source tree is given by the `HTML_API_BENCH_WP_SRC` environment variable
 * and should point to the `src/` directory of the checkout being measured.
 *
 * There is no database or object cache, so the few options these functions
 * read are answered here, presenting a UTF-8 site at example.com. Reading any
 * other option fails, which points to whatever new dependency needs it.
 *
 * When a change adds a file to the HTML API, add it to the list below.
 *
 * @package WordPress
 */

$wp_src = rtrim( getenv( 'HTML_API_BENCH_WP_SRC' ), '/' );

define( 'ABSPATH', "{$wp_src}/" );
define( 'WPINC', 'wp-includes' );
define( 'WP_DEBUG', false );
define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
define( 'WP_CONTENT_URL', 'https://example.com/wp-content' );

unset( $wp_src );

// Polyfills.
require ABSPATH . WPINC . '/compat-utf8.php';
require ABSPATH . WPINC . '/compat.php';

// wp_installing(), get_current_blog_id(), and is_multisite().
require ABSPATH . WPINC . '/load.php';

// Hooks. This also loads class-wp-hook.php and class-wp-filter-sentinel.php.
require ABSPATH . WPINC . '/plugin.php';

// wp_allowed_protocols(), wp_upload_dir(), and _doing_it_wrong(). This also loads option.php.
require ABSPATH . WPINC . '/functions.php';

// esc_attr() and esc_url().
require ABSPATH . WPINC . '/formatting.php';

// wp_parse_url().
require ABSPATH . WPINC . '/http.php';

require ABSPATH . WPINC . '/class-wp-token-map.php';
require ABSPATH . WPINC . '/utf8.php';
require ABSPATH . WPINC . '/kses.php';

// The HTML API, in the order wp-settings.php loads it.
require ABSPATH . WPINC . '/html-api/html5-named-character-references.php';
require ABSPATH . WPINC . '/html-api/class-wp-html-attribute-token.php';
require ABSPATH . WPINC . '/html-api/class-wp-html-span.php';
require ABSPATH . WPINC . '/html-api/class-wp-html-doctype-info.php';
require ABSPATH . WPINC . '/html-api/class-wp-html-text-replacement.php';
require ABSPATH . WPINC . '/html-api/class-wp-html-decoder.php';
require ABSPATH . WPINC . '/html-api/class-wp-html-tag-processor.php';
require ABSPATH . WPINC . '/html-api/class-wp-html-unsupported-exception.php';
require ABSPATH . WPINC . '/html-api/class-wp-html-active-formatting-elements.php';
require ABSPATH . WPINC . '/html-api/class-wp-html-open-elements.php';
require ABSPATH . WPINC . '/html-api/class-wp-html-token.php';
require ABSPATH . WPINC . '/html-api/class-wp-html-stack-event.php';
require ABSPATH . WPINC . '/html-api/class-wp-html-processor-state.php';
require ABSPATH . WPINC . '/html-api/class-wp-html-processor.php';

// l10n.php is not loaded, so messages are left untranslated.
function __( $text ) {
	return $text;
}

// Present a UTF-8 site at example.com. Nothing else answers options: there is no object cache or database.
add_filter(
	'pre_option_blog_charset',
	static function () {
		return 'UTF-8';
	}
);
add_filter(
	'pre_option_siteurl',
	static function () {
		return 'https://example.com';
	}
);
add_filter(
	'pre_option_upload_path',
	static function () {
		return '';
	}
);
add_filter(
	'pre_option_upload_url_path',
	static function () {
		return '';
	}
);
// Without year and month upload folders, wp_upload_dir() does not need the site's time zone.
add_filter(
	'pre_option_uploads_use_yearmonth_folders',
	static function () {
		return '';
	}
);
