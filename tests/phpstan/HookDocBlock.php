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
use PhpParser\Node\Expr\Variable;
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
	 * Returns the number of parameters documented for a hook call, resolving the
	 * docblock the same way as the return-type extension (inline or via a
	 * "documented in" reference).
	 *
	 * Unlike getNullableHookDocBlock(), this does NOT fall back to the reference
	 * comment when a reference cannot be resolved to a canonical docblock; it
	 * returns null instead, so callers do not mistake an unresolved reference
	 * (which has no `@param` tags) for a genuine zero-parameter hook.
	 *
	 * @param FuncCall $function_call Hook function call node.
	 * @param Scope    $scope         Analysis scope.
	 * @return int|null Documented parameter count, or null when there is no
	 *                  docblock or a reference cannot be resolved.
	 */
	public function getDocumentedParamCount( FuncCall $function_call, Scope $scope ): ?int {
		$comment = self::getNullableNodeComment( $function_call );
		if ( null === $comment ) {
			return null;
		}

		$code = $comment->getText();

		if ( preg_match( self::REFERENCE_PATTERN, $code ) ) {
			$referenced = $this->resolveDocumentedInReference( $code, $function_call, $scope );
			if ( null === $referenced ) {
				return null;
			}
			return count( $referenced->getParamTags() );
		}

		$class_reflection = $scope->getClassReflection();
		$trait_reflection = $scope->getTraitReflection();

		$resolved = $this->fileTypeMapper->getResolvedPhpDoc(
			$scope->getFile(),
			( $scope->isInClass() && null !== $class_reflection ) ? $class_reflection->getName() : null,
			( $scope->isInTrait() && null !== $trait_reflection ) ? $trait_reflection->getName() : null,
			$scope->getFunctionName(),
			$code
		);

		return count( $resolved->getParamTags() );
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

		$matcher = self::getHookNameMatcher( $function_call );
		if ( null === $matcher ) {
			return null;
		}

		$reference_path = $matches[1];
		$target_file    = self::resolveReferencePath( $scope->getFile(), $reference_path );

		// The referenced file could not be located up the directory tree.
		if ( null === $target_file ) {
			return array(
				'path'    => $reference_path,
				'hook'    => self::getHookNameDisplay( $function_call ),
				'problem' => self::PROBLEM_FILE_MISSING,
			);
		}

		if ( null === $this->findHookDoc( $target_file, $matcher ) ) {
			return array(
				'path'    => $reference_path,
				'hook'    => self::getHookNameDisplay( $function_call ),
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

		$matcher = self::getHookNameMatcher( $function_call );
		if ( null === $matcher ) {
			return null;
		}

		$target_file = self::resolveReferencePath( $scope->getFile(), $matches[1] );
		if ( null === $target_file ) {
			return null;
		}

		$doc_text = $this->findHookDoc( $target_file, $matcher );
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
	 * @param string                                       $file    Absolute path to the file declaring the hook.
	 * @param array{kind: 'literal'|'pattern', value: string} $matcher Hook name matcher from getHookNameMatcher().
	 * @return string|null Docblock text, or null when no documented invocation is found.
	 */
	private function findHookDoc( string $file, array $matcher ): ?string {
		if ( ! isset( $this->fileHookDocs[ $file ] ) ) {
			$this->fileHookDocs[ $file ] = self::parseHookDocs( $file );
		}

		$docs = $this->fileHookDocs[ $file ];

		if ( 'literal' === $matcher['kind'] ) {
			$name = $matcher['value'];

			if ( isset( $docs['exact'][ $name ] ) ) {
				return $docs['exact'][ $name ];
			}

			// A literal name may be an instance of a dynamic canonical hook
			// (e.g. "index_template_hierarchy" matching "{$type}_template_hierarchy").
			foreach ( $docs['patterns'] as $pattern ) {
				if ( preg_match( $pattern['regex'], $name ) ) {
					return $pattern['text'];
				}
			}

			return null;
		}

		// A dynamic referencing name matches the same dynamic canonical (identical
		// regex), or a literal canonical the pattern covers.
		$regex = $matcher['value'];

		foreach ( $docs['patterns'] as $pattern ) {
			if ( $pattern['regex'] === $regex ) {
				return $pattern['text'];
			}
		}

		foreach ( $docs['exact'] as $name => $text ) {
			if ( preg_match( $regex, $name ) ) {
				return $text;
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
	 * The reference comment names the exact file (e.g. "wp-includes/media.php"), so
	 * resolution simply walks up from the current file's directory until that
	 * relative path resolves to a real file. This works regardless of where the
	 * referencing file lives (core, a bundled theme, the install root, ...) and
	 * only ever touches the single named file — no directory is enumerated.
	 *
	 * @param string $current_file   Absolute path to the file with the reference comment.
	 * @param string $reference_path Root-relative path (e.g. "wp-includes/media.php").
	 * @return string|null Absolute path to the referenced file, or null when it cannot be located.
	 */
	private static function resolveReferencePath( string $current_file, string $reference_path ): ?string {
		$reference_path = ltrim( $reference_path, '/' );
		$dir            = dirname( $current_file );

		while ( true ) {
			$candidate = $dir . '/' . $reference_path;
			if ( is_file( $candidate ) ) {
				return $candidate;
			}

			$parent = dirname( $dir );
			if ( $parent === $dir ) {
				return null;
			}
			$dir = $parent;
		}
	}

	/**
	 * Returns a matcher describing a hook call's name: a literal string to look up
	 * exactly, or a regex for a dynamic name (e.g. "{$type}_template_hierarchy").
	 *
	 * @param FuncCall $call Hook function call node.
	 * @return array{kind: 'literal'|'pattern', value: string}|null
	 *   Null when the name carries no identifiable text (e.g. a bare variable).
	 */
	private static function getHookNameMatcher( FuncCall $call ): ?array {
		$args = $call->getArgs();
		if ( ! isset( $args[0] ) ) {
			return null;
		}

		$expr = $args[0]->value;

		if ( $expr instanceof String_ ) {
			return array(
				'kind'  => 'literal',
				'value' => $expr->value,
			);
		}

		$regex = self::buildHookNameRegex( $expr );
		if ( null !== $regex ) {
			return array(
				'kind'  => 'pattern',
				'value' => $regex,
			);
		}

		return null;
	}

	/**
	 * Renders a hook name expression to a readable string for diagnostics, e.g.
	 * "default_option_{$option}".
	 *
	 * @param FuncCall $call Hook function call node.
	 * @return string
	 */
	private static function getHookNameDisplay( FuncCall $call ): string {
		$args = $call->getArgs();
		if ( ! isset( $args[0] ) ) {
			return '';
		}

		return self::renderHookName( $args[0]->value );
	}

	/**
	 * Recursively renders a hook name expression to a readable string.
	 *
	 * @param Expr $expr Hook name expression.
	 * @return string
	 */
	private static function renderHookName( Expr $expr ): string {
		if ( $expr instanceof String_ ) {
			return $expr->value;
		}

		if ( $expr instanceof Concat ) {
			return self::renderHookName( $expr->left ) . self::renderHookName( $expr->right );
		}

		if ( $expr instanceof InterpolatedString ) {
			$out = '';
			foreach ( $expr->parts as $part ) {
				if ( $part instanceof InterpolatedStringPart ) {
					$out .= $part->value;
				} elseif ( $part instanceof Variable && is_string( $part->name ) ) {
					$out .= '{$' . $part->name . '}';
				} else {
					$out .= '{...}';
				}
			}
			return $out;
		}

		if ( $expr instanceof Variable && is_string( $expr->name ) ) {
			return '$' . $expr->name;
		}

		return '...';
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