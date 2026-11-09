<?php
/**
 * Fixture for HookParamCountRule.
 *
 * @package WordPress
 */

/**
 * Two parameters, two arguments.
 *
 * @param string $value   Value.
 * @param string $context Context.
 */
$value = apply_filters( 'two_params', $value, $context );

/**
 * Two parameters, one argument.
 *
 * @param string $value   Value.
 * @param string $context Context.
 */
$value = apply_filters( 'two_params_short', $value );

/**
 * One parameter, two arguments.
 *
 * @param string $value Value.
 */
$value = apply_filters( 'one_param_long', $value, $context );

/**
 * Fires with no arguments.
 */
do_action( 'no_params' );

/**
 * Fires with an argument its docblock does not mention.
 */
do_action( 'no_params_extra', $value );

/**
 * Fires with two arguments its docblock does not mention.
 */
do_action( 'no_params_extra_two', $value, $context );

/**
 * One parameter, two arguments in the array.
 *
 * @param string $value Value.
 */
do_action_ref_array( 'ref_array_long', array( $value, $context ) );

/**
 * Two parameters, one argument in the array.
 *
 * @param string $value   Value.
 * @param string $context Context.
 */
$value = apply_filters_deprecated( 'deprecated_short', array( $value ), '6.0.0' );

/**
 * One parameter, one argument in the array.
 *
 * @param string $value Value.
 */
do_action_deprecated( 'deprecated_ok', array( $value ), '6.0.0' );

/**
 * The array's size is unknown, so nothing is compared.
 *
 * @param string $value Value.
 */
$value = apply_filters_ref_array( 'ref_array_unknown', $args );

$spread = array( $value, $context );

/**
 * Spread arguments of known size are counted.
 *
 * @param string $value   Value.
 * @param string $context Context.
 */
$value = apply_filters( 'spread_known', ...$spread );

/**
 * Spread arguments of known size are counted, one too many here.
 *
 * @param string $value Value.
 */
$value = apply_filters( 'spread_known_long', $value, ...$spread );

/**
 * Spread arguments of unknown size are not compared.
 *
 * @param string $value Value.
 */
$value = apply_filters( 'spread_unknown', $value, ...$args );

$value = apply_filters( 'undocumented', $value, $context );

/**
 * A filter documented without parameters is left to HookDocumentationRule.
 */
$value = apply_filters( 'paramless', $value, $context );

/** This filter is documented in wp-includes/missing.php */
$value = apply_filters( 'documented_filter', $value );

/** This filter is documented in wp-includes/canonical.php */
$value = apply_filters( 'documented_filter', $value, $context );

/** This filter is documented in wp-includes/canonical.php */
$value = apply_filters( 'documented_filter', $value );

/** This action is documented in wp-includes/canonical.php */
do_action( 'documented_action', $context, $value );

/** This filter is documented in wp-includes/canonical.php */
$templates = apply_filters( 'index_template_hierarchy', array(), $context );

/**
 * Two tags documenting the same name are two tags.
 *
 * @param string $value Value.
 * @param string $value A copy-and-paste slip.
 */
$value = apply_filters( 'duplicate_names', $value, $context );

/**
 * A tag without a variable name is not a documented parameter.
 *
 * @param bool false Whether to skip.
 * @param string $src Source.
 */
$value = apply_filters( 'malformed_tag', false, $src );

$value = apply_filters( $hook_name, $value, $context );

/**
 * Fires a dynamic hook with one argument too many.
 *
 * @param string $value Value.
 */
do_action( "wp_tests_{$type}_fired", $value, $context );
