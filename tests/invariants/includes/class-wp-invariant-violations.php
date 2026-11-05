<?php
/**
 * Collects invariant violations while the suite runs.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

/**
 * Counts violations by invariant and test, and keeps a few examples of each.
 *
 * Memory stays bounded over the whole suite: every violation is counted, but
 * only the first examples of each invariant are kept.
 */
final class WP_Invariant_Violations {

	/**
	 * Examples kept for each invariant.
	 */
	const EXAMPLES = 20;

	/**
	 * Violations kept for the running test, for the self-tests.
	 */
	const IN_TEST = 1000;

	/**
	 * Name of the running test.
	 *
	 * @var string
	 */
	private static $test = '(bootstrap)';

	/**
	 * By invariant id: `violations`, `hook_errors`, `tests` (test => count) and `examples`.
	 *
	 * @var array[]
	 */
	private static $by_invariant = array();

	/**
	 * Every violation in the running test, for the self-tests.
	 *
	 * @var array[]
	 */
	private static $in_test = array();

	/**
	 * Attributes the violations which follow to a test.
	 *
	 * @param string $test Name of the test.
	 */
	public static function start_test( $test ) {
		self::$test    = $test;
		self::$in_test = array();
	}

	/**
	 * Records a violation.
	 *
	 * @param WP_Invariant      $invariant The invariant which was violated.
	 * @param string            $message   What was wrong.
	 * @param WP_Invariant_Call $call      The call it was found on.
	 */
	public static function record( WP_Invariant $invariant, $message, WP_Invariant_Call $call ) {
		if ( $invariant->ignores( $call ) ) {
			return;
		}

		self::add( $invariant->id(), 'violation', $message, $call->target(), $call->when(), $call->stack() );
	}

	/**
	 * Records a check which threw instead of finishing.
	 *
	 * @param WP_Invariant           $invariant The invariant whose check threw.
	 * @param array                  $hook      The hook, with `when` and `target`.
	 * @param WP_Invariant_Call|null $call      The call, or null when it could not be read.
	 * @param Throwable              $error     What it threw.
	 */
	public static function record_hook_error( WP_Invariant $invariant, array $hook, $call, Throwable $error ) {
		if ( $call && $invariant->ignores( $call ) ) {
			return;
		}

		$message = sprintf( 'The check threw %s: %s (%s:%d)', get_class( $error ), $error->getMessage(), WP_Invariant::relative_path( $error->getFile() ), $error->getLine() );
		self::add( $invariant->id(), 'hook-error', $message, $hook['target'], 'pre' === $hook['when'] ? 'before' : 'after', $call ? $call->stack() : array() );
	}

	/**
	 * Returns everything recorded, by invariant id.
	 *
	 * @return array[]
	 */
	public static function all() {
		return self::$by_invariant;
	}

	/**
	 * Returns the violations recorded in the running test, for the self-tests.
	 *
	 * @param string|null $id Only those of this invariant.
	 * @return array[] Each with `invariant`, `kind`, `message`, `target`, `when`, `test` and `stack`.
	 */
	public static function in_test( $id = null ) {
		if ( null === $id ) {
			return self::$in_test;
		}

		return array_values(
			array_filter(
				self::$in_test,
				static function ( $violation ) use ( $id ) {
					return $violation['invariant'] === $id;
				}
			)
		);
	}

	/**
	 * Counts a violation, and keeps it when there are few examples of its invariant yet.
	 *
	 * @param string   $id      Invariant id.
	 * @param string   $kind    `violation` or `hook-error`.
	 * @param string   $message What was wrong.
	 * @param string   $target  The hooked function or method.
	 * @param string   $when    `before` or `after`.
	 * @param string[] $stack   Frames in the code under test.
	 */
	private static function add( $id, $kind, $message, $target, $when, array $stack ) {
		if ( ! isset( self::$by_invariant[ $id ] ) ) {
			self::$by_invariant[ $id ] = array(
				'violations'  => 0,
				'hook_errors' => 0,
				'tests'       => array(),
				'examples'    => array(),
			);
		}

		$entry = &self::$by_invariant[ $id ];
		if ( 'hook-error' === $kind ) {
			++$entry['hook_errors'];
		} else {
			++$entry['violations'];
		}
		$entry['tests'][ self::$test ] = ( isset( $entry['tests'][ self::$test ] ) ? $entry['tests'][ self::$test ] : 0 ) + 1;

		$violation = array(
			'invariant' => $id,
			'kind'      => $kind,
			'message'   => $message,
			'target'    => $target,
			'when'      => $when,
			'test'      => self::$test,
			'stack'     => $stack,
		);
		if ( count( $entry['examples'] ) < self::EXAMPLES ) {
			$entry['examples'][] = $violation;
		}
		if ( count( self::$in_test ) < self::IN_TEST ) {
			self::$in_test[] = $violation;
		}
	}
}
