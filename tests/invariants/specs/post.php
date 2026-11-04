<?php
/**
 * Invariants of posts.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

return array(
	WP_Invariant::define( 'positive-id' )
		->describe( 'A numeric post ID given to get_post() is a whole number above 0, or 0 for the current post.' )
		->before(
			'get_post',
			static function ( WP_Invariant_Call $call ) {
				$post = $call->arg( 'post' );
				if ( ! is_numeric( $post ) ) {
					return;
				}

				$id = (int) $post;
				$call->expect(
					$id >= 0 && ( is_int( $post ) || (string) $id === (string) $post ),
					'get_post() received the post ID %s.',
					var_export( $post, true )
				);
			}
		),

	WP_Invariant::define( 'returns-requested-post' )
		->describe( 'get_post() given a post ID returns the post with that ID, or null.' )
		->after(
			'get_post',
			static function ( WP_Invariant_Call $call ) {
				$post   = $call->arg( 'post' );
				$result = $call->return_value();
				if ( ! is_numeric( $post ) || (int) $post <= 0 || ! is_object( $result ) || ! isset( $result->ID ) ) {
					return;
				}

				$call->expect( (int) $post === (int) $result->ID, 'get_post( %s ) returned the post %d.', var_export( $post, true ), $result->ID );
			}
		),
);
