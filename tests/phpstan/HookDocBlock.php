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
 * against the literal name used at the referencing site, provided they are
 * anchored on enough literal text to identify a hook, and the most specific match
 * wins.
 *
 * @link https://github.com/szepeviktor/phpstan-wordpress/blob/20f0406fcb96f8e1b8369d8c0df6f5c525a761aa/src/HookDocBlock.php Adapted from szepeviktor/phpstan-wordpress, MIT license.
 *
 * @see HookDocBlock::isAnchorableLiteral()
 *
 * @package WordPress
 */

declare(strict_types=1);

namespace WordPress\PHPStan;

use FilesystemIterator;
use PhpParser\Comment\Doc;
use PhpParser\Error as PhpParserError;
use PhpParser\ErrorHandler\Collecting;
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
use PHPStan\ShouldNotHappenException;
use PHPStan\Type\FileTypeMapper;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Bridges the docblock attached to a hook call by HookDocsVisitor to PHPStan's
 * docblock resolution machinery.
 *
 * @see HookDocsVisitor
 *
 * @phpstan-type HookDocs array{
 *     exact: array<string, string>,
 *     patterns: list<array{
 *         regex: non-falsy-string,
 *         literal: non-empty-string,
 *         text: string,
 *     }>,
 * }
 * @phpstan-type HookNameMatcher array{
 *     kind: 'literal'|'pattern',
 *     value: string,
 *     literal: string,
 * }
 * @phpstan-type HookDocumentationProblem array{
 *     path: non-empty-string,
 *     hook: string,
 *     problem: self::PROBLEM_FILE_MISSING|self::PROBLEM_HOOK_MISSING,
 * }
 * @phpstan-type HookDocumentation array{
 *     kind: 'inline'|'reference',
 *     resolved: ResolvedPhpDocBlock|null,
 *     paramCount: int<0, max>|null,
 *     problem: HookDocumentationProblem|null,
 * }
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
	 * Hook functions that filter a value, and so always pass at least one argument.
	 */
	public const FILTER_FUNCTIONS = array(
		'apply_filters',
		'apply_filters_deprecated',
		'apply_filters_ref_array',
	);

	/**
	 * Directories, relative to the WordPress root, scanned for reference comments
	 * when hashing the docblocks that call sites can inherit.
	 *
	 * @see HookDocBlock::getScannedFiles()
	 */
	private const SCANNED_DIRECTORIES = array(
		'wp-admin',
		'wp-includes',
		'wp-content/themes',
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
	 * @var FileTypeMapper
	 */
	protected FileTypeMapper $fileTypeMapper;

	/**
	 * In-memory cache of parsed hook documentation, keyed by absolute file path.
	 *
	 * @var array<string, HookDocs>
	 */
	private array $fileHookDocs = array();

	/**
	 * Absolute path to the WordPress root that reference comment paths resolve against.
	 *
	 * @var string
	 */
	private string $wordpressRoot;

	/**
	 * Canonical form of the WordPress root, with symlinks and dot segments resolved,
	 * against which a candidate path is tested for being inside the tree.
	 *
	 * @var string
	 */
	private string $canonicalWordpressRoot;

	/**
	 * Constructor.
	 *
	 * @param FileTypeMapper $file_type_mapper File type mapper.
	 * @param string|null    $wordpress_root   Absolute path to the WordPress root that
	 *                                         "documented in <file>" paths are relative to.
	 *                                         Defaults to the `src` directory of this checkout.
	 */
	public function __construct( FileTypeMapper $file_type_mapper, ?string $wordpress_root = null ) {
		$this->fileTypeMapper = $file_type_mapper;
		$this->wordpressRoot  = rtrim( $wordpress_root ?? dirname( __DIR__, 2 ) . '/src', '/' );

		$canonical_root               = realpath( $this->wordpressRoot );
		$this->canonicalWordpressRoot = false === $canonical_root ? $this->wordpressRoot : $canonical_root;
	}

	/**
	 * Returns a hash of every hook docblock that a call site can inherit through a
	 * "documented elsewhere" reference comment.
	 *
	 * Those docblocks are read with plain file I/O, so PHPStan's dependency graph
	 * does not know that the referencing files depend on them: editing a canonical
	 * docblock re-analyzes only the file it lives in, leaving the cached results of
	 * every referencing file in place. Folding this hash into the result cache key
	 * invalidates the cache when an inheritable docblock changes, and only then.
	 *
	 * @see HookDocsResultCacheMetaExtension
	 *
	 * @return non-falsy-string
	 */
	public function getReferencedHookDocsHash(): string {
		$docs = array();

		foreach ( $this->getScannedFiles() as $file ) {
			$code = file_get_contents( $file );

			if ( false === $code || ! str_contains( $code, 'is documented in' ) ) {
				continue;
			}

			if ( ! preg_match_all( self::REFERENCE_PATTERN, $code, $matches ) ) {
				continue;
			}

			foreach ( $matches[1] as $reference_path ) {
				$target = $this->resolveReferencePath( $file, $reference_path );

				if ( null === $target ) {
					continue;
				}

				// Key on the path relative to the WordPress root so the hash does not
				// depend on where the checkout lives.
				$key = $this->getRootRelativePath( $target );

				if ( ! isset( $docs[ $key ] ) ) {
					$docs[ $key ] = $this->getHookDocs( $target );
				}
			}
		}

		ksort( $docs );

		return md5( (string) json_encode( $docs ) );
	}

	/**
	 * Returns the files scanned for reference comments: the WordPress directories
	 * that can contain them, plus the PHP files at the root of the install.
	 *
	 * `wp-content/plugins` is deliberately not scanned. A checkout may have plugins
	 * carrying large `vendor` and `node_modules` trees, and core's reference comments
	 * only ever point within core.
	 *
	 * @return list<string> Absolute file paths.
	 */
	private function getScannedFiles(): array {
		$files = array();

		foreach ( self::SCANNED_DIRECTORIES as $directory ) {
			$path = $this->wordpressRoot . '/' . $directory;

			if ( ! is_dir( $path ) ) {
				continue;
			}

			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS )
			);

			foreach ( $iterator as $file ) {
				if ( $file instanceof SplFileInfo && $file->isFile() && 'php' === strtolower( $file->getExtension() ) ) {
					$files[] = $file->getPathname();
				}
			}
		}

		$root_files = glob( $this->wordpressRoot . '/*.php' );

		if ( is_array( $root_files ) ) {
			$files = array_merge( $files, $root_files );
		}

		return $files;
	}

	/**
	 * Expresses an absolute path relative to the WordPress root when it sits inside
	 * it, so that hashes do not depend on the checkout location.
	 *
	 * Paths reached through a reference comment are canonical, so the canonical root is
	 * what they are relative to.
	 *
	 * @param string $path Absolute path.
	 * @return string
	 */
	private function getRootRelativePath( string $path ): string {
		$prefix = $this->canonicalWordpressRoot . '/';

		return str_starts_with( $path, $prefix ) ? substr( $path, strlen( $prefix ) ) : $path;
	}

	/**
	 * Resolves the documentation for a hook call: the docblock written above it, or
	 * the canonical docblock a "documented elsewhere" comment points at.
	 *
	 * This is the one entry point the rules and the return type extension share, so
	 * all three necessarily agree on what documents a given call.
	 *
	 * A reference that cannot be resolved to a canonical docblock leaves `resolved`
	 * and `paramCount` null rather than falling back to the reference comment itself,
	 * so an unresolved reference is never mistaken for a hook documented with no
	 * parameters. `problem` says why it could not be resolved, when the reason is one
	 * worth reporting.
	 *
	 * @param FuncCall $function_call Hook function call node.
	 * @param Scope    $scope         Analysis scope.
	 * @return HookDocumentation|null Null when no docblock precedes the call.
	 * @throws ShouldNotHappenException
	 */
	public function getHookDoc( FuncCall $function_call, Scope $scope ): ?array {
		$comment = self::getNullableNodeComment( $function_call );

		if ( null === $comment ) {
			return null;
		}

		$text = $comment->getText();

		// A docblock written at the call site documents the hook in place.
		if ( ! preg_match( self::REFERENCE_PATTERN, $text, $matches ) ) {
			$resolved = $this->resolveInlineDocBlock( $text, $scope );

			return array(
				'kind'       => 'inline',
				'resolved'   => $resolved,
				'paramCount' => self::countParamTags( $resolved ),
				'problem'    => null,
			);
		}

		$hook_doc = array(
			'kind'       => 'reference',
			'resolved'   => null,
			'paramCount' => null,
			'problem'    => null,
		);

		// Without an identifiable hook name there is nothing to look up in the
		// referenced file, and so nothing to report either.
		$matcher = self::getHookNameMatcher( $function_call );
		if ( null === $matcher ) {
			return $hook_doc;
		}

		$reference_path = $matches[1];
		$target_file    = $this->resolveReferencePath( $scope->getFile(), $reference_path );

		// The referenced file could not be located up the directory tree.
		if ( null === $target_file ) {
			$hook_doc['problem'] = array(
				'path'    => $reference_path,
				'hook'    => self::getHookNameDisplay( $function_call ),
				'problem' => self::PROBLEM_FILE_MISSING,
			);

			return $hook_doc;
		}

		$doc_text = $this->findHookDoc( $target_file, $matcher );

		if ( null === $doc_text ) {
			$hook_doc['problem'] = array(
				'path'    => $reference_path,
				'hook'    => self::getHookNameDisplay( $function_call ),
				'problem' => self::PROBLEM_HOOK_MISSING,
			);

			return $hook_doc;
		}

		// Resolve the canonical docblock in the global namespace, with no file
		// context. Hook docblocks describe global/plain types (e.g. string[],
		// WP_REST_Response), so the referenced file's `use` imports are not needed.
		// Passing the referenced file here would also re-enter PHPStan's name-scope
		// builder while that file is itself being analyzed, which makes
		// getResolvedPhpDoc return an empty docblock (NameScopeAlreadyBeingCreated).
		$resolved = $this->fileTypeMapper->getResolvedPhpDoc( null, null, null, null, $doc_text );

		$hook_doc['resolved']   = $resolved;
		$hook_doc['paramCount'] = self::countParamTags( $resolved );

		return $hook_doc;
	}

	/**
	 * Resolves the docblock preceding the given function call, if any.
	 *
	 * @param FuncCall $function_call Hook function call node.
	 * @param Scope    $scope         Analysis scope.
	 * @return ResolvedPhpDocBlock|null Resolved docblock, or null when none precedes
	 *                                  the call or a reference cannot be resolved.
	 * @throws ShouldNotHappenException
	 */
	public function getNullableHookDocBlock( FuncCall $function_call, Scope $scope ): ?ResolvedPhpDocBlock {
		$hook_doc = $this->getHookDoc( $function_call, $scope );

		return null === $hook_doc ? null : $hook_doc['resolved'];
	}

	/**
	 * Resolves a docblock written at a call site, in the scope of that site.
	 *
	 * @param string $text  Docblock text.
	 * @param Scope  $scope Analysis scope.
	 * @return ResolvedPhpDocBlock
	 * @throws ShouldNotHappenException
	 */
	private function resolveInlineDocBlock( string $text, Scope $scope ): ResolvedPhpDocBlock {
		$class_reflection = $scope->getClassReflection();
		$trait_reflection = $scope->getTraitReflection();

		return $this->fileTypeMapper->getResolvedPhpDoc(
			$scope->getFile(),
			( $scope->isInClass() && null !== $class_reflection ) ? $class_reflection->getName() : null,
			( $scope->isInTrait() && null !== $trait_reflection ) ? $trait_reflection->getName() : null,
			$scope->getFunctionName(),
			$text
		);
	}

	/**
	 * Counts the `@param` tags a resolved docblock declares.
	 *
	 * ResolvedPhpDocBlock::getParamTags() is keyed by parameter name, so two tags
	 * documenting the same name — a copy-and-paste slip — collapse into a single
	 * entry. That undercounts, which both reports a hook passing the documented
	 * number of arguments as a mismatch and hides a hook that genuinely passes too
	 * few. The parsed docblock nodes list every tag, so they are counted instead.
	 *
	 * Tags PHPStan cannot parse as a `@param` — one missing its variable name, say —
	 * are still left out, so a malformed tag continues to surface rather than passing
	 * for documentation of a parameter.
	 *
	 * @param ResolvedPhpDocBlock $resolved_php_doc Resolved docblock.
	 * @return int<0, max>
	 */
	private static function countParamTags( ResolvedPhpDocBlock $resolved_php_doc ): int {
		$count = 0;

		foreach ( $resolved_php_doc->getPhpDocNodes() as $php_doc_node ) {
			$count += count( $php_doc_node->getParamTagValues() );
		}

		return $count;
	}

	/**
	 * Determines whether a filter call resolves to a docblock that documents no
	 * parameters.
	 *
	 * A filter always passes at least the value being filtered, so such a docblock
	 * does not document the hook. It is either hook documentation with its `@param`
	 * tags missing, or an unrelated annotation — typically a `@var` block — that
	 * happens to sit immediately above the call.
	 *
	 * This holds wherever the docblock was found, so no argument count is worth
	 * comparing against it. HookDocumentationRule reports it only for a docblock
	 * written at the call itself: a hook documented elsewhere is fixed where its
	 * canonical docblock lives, rather than once per site inheriting it.
	 *
	 * @param FuncCall          $function_call Hook function call node.
	 * @param HookDocumentation $hook_doc      Documentation resolved for the call.
	 * @return bool
	 */
	public static function isFilterMissingParamDocs( FuncCall $function_call, array $hook_doc ): bool {
		if ( 0 !== $hook_doc['paramCount'] ) {
			return false;
		}

		return $function_call->name instanceof Name
			&& in_array( $function_call->name->toString(), self::FILTER_FUNCTIONS, true );
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

		return null !== self::buildHookNamePattern( $value );
	}

	/**
	 * Returns the canonical docblock text for a hook documented in the given file.
	 *
	 * @param string          $file    Absolute path to the file declaring the hook.
	 * @param HookNameMatcher $matcher Hook name matcher from getHookNameMatcher().
	 * @return string|null Docblock text, or null when no documented invocation is found.
	 */
	private function findHookDoc( string $file, array $matcher ): ?string {
		$docs = $this->getHookDocs( $file );

		if ( 'literal' === $matcher['kind'] ) {
			$name = $matcher['value'];

			if ( isset( $docs['exact'][ $name ] ) ) {
				return $docs['exact'][ $name ];
			}

			// A literal name may be an instance of a dynamic canonical hook
			// (e.g. "index_template_hierarchy" matching "{$type}_template_hierarchy").
			// The most specifically anchored match wins, so a name is not attributed
			// to a loosely anchored hook that merely happens to match it as well.
			$best       = null;
			$anchor_len = -1;
			foreach ( $docs['patterns'] as $pattern ) {
				$literal_len = strlen( $pattern['literal'] );

				if ( $literal_len <= $anchor_len || ! self::isAnchorableLiteral( $pattern['literal'] ) ) {
					continue;
				}

				if ( preg_match( $pattern['regex'], $name ) ) {
					$best       = $pattern['text'];
					$anchor_len = $literal_len;
				}
			}

			return $best;
		}

		// A dynamic referencing name matches the same dynamic canonical (identical
		// regex), or a literal canonical the pattern covers.
		$regex = $matcher['value'];

		foreach ( $docs['patterns'] as $pattern ) {
			if ( $pattern['regex'] === $regex ) {
				return $pattern['text'];
			}
		}

		// Covering a literal canonical is only meaningful for a pattern anchored
		// specifically enough to identify a hook.
		if ( ! self::isAnchorableLiteral( $matcher['literal'] ) ) {
			return null;
		}

		foreach ( $docs['exact'] as $name => $text ) {
			if ( preg_match( $regex, $name ) ) {
				return $text;
			}
		}

		return null;
	}

	/**
	 * Returns the hook documentation declared by a file, parsing each file at most
	 * once per process.
	 *
	 * @param string $file Absolute path to the file.
	 * @return HookDocs
	 */
	private function getHookDocs( string $file ): array {
		if ( ! isset( $this->fileHookDocs[ $file ] ) ) {
			$this->fileHookDocs[ $file ] = self::loadHookDocs( $file );
		}

		return $this->fileHookDocs[ $file ];
	}

	/**
	 * Reads and parses the hook documentation declared by a file.
	 *
	 * @param string $file Absolute path to the file.
	 * @return HookDocs
	 */
	private static function loadHookDocs( string $file ): array {
		$empty = array(
			'exact'    => array(),
			'patterns' => array(),
		);

		if ( ! is_file( $file ) || ! is_readable( $file ) ) {
			return $empty;
		}

		$code = file_get_contents( $file );
		if ( false === $code ) {
			return $empty;
		}

		return self::parseHookDocs( $code );
	}

	/**
	 * Collects the canonical docblock text for each hook invocation documented in
	 * the given PHP source.
	 *
	 * A docblock is treated as canonical when it is not itself a "documented
	 * elsewhere" reference, so referencing call sites do not count as the source
	 * of documentation. Hooks with a literal name are indexed exactly; hooks with
	 * a dynamic name that contains literal text are indexed as a regex, alongside
	 * that literal text, which findHookDoc() uses to rank how specifically a pattern
	 * identifies a hook.
	 *
	 * @see HookDocBlock::findHookDoc()
	 *
	 * @param string $code PHP source code.
	 * @return HookDocs
	 */
	private static function parseHookDocs( string $code ): array {
		$docs = array(
			'exact'    => array(),
			'patterns' => array(),
		);

		// Source that cannot be parsed documents nothing this can read, and must not stop
		// the analysis: a file is temporarily incomplete while it is being edited, and may
		// use syntax the host PHP version does not know. Collecting the parse errors
		// rather than throwing keeps the hooks documented ahead of the error wherever the
		// parser can recover, and yields none where it cannot.
		$parser = ( new ParserFactory() )->createForHostVersion();

		try {
			$stmts = $parser->parse( $code, new Collecting() );
		} catch ( PhpParserError $parse_error ) {
			return $docs;
		}

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

			$pattern = self::buildHookNamePattern( $name_expr );
			if ( null !== $pattern && ! isset( $seen[ $pattern['regex'] ] ) ) {
				$seen[ $pattern['regex'] ] = true;
				$docs['patterns'][]        = array(
					'regex'   => $pattern['regex'],
					'literal' => $pattern['literal'],
					'text'    => $doc->getText(),
				);
			}
		}

		return $docs;
	}

	/**
	 * Builds an anchored regex matching a dynamic hook name expression, together
	 * with the literal text it is anchored on, or null when the expression carries
	 * no literal text at all.
	 *
	 * @param Expr $expr Hook name expression.
	 * @return array{
	 *     regex: non-falsy-string,
	 *     literal: non-empty-string,
	 * }|null
	 */
	private static function buildHookNamePattern( Expr $expr ): ?array {
		$parts = self::hookNameRegexParts( $expr );
		if ( null === $parts || '' === $parts[1] ) {
			return null;
		}

		return array(
			'regex'   => '#^' . $parts[0] . '$#',
			'literal' => $parts[1],
		);
	}

	/**
	 * Determines whether the literal text of a dynamic hook name identifies a hook
	 * specifically enough to resolve documentation through it.
	 *
	 * A name whose literal text is nothing but separators — e.g. taxonomy.php's
	 * `"{$taxonomy}_{$field}"`, which becomes `#^.+_.+$#` — matches almost any hook
	 * name, so honoring it would attribute a call to an unrelated hook's
	 * documentation and hide a genuinely broken reference comment. The hook is still
	 * required to be documented at its own call site; only its use as the
	 * documentation *source* for a differently named hook is refused.
	 *
	 * @param string $literal Concatenated literal text of a hook name expression.
	 * @return bool
	 */
	private static function isAnchorableLiteral( string $literal ): bool {
		return '' !== trim( $literal, "-_ \t\n\r\0\x0B" );
	}

	/**
	 * Recursively converts a hook name expression into a regex fragment and the
	 * literal text that fragment is anchored on.
	 *
	 * @param Expr $expr Hook name expression.
	 * @return array{
	 *     0: string,
	 *     1: string,
	 * }|null Fragment and its literal text, or null if unsupported.
	 */
	private static function hookNameRegexParts( Expr $expr ): ?array {
		if ( $expr instanceof String_ ) {
			return array( preg_quote( $expr->value, '#' ), $expr->value );
		}

		if ( $expr instanceof Concat ) {
			$left  = self::hookNameRegexParts( $expr->left );
			$right = self::hookNameRegexParts( $expr->right );
			if ( null === $left || null === $right ) {
				return null;
			}
			return array( $left[0] . $right[0], $left[1] . $right[1] );
		}

		if ( $expr instanceof InterpolatedString ) {
			$fragment = '';
			$literal  = '';
			foreach ( $expr->parts as $part ) {
				if ( $part instanceof InterpolatedStringPart ) {
					$fragment .= preg_quote( $part->value, '#' );
					$literal  .= $part->value;
				} else {
					$fragment .= '.+';
				}
			}
			return array( $fragment, $literal );
		}

		// Variables, property fetches, etc.: a wildcard with no literal anchor.
		return array( '.+', '' );
	}

	/**
	 * Resolves a WordPress-root-relative reference path against the file
	 * containing the reference comment.
	 *
	 * The reference comment names the exact file (e.g. "wp-includes/media.php"), so
	 * resolution proceeds in two steps:
	 *
	 * 1. Walk up from the current file's directory, as far as the WordPress root,
	 *    until the relative path resolves to a real file inside the tree. This works
	 *    regardless of where in the tree the referencing file lives (core, a bundled
	 *    theme, the install root, ...), and also resolves the sibling references used
	 *    by the bundled themes (e.g. "author.php"). The walk stops at the root because
	 *    a path that only resolves above the tree under analysis is a coincidence
	 *    rather than the file the comment names.
	 * 2. Fall back to the WordPress root. Step 1 assumes the analysed file sits in
	 *    its real location, which does not hold when an IDE runs PHPStan against a
	 *    temporary copy of the editor buffer. Without this fallback, every
	 *    reference comment in such a copy is reported as naming a missing file.
	 *
	 * Only the single named file is ever tested; no directory is enumerated.
	 *
	 * @param string $current_file   Absolute path to the file with the reference comment.
	 * @param string $reference_path Root-relative path (e.g. "wp-includes/media.php").
	 * @return string|null Absolute path to the referenced file, or null when it cannot be located.
	 */
	private function resolveReferencePath( string $current_file, string $reference_path ): ?string {
		$reference_path = ltrim( $reference_path, '/' );
		$dir            = dirname( $current_file );

		while ( $dir === $this->wordpressRoot || str_starts_with( $dir, $this->wordpressRoot . '/' ) ) {
			$target = $this->resolveWithinRoot( $dir . '/' . $reference_path );
			if ( null !== $target ) {
				return $target;
			}

			// The root has just been tested, so the walk is done.
			if ( $dir === $this->wordpressRoot ) {
				return null;
			}

			$dir = dirname( $dir );
		}

		// The file holding the comment is not in the tree, so resolve against the root.
		return $this->resolveWithinRoot( $this->wordpressRoot . '/' . $reference_path );
	}

	/**
	 * Canonicalizes a candidate path, accepting it only when it is a file inside the
	 * WordPress tree.
	 *
	 * A reference path is usually a plain relative path, but the convention is also
	 * written with dot segments relative to the file holding the comment: WooCommerce
	 * references `../wc-user-functions.php` and MainWP `../widgets/…`, so those have to
	 * keep resolving. WordPress core has never used that form, which is exactly why
	 * rejecting dot segments outright would look harmless here and break those plugins.
	 *
	 * Canonicalizing the candidate and requiring the result to be inside the tree keeps
	 * them working while ensuring a reference cannot reach a file outside it. That
	 * matters because whatever resolution finds is then read and parsed.
	 *
	 * @param string $candidate Absolute candidate path, possibly containing dot segments.
	 * @return string|null Canonical path, or null when it is not a file inside the tree.
	 */
	private function resolveWithinRoot( string $candidate ): ?string {
		if ( ! is_file( $candidate ) ) {
			return null;
		}

		$canonical = realpath( $candidate );

		if ( false === $canonical ) {
			return null;
		}

		return str_starts_with( $canonical, $this->canonicalWordpressRoot . '/' ) ? $canonical : null;
	}

	/**
	 * Returns a matcher describing a hook call's name: a literal string to look up
	 * exactly, or a regex for a dynamic name (e.g. "{$type}_template_hierarchy").
	 *
	 * @param FuncCall $call Hook function call node.
	 * @return HookNameMatcher|null Null when the name carries no identifiable text
	 *                             (e.g. a bare variable).
	 */
	private static function getHookNameMatcher( FuncCall $call ): ?array {
		$args = $call->getArgs();
		if ( ! isset( $args[0] ) ) {
			return null;
		}

		$expr = $args[0]->value;

		if ( $expr instanceof String_ ) {
			return array(
				'kind'    => 'literal',
				'value'   => $expr->value,
				'literal' => $expr->value,
			);
		}

		$pattern = self::buildHookNamePattern( $expr );
		if ( null !== $pattern ) {
			return array(
				'kind'    => 'pattern',
				'value'   => $pattern['regex'],
				'literal' => $pattern['literal'],
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
	public static function getHookNameDisplay( FuncCall $call ): string {
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
		/** @var Doc|null $doc */
		$doc = $node->getAttribute( 'latestDocComment' );
		return $doc;
	}
}
