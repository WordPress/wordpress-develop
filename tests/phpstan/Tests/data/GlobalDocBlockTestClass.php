<?php
/**
 * Fixture for GlobalDocBlockVisitor: globals imported within methods.
 *
 * @package WordPress
 */

declare(strict_types=1);

namespace WordPress\PHPStan\Tests\data;

use function PHPStan\Testing\assertType;

/**
 * Imports globals from methods.
 */
class GlobalDocBlockTestClass {

	/**
	 * Documents the global it imports.
	 *
	 * @global string $pagenow Current admin page.
	 */
	public function documented(): void {
		global $pagenow;

		assertType( 'string', $pagenow );
	}

	/**
	 * Documents a global under a relative class name, resolved in this namespace.
	 *
	 * @global GlobalDocBlockTestClass $wp_tests_instance Instance.
	 */
	public function relative_class_name(): void {
		global $wp_tests_instance;

		assertType( 'WordPress\PHPStan\Tests\data\GlobalDocBlockTestClass', $wp_tests_instance );
	}

	/**
	 * Documents nothing.
	 */
	public function undocumented(): void {
		global $pagenow;

		assertType( 'mixed', $pagenow );
	}
}
