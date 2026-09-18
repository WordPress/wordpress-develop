<?php
/**
 * PHPStan parser node visitor that bridges WordPress core's hash notation
 * PHPDoc convention to PHPStan's array and object shape types.
 *
 * @package WordPress
 */

declare(strict_types=1);

namespace WordPress\PHPStan;

use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\ParserException;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\PhpDocParser\ParserConfig;

/**
 * Reads WordPress hash notation from `@param` and `@return` tags and injects
 * the equivalent `@phpstan-*` array or object shape into the docblock.
 *
 * Core documents the contents of an array or object with a nested list of
 * `@type` tags:
 *
 *     @param array $args {
 *         Optional. An array of arguments.
 *
 *         @type string $post_type   Post type. Default 'post'.
 *         @type int    $post_author Post author ID.
 *     }
 *
 * PHPStan reads that hash as free text, so the value stays a plain `array` and
 * nothing inside it is typed. This visitor translates the hash into the shape
 * PHPStan understands and appends it to the docblock, which for the example
 * above yields:
 *
 *     @phpstan-param array{post_type?: string, post_author?: int, ...} $args
 *
 * so the same documentation serves the reader and the analysis, rather than
 * each shape having to be written a second time by hand.
 *
 * A hash whose translation would be a guess is left alone, and the value keeps
 * whatever type it has today. This visitor therefore only ever narrows a type,
 * never contradicts one:
 *
 * - A `@phpstan-` counterpart already written by hand always wins; the tag it
 *   covers is skipped, so a shape that has been tuned in the source is never
 *   overwritten by the derived one. Only that tag: a `@phpstan-param` written
 *   for one parameter says nothing about the next, and a conditional
 *   `@phpstan-return` naming a parameter is not a shape for it.
 * - The declared type must name something a shape can be put on: a bare `array`
 *   or `object`, or a class, on its own or as one member of a union such as
 *   `string|array` or `array|WP_Error`, where the bare member is the one the
 *   hash is about. A type that is already more specific than the hash, such as
 *   `array<string, string|bool>`, is left as written. A shape derived for a
 *   named class is intersected with it, as `stdClass&object{...}`, because an
 *   object shape is structural on its own and one derived from a bare `object`
 *   would not be assignable to a property declared `stdClass`.
 * - The hash has to be well formed: every `{` closed by a `}` on a line of its
 *   own, and every `@type` carrying a `$name` and a type PHPStan's own type
 *   parser reads as one. Anything else, and the whole tag is skipped.
 * - A hash on a bare `object` that would have to stay open is skipped: PHPStan's
 *   object shapes have a fixed member list and no `...`, so sealing one would
 *   report every member the hash does not name. A hash on a named class is not
 *   skipped, because the intersection leaves that class to decide what else may
 *   be read, and a `stdClass` accepts anything.
 * - A parameter taken by reference is skipped. PHPStan checks a by-reference
 *   argument in both directions, so a shape there is a contract every caller's
 *   variable has to satisfy before the call, which is not what the hash says.
 *
 * Keys of a `@param` hash are optional, at every level, because a caller may
 * pass any subset of them, and a shape whose keys were required would report
 * every partial array as an error. Keys of a `@return` hash are required, since
 * they describe a value core itself builds. Numbered keys such as `$0`, `$1`
 * used for positional arguments are required in either case, because they are
 * passed in order. A description opening with `Optional.` overrides all of
 * that, and is the only thing that does; the word elsewhere in a description is
 * prose, not a marker.
 *
 * The shape of a `@param` hash is left open, with a trailing `...`, because the
 * hash lists the keys core reads rather than the only keys a caller may pass.
 * A sealed shape would report reading or testing for any other key as an error,
 * and would contradict the conditional return types core writes by hand. The
 * shape of a `@return` hash is sealed, so reading a key core does not document
 * is reported rather than silently typed as `mixed` — unless the hash itself
 * says otherwise, by listing a `...$N` entry beside its named keys.
 *
 * @link https://developer.wordpress.org/coding-standards/inline-documentation-standards/php/#1-1-parameters-that-are-arrays Hash notation in the documentation standards.
 * @link https://github.com/php-stubs/wordpress-stubs/blob/master/src/Visitor.php The equivalent translation php-stubs/wordpress-stubs performs when generating stubs, MIT license.
 *
 * Registered as `phpstan.parser.richParserNodeVisitor` in `base.neon`.
 */
final class HashNotationVisitor extends NodeVisitorAbstract {

	/**
	 * Docblock tags whose description may carry a hash.
	 *
	 * `@var` is left out. A property declaration is inherited by every subclass
	 * and has to accept its own default, so a shape there would say more than
	 * the hash does: that no subclass may widen the property, and that the
	 * declared default already has the shape.
	 */
	private const HASH_TAGS = array( 'param', 'return' );

	/**
	 * Names PHPDoc gives a meaning of its own, which are therefore not classes.
	 *
	 * `array` and `object` are left out: they take a shape directly rather than
	 * through an intersection, and `is_shapeable()` answers for them first.
	 */
	private const TYPE_KEYWORDS = array(
		'bool'     => true,
		'boolean'  => true,
		'callable' => true,
		'double'   => true,
		'false'    => true,
		'float'    => true,
		'int'      => true,
		'integer'  => true,
		'iterable' => true,
		'list'     => true,
		'mixed'    => true,
		'never'    => true,
		'null'     => true,
		'number'   => true,
		'numeric'  => true,
		'parent'   => true,
		'resource' => true,
		'scalar'   => true,
		'self'     => true,
		'static'   => true,
		'string'   => true,
		'this'     => true,
		'true'     => true,
		'void'     => true,
	);

	/**
	 * Lexer for the type parser, built on first use.
	 */
	private ?Lexer $lexer = null;

	/**
	 * PHPStan's own PHPDoc type parser, built on first use.
	 */
	private ?TypeParser $type_parser = null;

	/**
	 * Answers `parses_as_type()` has already given, keyed by the type.
	 *
	 * Core writes a small vocabulary of types across a great many hashes, so the
	 * same handful of strings is asked about over and over.
	 *
	 * @var array<string, bool>
	 */
	private array $parsed = array();

	/**
	 * Translates the hashes in a node's docblock into `@phpstan-*` shapes.
	 *
	 * @param Node $node The node being entered.
	 * @return null
	 */
	public function enterNode( Node $node ): ?Node {
		if ( ! $node instanceof Node\FunctionLike ) {
			return null;
		}

		$doc = $node->getDocComment();
		if ( null === $doc ) {
			return null;
		}

		$text = $doc->getText();
		if ( preg_match( '#@type[ \t]#', $text ) !== 1 ) {
			return null;
		}

		$additions = $this->build_additions( $text, $this->by_reference_parameters( $node ) );
		if ( array() === $additions ) {
			return null;
		}

		$lines = array();
		foreach ( $additions as $addition ) {
			$lines[] = ' * ' . $addition;
		}

		/*
		 * Insert the derived tags just before the docblock's closing marker. The
		 * replacement comes from a callback rather than being passed to
		 * preg_replace() as a string, because a derived tag ends in the variable
		 * it documents, and a `$0` there would be read as a backreference to the
		 * match rather than as the name it is.
		 */
		$merged = preg_replace_callback(
			'#\s*\*/\s*$#',
			static function () use ( $lines ): string {
				return "\n" . implode( "\n", $lines ) . "\n */";
			},
			$text,
			1
		);
		if ( ! is_string( $merged ) ) {
			return null;
		}

		/*
		 * The rewritten docblock is longer than the one in the file, so it carries no
		 * position. Keeping the original start would point at a span of the source that
		 * no longer holds this text, which is the same reason GlobalDocBlockVisitor
		 * leaves it off.
		 */
		$node->setDocComment( new Doc( $merged ) );

		return null;
	}

	/**
	 * Collects the parameters a function takes by reference.
	 *
	 * @param Node\FunctionLike $node Node the docblock is attached to.
	 * @return array<string, true> Set of parameter names, without the `$`.
	 */
	private function by_reference_parameters( Node\FunctionLike $node ): array {
		$names = array();
		foreach ( $node->getParams() as $param ) {
			if ( $param->byRef && $param->var instanceof Node\Expr\Variable && is_string( $param->var->name ) ) {
				$names[ $param->var->name ] = true;
			}
		}

		return $names;
	}

	/**
	 * Builds the `@phpstan-*` tags derived from every hash in a docblock.
	 *
	 * @param string              $text         Raw docblock text including the `/**` markers.
	 * @param array<string, true> $by_reference Parameters the function takes by reference.
	 * @return list<string> Tag lines, without the leading ` * `.
	 */
	private function build_additions( string $text, array $by_reference ): array {
		$additions = array();
		$tags      = $this->split_tags( $text );
		$covered   = $this->phpstan_counterparts( $tags );

		foreach ( $tags as $tag ) {
			if ( ! in_array( $tag['name'], self::HASH_TAGS, true ) ) {
				continue;
			}

			$header = rtrim( $tag['header'] );
			if ( ! str_ends_with( $header, '{' ) ) {
				continue;
			}

			$head = rtrim( substr( $header, 0, -1 ) );
			$split = $this->split_type( $head );
			if ( null === $split ) {
				continue;
			}

			list( $declared, $remainder ) = $split;

			$variable = null;
			if ( preg_match( '#^\$([A-Za-z0-9_]+)#', $remainder, $matches ) === 1 ) {
				$variable = $matches[1];
			}

			// A `@param` hash without a variable name documents nothing PHPStan can attach a type to.
			if ( 'param' === $tag['name'] && null === $variable ) {
				continue;
			}

			/*
			 * A by-reference parameter is checked in both directions, so a shape
			 * derived for one would have to be reached by every caller's variable
			 * before the call. The hash describes what the function reads, not a
			 * contract on the caller's variable, so it is left out.
			 */
			if ( 'param' === $tag['name'] && isset( $by_reference[ $variable ] ) ) {
				continue;
			}

			$key = 'return' === $tag['name'] ? 'return' : 'param $' . $variable;
			if ( isset( $covered[ $key ] ) ) {
				continue;
			}

			$index   = 0;
			$entries = $this->parse_entries( $tag['body'], $index );
			if ( null === $entries || array() === $entries ) {
				continue;
			}

			$type = $this->substitute( $declared, $entries, 'param' === $tag['name'] );
			if ( null === $type ) {
				continue;
			}

			$additions[] = sprintf(
				'@phpstan-%s %s%s',
				$tag['name'],
				$type,
				'return' === $tag['name'] ? '' : ' $' . $variable
			);
		}

		return $additions;
	}

	/**
	 * Splits a docblock into its tags.
	 *
	 * The docblock furniture is removed first, so a line reads as it would in a
	 * plain text file: `@param array $args {` for a tag, and the hash body
	 * indented below it.
	 *
	 * @param string $text Raw docblock text including the `/**` markers.
	 * @return list<array{name: string, header: string, body: list<string>}>
	 */
	private function split_tags( string $text ): array {
		$body = preg_replace( '#^\s*/\*\*#', '', $text, 1 );
		$body = preg_replace( '#\*/\s*$#', '', (string) $body, 1 );

		$tags    = array();
		$current = null;

		foreach ( preg_split( '#\R#', (string) $body ) ?: array() as $line ) {
			$line = (string) preg_replace( '#^\s*\*[ ]?#', '', $line, 1 );

			if ( preg_match( '#^@([a-zA-Z][a-zA-Z0-9_-]*)[ \t]*(.*)$#', $line, $matches ) === 1 ) {
				$tags[] = array(
					'name'   => strtolower( $matches[1] ),
					'header' => $matches[2],
					'body'   => array(),
				);
				$current = count( $tags ) - 1;
				continue;
			}

			if ( null !== $current ) {
				$tags[ $current ]['body'][] = $line;
			}
		}

		return $tags;
	}

	/**
	 * Collects what the docblock already documents for PHPStan by hand.
	 *
	 * A hand-written shape often spans several lines, so the variable a
	 * `@phpstan-param` documents can be far from the tag that opens it. The
	 * search stays inside that one tag rather than running over the whole
	 * docblock: a `@phpstan-param` written for one parameter says nothing about
	 * the next, and a conditional `@phpstan-return` naming a parameter is not a
	 * shape for it.
	 *
	 * @param list<array{name: string, header: string, body: list<string>}> $tags Tags of the docblock.
	 * @return array<string, true> Set keyed as `param $name`, or `return`.
	 */
	private function phpstan_counterparts( array $tags ): array {
		$covered = array();

		foreach ( $tags as $tag ) {
			if ( 'phpstan-return' === $tag['name'] ) {
				$covered['return'] = true;
				continue;
			}

			if ( 'phpstan-param' !== $tag['name'] ) {
				continue;
			}

			$written = trim( $tag['header'] . ' ' . implode( ' ', $tag['body'] ) );
			$split   = $this->split_type( $written );

			if ( null !== $split && preg_match( '#^\$([A-Za-z0-9_]+)#', $split[1], $matches ) === 1 ) {
				$covered[ 'param $' . $matches[1] ] = true;
				continue;
			}

			/*
			 * A tag whose type cannot be split covers every name it mentions. It
			 * was written by hand for a reason, and overwriting it would leave two
			 * shapes for the same parameter.
			 */
			if ( preg_match_all( '#\$([A-Za-z0-9_]+)#', $written, $matches ) > 0 ) {
				foreach ( $matches[1] as $name ) {
					$covered[ 'param $' . $name ] = true;
				}
			}
		}

		return $covered;
	}

	/**
	 * Parses the `@type` entries of one hash level.
	 *
	 * Nesting is tracked through the braces rather than through indentation,
	 * because core aligns a hash under the description column of the tag that
	 * opens it, and that column moves with the longest parameter name.
	 *
	 * @param list<string> $lines Body lines of the tag, with docblock furniture removed.
	 * @param int          $index Current position in `$lines`, advanced as entries are read.
	 * @param string       $intro Set to the prose written above this level's first entry.
	 * @return list<array{type: string, name: string, variadic: bool, description: string, children: list<mixed>}>|null
	 *         Entries of this level, or null if the hash is malformed.
	 */
	private function parse_entries( array $lines, int &$index, string &$intro = '' ): ?array {
		$entries = array();
		$last    = null;
		$intro   = '';
		$count   = count( $lines );

		while ( $index < $count ) {
			$line = trim( $lines[ $index ] );
			++$index;

			if ( '}' === $line ) {
				return $entries;
			}

			if ( preg_match( '#^@type[ \t]+(.*)$#', $line, $matches ) === 1 ) {
				$entry = $this->parse_entry( $matches[1] );
				if ( null === $entry ) {
					return null;
				}

				if ( $entry['opens'] ) {
					$nested   = '';
					$children = $this->parse_entries( $lines, $index, $nested );
					if ( null === $children || array() === $children ) {
						return null;
					}

					$entry['children'] = $children;

					/*
					 * An entry that opens a hash carries no description beside its
					 * `@type`, because the `{` ends the line. What describes it is the
					 * hash's own intro, which is where a marker such as `Optional.`
					 * for the whole block is written.
					 */
					$entry['description'] = trim( $entry['description'] . ' ' . $nested );
				}

				unset( $entry['opens'] );
				$entries[] = $entry;

				/*
				 * A line below a nested hash describes the level that hash sits in
				 * rather than the entry that opened it, whose description was read
				 * before its `{`. It belongs to no key, so nothing collects it.
				 */
				$last = array() === $entry['children'] ? count( $entries ) - 1 : null;
				continue;
			}

			// A tag other than `@type` inside a hash means the hash was never closed.
			if ( str_starts_with( $line, '@' ) ) {
				return null;
			}

			if ( '' === $line ) {
				continue;
			}

			if ( null !== $last ) {
				$entries[ $last ]['description'] .= ' ' . $line;
			} elseif ( array() === $entries ) {
				$intro = '' === $intro ? $line : $intro . ' ' . $line;
			}
		}

		return null;
	}

	/**
	 * Parses one `@type` entry.
	 *
	 * @param string $rest Everything after `@type `.
	 * @return array{type: string, name: string, variadic: bool, description: string, children: list<mixed>, opens: bool}|null
	 */
	private function parse_entry( string $rest ): ?array {
		$rest  = rtrim( $rest );
		$opens = false;

		if ( str_ends_with( $rest, '{' ) ) {
			$opens = true;
			$rest  = rtrim( substr( $rest, 0, -1 ) );
		}

		$split = $this->split_type( $rest );
		if ( null === $split ) {
			return null;
		}

		list( $type, $remainder ) = $split;

		// Core keys are not always identifiers: `$mime-type` and `$post-trashed` are both documented.
		if ( preg_match( '#^(\.\.\.)?\$([A-Za-z0-9_-]+)[ \t]*(.*)$#', $remainder, $matches ) !== 1 ) {
			return null;
		}

		return array(
			'type'        => $type,
			'name'        => $matches[2],
			'variadic'    => '' !== $matches[1],
			'description' => $matches[3],
			'children'    => array(),
			'opens'       => $opens,
		);
	}

	/**
	 * Splits a leading type off a string, keeping bracketed groups together.
	 *
	 * `array<string, string> $deps` splits into `array<string, string>` and
	 * `$deps`, rather than at the space inside the angle brackets.
	 *
	 * @param string $text Text beginning with a type.
	 * @return array{0: string, 1: string}|null Type and remainder, or null if there is no type.
	 */
	private function split_type( string $text ): ?array {
		$text   = ltrim( $text );
		$length = strlen( $text );
		$depth  = 0;
		$offset = $length;

		for ( $position = 0; $position < $length; $position++ ) {
			$character = $text[ $position ];

			if ( '<' === $character || '{' === $character || '(' === $character || '[' === $character ) {
				++$depth;
			} elseif ( '>' === $character || '}' === $character || ')' === $character || ']' === $character ) {
				--$depth;
				if ( $depth < 0 ) {
					return null;
				}
			} elseif ( 0 === $depth && ( ' ' === $character || "\t" === $character ) ) {
				$offset = $position;
				break;
			}
		}

		if ( 0 !== $depth ) {
			return null;
		}

		$type = substr( $text, 0, $offset );
		if ( '' === $type ) {
			return null;
		}

		return array( $type, ltrim( substr( $text, $offset ) ) );
	}

	/**
	 * Replaces the bare `array` member of a type with a shape.
	 *
	 * @param string      $declared  Type as written in the docblock.
	 * @param list<mixed> $entries   Entries of the hash describing it.
	 * @param bool        $for_param Whether the hash documents a `@param`.
	 * @return string|null The type with the shape substituted in, or null if it cannot be.
	 */
	private function substitute( string $declared, array $entries, bool $for_param ): ?string {
		if ( ! $this->parses_as_type( $declared ) ) {
			return null;
		}

		$members = $this->split_union( $declared );
		if ( null === $members ) {
			return null;
		}

		$bare      = array();
		$shapeable = array();

		foreach ( $members as $position => $member ) {
			if ( ! $this->is_shapeable( $member ) ) {
				continue;
			}

			$shapeable[] = $position;
			$normalized  = strtolower( $member );

			if ( 'array' === $normalized || 'object' === $normalized ) {
				$bare[] = $position;
			}
		}

		/*
		 * A class beside a bare `array` is what the function returns instead of the
		 * array, as `array|WP_Error` says, and never what the hash describes: a
		 * `WP_Error` carries no keys. So the bare member takes the shape whenever
		 * there is exactly one. Two of them, or two classes and no bare member,
		 * would leave it a guess which one the hash is about.
		 */
		if ( 1 === count( $bare ) ) {
			$target = $bare[0];
		} elseif ( array() === $bare && 1 === count( $shapeable ) ) {
			$target = $shapeable[0];
		} else {
			return null;
		}

		$member     = $members[ $target ];
		$normalized = strtolower( $member );
		$named      = 'array' !== $normalized && 'object' !== $normalized;
		$shape      = $this->resolve_container( $entries, $for_param, 'array' === $normalized, $named );
		if ( null === $shape ) {
			return null;
		}

		/*
		 * An object shape is structural, so one derived for a value core builds as a
		 * `stdClass` would no longer be assignable to a property declared `stdClass`.
		 * Naming the class in the docblock keeps both: the value stays that class, and
		 * its members are typed by the shape intersected with it.
		 */
		if ( $named ) {
			$shape = $member . '&' . $shape;

			// An intersection inside a union needs parentheses to parse.
			if ( count( $members ) > 1 ) {
				$shape = '(' . $shape . ')';
			}
		}

		$members[ $target ] = $shape;

		return implode( '|', $members );
	}

	/**
	 * Reports whether a member of a union type can carry a shape.
	 *
	 * `array` and `object` take one directly. A class name takes one through an
	 * intersection, so the value keeps the class it is documented as, which is
	 * what makes a `stdClass` hash usable where the class is expected.
	 *
	 * @param string $member One member of a union type.
	 * @return bool
	 */
	private function is_shapeable( string $member ): bool {
		$normalized = strtolower( $member );

		// PHP type names are case-insensitive, and core writes `Array` in places.
		if ( 'array' === $normalized || 'object' === $normalized ) {
			return true;
		}

		// A name PHPDoc gives a meaning of its own is not a class, whatever its shape.
		if ( isset( self::TYPE_KEYWORDS[ $normalized ] ) ) {
			return false;
		}

		return preg_match( '#^\\\\?[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*$#', $member ) === 1;
	}

	/**
	 * Builds the shape for one hash level.
	 *
	 * @param list<mixed> $entries   Entries of this level.
	 * @param bool        $for_param Whether the hash documents a `@param`.
	 * @param bool        $is_array  Whether the hash describes an array rather than an object.
	 * @param bool        $named     Whether the shape will be intersected with a named class.
	 * @return string|null
	 */
	private function resolve_container( array $entries, bool $for_param, bool $is_array, bool $named = false ): ?string {
		/*
		 * A single `...$0` entry describes a repeated value rather than a key.
		 * The hash says nothing about the keys it repeats under, and core uses
		 * both numbered and named ones, so the keys stay `array-key`.
		 */
		if ( 1 === count( $entries ) && $entries[0]['variadic'] ) {
			if ( ! $is_array ) {
				return null;
			}

			$inner = $this->resolve_entry_type( $entries[0], $for_param );

			return null === $inner ? null : sprintf( 'array<array-key, %s>', $inner );
		}

		$members = array();
		$open    = $for_param;

		foreach ( $entries as $entry ) {
			/*
			 * A `...$N` entry beside named ones is core's way of writing "and the
			 * rest", as the positional hashes of `wp_maybe_grant_site_health_caps()`
			 * and `WP_Meta_Query` do. The named keys are kept and the shape is left
			 * open, which says the same thing about the keys it does not name.
			 */
			if ( $entry['variadic'] ) {
				$open = true;
				continue;
			}

			$type = $this->resolve_entry_type( $entry, $for_param );
			if ( null === $type ) {
				return null;
			}

			$members[] = sprintf(
				'%s%s: %s',
				$this->format_key( $entry['name'] ),
				$this->is_optional( $entry, $for_param ) ? '?' : '',
				$type
			);
		}

		if ( array() === $members ) {
			return null;
		}

		/*
		 * PHPStan's object shapes have no `...`, so a bare one that has to stay open
		 * cannot be expressed and is left alone rather than sealed. Intersecting with
		 * the class named in the docblock does not seal it: the class decides what
		 * else may be read, and a `stdClass` accepts anything, which is what a hash on
		 * a value core builds with `wp_parse_args()` needs.
		 */
		if ( ! $is_array ) {
			return $open && ! $named ? null : sprintf( 'object{%s}', implode( ', ', $members ) );
		}

		/*
		 * A `@param` hash lists the keys core reads, not the only keys a caller
		 * may pass, so its shape stays open with a trailing `...`. Without it
		 * the shape would be sealed, and reading or testing for an undocumented
		 * key would be reported as an error at every call site that adds one.
		 */
		return sprintf( 'array{%s%s}', implode( ', ', $members ), $open ? ', ...' : '' );
	}

	/**
	 * Resolves the type of one entry, descending into its own hash if it has one.
	 *
	 * @param array{type: string, children: list<mixed>} $entry     Entry to resolve.
	 * @param bool                                       $for_param Whether the hash documents a `@param`.
	 * @return string|null
	 */
	private function resolve_entry_type( array $entry, bool $for_param ): ?string {
		if ( array() === $entry['children'] ) {
			return $this->parses_as_type( $entry['type'] ) ? $entry['type'] : null;
		}

		return $this->substitute( $entry['type'], $entry['children'], $for_param );
	}

	/**
	 * Reports whether a key is optional.
	 *
	 * @param array{name: string, description: string} $entry Entry to inspect.
	 * @param bool                                     $for_param Whether the hash documents a `@param`.
	 * @return bool
	 */
	private function is_optional( array $entry, bool $for_param ): bool {
		/*
		 * The documentation standard opens the description of an optional value
		 * with `Optional.`, so that sentence is the marker. Matching the bare word
		 * would take it out of prose that only mentions it: from a callback that
		 * "receives optional mixed input", or from the "Optional self closing
		 * slash" of a match array whose every group is always set.
		 */
		if ( preg_match( '#^\s*Optional\.#i', $entry['description'] ) === 1 ) {
			return true;
		}

		/*
		 * A `@return` hash describes a value core builds, so its keys are
		 * present unless the description says otherwise. `Default ...` is not
		 * that: a key documented with a default is still always set.
		 */
		if ( ! $for_param ) {
			return false;
		}

		// Numbered keys document positional arguments, which are passed in order.
		if ( preg_match( '#^[0-9]+$#', $entry['name'] ) === 1 ) {
			return false;
		}

		return true;
	}

	/**
	 * Formats a key for use in a shape, quoting it when it is not an identifier.
	 *
	 * @param string $name Key name, without the `$`.
	 * @return string
	 */
	private function format_key( string $name ): string {
		if ( preg_match( '#^(?:[A-Za-z_][A-Za-z0-9_]*|[0-9]+)$#', $name ) === 1 ) {
			return $name;
		}

		return "'" . str_replace( "'", "\\'", $name ) . "'";
	}

	/**
	 * Reports whether a string PHPStan would have to read as a type parses as one.
	 *
	 * Guards against prose that has drifted into the type column of a `@type` tag,
	 * which would otherwise be emitted as a type PHPStan cannot parse. The question
	 * is answered by PHPStan's own type parser rather than by a description of what
	 * a type may contain, because such a description has to be kept in step with a
	 * grammar that keeps growing, and silently rejects the notation it has not
	 * caught up with: `?string`, `(string|null)[]` and `callable(): void` are all
	 * types core writes, or could.
	 *
	 * @param string $type Type as written in the docblock.
	 * @return bool
	 */
	private function parses_as_type( string $type ): bool {
		if ( isset( $this->parsed[ $type ] ) ) {
			return $this->parsed[ $type ];
		}

		if ( null === $this->lexer || null === $this->type_parser ) {
			$config            = new ParserConfig( array() );
			$this->lexer       = new Lexer( $config );
			$this->type_parser = new TypeParser( $config, new ConstExprParser( $config ) );
		}

		try {
			$tokens = new TokenIterator( $this->lexer->tokenize( $type ) );
			$this->type_parser->parse( $tokens );

			// Prose whose first word happens to parse leaves the rest of itself behind.
			$this->parsed[ $type ] = $tokens->isCurrentTokenType( Lexer::TOKEN_END );
		} catch ( ParserException $exception ) {
			$this->parsed[ $type ] = false;
		}

		return $this->parsed[ $type ];
	}

	/**
	 * Splits a union type into its members, ignoring `|` inside brackets.
	 *
	 * The type is expected to have been through `parses_as_type()` already; this
	 * only finds the `|` that separate its top-level members.
	 *
	 * @param string $type Type as written in the docblock.
	 * @return list<string>|null Members, or null if the brackets are unbalanced.
	 */
	private function split_union( string $type ): ?array {
		$type = trim( $type );

		$members = array();
		$member  = '';
		$depth   = 0;
		$length  = strlen( $type );

		for ( $position = 0; $position < $length; $position++ ) {
			$character = $type[ $position ];

			if ( '<' === $character || '{' === $character || '(' === $character || '[' === $character ) {
				++$depth;
			} elseif ( '>' === $character || '}' === $character || ')' === $character || ']' === $character ) {
				--$depth;
				if ( $depth < 0 ) {
					return null;
				}
			} elseif ( '|' === $character && 0 === $depth ) {
				if ( '' === $member ) {
					return null;
				}
				$members[] = $member;
				$member    = '';
				continue;
			}

			$member .= $character;
		}

		if ( 0 !== $depth || '' === $member ) {
			return null;
		}

		$members[] = $member;

		return $members;
	}
}
