<?php
/**
 * PHPStan dynamic return type extension that types the return value of
 * `apply_filters()` (and its variants) from the `@param` type documented in the
 * docblock immediately preceding the call.
 *
 * For example, given:
 *
 *     /**
 *      * @param WP_REST_Response $response The response object.
 *      * ...
 *      *\/
 *     return apply_filters( 'rest_prepare_attachment', $response, $post, $request );
 *
 * PHPStan will infer the call returns `WP_REST_Response` rather than `mixed`.
 *
 * This assumes filters honor the documented type. A misbehaving filter could
 * return something else, but that is treated as the unusual case.
 *
 * Adapted from szepeviktor/phpstan-wordpress (ApplyFiltersDynamicFunctionReturnTypeExtension):
 * https://github.com/szepeviktor/phpstan-wordpress/blob/master/src/ApplyFiltersDynamicFunctionReturnTypeExtension.php
 *
 * @package WordPress
 */

declare(strict_types=1);

namespace WordPress\PHPStan;

use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Type\DynamicFunctionReturnTypeExtension;
use PHPStan\Type\MixedType;
use PHPStan\Type\Type;

/**
 * Resolves the return type of WordPress filter functions from their docblocks.
 */
class ApplyFiltersDynamicFunctionReturnTypeExtension implements DynamicFunctionReturnTypeExtension {

	/**
	 * Hook docblock resolver.
	 *
	 * @var \WordPress\PHPStan\HookDocBlock
	 */
	protected $hookDocBlock;

	/**
	 * Constructor.
	 *
	 * @param HookDocBlock $hook_doc_block Hook docblock resolver.
	 */
	public function __construct( HookDocBlock $hook_doc_block ) {
		$this->hookDocBlock = $hook_doc_block;
	}

	/**
	 * Determines whether this extension applies to the given function.
	 *
	 * @param FunctionReflection $function_reflection Function being analyzed.
	 * @return bool
	 */
	public function isFunctionSupported( FunctionReflection $function_reflection ): bool {
		return in_array(
			$function_reflection->getName(),
			array(
				'apply_filters',
				'apply_filters_deprecated',
				'apply_filters_ref_array',
			),
			true
		);
	}

	/**
	 * Resolves the return type of the filter call from its preceding docblock.
	 *
	 * @see https://developer.wordpress.org/reference/functions/apply_filters/
	 * @see https://developer.wordpress.org/reference/functions/apply_filters_deprecated/
	 * @see https://developer.wordpress.org/reference/functions/apply_filters_ref_array/
	 *
	 * @param FunctionReflection $function_reflection Function being analyzed.
	 * @param FuncCall           $function_call       The function call node.
	 * @param Scope              $scope               Analysis scope.
	 * @return Type
	 */
	public function getTypeFromFunctionCall( FunctionReflection $function_reflection, FuncCall $function_call, Scope $scope ): Type {
		unset( $function_reflection );

		$default          = new MixedType();
		$resolved_php_doc = $this->hookDocBlock->getNullableHookDocBlock( $function_call, $scope );

		if ( null === $resolved_php_doc ) {
			return $default;
		}

		// Fetch the `@param` values from the docblock; the first describes the filtered value.
		$params = $resolved_php_doc->getParamTags();

		foreach ( $params as $param ) {
			return $param->getType();
		}

		return $default;
	}
}
