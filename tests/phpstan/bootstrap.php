<?php
/**
 * Defines default WordPress constants for discovery.
 *
 * Mocks the constant initiation that would normally happen in wp-includes/wp-setttings.php.
 */

// wp_initial_constants()
define( 'KB_IN_BYTES', 1024 );
define( 'MB_IN_BYTES', 1024 * KB_IN_BYTES );
define( 'GB_IN_BYTES', 1024 * MB_IN_BYTES );
define( 'TB_IN_BYTES', 1024 * GB_IN_BYTES );
define( 'PB_IN_BYTES', 1024 * TB_IN_BYTES );
define( 'EB_IN_BYTES', 1024 * PB_IN_BYTES );
define( 'ZB_IN_BYTES', 1024 * EB_IN_BYTES );
define( 'YB_IN_BYTES', 1024 * ZB_IN_BYTES );
define( 'WP_START_TIMESTAMP', microtime( true ) );
define( 'WP_MEMORY_LIMIT', '' );
define( 'WP_MAX_MEMORY_LIMIT', '' );
define( 'WP_CONTENT_DIR', '' );
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

// wp_set_lang_dir()
define( 'WP_LANG_DIR', '' );

// wp_plugin_directory_constants()
define( 'WP_CONTENT_URL', '' );
define( 'WP_PLUGIN_DIR', '' );
define( 'WP_PLUGIN_URL', '' );
define( 'PLUGINDIR', '' );
define( 'WPMU_PLUGIN_DIR', '' );
define( 'WPMU_PLUGIN_URL', '' );
define( 'MUPLUGINDIR', '' );

// ms_cookie_constants()
define( 'COOKIEPATH', '' );
define( 'SITECOOKIEPATH', '' );
define( 'ADMIN_COOKIE_PATH', '' );
define( 'COOKIE_DOMAIN', '' );

// wp_cookie_constants()
define( 'COOKIEHASH', '' );
define( 'USER_COOKIE', '' );
define( 'PASS_COOKIE', '' );
define( 'AUTH_COOKIE', '' );
define( 'SECURE_AUTH_COOKIE', '' );
define( 'LOGGED_IN_COOKIE', '' );
define( 'TEST_COOKIE', '' );
define( 'PLUGINS_COOKIE_PATH', '' );
define( 'RECOVERY_MODE_COOKIE', '' );

// wp_ssl_constants()
define( 'FORCE_SSL_LOGIN', false );
define( 'FORCE_SSL_ADMIN', false );

// wp_functionality_constants()
define( 'AUTOSAVE_INTERVAL', MINUTE_IN_SECONDS );
define( 'EMPTY_TRASH_DAYS', 1 );
define( 'WP_POST_REVISIONS', true );
define( 'WP_CRON_LOCK_TIMEOUT', MINUTE_IN_SECONDS );

// wp_templating_constants()
define( 'TEMPLATEPATH', '' );
define( 'STYLESHEETPATH', '' );
define( 'WP_DEFAULT_THEME', '' );

// ms_file_constants()
define( 'WPMU_SENDFILE', false );
define( 'WPMU_ACCEL_REDIRECT', false );

// ms_load_current_site_and_network()
define( 'NOBLOGREDIRECT', '' );

// ms_upload_constants()
define( 'UPLOADBLOGSDIR', '' );
define( 'BLOGUPLOADDIR', '' );

// Misc constants not part of the default lifecycle.
define( 'FS_CONNECT_TIMEOUT', 1 );
define( 'FS_TIMEOUT', 1 );
define( 'FS_CHMOD_DIR', 1 );
define( 'FS_CHMOD_FILE', 1 );
