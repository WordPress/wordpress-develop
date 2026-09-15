<?php
/**
 * PHPStan dynamic return type extension that types the return value of
 * `wp_parse_args()` from the arguments it was called with.
 *
 * The documented return type describes what the function promises for every call.
 * The body ends in `array_merge( $defaults, $parsed_args )`, so a particular call
 * passing a defaults array that is known statically returns an array whose keys
 * are known too:
 *
 *     wp_parse_args( $query, array( 'orderby' => 'name', 'number' => 10 ) )
 *
 * is `array{orderby: mixed, number: mixed, ...<string, mixed>}` rather than
 * `array<array-key, mixed>`. Where `$args` itself carries a shape — which is
 * common, since `HashNotationVisitor` derives one from the `@type` hash
 * documenting an `$args` parameter — the merged value types survive as well.
 *
 * Nothing here assumes a caller honors the types of the defaults it overrides.
 * The branches the body takes for `$args` are modeled as they are written, and
 * both the merge and the object conversion are handed back to PHPStan's own
 * support for `array_merge()` and `get_object_vars()` rather than reimplemented.
 *
 * @link https://github.com/szepeviktor/phpstan-wordpress/pull/309 The object handling below is adapted from szepeviktor/phpstan-wordpress, MIT license.
 *
 * @package WordPress
 */

declare(strict_types=1);

namespace WordPress\PHPStan;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Node\Expr\TypeExpr;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Type\ArrayType;
use PHPStan\Type\DynamicFunctionReturnTypeExtension;
use PHPStan\Type\MixedType;
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
	 * Returns null when the arguments say too little to model, which leaves the
	 * documented return type in place.
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

		// An unpacked argument is not matched up with the parameter it lands on.
		foreach ( $args as $arg ) {
			if ( $arg->unpack ) {
				return null;
			}
		}

		$parsed_expr = $this->getParsedArgsExpr( $args[0]->value, $scope );

		if ( null === $parsed_expr ) {
			return null;
		}

		$parsed_type = $scope->getType( $parsed_expr );

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
		 * first array's value for it, and how two shapes combine.
		 */
		$merged_type = $scope->getType(
			new FuncCall(
				new Name( 'array_merge' ),
				array( $args[1], new Arg( $parsed_expr ) )
			)
		);

		if ( $merges->yes() ) {
			return $merged_type;
		}

		// Empty or non-array defaults are possible, so both branches are.
		return TypeCombinator::union( $merged_type, $parsed_type );
	}

	/**
	 * Builds the expression whose type is the array the body derives from `$args`.
	 *
	 * @param Expr  $args_expr Expression passed as `$args`.
	 * @param Scope $scope     Analysis scope.
	 * @return Expr|null Expression to merge the defaults into, or null when `$args`
	 *                   is too unconstrained to model.
	 */
	private function getParsedArgsExpr( Expr $args_expr, Scope $scope ): ?Expr {
		$args_type = $scope->getType( $args_expr );

		// `is_array( $args )`: the array is taken as it is, shape and all.
		if ( $args_type->isArray()->yes() ) {
			return $args_expr;
		}

		if ( $args_type->isObject()->yes() ) {
			/*
			 * `is_object( $args )`: get_object_vars() decides both which properties
			 * come back and what their keys are, so the call is handed to PHPStan
			 * rather than described here.
			 *
			 * It is only safe to synthesize outside class scope. wp_parse_args() is a
			 * global function, so the get_object_vars() inside it sees public properties
			 * only, while a call resolved in the caller's scope sees the private and
			 * protected ones too.
			 */
			if ( $scope->isInClass() ) {
				return null;
			}

			return new FuncCall( new Name( 'get_object_vars' ), array( new Arg( $args_expr ) ) );
		}

		// An object remains possible, so neither branch above can be relied on.
		if ( ! $args_type->isObject()->no() ) {
			return null;
		}

		/*
		 * What is left is the wp_parse_str() branch, on its own or beside an array.
		 * Its keys are not narrowed to strings: parse_str() reads `0=a` as an integer
		 * key, and the `wp_parse_str` filter it ends with documents a plain array.
		 */
		$branches   = $args_type->getArrays();
		$branches[] = new ArrayType( new MixedType(), new MixedType() );

		return new TypeExpr( TypeCombinator::union( ...$branches ) );
	}
}
