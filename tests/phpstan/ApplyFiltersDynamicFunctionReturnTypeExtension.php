<?php
/**
 * PHPStan dynamic return type extension that types the return value of
 * `apply_filters()` (and its variants) from the `@param` type documented in the
 * docblock immediately preceding the call.
 *
 * For example, given:
 *
 *     /**
 *      * @param array $data Response data.
 *      * ...
 *      *\/
 *     return apply_filters( 'response_data', $data );
 *
 * PHPStan will infer the call returns `array` rather than `mixed`.
 *
 * This assumes filters honor the documented type. A misbehaving filter could
 * return something else, but that is treated as the unusual case.
 *
 * @link https://github.com/szepeviktor/phpstan-wordpress/blob/20f0406fcb96f8e1b8369d8c0df6f5c525a761aa/src/ApplyFiltersDynamicFunctionReturnTypeExtension.php Adapted from szepeviktor/phpstan-wordpress, MIT license.
 *
 * @package WordPress
 */

declare(strict_types=1);

namespace WordPress\PHPStan;

use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\ShouldNotHappenException;
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
	 * @var HookDocBlock
	 */
	protected HookDocBlock $hookDocBlock;

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
	 * @param FunctionReflection $functionReflection Function being analyzed.
	 * @return bool
	 */
	public function isFunctionSupported( FunctionReflection $functionReflection ): bool {
		return in_array( $functionReflection->getName(), HookDocBlock::FILTER_FUNCTIONS, true );
	}

	/**
	 * Resolves the return type of the filter call from its preceding docblock.
	 *
	 * @link https://developer.wordpress.org/reference/functions/apply_filters/
	 * @link https://developer.wordpress.org/reference/functions/apply_filters_deprecated/
	 * @link https://developer.wordpress.org/reference/functions/apply_filters_ref_array/
	 *
	 * @param FunctionReflection $functionReflection Function being analyzed.
	 * @param FuncCall           $functionCall       The function call node.
	 * @param Scope              $scope              Analysis scope.
	 * @return Type
	 * @throws ShouldNotHappenException
	 */
	public function getTypeFromFunctionCall( FunctionReflection $functionReflection, FuncCall $functionCall, Scope $scope ): Type {
		$default          = new MixedType();
		$resolved_php_doc = $this->hookDocBlock->getNullableHookDocBlock( $functionCall, $scope );

		if ( null === $resolved_php_doc ) {
			return $default;
		}

		// The first `@param` describes the value being filtered.
		$params = $resolved_php_doc->getParamTags();
		$param  = reset( $params );

		return false === $param ? $default : $param->getType();
	}
}
