<?php
/**
 * PHPStan result cache metadata for the hook documentation tooling.
 *
 * The hook rules and the `apply_filters()` return type extension read two things
 * that PHPStan's dependency graph cannot see:
 *
 * 1. Canonical hook docblocks in other files, resolved through WordPress core's
 *    `/** This filter is documented in <file> *\/` convention and read with plain
 *    file I/O. A referencing file has no symbol dependency on its reference target,
 *    so editing a canonical docblock re-analyzes only the file that docblock lives
 *    in; every call site inheriting it keeps its cached result.
 * 2. The tooling's own source files. PHPStan hashes its configuration, but changing
 *    a rule's logic does not invalidate results that rule already produced.
 *
 * Both are folded into the result cache key here, so cached results are discarded
 * when — and only when — an inheritable docblock or the tooling itself changes.
 *
 * @see HookDocBlock::getReferencedHookDocsHash()
 *
 * @package WordPress
 */

declare(strict_types=1);

namespace WordPress\PHPStan;

use PHPStan\Analyser\ResultCache\ResultCacheMetaExtension;

/**
 * Invalidates the result cache when hook documentation read from another file, or
 * the tooling reading it, changes.
 */
final class HookDocsResultCacheMetaExtension implements ResultCacheMetaExtension {

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
	 * Returns the key identifying this metadata source.
	 *
	 * @return non-empty-string
	 */
	public function getKey(): string {
		return 'wordpressHookDocs';
	}

	/**
	 * Returns a hash of the inheritable hook documentation and of the tooling that
	 * reads it.
	 *
	 * @return non-falsy-string
	 */
	public function getHash(): string {
		return md5( self::getToolingHash() . '|' . $this->hookDocBlock->getReferencedHookDocsHash() );
	}

	/**
	 * Hashes the PHPStan extension sources in this directory.
	 *
	 * @return non-falsy-string
	 */
	private static function getToolingHash(): string {
		$files = glob( __DIR__ . '/*.php' );

		if ( ! is_array( $files ) ) {
			$files = array();
		}

		sort( $files );

		$parts = array();

		foreach ( $files as $file ) {
			$contents = file_get_contents( $file );

			if ( false === $contents ) {
				continue;
			}

			$parts[] = basename( $file ) . ':' . md5( $contents );
		}

		return md5( implode( '|', $parts ) );
	}
}
