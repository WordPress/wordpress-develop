<?php
/**
 * Fixture for ApplyFiltersDynamicFunctionReturnTypeExtension.
 *
 * Adapted from szepeviktor/phpstan-wordpress, MIT license.
 *
 * @package WordPress
 */

declare(strict_types=1);

use function PHPStan\Testing\assertType;

$value = apply_filters( 'filter', 'Hello, World' );
assertType( 'mixed', $value );

/**
 * A docblock documenting no parameter.
 */
$value = apply_filters( 'filter', $foo );
assertType( 'mixed', $value );

/** @var int $value */
$value = apply_filters( 'filter', $foo );
assertType( 'int', $value );

/**
 * Single type.
 *
 * @param string $foo Hello, World.
 */
$value = apply_filters( 'filter', $foo );
assertType( 'string', $value );

/**
 * A PHPStan-specific type, which takes precedence over the one in `@param`.
 *
 * @param string $foo Hello, World.
 * @phpstan-param non-empty-string $foo
 */
$value = apply_filters( 'filter', $foo );
assertType( 'non-empty-string', $value );

/**
 * Multiple lines and multiple parameters.
 *
 * @param string $foo Hello.
 * @param bool   $bar World.
 */
$value = apply_filters(
	'filter',
	$foo,
	$bar
);
assertType( 'string', $value );

/**
 * Single constant type.
 *
 * @param string $foo Hello, World.
 */
$value = apply_filters( 'filter', 'I am a string' );
assertType( 'string', $value );

/**
 * A constant of a type that differs from the docblock. The docblock wins.
 *
 * @param string $foo Hello, World.
 */
$value = apply_filters( 'filter', 123 );
assertType( 'string', $value );

/**
 * Union type.
 *
 * @param string|null $foo Hello, World.
 */
$value = apply_filters( 'filter', $foo );
assertType( 'string|null', $value );

/**
 * Hash notation, translated by HashNotationVisitor.
 *
 * @param array $foo {
 *     Hello, World.
 *
 *     @type string $bar Bar.
 * }
 */
$value = apply_filters( 'filter', $foo );
assertType( 'array{bar?: string, ...}', $value );

/**
 * Value wrapped inside another call.
 *
 * @param string $foo Hello, World.
 */
$value = wp_tests_return_value( apply_filters( 'filter', $foo ) );
assertType( 'string', $value );

/**
 * A docblock on the statement reaches a call however deeply it is nested.
 *
 * @param string $foo Hello, World.
 */
$value = wp_tests_return_value(
	wp_tests_return_value(
		wp_tests_return_value(
			apply_filters( 'filter', $foo )
		)
	)
);
assertType( 'string', $value );

/**
 * Casting to a type.
 *
 * @param float|int|null $foo Hello, World.
 */
$value = (int) apply_filters( 'filter', $foo );
assertType( 'int', $value );

/**
 * Global class.
 *
 * @param \WP_Post|null $foo Hello, World.
 */
$value = apply_filters( 'filter', $foo );
assertType( 'WP_Post|null', $value );

/**
 * Constant string type.
 *
 * @param 'aaa'|'bbb' $foo Hello, World.
 */
$value = apply_filters( 'filter', $foo );
assertType( "'aaa'|'bbb'", $value );

/**
 * Typed array passed through `list()`. The hash is on `int[]`, which carries no
 * shape, so the declared type stands.
 *
 * @param int[]        $max_image_size {
 *     An array of width and height values.
 *
 *     @type int $0 The maximum width in pixels.
 *     @type int $1 The maximum height in pixels.
 * }
 * @param string|int[] $size    Requested image size.
 * @param string       $context The context the image is being resized for.
 */
list( $max_width, $max_height ) = apply_filters( 'editor_max_image_size', array( $max_width, $max_height ), $size, $context );
assertType( 'int', $max_width );
assertType( 'int', $max_height );

/**
 * Filter inside a ternary.
 *
 * @param string            $slug The editable slug.
 * @param \WP_Term|\WP_Post $tag  Term or post object.
 */
$slug = isset( $tag->slug ) ? apply_filters( 'editable_slug', $tag->slug, $tag ) : 123;
assertType( '123|string', $slug );

/**
 * Return type for `apply_filters_ref_array()`.
 *
 * @param string $foo Hello.
 * @param int    $bar World.
 */
$value = apply_filters_ref_array( 'foo', array( 'Hello', 123 ) );
assertType( 'string', $value );

/**
 * Return type for `apply_filters_deprecated()`.
 *
 * @param string $foo Hello.
 * @param bool   $bar World.
 */
$value = apply_filters_deprecated( 'foo', array( 'Hello', true ), '6.0.0' );
assertType( 'string', $value );

/**
 * An action is not a filter: its docblock does not type its return value, which
 * stays whatever `do_action()` itself declares.
 *
 * @param string $foo Hello.
 */
$value = do_action( 'foo', 'Hello' );
assertType( 'mixed', $value );

/** This filter is documented in wp-includes/missing.php */
$value = apply_filters( 'documented_filter', 123 );
assertType( 'mixed', $value );

/** This filter is documented in wp-includes/canonical.php */
$value = apply_filters( 'documented_filter', 123, $context );
assertType( 'string', $value );

/** This filter is documented in wp-includes/canonical.php */
$value = apply_filters( 'not_documented_there', 123 );
assertType( 'mixed', $value );

/** This filter is documented in wp-includes/canonical.php */
$value = apply_filters( 'index_template_hierarchy', array() );
assertType( 'array<string>', $value );

/** This filter is documented in wp-includes/canonical.php */
$value = apply_filters( 'documented_hash_filter', array() );
assertType( 'array{post_type?: string, per_page?: int, ...}', $value );

/** This filter is documented in wp-includes/canonical.php */
$value = apply_filters( $hook_name, 123 );
assertType( 'mixed', $value );
