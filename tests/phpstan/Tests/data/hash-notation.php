<?php
/**
 * Fixture for HashNotationVisitor: the shapes PHPStan sees once a hash is translated.
 *
 * This file is also loaded by tests.neon, so that the functions it declares are
 * reflected and their return types can be asserted at a call site. Nothing in it
 * runs at load time for that reason.
 *
 * @package WordPress
 */

declare(strict_types=1);

use function PHPStan\Testing\assertType;

/**
 * A `@param` hash is open, with every key optional.
 *
 * @param array $args {
 *     Arguments.
 *
 *     @type string $post_type Post type.
 *     @type int    $per_page  Per page.
 * }
 * @return array {
 *     @type string $name  Name.
 *     @type int    $count Count.
 * }
 */
function wp_tests_hash_param_and_return( $args ) {
	assertType( 'array{post_type?: string, per_page?: int, ...}', $args );

	return array(
		'name'  => 'x',
		'count' => 1,
	);
}

/**
 * A hash on a class produces an intersection.
 *
 * @return stdClass {
 *     @type string $name Name.
 * }
 */
function wp_tests_hash_object_return() {
	$object       = new stdClass();
	$object->name = 'x';

	return $object;
}

/**
 * The bare member of a union takes the shape.
 *
 * @return array|WP_Error {
 *     @type string $name Name.
 * }
 */
function wp_tests_hash_union_return() {
	return array( 'name' => 'x' );
}

/**
 * A hand-written `@phpstan-return` wins.
 *
 * @return array {
 *     @type string $name Name.
 * }
 * @phpstan-return array{name: non-empty-string}
 */
function wp_tests_hash_handwritten_return() {
	return array( 'name' => 'x' );
}

/**
 * A by-reference parameter keeps its declared type.
 *
 * @param array $args {
 *     @type string $name Name.
 * }
 */
function wp_tests_hash_by_reference( &$args ) {
	assertType( 'array', $args );
}

/**
 * A hook documented with a hash is typed from the shape.
 *
 * @param array $args {
 *     @type string $post_type Post type.
 * }
 */
function wp_tests_hash_hook( $args ) {
	/**
	 * Filters the arguments.
	 *
	 * @param array $args {
	 *     Arguments.
	 *
	 *     @type string $post_type Post type.
	 *     @type int    $per_page  Per page.
	 * }
	 */
	$args = apply_filters( 'wp_tests_hash_hook', $args );

	assertType( 'array{post_type?: string, per_page?: int, ...}', $args );
}

/**
 * The return shapes as a caller sees them.
 */
function wp_tests_hash_call_sites() {
	assertType( 'array{name: string, count: int}', wp_tests_hash_param_and_return( array() ) );
	assertType( 'object{name: string}&stdClass', wp_tests_hash_object_return() );
	assertType( 'array{name: string}|WP_Error', wp_tests_hash_union_return() );
	assertType( 'array{name: non-empty-string}', wp_tests_hash_handwritten_return() );
}
