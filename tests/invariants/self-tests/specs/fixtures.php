<?php
/**
 * Invariants on the fixtures, which the self-tests trigger on purpose.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

return array(
	WP_Invariant::define( 'positive' )
		->describe( 'wp_invariants_fixture_positive() is given a number above 0.' )
		->before(
			'wp_invariants_fixture_positive',
			static function ( WP_Invariant_Call $call ) {
				$call->expect( $call->arg( 'n' ) > 0, 'It was given %s.', $call->arg( 'n' ) );
			}
		),

	WP_Invariant::define( 'positive-from-anywhere' )
		->describe( 'wp_invariants_fixture_positive() is given a number above 0, even by test code.' )
		->include_test_callers()
		->before(
			'wp_invariants_fixture_positive',
			static function ( WP_Invariant_Call $call ) {
				$call->expect( $call->arg( 'n' ) > 0, 'It was given %s.', $call->arg( 'n' ) );
			}
		),

	WP_Invariant::define( 'label-is-default' )
		->describe( 'wp_invariants_fixture_positive() is called with its default label.' )
		->before(
			'wp_invariants_fixture_positive',
			static function ( WP_Invariant_Call $call ) {
				$call->expect( 'default' === $call->arg( 'label' ), 'Its label was %s.', $call->arg( 'label' ) );
			}
		),

	WP_Invariant::define( 'entry-values' )
		->describe( 'An after check sees the arguments as they were when the call started.' )
		->after(
			'wp_invariants_fixture_positive',
			static function ( WP_Invariant_Call $call ) {
				$call->expect( $call->arg( 'n' ) === $call->return_value(), 'It was given %s and returned %s.', $call->arg( 'n' ), $call->return_value() );
			}
		),

	WP_Invariant::define( 'paired-recursion' )
		->describe( 'Each after check gets the result of the before check of its own call, through recursion.' )
		->before(
			'wp_invariants_fixture_recursive',
			static function ( WP_Invariant_Call $call ) {
				return $call->arg( 'n' );
			}
		)
		->after(
			'wp_invariants_fixture_recursive',
			static function ( WP_Invariant_Call $call ) {
				$call->expect( $call->before() === $call->arg( 'n' ), 'The call with %s got the before result %s.', $call->arg( 'n' ), $call->before() );
			}
		),

	WP_Invariant::define( 'paired-throw' )
		->describe( 'Each after check gets the result of the before check of its own call, when a call inside it threw.' )
		->before(
			'wp_invariants_fixture_throwing',
			static function ( WP_Invariant_Call $call ) {
				return $call->arg( 'n' );
			}
		)
		->after(
			'wp_invariants_fixture_throwing',
			static function ( WP_Invariant_Call $call ) {
				$call->expect( $call->before() === $call->arg( 'n' ), 'The call with %s got the before result %s.', $call->arg( 'n' ), $call->before() );
			}
		),

	WP_Invariant::define( 'method-self' )
		->describe( 'An instance method check gets the object, with its state after the call.' )
		->after(
			'WP_Invariants_Fixture::add',
			static function ( WP_Invariant_Call $call ) {
				$self = $call->self();
				$call->expect( $self instanceof WP_Invariants_Fixture && $self->total === $call->return_value(), 'The object was %s and the call returned %s.', $self, $call->return_value() );
			}
		),

	WP_Invariant::define( 'static-method' )
		->describe( 'A static method check gets no object, and the arguments in order.' )
		->before(
			'WP_Invariants_Fixture::twice',
			static function ( WP_Invariant_Call $call ) {
				$call->expect( null === $call->self() && array( 7 ) === $call->args(), 'It got the object %s and the arguments %s.', $call->self(), $call->args() );
			}
		),

	WP_Invariant::define( 'counted-per-test' )
		->describe( 'Counts calls to wp_invariants_fixture_counter() in each test, in its own state and in a shared object.' )
		->state(
			static function () {
				return array( 'calls' => 0 );
			}
		)
		->before(
			'wp_invariants_fixture_counter',
			static function ( WP_Invariant_Call $call, array &$state ) {
				++$state['calls'];

				$shared        = WP_Invariant::shared( 'self-test/counter' );
				$shared->calls = isset( $shared->calls ) ? $shared->calls + 1 : 1;
			}
		),

	WP_Invariant::define( 'counted-per-run' )
		->describe( 'Counts calls to wp_invariants_fixture_counter() over the whole run.' )
		->state(
			static function () {
				return array( 'calls' => 0 );
			},
			'run'
		)
		->before(
			'wp_invariants_fixture_counter',
			static function ( WP_Invariant_Call $call, array &$state ) {
				++$state['calls'];
			}
		),

	WP_Invariant::define( 'depth' )
		->describe( 'A call a target makes is one deeper than the target, and a check on each target runs once per call.' )
		->after_each(
			array( 'wp_invariants_fixture_inner', 'wp_invariants_fixture_outer' ),
			static function ( WP_Invariant_Call $call ) {
				$depths                           = WP_Invariant::shared( 'self-test/depths' );
				$depths->seen[ $call->target() ] = $call->depth();
			}
		),

	WP_Invariant::define( 'caller-args' )
		->describe( 'A check can see the arguments of the function which called its target.' )
		->include_test_callers()
		->before(
			'wp_invariants_fixture_positive',
			static function ( WP_Invariant_Call $call ) {
				WP_Invariant::shared( 'self-test/caller-args' )->seen = $call->caller_args();
			}
		),

	WP_Invariant::define( 'pcre-error-survives' )
		->describe( 'A check on a PCRE function sees the error of the call it is checking.' )
		->include_test_callers()
		->after(
			'preg_match',
			static function ( WP_Invariant_Call $call ) {
				$error = preg_last_error();

				WP_Invariant::shared( 'self-test/pcre' )->errors[] = array( $call->arg( 'pattern' ), $error );
			}
		),

	WP_Invariant::define( 'extra-arguments' )
		->describe( 'Checks see every argument of a variadic function, before and after the call. The gameshark extension at a7f9b79 passed undefined values instead of the extra ones.' )
		->before(
			'wp_invariants_fixture_variadic',
			static function ( WP_Invariant_Call $call ) {
				$call->expect( array( 1, 'two', array( 3 ) ) === $call->args() && array( 'two', array( 3 ) ) === $call->arg( 'rest' ), 'A before check got %s.', $call->args() );
			}
		)
		->after(
			'wp_invariants_fixture_variadic',
			static function ( WP_Invariant_Call $call ) {
				$call->expect( array( 1, 'two', array( 3 ) ) === $call->args() && 2 === $call->return_value(), 'An after check got %s.', $call->args() );
			}
		),

	WP_Invariant::define( 'check-throws' )
		->describe( 'A check which throws is recorded as a hook error.' )
		->before(
			'wp_invariants_fixture_error',
			static function () {
				throw new RuntimeException( 'This check always throws.' );
			}
		),
);
