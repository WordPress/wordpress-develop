<?php
/**
 * Helper declarations for the fixtures, loaded through `bootstrapFiles` in tests.neon.
 *
 * @package WordPress
 */

declare(strict_types=1);

/**
 * Returns the passed value.
 *
 * @template T
 * @param T $value Value.
 * @return T Value.
 */
function wp_tests_return_value( $value ) {
	return $value;
}
