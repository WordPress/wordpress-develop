<?php
/**
 * PHPStan extension limiting the errors reported to a list of paths.
 *
 * The configuration in `phpstan-level-10.neon.dist` analyzes the code at a
 * stricter rule level than `phpstan.neon.dist` does, but only holds part of the
 * codebase to it. PHPStan has no way to set a rule level per path, and narrowing
 * the analyzed `paths` instead would change the types the analysis sees:
 * the parser node visitors in this directory, such as HashNotationVisitor, only
 * rewrite the docblocks of files that are analyzed, so every function outside
 * the narrowed paths would be read without them.
 *
 * So the stricter analysis covers every path the default configuration does,
 * and this extension drops each error reported outside the paths it lists.
 *
 * @package WordPress
 */

declare(strict_types=1);

namespace WordPress\PHPStan;

use InvalidArgumentException;
use PhpParser\Node;
use PHPStan\Analyser\Error;
use PHPStan\Analyser\IgnoreErrorExtension;
use PHPStan\Analyser\Scope;

/**
 * Ignores every error reported outside a list of paths.
 */
final class StrictPathsIgnoreErrorExtension implements IgnoreErrorExtension {

	/**
	 * Absolute paths of the directories whose files are reported, each with a trailing slash.
	 *
	 * @var list<non-empty-string>
	 */
	private array $directories = array();

	/**
	 * Absolute paths of the individual files that are reported, as keys.
	 *
	 * @var array<non-empty-string, true>
	 */
	private array $files = array();

	/**
	 * Constructor.
	 *
	 * @param string[] $paths Paths relative to the repository root. A path ending in a slash is a
	 *                        directory, which covers every file below it. Any other path is a file.
	 *
	 * @throws InvalidArgumentException When a path does not exist, so that a renamed or deleted path
	 *                                  cannot silently stop being checked.
	 */
	public function __construct( array $paths ) {
		$root = self::normalize( dirname( __DIR__, 2 ) ) . '/';

		foreach ( $paths as $path ) {
			$relative = ltrim( self::normalize( $path ), '/' );
			$absolute = $root . $relative;

			if ( str_ends_with( $relative, '/' ) ) {
				if ( ! is_dir( $absolute ) ) {
					throw new InvalidArgumentException( "The strict path \"$path\" is not a directory." );
				}
				$this->directories[] = $absolute;
			} else {
				if ( ! is_file( $absolute ) ) {
					throw new InvalidArgumentException( "The strict path \"$path\" is not a file. End it with a slash if it is a directory." );
				}
				$this->files[ $absolute ] = true;
			}
		}
	}

	/**
	 * Determines whether an error is outside the listed paths, and so ignored.
	 *
	 * The path checked is the file the code reported on is in, which for an error in a trait is
	 * the trait's file rather than that of the class using it.
	 *
	 * @param Error $error Error reported.
	 * @param Node  $node  Node the error was reported on.
	 * @param Scope $scope Scope of the node.
	 * @return bool Whether to ignore the error.
	 */
	public function shouldIgnore( Error $error, Node $node, Scope $scope ): bool {
		$file = self::normalize( $error->getFilePath() );

		if ( isset( $this->files[ $file ] ) ) {
			return false;
		}

		foreach ( $this->directories as $directory ) {
			if ( str_starts_with( $file, $directory ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Normalizes the directory separators of a path to forward slashes.
	 *
	 * @param string $path Path.
	 * @return string Normalized path.
	 */
	private static function normalize( string $path ): string {
		return str_replace( '\\', '/', $path );
	}
}
