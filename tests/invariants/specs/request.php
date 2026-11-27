<?php
/**
 * Invariants of what requests to change things check first: a nonce, and a capability.
 *
 * A request scope is the run of an AJAX, admin-post or admin action handler, or of
 * a REST callback. Inside one, functions which change state must come after a
 * nonce check which passed, against cross-site request forgery, and after a
 * capability check which passed, against users doing what they may not.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

// The request scopes open in the running test, innermost last.
$scopes = WP_Invariant::shared( 'request/scopes' );

// Actions which run handlers for requests: kind => prefix.
$handlers = array(
	'AJAX'         => 'wp_ajax_',
	'admin-post'   => 'admin_post_',
	'admin action' => 'admin_action_',
);

// Handlers which are meant to be public, and change nothing a forged request could abuse.
$public = array(
	'wp_ajax_nopriv_heartbeat'         => true,
	'wp_ajax_nopriv_generate-password' => true,
);

// Functions which change state, and the argument which names what they change, if any.
$writes = array(
	'wp_insert_post'        => null,
	'wp_update_post'        => null,
	'wp_delete_post'        => null,
	'wp_trash_post'         => null,
	'add_option'            => 'option',
	'update_option'         => 'option',
	'delete_option'         => 'option',
	'wp_insert_user'        => null,
	'wp_update_user'        => null,
	'wp_delete_user'        => null,
	'wp_set_password'       => null,
	'wp_set_object_terms'   => null,
	'wp_delete_comment'     => null,
	'wp_set_comment_status' => null,
	'activate_plugin'       => null,
	'switch_theme'          => null,
	'update_user_meta'      => 'meta_key',
	'update_post_meta'      => 'meta_key',
);

// Options WordPress keeps for itself, which any request may change.
$housekeeping = static function ( $name ) {
	return ! is_string( $name ) || 0 === strpos( $name, '_transient_' ) || 0 === strpos( $name, '_site_transient_' ) || in_array( $name, array( 'cron', 'rewrite_rules' ), true );
};

/*
 * Returns the innermost request scope which is still running, after dropping
 * those which ended, such as by throwing from wp_die().
 */
$current = static function () use ( $scopes ) {
	if ( empty( $scopes->stack ) ) {
		return null;
	}

	while ( array() !== $scopes->stack ) {
		$scope = end( $scopes->stack );
		if ( WP_Invariant::running( $scope['mark'], $scope['function'] ) ) {
			return $scope;
		}
		array_pop( $scopes->stack );
	}

	return null;
};

// Marks the innermost running scope as having passed a check.
$passed = static function ( $check ) use ( $scopes, $current ) {
	if ( null !== $current() ) {
		$scopes->stack[ count( $scopes->stack ) - 1 ][ $check ] = true;
	}
};

/*
 * Returns the scope a write happens in, when it is one the invariant looks at:
 * not a write WordPress makes for itself.
 */
$write_scope = static function ( WP_Invariant_Call $call ) use ( $scopes, $writes, $housekeeping, $current ) {
	if ( empty( $scopes->stack ) ) {
		return null;
	}

	if ( 'option' === $writes[ $call->target() ] && $housekeeping( $call->arg( 'option' ) ) ) {
		return null;
	}

	$scope = $current();
	if ( null === $scope ) {
		return null;
	}

	// A write inside another, such as add_option() in update_option(), is the outer one's; wp_salt() saves salts it makes on first use.
	$running = array_count_values( WP_Invariant::running_functions() );
	foreach ( array_keys( $writes ) as $write ) {
		if ( isset( $running[ $write ] ) && ( $write !== $call->target() || $running[ $write ] > 1 ) ) {
			return null;
		}
	}
	if ( isset( $running['wp_salt'] ) ) {
		return null;
	}

	return $scope;
};

$run_state = static function () {
	return array();
};

return array(
	WP_Invariant::define( 'writes-check-a-nonce' )
		->describe( 'Inside an AJAX, admin-post or admin action handler, functions which change posts, options, users, terms, comments, plugins or themes run only after a nonce check passed in that handler: wp_verify_nonce(), check_ajax_referer() or check_admin_referer(). Public handlers, such as heartbeat, transients, cron, rewrite rules and salts, and writes inside other writes are left out. Each handler is reported once for each function.' )
		->state( $run_state, 'run' )
		->before(
			'do_action',
			static function ( WP_Invariant_Call $call ) use ( $scopes, $handlers ) {
				$hook = $call->arg( 'hook_name' );
				// Most actions are not handlers; only those starting with w or a can be.
				if ( ! is_string( $hook ) || '' === $hook || ( 'w' !== $hook[0] && 'a' !== $hook[0] ) ) {
					return;
				}

				foreach ( $handlers as $kind => $prefix ) {
					if ( 0 === strncmp( $hook, $prefix, strlen( $prefix ) ) ) {
						if ( ! isset( $scopes->stack ) ) {
							$scopes->stack = array();
						}
						$scopes->stack[] = array(
							'kind'       => $kind,
							'name'       => $hook,
							'function'   => 'do_action',
							'mark'       => WP_Invariant::frame_mark( 'do_action' ),
							'nonce'      => false,
							'capability' => false,
							'user'       => function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0,
						);
						return;
					}
				}
			}
		)
		->after_each(
			array( 'wp_verify_nonce', 'check_ajax_referer', 'check_admin_referer' ),
			static function ( WP_Invariant_Call $call ) use ( $passed ) {
				if ( $call->return_value() ) {
					$passed( 'nonce' );
				}
			}
		)
		->before_each(
			array_keys( $writes ),
			static function ( WP_Invariant_Call $call, array &$reported ) use ( $write_scope, $public ) {
				$scope = $write_scope( $call );
				if ( null === $scope || 'REST' === $scope['kind'] || $scope['nonce'] || isset( $public[ $scope['name'] ] ) ) {
					return;
				}

				$key = "{$scope['name']} {$call->target()}";
				if ( isset( $reported[ $key ] ) ) {
					return;
				}
				$reported[ $key ] = true;
				$call->expect( false, '%s() ran in the %s handler %s before any nonce check passed.', $call->target(), $scope['kind'], $scope['name'] );
			}
		),

	WP_Invariant::define( 'writes-check-a-capability' )
		->describe( 'Inside an AJAX, admin-post or admin action handler, or a REST callback, functions which change posts, options, users, terms, comments, plugins or themes run only after a capability check for the current user passed in it: WP_User::has_cap() returned true for a capability other than exist, as current_user_can() and the REST route\'s permission callback do. Users may change their own user meta. In a REST callback, the current user is the one the permission check ran for. Public handlers, such as heartbeat, transients, cron, rewrite rules and salts, and writes inside other writes are left out. Each handler or route is reported once for each function.' )
		->state( $run_state, 'run' )
		->before(
			'WP_REST_Server::dispatch',
			static function ( WP_Invariant_Call $call ) {
				// The parameters of a REST request are values the request sent, for every invariant which follows them.
				$request = $call->arg( 'request' );
				WP_Invariant_Request::start_rest_request( $request instanceof WP_REST_Request ? $request->get_params() : array() );
			}
		)
		->after(
			'WP_REST_Server::dispatch',
			static function () {
				WP_Invariant_Request::end_rest_request();
			}
		)
		->before(
			'WP_REST_Server::respond_to_request',
			static function ( WP_Invariant_Call $call ) use ( $scopes ) {
				$route = $call->arg( 'route' );
				if ( ! isset( $scopes->stack ) ) {
					$scopes->stack = array();
				}
				$scopes->stack[] = array(
					'kind'       => 'REST',
					'name'       => is_string( $route ) ? $route : '?',
					'function'   => 'WP_REST_Server::respond_to_request',
					'mark'       => WP_Invariant::frame_mark( 'WP_REST_Server::respond_to_request' ),
					'nonce'      => false,
					'capability' => false,
					'user'       => get_current_user_id(),
				);
			}
		)
		->after(
			'WP_User::has_cap',
			static function ( WP_Invariant_Call $call ) use ( $scopes, $passed ) {
				if ( ! empty( $scopes->stack ) && true === $call->return_value() && 'exist' !== $call->arg( 'cap' ) ) {
					$passed( 'capability' );
				}
			}
		)
		->before_each(
			array_keys( $writes ),
			static function ( WP_Invariant_Call $call, array &$reported ) use ( $write_scope, $public ) {
				$scope = $write_scope( $call );
				if ( null === $scope || isset( $public[ $scope['name'] ] ) ) {
					return;
				}

				// A user may change their own settings, such as which boxes are closed, without any capability.
				if ( 'update_user_meta' === $call->target() && (int) $call->arg( 'user_id' ) === get_current_user_id() ) {
					return;
				}

				if ( 'REST' === $scope['kind'] && get_current_user_id() !== $scope['user'] ) {
					$call->expect( false, '%s() ran in the REST callback for %s as user %d, but its permission check ran for user %d.', $call->target(), $scope['name'], get_current_user_id(), $scope['user'] );
					return;
				}

				$key = "{$scope['name']} {$call->target()}";
				if ( $scope['capability'] || isset( $reported[ $key ] ) ) {
					return;
				}
				$reported[ $key ] = true;
				$call->expect( false, '%s() ran in the %s %s %s before any capability check passed.', $call->target(), $scope['kind'], 'REST' === $scope['kind'] ? 'callback for' : 'handler', $scope['name'] );
			}
		),
);
