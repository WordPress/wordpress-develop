<?php
/**
 * Fixture for GlobalDocBlockVisitor.
 *
 * @package WordPress
 */

declare(strict_types=1);

use function PHPStan\Testing\assertType;

/**
 * Documents the globals it imports.
 *
 * @global \wpdb       $wpdb         WordPress database abstraction object.
 * @global string      $pagenow      Current admin page.
 * @global int[]|null  $wp_tests_ids Identifiers, or null before they are known.
 * @global array<string, string> $wp_tests_map A type containing whitespace.
 */
function wp_tests_documented_globals(): void {
	global $wpdb, $pagenow, $wp_tests_ids, $wp_tests_map, $wp_tests_undocumented;

	assertType( 'wpdb', $wpdb );
	assertType( 'string', $pagenow );
	assertType( 'array<int>|null', $wp_tests_ids );
	assertType( 'array<string, string>', $wp_tests_map );
	assertType( 'mixed', $wp_tests_undocumented );
}

/**
 * Documents nothing.
 */
function wp_tests_no_global_tags(): void {
	global $wpdb;

	assertType( 'mixed', $wpdb );
}

function wp_tests_no_docblock(): void {
	global $wpdb;

	assertType( 'mixed', $wpdb );
}

/**
 * A hand-written `@var` on the statement is left alone, per variable.
 *
 * @global string $pagenow Current admin page.
 * @global string $wp_tests_other Something else.
 */
function wp_tests_handwritten_var(): void {
	/** @var int $pagenow */
	global $pagenow, $wp_tests_other;

	assertType( 'int', $pagenow );
	assertType( 'string', $wp_tests_other );
}

/**
 * A `@global` tag on the statement itself takes precedence.
 *
 * @global string $pagenow Current admin page.
 */
function wp_tests_statement_level_tag(): void {
	/** @global int $pagenow */
	global $pagenow;

	assertType( 'int', $pagenow );
}

/**
 * A closure is its own frame, documented or not.
 *
 * @global string $pagenow Current admin page.
 */
function wp_tests_closure_frames(): void {
	$undocumented = function (): void {
		global $pagenow;

		assertType( 'mixed', $pagenow );
	};

	/**
	 * A docblock on the assignment belongs to the statement, not to the closure.
	 *
	 * @global int $pagenow Current admin page, as the closure sees it.
	 */
	$documented_statement = function (): void {
		global $pagenow;

		assertType( 'mixed', $pagenow );
	};

	$documented_import = function (): void {
		/** @global int $pagenow Current admin page, as the closure sees it. */
		global $pagenow;

		assertType( 'int', $pagenow );
	};

	global $pagenow;

	assertType( 'string', $pagenow );
}

/**
 * A tag for a variable the function does not import changes nothing.
 *
 * @global string $pagenow Current admin page.
 */
function wp_tests_unused_tag(): void {
	global $wpdb;

	assertType( 'mixed', $wpdb );
}

/** @global \WP_Query $wp_query WordPress Query object. */
global $wp_query;

assertType( 'WP_Query', $wp_query );

global $wp_the_query;

assertType( 'mixed', $wp_the_query );
