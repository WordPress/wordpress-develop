<?php
/**
 * PHPStan rule enforcing that WordPress hook invocations are documented.
 *
 * Every call to apply_filters()/do_action() (and their variants) must be
 * preceded by either:
 *
 *   - a docblock documenting the hook, or
 *   - a `/** This filter/action is documented in <file> *\/` reference comment.
 *
 * When a reference comment is used, the referenced file must exist and must
 * actually document a hook of the same name; otherwise an error is reported.
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

/**
 * Reports undocumented hooks and broken "documented elsewhere" references.
 *
 * @implements Rule<FuncCall>
 */
class HookDocumentationRule implements Rule {

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

		if ( ! in_array( $node->name->toString(), HookDocBlock::HOOK_FUNCTIONS, true ) ) {
			return array();
		}

		// Skip calls whose hook name carries no literal text, i.e. a bare variable
		// such as the generic apply_filters_ref_array( $hook_name, $args )
		// re-dispatch in plugin.php. There is no concrete hook to document or look
		// up. Calls naming a hook literally (e.g. apply_filters_ref_array( 'the_posts',
		// ... )) or dynamically with literal text (e.g. "{$type}_template_hierarchy")
		// remain subject to the documentation requirement.
		if ( ! HookDocBlock::hasIdentifiableHookName( $node ) ) {
			return array();
		}

		$function_name = $node->name->toString();
		$hook_doc      = $this->hookDocBlock->getHookDoc( $node, $scope );

		// No preceding docblock at all: the hook is undocumented.
		if ( null === $hook_doc ) {
			return array(
				RuleErrorBuilder::message(
					sprintf(
						'%s() call for hook "%s" is not preceded by a docblock documenting the hook, nor by a "This filter/action is documented in <file>" reference comment.',
						$function_name,
						HookDocBlock::getHookNameDisplay( $node )
					)
				)
					->identifier( 'wordpress.hookDocMissing' )
					->line( $node->getStartLine() )
					->build(),
			);
		}

		// An inline docblock documents the hook in place, provided it describes the
		// value being filtered.
		if ( 'reference' !== $hook_doc['kind'] ) {
			if ( ! HookDocBlock::isFilterMissingParamDocs( $node, $hook_doc ) ) {
				return array();
			}

			return array(
				RuleErrorBuilder::message(
					sprintf(
						'%s() call for hook "%s" is preceded by a docblock that documents no parameters. A filter is documented with a `@param` tag for the value being filtered, plus one for each further argument passed.',
						$function_name,
						HookDocBlock::getHookNameDisplay( $node )
					)
				)
					->identifier( 'wordpress.hookDocNoParams' )
					->line( $node->getStartLine() )
					->build(),
			);
		}

		// A reference comment must point at a file that documents this hook.
		$problem = $hook_doc['problem'];
		if ( null === $problem ) {
			return array();
		}

		if ( HookDocBlock::PROBLEM_FILE_MISSING === $problem['problem'] ) {
			return array(
				RuleErrorBuilder::message(
					sprintf(
						'%s() call for hook "%s" references documentation in "%s", but no such file exists in the tree being analyzed.',
						$function_name,
						$problem['hook'],
						$problem['path']
					)
				)
					->identifier( 'wordpress.hookDocReferenceFileMissing' )
					->line( $node->getStartLine() )
					->build(),
			);
		}

		return array(
			RuleErrorBuilder::message(
				sprintf(
					'%s() call for hook "%s" references documentation in "%s", but no documented "%s" hook is found there.',
					$function_name,
					$problem['hook'],
					$problem['path'],
					$problem['hook']
				)
			)
				->identifier( 'wordpress.hookDocReferenceHookMissing' )
				->line( $node->getStartLine() )
				->build(),
		);
	}
}
