<?php
/**
 * Default authentication hooks for the REST API.
 *
 * @package WordPress
 * @subpackage REST_API
 * @since 7.2.0
 */

add_action( 'auth_cookie_malformed', 'rest_cookie_collect_status' );
add_action( 'auth_cookie_expired', 'rest_cookie_collect_status' );
add_action( 'auth_cookie_bad_username', 'rest_cookie_collect_status' );
add_action( 'auth_cookie_bad_hash', 'rest_cookie_collect_status' );
add_action( 'auth_cookie_valid', 'rest_cookie_collect_status' );
add_action( 'application_password_failed_authentication', 'rest_application_password_collect_status' );
add_action( 'application_password_did_authenticate', 'rest_application_password_collect_status', 10, 2 );
add_filter( 'rest_authentication_errors', 'rest_application_password_check_errors', 90 );
add_filter( 'rest_authentication_errors', 'rest_cookie_check_errors', 100 );
