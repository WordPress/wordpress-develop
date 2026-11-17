<?php
/**
 * Defines default WordPress constants for PHPStan discovery.
 *
 * Mocks the constant initiation that would normally happen in wp-includes/wp-settings.php.
 *
 * Loaded as a `bootstrapFile` by PHPStan; see `base.neon`.
 *
 * For a constant listed under `dynamicConstantNames` in `base.neon`, a define() here only
 * declares the constant, since PHPStan does not discover the ones core defines inside a
 * function, and reports reads of them outside a defined() check as not found. Its value
 * is ignored, and the type comes from `base.neon` instead.
 */

define( 'ABSPATH', '/var/www/html/' );

/** @see wp_initial_constants() */
define( 'KB_IN_BYTES', 1024 );
define( 'MB_IN_BYTES', 1024 * KB_IN_BYTES );
define( 'GB_IN_BYTES', 1024 * MB_IN_BYTES );
define( 'TB_IN_BYTES', 1024 * GB_IN_BYTES );
define( 'PB_IN_BYTES', 1024 * TB_IN_BYTES );
define( 'EB_IN_BYTES', 1024 * PB_IN_BYTES );
define( 'ZB_IN_BYTES', 1024 * EB_IN_BYTES );
define( 'YB_IN_BYTES', 1024 * ZB_IN_BYTES );
define( 'WP_START_TIMESTAMP', 1700000000.0 );
define( 'WP_MEMORY_LIMIT', '40M' );
define( 'WP_MAX_MEMORY_LIMIT', '256M' );
define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
define( 'WP_DEVELOPMENT_MODE', '' );
define( 'WP_DEBUG', false );
define( 'WP_DEBUG_DISPLAY', false );
define( 'WP_DEBUG_LOG', false );
define( 'WP_CACHE', false );
define( 'SCRIPT_DEBUG', false );
define( 'MEDIA_TRASH', false );
define( 'SHORTINIT', false );
define( 'WP_FEATURE_BETTER_PASSWORDS', true );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 60 * MINUTE_IN_SECONDS );
define( 'DAY_IN_SECONDS', 24 * HOUR_IN_SECONDS );
define( 'WEEK_IN_SECONDS', 7 * DAY_IN_SECONDS );
define( 'MONTH_IN_SECONDS', 30 * DAY_IN_SECONDS );
define( 'YEAR_IN_SECONDS', 365 * DAY_IN_SECONDS );

/** @see wp_set_lang_dir() */
define( 'WP_LANG_DIR', WP_CONTENT_DIR . '/languages' );

// wp_plugin_directory_constants()
define( 'WP_CONTENT_URL', 'https://example.com/wp-content' );
define( 'WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins' );
define( 'WP_PLUGIN_URL', WP_CONTENT_URL . '/plugins' );
define( 'PLUGINDIR', 'wp-content/plugins' );
define( 'WPMU_PLUGIN_DIR', WP_CONTENT_DIR . '/mu-plugins' );
define( 'WPMU_PLUGIN_URL', WP_CONTENT_URL . '/mu-plugins' );
define( 'MUPLUGINDIR', 'wp-content/mu-plugins' );

/** @see ms_cookie_constants() */
define( 'COOKIEPATH', '' );
define( 'SITECOOKIEPATH', '' );
define( 'ADMIN_COOKIE_PATH', '' );
define( 'COOKIE_DOMAIN', '' );

/** @see wp_cookie_constants() */
define( 'COOKIEHASH', '' );
define( 'USER_COOKIE', 'wordpressuser_' );
define( 'PASS_COOKIE', 'wordpresspass_' );
define( 'AUTH_COOKIE', 'wordpress_' );
define( 'SECURE_AUTH_COOKIE', 'wordpress_sec_' );
define( 'LOGGED_IN_COOKIE', 'wordpress_logged_in_' );
define( 'TEST_COOKIE', 'wordpress_test_cookie' );
define( 'PLUGINS_COOKIE_PATH', '' );
define( 'RECOVERY_MODE_COOKIE', 'wordpress_rec_' );

/** @see wp_ssl_constants() */
define( 'FORCE_SSL_LOGIN', false );
define( 'FORCE_SSL_ADMIN', false );

/** @see wp_functionality_constants() */
define( 'AUTOSAVE_INTERVAL', MINUTE_IN_SECONDS );
define( 'EMPTY_TRASH_DAYS', 1 );
define( 'WP_POST_REVISIONS', true );
define( 'WP_CRON_LOCK_TIMEOUT', MINUTE_IN_SECONDS );

/** @see wp_templating_constants() */
define( 'TEMPLATEPATH', WP_CONTENT_DIR . '/themes/ipsum' );
define( 'STYLESHEETPATH', WP_CONTENT_DIR . '/themes/ipsum' );
define( 'WP_DEFAULT_THEME', 'ipsum' );

/** @see ms_file_constants() */
define( 'WPMU_SENDFILE', false );
define( 'WPMU_ACCEL_REDIRECT', false );

/** @see ms_load_current_site_and_network() */
define( 'NOBLOGREDIRECT', '%siteurl%' );

/** @see ms_upload_constants() */
define( 'UPLOADBLOGSDIR', 'wp-content/blogs.dir' );
define( 'BLOGUPLOADDIR', WP_CONTENT_DIR . '/blogs.dir/1/files/' );

/** @see WP_Filesystem() */
define( 'FS_CONNECT_TIMEOUT', 30 ); // 30 seconds.
define( 'FS_TIMEOUT', 30 ); // 30 seconds.
define( 'FS_CHMOD_DIR', 0755 );
define( 'FS_CHMOD_FILE', 0644 );

/** @see add_theme_support() */
define( 'NO_HEADER_TEXT', false );
define( 'HEADER_IMAGE_WIDTH', 0 );
define( 'HEADER_IMAGE_HEIGHT', 0 );
define( 'HEADER_TEXTCOLOR', '' );
define( 'HEADER_IMAGE', '' );
define( 'BACKGROUND_COLOR', '' );
define( 'BACKGROUND_IMAGE', '' );
