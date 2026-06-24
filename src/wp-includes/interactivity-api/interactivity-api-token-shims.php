<?php
/**
 * Interactivity API: PHP token constant shims.
 *
 * The Interactivity API's server-side expression validator inspects a token
 * stream produced by `token_get_all()` and rejects dangerous constructs by
 * comparing each token's ID against a reject-list of `T_*` constants. Several
 * of those constants were introduced after PHP 7.4, which is the minimum
 * supported version for WordPress 7.0. On PHP 7.4 those constants are
 * undefined and would raise "Use of undefined constant" notices, so this file
 * defines them with large sentinel integer values that cannot collide with
 * any real token ID produced by PHP's lexer.
 *
 * The strategy follows the recommendation in the PHP manual's "List of Parser
 * Tokens" (see `phptokens.md`): use `defined() || define()` with large
 * integers. The sentinel values are intentionally spaced far apart from one
 * another and from PHP's actual token values (which historically occupy the
 * low hundreds) so that they cannot be misidentified on any PHP version that
 * does define them natively — `defined()` short-circuits before `define()` on
 * versions where the constant already exists.
 *
 * @package WordPress
 * @subpackage Interactivity API
 * @since 6.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Each line targets one PHP-version-gated token that the Interactivity API
 * validator references. The `defined() || define()` pattern is a no-op on PHP
 * versions where the constant already exists, and provides a stable sentinel
 * on versions where it does not.
 *
 * Sentinel values are grouped by introducing PHP version for clarity. Do not
 * change a sentinel once assigned: doing so could break existing serialized
 * state that referenced those values, and the validation logic relies on
 * `in_array( $token_id, $dangerous, true )` matching the same integer that
 * `token_get_all()` returns (which is the native value on PHP versions where
 * the constant exists, and the sentinel here on versions where it does not).
 */

// PHP 8.0.0.
defined( 'T_NAME_FULLY_QUALIFIED' ) || define( 'T_NAME_FULLY_QUALIFIED', 10001 );
defined( 'T_NAME_QUALIFIED' ) || define( 'T_NAME_QUALIFIED', 10002 );
defined( 'T_NAME_RELATIVE' ) || define( 'T_NAME_RELATIVE', 10003 );
defined( 'T_MATCH' ) || define( 'T_MATCH', 10004 );
defined( 'T_NULLSAFE_OBJECT_OPERATOR' ) || define( 'T_NULLSAFE_OBJECT_OPERATOR', 10005 );
defined( 'T_ATTRIBUTE' ) || define( 'T_ATTRIBUTE', 10006 );

// PHP 8.1.0.
defined( 'T_READONLY' ) || define( 'T_READONLY', 10011 );
defined( 'T_ENUM' ) || define( 'T_ENUM', 10012 );
defined( 'T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG' ) || define( 'T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG', 10013 );
defined( 'T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG' ) || define( 'T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG', 10014 );

// PHP 8.4.0.
defined( 'T_PROPERTY_C' ) || define( 'T_PROPERTY_C', 10021 );
defined( 'T_PRIVATE_SET' ) || define( 'T_PRIVATE_SET', 10022 );
defined( 'T_PROTECTED_SET' ) || define( 'T_PROTECTED_SET', 10023 );
defined( 'T_PUBLIC_SET' ) || define( 'T_PUBLIC_SET', 10024 );

// PHP 8.5.0.
defined( 'T_PIPE' ) || define( 'T_PIPE', 10031 );
defined( 'T_VOID_CAST' ) || define( 'T_VOID_CAST', 10032 );