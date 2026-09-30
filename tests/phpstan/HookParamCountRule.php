<?php
/**
 * PHPStan rule ensuring a hook invocation passes as many arguments as its
 * documentation describes.
 *
 * WordPress passes a hook's arguments straight through to its callbacks, so a
 * mismatch between the number of `@param` tags documenting a hook and the number
 * of arguments a `do_action()`/`apply_filters()` call actually provides is a real
 * defect: a callback registered for the documented argument count (e.g.
 * `add_action( 'hook', $cb, 10, 3 )` with a three-required-argument callback)
 * triggers an `ArgumentCountError` when the hook fires with fewer arguments.
 *
 * The documentation is resolved the same way as for the return-type extension,
 * so a hook documented elsewhere via a "This filter is documented in <file>"
 * reference is checked against its canonical docblock.
 *
 * @package WordPress
 */

declare(strict_types=1);

namespace WordPress\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\ShouldNotHappenException;
use PHPStan\Type\Constant\ConstantIntegerType;

/**
 * Reports hook invocations whose argument count does not match the number of
 * documented parameters.
 *
 * @implements Rule<FuncCall>
 */
class HookParamCountRule implements Rule {

	/**
	 * Hook functions that receive the hook arguments as variadic parameters.
	 */
	private const VARIADIC_FUNCTIONS = array(
		'apply_filters',
		'do_action',
	);

	/**
	 * Hook functions that receive the hook arguments as an array in their second
	 * parameter.
	 */
	private const ARRAY_ARG_FUNCTIONS = array(
		'apply_filters_ref_array',
		'apply_filters_deprecated',
		'do_action_ref_array',
		'do_action_deprecated',
	);

	/**
	 * Hook docblock resolver.
	 *
	 * @var HookDocBlock
	 */
	private HookDocBlock $hookDocBlock;

	/**
	 * Constructor.
	 *
	 * @param HookDocBlock $hook_doc_block Hook docblock resolver.
	 */
	public function __construct( HookDocBlock $hook_doc_block ) {
		$this->hookDocBlock = $hook_doc_block;
	}

	/**
	 * Returns the node type this rule processes.
	 *
	 * @return string
	 */
	public function getNodeType(): string {
		return FuncCall::class;
	}

	/**
	 * Processes a function call node.
	 *
	 * @param Node  $node  Function call node.
	 * @param Scope $scope Analysis scope.
	 * @return list<IdentifierRuleError>
	 * @throws ShouldNotHappenException
	 */
	public function processNode( Node $node, Scope $scope ): array {
		if ( ! $node instanceof FuncCall || ! $node->name instanceof Name ) {
			return array();
		}

		$function_name = $node->name->toString();
		$is_variadic   = in_array( $function_name, self::VARIADIC_FUNCTIONS, true );
		if ( ! $is_variadic && ! in_array( $function_name, self::ARRAY_ARG_FUNCTIONS, true ) ) {
			return array();
		}

		// Without an identifiable hook name there is nothing to document or look up.
		if ( ! HookDocBlock::hasIdentifiableHookName( $node ) ) {
			return array();
		}

		// Only compare against documentation that actually resolves. Missing docs and
		// unresolvable/broken references (reported by HookDocumentationRule) leave the
		// documented count unknown, and are skipped rather than compared against a
		// bogus zero count.
		$hook_doc = $this->hookDocBlock->getHookDoc( $node, $scope );
		if ( null === $hook_doc || null === $hook_doc['paramCount'] ) {
			return array();
		}

		// A filter whose docblock documents no parameters is not documented at all,
		// which HookDocumentationRule reports. Comparing counts as well would report
		// one defect twice.
		if ( HookDocBlock::isFilterMissingParamDocs( $node, $hook_doc ) ) {
			return array();
		}

		$documented = $hook_doc['paramCount'];

		$provided = $is_variadic
			? self::countVariadicArguments( $node, $scope )
			: self::countArrayArguments( $node, $scope );

		// The provided count could not be determined statically; skip rather than
		// guess (e.g. arguments spread from a variable of unknown size).
		if ( null === $provided || $provided === $documented ) {
			return array();
		}

		$hook_name = HookDocBlock::getHookNameDisplay( $node );

		// An action documented without any `@param` tag reads better as a statement
		// about its docblock than as a count of zero.
		$message = 0 === $documented
			? sprintf(
				'%s() for hook "%s" provides %d argument%s, but its docblock documents no parameters.',
				$function_name,
				$hook_name,
				$provided,
				1 === $provided ? '' : 's'
			)
			: sprintf(
				'%s() for hook "%s" provides %d argument%s, but the hook is documented with %d parameter%s.',
				$function_name,
				$hook_name,
				$provided,
				1 === $provided ? '' : 's',
				$documented,
				1 === $documented ? '' : 's'
			);

		return array(
			RuleErrorBuilder::message( $message )
				->identifier( 'wordpress.hookParamCountMismatch' )
				->line( $node->getStartLine() )
				->build(),
		);
	}

	/**
	 * Counts the arguments a variadic hook call passes after the hook name.
	 *
	 * @param FuncCall $node  Hook function call node.
	 * @param Scope    $scope Analysis scope.
	 * @return int|null Argument count, or null when it cannot be determined statically.
	 */
	private static function countVariadicArguments( FuncCall $node, Scope $scope ): ?int {
		$args  = $node->getArgs();
		$count = 0;

		// Skip index 0, the hook name.
		for ( $i = 1, $len = count( $args ); $i < $len; $i++ ) {
			$arg = $args[ $i ];

			if ( $arg->unpack ) {
				$size = $scope->getType( $arg->value )->getArraySize();
				if ( ! $size instanceof ConstantIntegerType ) {
					return null;
				}
				$count += $size->getValue();
				continue;
			}

			++$count;
		}

		return $count;
	}

	/**
	 * Counts the arguments a hook call passes via its array argument.
	 *
	 * @param FuncCall $node  Hook function call node.
	 * @param Scope    $scope Analysis scope.
	 * @return int|null Argument count, or null when it cannot be determined statically.
	 */
	private static function countArrayArguments( FuncCall $node, Scope $scope ): ?int {
		$args = $node->getArgs();
		if ( ! isset( $args[1] ) ) {
			return null;
		}

		$size = $scope->getType( $args[1]->value )->getArraySize();
		if ( ! $size instanceof ConstantIntegerType ) {
			return null;
		}

		return $size->getValue();
	}
}
