<?php
/**
 * Tests for HashNotationVisitor.
 *
 * @package WordPress
 */

declare(strict_types=1);

namespace WordPress\PHPStan\Tests;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use WordPress\PHPStan\HashNotationVisitor;

/**
 * Checks the `@phpstan-*` tags the visitor derives from a docblock.
 *
 * The visitor rewrites docblocks in the syntax tree, so each case parses a snippet,
 * runs the visitor over it and compares the lines the docblock gained. The types
 * PHPStan then infers from those lines are covered by HashNotationTypeTest.
 */
final class HashNotationVisitorTest extends TestCase {

	/**
	 * @dataProvider data_derived_tags
	 *
	 * @param string       $code     PHP source.
	 * @param list<string> $expected Tag lines the docblocks are expected to gain.
	 */
	public function test_derived_tags( string $code, array $expected ): void {
		$this->assertSame( $expected, self::derived_tags( $code ) );
	}

	/**
	 * @return array<string, array{string, list<string>}>
	 */
	public function data_derived_tags(): array {
		return array(
			'param hash is open with optional keys'       => array(
				self::fn(
					'
					 * @param array $args {
					 *     Arguments.
					 *
					 *     @type string $post_type   Post type. Default \'post\'.
					 *     @type int    $post_author Post author ID.
					 * }
					'
				),
				array( '@phpstan-param array{post_type?: string, post_author?: int, ...} $args' ),
			),
			'return hash is sealed with required keys'    => array(
				self::fn(
					'
					 * @return array {
					 *     @type string $name  Name.
					 *     @type int    $count Count. Default 0.
					 * }
					'
				),
				array( '@phpstan-return array{name: string, count: int}' ),
			),
			'numbered keys are required in a param hash'  => array(
				self::fn(
					'
					 * @param array $size {
					 *     @type int $0 Width.
					 *     @type int $1 Height.
					 * }
					',
					'( $size )'
				),
				array( '@phpstan-param array{0: int, 1: int, ...} $size' ),
			),
			'Optional. marks a return key optional'       => array(
				self::fn(
					'
					 * @return array {
					 *     @type string $name  Name.
					 *     @type int    $count Optional. Count.
					 *     @type string $slash Optional self closing slash, which is prose.
					 * }
					'
				),
				array( '@phpstan-return array{name: string, count?: int, slash: string}' ),
			),
			'Optional. marks a numbered param key optional' => array(
				self::fn(
					'
					 * @param array $size {
					 *     @type int $0 Width.
					 *     @type int $1 Optional. Height.
					 * }
					',
					'( $size )'
				),
				array( '@phpstan-param array{0: int, 1?: int, ...} $size' ),
			),
			'a lone variadic entry describes the values'  => array(
				self::fn(
					'
					 * @param array $callbacks {
					 *     @type callable ...$0 Callbacks.
					 * }
					',
					'( $callbacks )'
				),
				array( '@phpstan-param array<array-key, callable> $callbacks' ),
			),
			'a variadic entry beside named keys opens a return hash' => array(
				self::fn(
					'
					 * @return array {
					 *     @type string $name Name.
					 *     @type mixed  ...$1 The rest.
					 * }
					'
				),
				array( '@phpstan-return array{name: string, ...}' ),
			),
			'nested hashes nest'                          => array(
				self::fn(
					'
					 * @param array $args {
					 *     @type array $labels {
					 *         @type string $name     Name.
					 *         @type string $singular Singular name.
					 *     }
					 *     @type bool $public Whether public.
					 * }
					'
				),
				array( '@phpstan-param array{labels?: array{name?: string, singular?: string, ...}, public?: bool, ...} $args' ),
			),
			'a nested hash in a return is sealed too'     => array(
				self::fn(
					'
					 * @return array {
					 *     @type array $labels {
					 *         @type string $name Name.
					 *     }
					 * }
					'
				),
				array( '@phpstan-return array{labels: array{name: string}}' ),
			),
			'keys that are not identifiers are quoted'    => array(
				self::fn(
					'
					 * @return array {
					 *     @type string $mime-type MIME type.
					 *     @type string $ext       Extension.
					 * }
					'
				),
				array( "@phpstan-return array{'mime-type': string, ext: string}" ),
			),
			'Array is array'                              => array(
				self::fn(
					'
					 * @param Array $args {
					 *     @type string $name Name.
					 * }
					'
				),
				array( '@phpstan-param array{name?: string, ...} $args' ),
			),
			'the bare member of a union takes the shape'  => array(
				self::fn(
					'
					 * @param string|array $args {
					 *     @type string $name Name.
					 * }
					 * @return array|WP_Error {
					 *     @type string $name Name.
					 * }
					'
				),
				array(
					'@phpstan-param string|array{name?: string, ...} $args',
					'@phpstan-return array{name: string}|WP_Error',
				),
			),
			'a class takes the shape through an intersection' => array(
				self::fn(
					'
					 * @return stdClass {
					 *     @type string $name Name.
					 * }
					'
				),
				array( '@phpstan-return stdClass&object{name: string}' ),
			),
			'an intersection inside a union is parenthesized' => array(
				self::fn(
					'
					 * @return stdClass|false {
					 *     @type string $name Name.
					 * }
					'
				),
				array( '@phpstan-return (stdClass&object{name: string})|false' ),
			),
			'a bare object return is a shape'             => array(
				self::fn(
					'
					 * @return object {
					 *     @type string $name Name.
					 * }
					'
				),
				array( '@phpstan-return object{name: string}' ),
			),
			'a bare object param would have to stay open, so is skipped' => array(
				self::fn(
					'
					 * @param object $args {
					 *     @type string $name Name.
					 * }
					'
				),
				array(),
			),
			'a class param stays open through the class'  => array(
				self::fn(
					'
					 * @param stdClass $args {
					 *     @type string $name Name.
					 * }
					'
				),
				array( '@phpstan-param stdClass&object{name?: string} $args' ),
			),
			'two bare members leave the target a guess'   => array(
				self::fn(
					'
					 * @return array|object {
					 *     @type string $name Name.
					 * }
					'
				),
				array(),
			),
			'two classes and no bare member leave the target a guess' => array(
				self::fn(
					'
					 * @return WP_Post|WP_Term {
					 *     @type string $name Name.
					 * }
					'
				),
				array(),
			),
			'a type more specific than the hash is left alone' => array(
				self::fn(
					'
					 * @param array<string, string> $args {
					 *     @type string $name Name.
					 * }
					'
				),
				array(),
			),
			'a PHPDoc keyword is not a class'             => array(
				self::fn(
					'
					 * @return mixed {
					 *     @type string $name Name.
					 * }
					'
				),
				array(),
			),
			'a hand-written counterpart wins for its tag only' => array(
				self::fn(
					'
					 * @param array $args {
					 *     @type string $name Name.
					 * }
					 * @param array $other {
					 *     @type string $name Name.
					 * }
					 * @phpstan-param array{name: non-empty-string} $args
					 * @return array {
					 *     @type string $name Name.
					 * }
					 * @phpstan-return array{name: non-empty-string}
					',
					'( $args, $other )'
				),
				array( '@phpstan-param array{name?: string, ...} $other' ),
			),
			'a multi-line hand-written counterpart covers the name it ends with' => array(
				self::fn(
					'
					 * @param array $args {
					 *     @type string $name Name.
					 * }
					 * @phpstan-param array{
					 *     name: non-empty-string,
					 * } $args
					'
				),
				array(),
			),
			'a conditional return naming a parameter is not a shape for it' => array(
				self::fn(
					'
					 * @param array $args {
					 *     @type string $name Name.
					 * }
					 * @phpstan-return ($args is array ? int : string)
					'
				),
				array( '@phpstan-param array{name?: string, ...} $args' ),
			),
			'a by-reference parameter is skipped'         => array(
				self::fn(
					'
					 * @param array $args {
					 *     @type string $name Name.
					 * }
					',
					'( &$args )'
				),
				array(),
			),
			'a param hash without a variable is skipped'  => array(
				self::fn(
					'
					 * @param array {
					 *     @type string $name Name.
					 * }
					'
				),
				array(),
			),
			'an unclosed hash is skipped'                 => array(
				self::fn(
					'
					 * @param array $args {
					 *     @type string $name Name.
					 * @return void
					'
				),
				array(),
			),
			'an entry without a name is skipped'          => array(
				self::fn(
					'
					 * @param array $args {
					 *     @type string Name.
					 * }
					'
				),
				array(),
			),
			'an entry whose type does not parse is skipped' => array(
				self::fn(
					'
					 * @param array $args {
					 *     @type string, $name Name.
					 * }
					'
				),
				array(),
			),
			'an entry with a nullable type is kept'       => array(
				self::fn(
					'
					 * @param array $args {
					 *     @type ?string         $name  Name.
					 *     @type (string|null)[] $names Names.
					 * }
					'
				),
				array( '@phpstan-param array{name?: ?string, names?: (string|null)[], ...} $args' ),
			),
			'a var hash is left alone'                    => array(
				'<?php
				class WP_Tests_Hash {
					/**
					 * @var array {
					 *     @type string $name Name.
					 * }
					 */
					public $args = array();
				}
				',
				array(),
			),
			'a hook docblock on a statement is translated' => array(
				'<?php
				/**
				 * Filters the arguments.
				 *
				 * @param array $args {
				 *     @type string $name Name.
				 * }
				 */
				$args = apply_filters( "wp_tests", $args );
				',
				array( '@phpstan-param array{name?: string, ...} $args' ),
			),
			'a docblock without a hash is left alone'     => array(
				self::fn(
					'
					 * @param array $args Arguments. See the @type tags elsewhere.
					 * @return array
					'
				),
				array(),
			),
		);
	}

	/**
	 * Tests that the rewritten docblock keeps everything it had, and gains its tags
	 * before the closing marker.
	 */
	public function test_rewritten_docblock_keeps_the_original_text(): void {
		$code = self::fn(
			'
			 * Does a thing.
			 *
			 * @since 1.0.0
			 *
			 * @param array $args {
			 *     @type string $name Name.
			 * }
			 * @return void
			'
		);

		$docblocks = self::docblocks_after_visiting( $code );

		$this->assertCount( 1, $docblocks );
		$this->assertStringContainsString( "Does a thing.\n", $docblocks[0] );
		$this->assertStringContainsString( "@since 1.0.0\n", $docblocks[0] );
		$this->assertStringContainsString( "@return void\n", $docblocks[0] );
		$this->assertStringEndsWith( " * @phpstan-param array{name?: string, ...} \$args\n */", $docblocks[0] );
	}

	/**
	 * Wraps a docblock body in a function declaration.
	 *
	 * @param string $body      Docblock lines, each starting with ` * `.
	 * @param string $signature Parameter list.
	 * @return string
	 */
	private static function fn( string $body, string $signature = '( $args )' ): string {
		return "<?php\n/**" . rtrim( $body ) . "\n */\nfunction wp_tests_fn" . $signature . " {}\n";
	}

	/**
	 * Returns the `@phpstan-*` lines the docblocks in a snippet gain from the visitor.
	 *
	 * @param string $code PHP source.
	 * @return list<string>
	 */
	private static function derived_tags( string $code ): array {
		$before = self::phpstan_lines( self::docblocks( $code, false ) );
		$after  = self::phpstan_lines( self::docblocks_after_visiting( $code ) );

		return array_values( array_diff( $after, $before ) );
	}

	/**
	 * Returns the docblocks in a snippet after the visitor has run.
	 *
	 * @param string $code PHP source.
	 * @return list<string>
	 */
	private static function docblocks_after_visiting( string $code ): array {
		return self::docblocks( $code, true );
	}

	/**
	 * Returns the text of every docblock in a snippet.
	 *
	 * @param string $code  PHP source.
	 * @param bool   $visit Whether to run the visitor first.
	 * @return list<string>
	 */
	private static function docblocks( string $code, bool $visit ): array {
		$stmts = ( new ParserFactory() )->createForHostVersion()->parse( $code );
		self::assertNotNull( $stmts );

		$collector = new class() extends NodeVisitorAbstract {
			/**
			 * @var list<string>
			 */
			public array $docblocks = array();

			public function enterNode( Node $node ): ?Node {
				$doc = $node->getDocComment();
				if ( null !== $doc ) {
					$this->docblocks[] = $doc->getText();
				}
				return null;
			}
		};

		$traverser = new NodeTraverser();
		if ( $visit ) {
			$traverser->addVisitor( new HashNotationVisitor() );
		}
		$traverser->addVisitor( $collector );
		$traverser->traverse( $stmts );

		return $collector->docblocks;
	}

	/**
	 * Extracts the `@phpstan-*` tag lines from docblocks, without their furniture.
	 *
	 * @param list<string> $docblocks Docblock texts.
	 * @return list<string>
	 */
	private static function phpstan_lines( array $docblocks ): array {
		$lines = array();
		foreach ( $docblocks as $docblock ) {
			if ( preg_match_all( '#^\s*\*\s*(@phpstan-\S+ .*)$#m', $docblock, $matches ) > 0 ) {
				foreach ( $matches[1] as $line ) {
					$lines[] = rtrim( $line );
				}
			}
		}
		return $lines;
	}
}
