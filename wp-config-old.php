<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'wordpress_develop' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', 'password' );

/** Database hostname */
define( 'DB_HOST', 'mysql' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         '1P&{cY5Xg`263lULTLXA=mb^jGNOpYKwh$DS;ejYR1Bpp*p5Ya_A$y41XiwWllC1' );
define( 'SECURE_AUTH_KEY',  'PV@#X9w4M&XB2*i$MbwT  ~ra8#p e]b_e7G.~wHLemK3P=/zghkw`bzcvGhJ[Qs' );
define( 'LOGGED_IN_KEY',    '>$z70(?J|WG_SOh]L)DFo{vm*UhB;]v%BG+VStebs?[+s4tFs<ut;gnV9n9y6`D$' );
define( 'NONCE_KEY',        'I`jR+_DeXKf<W8L2 9^`FQ@zDD:K=zWk9/^<celmxTP4OuGZJVUV/v *uz}V*,oE' );
define( 'AUTH_SALT',        'CINlmwZI^ytS!y~d>.BPLi`=]s=DRk-W2Gzbq[& B|?>]6p(wYd/6v`1{C`?8[Mo' );
define( 'SECURE_AUTH_SALT', '*cJd_o}aU50Zj!-;~5rW)5!F<#dJl[b4!ed|*Pnxnif#&Wb<X{F&:}Slq$%(kQbp' );
define( 'LOGGED_IN_SALT',   '&JANU%;,L8@!&aa khyU7G|pbn_9=Clz [:OpDum1#;7R#e1/QEwU8nVx1e#>7I[' );
define( 'NONCE_SALT',       'RE9jJ&.#oq+D0z2m(8sx4zWIgVFsP)u4?lf/%*HamK0};:Xte50W>M~zP+aB6+>=' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
