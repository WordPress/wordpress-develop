<?php
/**
 * Fixture for HookDocumentationRule.
 *
 * This file sits outside the fixture WordPress root, so its reference comments
 * resolve against that root directly, as they do for a temporary editor copy.
 *
 * @package WordPress
 */

// The file docblock above attaches to the first statement, so that is not a hook.
$value   = 'value';
$context = 'context';

$value = apply_filters( 'undocumented_filter', $value );

do_action( 'undocumented_action', $value );

/**
 * Documented in place.
 *
 * @param string $value Value.
 */
$value = apply_filters( 'inline_filter', $value );

/**
 * An action is documented even without parameters.
 */
do_action( 'inline_action' );

/**
 * A filter docblock without a `@param` documents nothing.
 */
$value = apply_filters( 'paramless_filter', $value );

/** @var string $value */
$value = apply_filters( 'var_annotated_filter', $value );

/** This filter is documented in wp-includes/canonical.php */
$value = apply_filters( 'documented_filter', $value, $context );

/** This action is documented in wp-includes/canonical.php */
do_action( 'documented_action', $context );

/** This filter is documented in wp-includes/missing.php */
$value = apply_filters( 'documented_filter', $value, $context );

/** This filter is documented in wp-includes/other.php */
$value = apply_filters( 'documented_filter', $value, $context );

/** This filter is documented in wp-includes/canonical.php */
$value = apply_filters( 'only_referenced_here', $value );

/** This filter is documented in wp-includes/canonical.php */
$templates = apply_filters( 'index_template_hierarchy', array() );

/** This filter is documented in wp-includes/canonical.php */
$templates = apply_filters( "{$type}_template_hierarchy", array() );

/** This filter is documented in wp-includes/canonical.php */
$value = apply_filters( 'foo_bar', $value );

/** This filter is documented in wp-includes/canonical.php */
$value = apply_filters( "{$prefix}_bar", $value );

$value = apply_filters( $hook_name, $value );

$value = apply_filters_ref_array( $hook_name, $args );

/** This filter is documented in wp-includes/canonical.php */
$value = apply_filters( $hook_name, $value );

$value = apply_filters( "pre_option_{$option}", $value );

$value = apply_filters( 'prefix_' . $context, $value );

/**
 * Filters whether to short-circuit, from above the condition.
 *
 * @param bool $short Whether to short-circuit.
 */
if ( apply_filters( 'short_circuit', false ) ) {
	return;
}

$list = array(
	/**
	 * Filters the first item.
	 *
	 * @param string $value Value.
	 */
	'first'  => apply_filters( 'first_item', $value ),
	'second' => apply_filters( 'second_item', $value ),
);

/**
 * Filters a value passed through another call.
 *
 * @param string $value Value.
 */
$value = wp_tests_return_value( apply_filters( 'wrapped_filter', $value ) );

$value = apply_filters_deprecated( 'old_filter', array( $value ), '6.0.0' );

do_action_ref_array( 'ref_action', array( $value ) );

/**
 * Fires a deprecated action.
 *
 * @param string $value Value.
 */
do_action_deprecated( 'old_action', array( $value ), '6.0.0' );

/**
 * This docblock belongs to the function, not to the hook inside it.
 *
 * @param string $value Value.
 */
function wp_tests_function_docblock_does_not_reach_hook( $value ) {
	return apply_filters( 'inside_function', $value );
}
