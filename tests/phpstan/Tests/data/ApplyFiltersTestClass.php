<?php
/**
 * Fixture for ApplyFiltersDynamicFunctionReturnTypeExtension: filters within a class.
 *
 * Adapted from szepeviktor/phpstan-wordpress, MIT license.
 *
 * @package WordPress
 */

declare(strict_types=1);

namespace WordPress\PHPStan\Tests\data;

use function PHPStan\Testing\assertType;

/**
 * Fires filters from methods.
 */
class ApplyFiltersTestClass {

	/**
	 * A filter documented within a method.
	 */
	public function documented_filter(): void {
		$foo = 0.0;

		/**
		 * Documented filter within a method.
		 *
		 * @param float $foo Hello, World.
		 */
		$value = apply_filters( 'filter', $foo );
		assertType( 'float', $value );
	}

	/**
	 * This is the method docblock, not the filter docblock. The filter has none.
	 *
	 * @param int $foo Hello, World.
	 */
	public function method_docblock( int $foo ): void {
		$value = apply_filters( 'filter', $foo );
		assertType( 'mixed', $value );
	}

	/**
	 * This is the method docblock, not the filter docblock.
	 *
	 * @param int $foo Hello, World.
	 */
	public function method_and_filter_docblocks( int $foo ): void {
		$bar = '';

		/**
		 * This is the filter docblock.
		 *
		 * @param string $bar Hello, World.
		 */
		$value = apply_filters( 'filter', $bar );
		assertType( 'string', $value );
	}

	/**
	 * A docblock in a class resolves `self` and `static`.
	 */
	public function self_type(): void {
		/**
		 * Filters the instance.
		 *
		 * @param self $instance Instance.
		 */
		$value = apply_filters( 'filter', $this );
		assertType( 'WordPress\PHPStan\Tests\data\ApplyFiltersTestClass', $value );
	}
}
