<?php
/**
 * Fixture for ReflectionParserTest: a function reflected from a file that is scanned
 * rather than analyzed, like most of core in an analysis of only part of it.
 *
 * @package WordPress
 */

declare(strict_types=1);

/**
 * A hash in a scanned file still produces a shape.
 *
 * @return array {
 *     @type string $name  Name.
 *     @type int    $count Count.
 * }
 */
function wp_tests_scanned_hash_return() {
	return array(
		'name'  => 'x',
		'count' => 1,
	);
}
