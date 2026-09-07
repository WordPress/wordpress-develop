<?php
/**
 * PHPStan dynamic return type extension that types the return value of
 * `wp_parse_args()` from the arguments it was called with.
 *
 * The function's `@return array` discards everything its body computes. The body
 * ends in `array_merge( $defaults, $parsed_args )`, so a call passing a defaults
 * array that is known statically returns an array whose keys are known too:
 *
 *     wp_parse_args( $query, array( 'orderby' => 'name', 'number' => 10 ) )
 *
 * is `array{orderby: mixed, number: mixed, ...<string, mixed>}` rather than
 * `array`. Where `$args` itself carries a shape — which is common, since
 * `HashNotationVisitor` derives one from the `@type` hash documenting an `$args`
 * parameter — the merged value types survive as well.
 *
 * Nothing here assumes a caller honors the types of the defaults it overrides.
 * The three branches the body takes for `$args` are modeled as they are written,
 * and the merge itself is handed back to PHPStan's own `array_merge()` support
 * rather than reimplemented.
 *
 * @package WordPress
 */

declare(strict_types=1);

namespace WordPress\PHPStan;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Node\Expr\TypeExpr;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Type\ArrayType;
use PHPStan\Type\DynamicFunctionReturnTypeExtension;
use PHPStan\Type\MixedType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

/**
 * Resolves the return type of wp_parse_args() from its arguments.
 */
class WpParseArgsDynamicFunctionReturnTypeExtension implements DynamicFunctionReturnTypeExtension {

	/**
	 * Determines whether this extension applies to the given function.
	 *
	 * @param FunctionReflection $functionReflection Function being analyzed.
	 * @return bool
	 */
	public function isFunctionSupported( FunctionReflection $functionReflection ): bool {
		return 'wp_parse_args' === $functionReflection->getName();
	}

	/**
	 * Reproduces what the function body does to its two arguments.
	 *
	 * Returns null when `$args` says too little to model, which leaves the
	 * documented `array` in place.
	 *
	 * @link https://developer.wordpress.org/reference/functions/wp_parse_args/
	 *
	 * @param FunctionReflection $functionReflection Function being analyzed.
	 * @param FuncCall           $functionCall       The function call node.
	 * @param Scope              $scope              Analysis scope.
	 * @return Type|null
	 */
	public function getTypeFromFunctionCall( FunctionReflection $functionReflection, FuncCall $functionCall, Scope $scope ): ?Type {
		$args = $functionCall->getArgs();

		if ( array() === $args ) {
			return null;
		}

		$args_type   = $scope->getType( $args[0]->value );
		$parsed_type = $this->getParsedArgsType( $args_type );

		if ( null === $parsed_type ) {
			return null;
		}

		/*
		 * `$defaults` defaults to an empty array, which the body's `&& $defaults`
		 * rejects, so the parsed arguments are returned unmerged.
		 */
		if ( count( $args ) < 2 ) {
			return $parsed_type;
		}

		$defaults_type = $scope->getType( $args[1]->value );

		// `if ( is_array( $defaults ) && $defaults )`.
		$merges = $defaults_type->isArray()->and( $defaults_type->isIterableAtLeastOnce() );

		if ( $merges->no() ) {
			return $parsed_type;
		}

		/*
		 * The merge is PHPStan's to compute: it already knows that array_merge()
		 * renumbers integer keys, that a key the second array may hold widens the
		 * first array's value for it, and how two shapes combine. Asking it costs
		 * a synthetic call node, which is cheaper and more correct than restating
		 * any of that here.
		 *
		 * The call's own argument nodes are passed through wherever they can be,
		 * and a `TypeExpr` stands in only for an `$args` that is not already an
		 * array — the case where the type being merged is one this class derived
		 * rather than one the source wrote. Keeping the real expressions is worth
		 * the branch: PHPStan reasons about them more precisely than about a bare
		 * type, which is what keeps the parsed comment data in
		 * `WP_REST_Comments_Controller::update_item()` from losing a key it sets.
		 */
		$parsed_expr = $args_type->isArray()->yes()
			? $args[0]
			: new Arg( new TypeExpr( $parsed_type ) );

		$merged_type = $scope->getType(
			new FuncCall(
				new Name( 'array_merge' ),
				array( $args[1], $parsed_expr )
			)
		);

		if ( $merges->yes() ) {
			return $merged_type;
		}

		// Empty or non-array defaults are possible, so both branches are.
		return TypeCombinator::union( $merged_type, $parsed_type );
	}

	/**
	 * Models the array that the body derives from `$args`.
	 *
	 * @param Type $args_type Type of the `$args` argument.
	 * @return Type|null Parsed arguments, or null when `$args` is unconstrained.
	 */
	private function getParsedArgsType( Type $args_type ): ?Type {
		// A `mixed` argument constrains nothing, so the branches below would all apply.
		if ( $args_type->isSuperTypeOf( new MixedType() )->yes() ) {
			return null;
		}

		$branches = array();

		// `is_array( $args )`: the array is taken as it is, shape and all.
		foreach ( $args_type->getArrays() as $array_type ) {
			$branches[] = $array_type;
		}

		// `is_object( $args )`: get_object_vars() yields the object's properties.
		if ( ! $args_type->isObject()->no() ) {
			$branches[] = new ArrayType( new StringType(), new MixedType() );
		}

		/*
		 * Anything else goes through wp_parse_str(). Its keys are not narrowed to
		 * strings: parse_str() reads `0=a` as an integer key, and the `wp_parse_str`
		 * filter it ends with documents a plain array.
		 *
		 * A union of only arrays and objects lands here too, widening the result
		 * rather than narrowing it, which is the safe direction to be wrong in.
		 */
		if ( ! $args_type->isArray()->yes() && ! $args_type->isObject()->yes() ) {
			$branches[] = new ArrayType( new MixedType(), new MixedType() );
		}

		return array() === $branches ? null : TypeCombinator::union( ...$branches );
	}
}
