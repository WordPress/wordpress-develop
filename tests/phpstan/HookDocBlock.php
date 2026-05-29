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
 *
 * Adapted from szepeviktor/phpstan-wordpress (HookDocBlock):
 * https://github.com/szepeviktor/phpstan-wordpress/blob/master/src/HookDocBlock.php
 *
 * @package WordPress
 */

declare(strict_types=1);

namespace WordPress\PHPStan;

use PhpParser\Comment\Doc;
use PhpParser\Node\Expr\FuncCall;
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
 */
class HookDocBlock {

	/**
	 * Hook functions that carry a documenting docblock for their first argument.
	 */
	private const HOOK_FUNCTIONS = array(
		'apply_filters',
		'apply_filters_deprecated',
		'apply_filters_ref_array',
		'do_action',
		'do_action_deprecated',
		'do_action_ref_array',
	);

	/**
	 * File type mapper used to resolve docblocks in scope.
	 *
	 * @var \PHPStan\Type\FileTypeMapper
	 */
	protected $fileTypeMapper;

	/**
	 * Cache of hook-name => canonical docblock text, keyed by absolute file path.
	 *
	 * @var array<string, array<string, string>>
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
	 * Resolves the canonical docblock referenced by a "This filter/action is
	 * documented in <file>" comment.
	 *
	 * @param string   $comment_text  Raw comment text preceding the hook call.
	 * @param FuncCall $function_call Hook function call node.
	 * @param Scope    $scope         Analysis scope.
	 * @return ResolvedPhpDocBlock|null Resolved canonical docblock, or null when it cannot be located.
	 */
	private function resolveDocumentedInReference( string $comment_text, FuncCall $function_call, Scope $scope ): ?ResolvedPhpDocBlock {
		if ( ! preg_match( '#This (?:filter|action) is documented in (\S+)#', $comment_text, $matches ) ) {
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

		// The canonical docblock lives in the referenced file; resolve it there so
		// any `use` imports in that file are taken into account. Hook docblocks
		// describe plain/global types, so class/trait/function context is omitted.
		return $this->fileTypeMapper->getResolvedPhpDoc(
			$target_file,
			null,
			null,
			null,
			$doc_text
		);
	}

	/**
	 * Returns the canonical docblock text for a hook defined in the given file.
	 *
	 * @param string $file      Absolute path to the file declaring the hook.
	 * @param string $hook_name Hook name to match.
	 * @return string|null Docblock text, or null when no documented invocation is found.
	 */
	private function getHookDocTextFromFile( string $file, string $hook_name ): ?string {
		if ( ! isset( $this->fileHookDocs[ $file ] ) ) {
			$this->fileHookDocs[ $file ] = self::parseHookDocs( $file );
		}

		return $this->fileHookDocs[ $file ][ $hook_name ] ?? null;
	}

	/**
	 * Parses a file and collects the docblock text for each documented hook
	 * invocation it contains.
	 *
	 * @param string $file Absolute path to the file.
	 * @return array<string, string> Map of hook name to docblock text.
	 */
	private static function parseHookDocs( string $file ): array {
		if ( ! is_file( $file ) || ! is_readable( $file ) ) {
			return array();
		}

		$code = file_get_contents( $file );
		if ( false === $code ) {
			return array();
		}

		$parser = ( new ParserFactory() )->createForHostVersion();
		$stmts  = $parser->parse( $code );
		if ( null === $stmts ) {
			return array();
		}

		// Propagate each docblock down to the nested hook-call node by line.
		$traverser = new NodeTraverser();
		$traverser->addVisitor( new HookDocsVisitor() );
		$stmts = $traverser->traverse( $stmts );

		$docs  = array();
		$calls = ( new NodeFinder() )->findInstanceOf( $stmts, FuncCall::class );
		foreach ( $calls as $call ) {
			if ( ! $call instanceof FuncCall || ! $call->name instanceof \PhpParser\Node\Name ) {
				continue;
			}

			if ( ! in_array( $call->name->toString(), self::HOOK_FUNCTIONS, true ) ) {
				continue;
			}

			$hook_name = self::getHookName( $call );
			if ( null === $hook_name || isset( $docs[ $hook_name ] ) ) {
				continue;
			}

			$doc = $call->getAttribute( 'latestDocComment' );
			// Only treat as canonical a docblock that actually documents parameters,
			// so reference comments ("documented in ...") are skipped.
			if ( $doc instanceof Doc && false !== strpos( $doc->getText(), '@param' ) ) {
				$docs[ $hook_name ] = $doc->getText();
			}
		}

		return $docs;
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

		foreach ( array( '/wp-includes/', '/wp-admin/' ) as $needle ) {
			$pos = strpos( $current_file, $needle );
			if ( false !== $pos ) {
				return substr( $current_file, 0, $pos ) . '/' . $reference_path;
			}
		}

		return null;
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