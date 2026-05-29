<?php
/**
 * Resolves the docblock that immediately precedes a hook function call (e.g.
 * `apply_filters()`) into a PHPStan `ResolvedPhpDocBlock`.
 *
 * Also resolves WordPress core's "documented elsewhere" convention, where a hook
 * is invoked under a reference comment such as:
 *
 *     /** This filter is documented in wp-includes/media.php *\/
 *     $output_formats = apply_filters( 'image_editor_output_format', ... );
 *
 * In that case the canonical docblock is looked up from the referenced file by
 * matching the hook name, and used as if it had been written at the call site.
 * Dynamic canonical hook names (e.g. `"{$type}_template_hierarchy"`) are matched
 * against the literal name used at the referencing site.
 *
 * Adapted from szepeviktor/phpstan-wordpress (HookDocBlock):
 * https://github.com/szepeviktor/phpstan-wordpress/blob/master/src/HookDocBlock.php
 *
 * @package WordPress
 */

declare(strict_types=1);

namespace WordPress\PHPStan;

use PhpParser\Comment\Doc;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\InterpolatedStringPart;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\InterpolatedString;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PHPStan\Analyser\Scope;
use PHPStan\PhpDoc\ResolvedPhpDocBlock;
use PHPStan\Type\FileTypeMapper;

/**
 * Bridges the docblock attached to a hook call by HookDocsVisitor to PHPStan's
 * docblock resolution machinery.
 *
 * @see HookDocsVisitor
 *
 * @phpstan-type HookDocs array{exact: array<string, string>, patterns: list<array{regex: string, text: string}>}
 */
class HookDocBlock {

	/**
	 * Hook functions that carry a documenting docblock for their first argument.
	 */
	public const HOOK_FUNCTIONS = array(
		'apply_filters',
		'apply_filters_deprecated',
		'apply_filters_ref_array',
		'do_action',
		'do_action_deprecated',
		'do_action_ref_array',
	);

	/**
	 * Problem code: the referenced file does not exist.
	 */
	public const PROBLEM_FILE_MISSING = 'fileMissing';

	/**
	 * Problem code: the hook is not documented in the referenced file.
	 */
	public const PROBLEM_HOOK_MISSING = 'hookMissing';

	/**
	 * Pattern matching WordPress core's "documented elsewhere" reference comment.
	 * Captures the referenced root-relative file path.
	 */
	private const REFERENCE_PATTERN = '#This (?:filter|action) is documented in (\S+)#';

	/**
	 * File type mapper used to resolve docblocks in scope.
	 *
	 * @var \PHPStan\Type\FileTypeMapper
	 */
	protected $fileTypeMapper;

	/**
	 * Cache of parsed hook documentation, keyed by absolute file path.
	 *
	 * @var array<string, HookDocs>
	 */
	private $fileHookDocs = array();

	/**
	 * Constructor.
	 *
	 * @param FileTypeMapper $file_type_mapper File type mapper.
	 */
	public function __construct( FileTypeMapper $file_type_mapper ) {
		$this->fileTypeMapper = $file_type_mapper;
	}

	/**
	 * Resolves the docblock preceding the given function call, if any.
	 *
	 * @param FuncCall $function_call Hook function call node.
	 * @param Scope    $scope         Analysis scope.
	 * @return ResolvedPhpDocBlock|null Resolved docblock, or null when none precedes the call.
	 */
	public function getNullableHookDocBlock( FuncCall $function_call, Scope $scope ): ?ResolvedPhpDocBlock {
		$comment = self::getNullableNodeComment( $function_call );

		if ( null === $comment ) {
			return null;
		}

		// Fetch the docblock contents.
		$code = $comment->getText();

		// Handle the "This filter/action is documented in <file>" convention by
		// substituting the canonical docblock from the referenced file.
		$referenced = $this->resolveDocumentedInReference( $code, $function_call, $scope );
		if ( null !== $referenced ) {
			return $referenced;
		}

		// Resolve the docblock in the current scope.
		$class_reflection = $scope->getClassReflection();
		$trait_reflection = $scope->getTraitReflection();

		return $this->fileTypeMapper->getResolvedPhpDoc(
			$scope->getFile(),
			( $scope->isInClass() && null !== $class_reflection ) ? $class_reflection->getName() : null,
			( $scope->isInTrait() && null !== $trait_reflection ) ? $trait_reflection->getName() : null,
			$scope->getFunctionName(),
			$code
		);
	}

	/**
	 * Returns the docblock preceding a hook call, classifying it as either an
	 * inline docblock or a "documented elsewhere" reference.
	 *
	 * @param FuncCall $function_call Hook function call node.
	 * @return array{type: 'inline'|'reference', text: string}|null
	 *   Null when no docblock precedes the call.
	 */
	public function getPrecedingDocBlock( FuncCall $function_call ): ?array {
		$comment = self::getNullableNodeComment( $function_call );
		if ( null === $comment ) {
			return null;
		}

		$text = $comment->getText();

		return array(
			'type' => preg_match( self::REFERENCE_PATTERN, $text ) ? 'reference' : 'inline',
			'text' => $text,
		);
	}

	/**
	 * Determines whether a hook call's name can be identified well enough to
	 * require or locate documentation.
	 *
	 * Calls whose hook name carries no literal text (e.g. the generic
	 * `apply_filters_ref_array( $hook_name, $args )` forwarders in plugin.php)
	 * cannot be meaningfully documented at the call site and are excluded.
	 *
	 * @param FuncCall $function_call Hook function call node.
	 * @return bool
	 */
	public static function hasIdentifiableHookName( FuncCall $function_call ): bool {
		$args = $function_call->getArgs();
		if ( ! isset( $args[0] ) ) {
			return false;
		}

		$value = $args[0]->value;
		if ( $value instanceof String_ ) {
			return true;
		}

		return null !== self::buildHookNameRegex( $value );
	}

	/**
	 * Validates a "documented elsewhere" reference comment preceding a hook call.
	 *
	 * Returns null when the comment is not such a reference, when the hook name is
	 * not a literal, when the WordPress root cannot be determined, or when the
	 * reference is valid. Otherwise returns the problem details.
	 *
	 * @param FuncCall $function_call Hook function call node.
	 * @param Scope    $scope         Analysis scope.
	 * @return array{path: string, hook: string, problem: string}|null
	 */
	public function getReferenceProblem( FuncCall $function_call, Scope $scope ): ?array {
		$comment = self::getNullableNodeComment( $function_call );
		if ( null === $comment ) {
			return null;
		}

		if ( ! preg_match( self::REFERENCE_PATTERN, $comment->getText(), $matches ) ) {
			return null;
		}

		$hook_name = self::getHookName( $function_call );
		if ( null === $hook_name ) {
			return null;
		}

		$reference_path = $matches[1];
		$target_file    = self::resolveReferencePath( $scope->getFile(), $reference_path );

		// The WordPress root could not be determined from the current file path;
		// skip rather than report a false positive.
		if ( null === $target_file ) {
			return null;
		}

		if ( ! is_file( $target_file ) ) {
			return array(
				'path'    => $reference_path,
				'hook'    => $hook_name,
				'problem' => self::PROBLEM_FILE_MISSING,
			);
		}

		if ( null === $this->getHookDocTextFromFile( $target_file, $hook_name ) ) {
			return array(
				'path'    => $reference_path,
				'hook'    => $hook_name,
				'problem' => self::PROBLEM_HOOK_MISSING,
			);
		}

		return null;
	}

	/**
	 * Resolves the canonical docblock referenced by a "This filter/action is
	 * documented in <file>" comment.
	 *
	 * @param string   $comment_text  Raw comment text preceding the hook call.
	 * @param FuncCall $function_call Hook function call node.
	 * @param Scope    $scope         Analysis scope.
	 * @return ResolvedPhpDocBlock|null Resolved canonical docblock, or null when it cannot be located.
	 */
	private function resolveDocumentedInReference( string $comment_text, FuncCall $function_call, Scope $scope ): ?ResolvedPhpDocBlock {
		if ( ! preg_match( self::REFERENCE_PATTERN, $comment_text, $matches ) ) {
			return null;
		}

		$hook_name = self::getHookName( $function_call );
		if ( null === $hook_name ) {
			return null;
		}

		$target_file = self::resolveReferencePath( $scope->getFile(), $matches[1] );
		if ( null === $target_file ) {
			return null;
		}

		$doc_text = $this->getHookDocTextFromFile( $target_file, $hook_name );
		if ( null === $doc_text ) {
			return null;
		}

		// Resolve the canonical docblock in the global namespace, with no file
		// context. Hook docblocks describe global/plain types (e.g. string[],
		// WP_REST_Response), so the referenced file's `use` imports are not needed.
		// Passing the referenced file here would also re-enter PHPStan's name-scope
		// builder while that file is itself being analysed, which makes
		// getResolvedPhpDoc return an empty docblock (NameScopeAlreadyBeingCreated).
		return $this->fileTypeMapper->getResolvedPhpDoc( null, null, null, null, $doc_text );
	}

	/**
	 * Returns the canonical docblock text for a hook documented in the given file.
	 *
	 * @param string $file      Absolute path to the file declaring the hook.
	 * @param string $hook_name Hook name to match.
	 * @return string|null Docblock text, or null when no documented invocation is found.
	 */
	private function getHookDocTextFromFile( string $file, string $hook_name ): ?string {
		if ( ! isset( $this->fileHookDocs[ $file ] ) ) {
			$this->fileHookDocs[ $file ] = self::parseHookDocs( $file );
		}

		$docs = $this->fileHookDocs[ $file ];

		if ( isset( $docs['exact'][ $hook_name ] ) ) {
			return $docs['exact'][ $hook_name ];
		}

		// Fall back to dynamic canonical hook names (e.g. "{$type}_foo").
		foreach ( $docs['patterns'] as $pattern ) {
			if ( preg_match( $pattern['regex'], $hook_name ) ) {
				return $pattern['text'];
			}
		}

		return null;
	}

	/**
	 * Parses a file and collects the canonical docblock text for each hook
	 * invocation it documents.
	 *
	 * A docblock is treated as canonical when it is not itself a "documented
	 * elsewhere" reference, so referencing call sites do not count as the source
	 * of documentation. Hooks with a literal name are indexed exactly; hooks with
	 * a dynamic name that contains literal text are indexed as a regex.
	 *
	 * @param string $file Absolute path to the file.
	 * @return HookDocs
	 */
	private static function parseHookDocs( string $file ): array {
		$docs = array(
			'exact'    => array(),
			'patterns' => array(),
		);

		if ( ! is_file( $file ) || ! is_readable( $file ) ) {
			return $docs;
		}

		$code = file_get_contents( $file );
		if ( false === $code ) {
			return $docs;
		}

		$parser = ( new ParserFactory() )->createForHostVersion();
		$stmts  = $parser->parse( $code );
		if ( null === $stmts ) {
			return $docs;
		}

		// Propagate each docblock down to the nested hook-call node.
		$traverser = new NodeTraverser();
		$traverser->addVisitor( new HookDocsVisitor() );
		$stmts = $traverser->traverse( $stmts );

		$seen  = array();
		$calls = ( new NodeFinder() )->findInstanceOf( $stmts, FuncCall::class );
		foreach ( $calls as $call ) {
			if ( ! $call instanceof FuncCall || ! $call->name instanceof Name ) {
				continue;
			}

			if ( ! in_array( $call->name->toString(), self::HOOK_FUNCTIONS, true ) ) {
				continue;
			}

			$args = $call->getArgs();
			if ( ! isset( $args[0] ) ) {
				continue;
			}

			$doc = $call->getAttribute( 'latestDocComment' );

			// Skip reference comments so only the canonical documentation counts.
			if ( ! $doc instanceof Doc || preg_match( self::REFERENCE_PATTERN, $doc->getText() ) ) {
				continue;
			}

			$name_expr = $args[0]->value;

			if ( $name_expr instanceof String_ ) {
				if ( ! isset( $docs['exact'][ $name_expr->value ] ) ) {
					$docs['exact'][ $name_expr->value ] = $doc->getText();
				}
				continue;
			}

			$regex = self::buildHookNameRegex( $name_expr );
			if ( null !== $regex && ! isset( $seen[ $regex ] ) ) {
				$seen[ $regex ]    = true;
				$docs['patterns'][] = array(
					'regex' => $regex,
					'text'  => $doc->getText(),
				);
			}
		}

		return $docs;
	}

	/**
	 * Builds an anchored regex matching a dynamic hook name expression, or null
	 * when the expression carries no literal text to anchor on.
	 *
	 * @param Expr $expr Hook name expression.
	 * @return string|null
	 */
	private static function buildHookNameRegex( Expr $expr ): ?string {
		$parts = self::hookNameRegexParts( $expr );
		if ( null === $parts || ! $parts[1] ) {
			return null;
		}

		return '#^' . $parts[0] . '$#';
	}

	/**
	 * Recursively converts a hook name expression into a regex fragment.
	 *
	 * @param Expr $expr Hook name expression.
	 * @return array{0: string, 1: bool}|null Fragment and whether it contains literal text, or null if unsupported.
	 */
	private static function hookNameRegexParts( Expr $expr ): ?array {
		if ( $expr instanceof String_ ) {
			return array( preg_quote( $expr->value, '#' ), true );
		}

		if ( $expr instanceof Concat ) {
			$left  = self::hookNameRegexParts( $expr->left );
			$right = self::hookNameRegexParts( $expr->right );
			if ( null === $left || null === $right ) {
				return null;
			}
			return array( $left[0] . $right[0], $left[1] || $right[1] );
		}

		if ( $expr instanceof InterpolatedString ) {
			$fragment    = '';
			$has_literal = false;
			foreach ( $expr->parts as $part ) {
				if ( $part instanceof InterpolatedStringPart ) {
					$fragment   .= preg_quote( $part->value, '#' );
					$has_literal = true;
				} else {
					$fragment .= '.+';
				}
			}
			return array( $fragment, $has_literal );
		}

		// Variables, property fetches, etc.: a wildcard with no literal anchor.
		return array( '.+', false );
	}

	/**
	 * Resolves a WordPress-root-relative reference path against the file
	 * containing the reference comment.
	 *
	 * @param string $current_file   Absolute path to the file with the reference comment.
	 * @param string $reference_path Root-relative path (e.g. "wp-includes/media.php").
	 * @return string|null Absolute path to the referenced file, or null when the root cannot be determined.
	 */
	private static function resolveReferencePath( string $current_file, string $reference_path ): ?string {
		$reference_path = ltrim( $reference_path, '/' );

		// Locate the earliest top-level WordPress directory in the current path;
		// everything before it is the WordPress root.
		$root_end = null;
		foreach ( array( '/wp-includes/', '/wp-admin/' ) as $needle ) {
			$pos = strpos( $current_file, $needle );
			if ( false !== $pos && ( null === $root_end || $pos < $root_end ) ) {
				$root_end = $pos;
			}
		}

		if ( null === $root_end ) {
			return null;
		}

		return substr( $current_file, 0, $root_end ) . '/' . $reference_path;
	}

	/**
	 * Returns the hook name (first string argument) of a hook function call.
	 *
	 * @param FuncCall $call Hook function call node.
	 * @return string|null Hook name, or null when it is not a string literal.
	 */
	private static function getHookName( FuncCall $call ): ?string {
		$args = $call->getArgs();
		if ( ! isset( $args[0] ) ) {
			return null;
		}

		$value = $args[0]->value;

		return $value instanceof String_ ? $value->value : null;
	}

	/**
	 * Returns the docblock attached to the node by HookDocsVisitor, if present.
	 *
	 * @param FuncCall $node Function call node.
	 * @return Doc|null
	 */
	private static function getNullableNodeComment( FuncCall $node ): ?Doc {
		/** @var \PhpParser\Comment\Doc|null $doc */
		$doc = $node->getAttribute( 'latestDocComment' );
		return $doc;
	}
}