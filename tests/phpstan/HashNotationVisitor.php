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
 *   overwritten by the derived one.
 * - The declared type must name something a shape can be put on: a bare `array`
 *   or `object`, or a class, on its own or as one member of a union such as
 *   `string|array`. A type that is already more specific than the hash, such as
 *   `array<string, string|bool>`, is left as written. A shape derived for a
 *   named class is intersected with it, as `stdClass&object{...}`, because an
 *   object shape is structural on its own and one derived from a bare `object`
 *   would not be assignable to a property declared `stdClass`.
 * - The hash has to be well formed: every `{` closed by a `}` on a line of its
 *   own, and every `@type` carrying a type and a `$name`. Anything else, and
 *   the whole tag is skipped.
 * - A parameter taken by reference is skipped. PHPStan checks a by-reference
 *   argument in both directions, so a shape there is a contract every caller's
 *   variable has to satisfy before the call, which is not what the hash says.
 *
 * Keys of a `@param` hash are optional, at every level, because a caller may
 * pass any subset of them, and a shape whose keys were required would report
 * every partial array as an error. Keys of a `@return` hash are required, since
 * they describe a value core itself builds, unless the description marks one
 * `Optional.` Numbered keys such as `$0`, `$1` used for positional arguments
 * are required in either case.
 *
 * The shape of a `@param` hash is left open, with a trailing `...`, because the
 * hash lists the keys core reads rather than the only keys a caller may pass.
 * A sealed shape would report reading or testing for any other key as an error,
 * and would contradict the conditional return types core writes by hand. The
 * shape of a `@return` hash is sealed, so reading a key core does not document
 * is reported rather than silently typed as `mixed`.
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
		if ( ! str_contains( $text, '@type ' ) ) {
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

		// Insert the derived tags just before the closing `*/`.
		$merged = preg_replace( '#\s*\*/\s*$#', "\n" . implode( "\n", $lines ) . "\n */", $text, 1 );
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

		foreach ( $this->split_tags( $text ) as $tag ) {
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
			if ( 'param' === $tag['name'] && isset( $by_reference[ (string) $variable ] ) ) {
				continue;
			}

			if ( $this->has_phpstan_counterpart( $text, $tag['name'], $variable ) ) {
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
				null !== $variable && 'return' !== $tag['name'] ? ' $' . $variable : ''
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
	 * Reports whether the docblock already documents this tag for PHPStan.
	 *
	 * @param string      $text     Raw docblock text.
	 * @param string      $tag      Tag name, one of `param`, `return` or `var`.
	 * @param string|null $variable Variable the tag documents, without the `$`.
	 * @return bool
	 */
	private function has_phpstan_counterpart( string $text, string $tag, ?string $variable ): bool {
		if ( 'return' === $tag ) {
			return str_contains( $text, '@phpstan-return' );
		}

		if ( null === $variable ) {
			return str_contains( $text, '@phpstan-' . $tag );
		}

		/*
		 * A hand-written shape often spans several lines, so the variable it
		 * documents can be far from the tag that opens it. Matching the tag and
		 * the variable without requiring them to be adjacent keeps a multi-line
		 * `@phpstan-param array{ ... } $args` recognized.
		 */
		return preg_match(
			'#@phpstan-' . $tag . '\s.*?\$' . preg_quote( $variable, '#' ) . '\b#s',
			$text
		) === 1;
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
	 * @return list<array{type: string, name: string, variadic: bool, description: string, children: list<mixed>}>|null
	 *         Entries of this level, or null if the hash is malformed.
	 */
	private function parse_entries( array $lines, int &$index ): ?array {
		$entries = array();
		$last    = null;
		$count   = count( $lines );

		while ( $index < $count ) {
			$line = trim( $lines[ $index ] );
			++$index;

			if ( '}' === $line ) {
				return $entries;
			}

			if ( str_starts_with( $line, '@type ' ) ) {
				$entry = $this->parse_entry( substr( $line, 6 ) );
				if ( null === $entry ) {
					return null;
				}

				if ( $entry['opens'] ) {
					$children = $this->parse_entries( $lines, $index );
					if ( null === $children || array() === $children ) {
						return null;
					}
					$entry['children'] = $children;
				}

				unset( $entry['opens'] );
				$entries[] = $entry;
				$last      = count( $entries ) - 1;
				continue;
			}

			// A tag other than `@type` inside a hash means the hash was never closed.
			if ( str_starts_with( $line, '@' ) ) {
				return null;
			}

			if ( '' !== $line && null !== $last ) {
				$entries[ $last ]['description'] .= ' ' . $line;
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
		$members = $this->split_union( $declared );
		if ( null === $members ) {
			return null;
		}

		$target = null;
		foreach ( $members as $position => $member ) {
			if ( ! $this->is_shapeable( $member ) ) {
				continue;
			}
			// Two shapeable members would leave it ambiguous which one the hash describes.
			if ( null !== $target ) {
				return null;
			}
			$target = $position;
		}

		if ( null === $target ) {
			return null;
		}

		$member = $members[ $target ];
		$shape  = $this->resolve_container( $entries, $for_param, 'array' === $member );
		if ( null === $shape ) {
			return null;
		}

		/*
		 * An object shape is structural, so one derived for a value core builds as a
		 * `stdClass` would no longer be assignable to a property declared `stdClass`.
		 * Naming the class in the docblock keeps both: the value stays that class, and
		 * its members are typed by the shape intersected with it.
		 */
		if ( 'array' !== $member && 'object' !== $member ) {
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
		if ( 'array' === $member || 'object' === $member ) {
			return true;
		}

		// A name PHPDoc gives a meaning of its own is not a class, whatever its shape.
		$keywords = array(
			'bool',
			'boolean',
			'callable',
			'double',
			'false',
			'float',
			'int',
			'integer',
			'iterable',
			'list',
			'mixed',
			'never',
			'null',
			'number',
			'numeric',
			'parent',
			'resource',
			'scalar',
			'self',
			'static',
			'string',
			'this',
			'true',
			'void',
		);

		if ( in_array( strtolower( $member ), $keywords, true ) ) {
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
	 * @return string|null
	 */
	private function resolve_container( array $entries, bool $for_param, bool $is_array ): ?string {
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
		foreach ( $entries as $entry ) {
			if ( $entry['variadic'] ) {
				return null;
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
		 * A `@param` hash lists the keys core reads, not the only keys a caller
		 * may pass, so its shape stays open with a trailing `...`. Without it
		 * the shape would be sealed, and reading or testing for an undocumented
		 * key would be reported as an error at every call site that adds one.
		 */
		if ( ! $is_array ) {
			return sprintf( 'object{%s}', implode( ', ', $members ) );
		}

		return sprintf( 'array{%s%s}', implode( ', ', $members ), $for_param ? ', ...' : '' );
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
			return $this->validate_type( $entry['type'] );
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
		 * A `@return` hash describes a value core builds, so its keys are
		 * present unless the description says otherwise. `Default ...` is not
		 * that: a key documented with a default is still always set.
		 */
		if ( ! $for_param ) {
			return preg_match( '#\bOptional\b#i', $entry['description'] ) === 1;
		}

		// Numbered keys document positional arguments, which are always present.
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
	 * Returns a type only if it is shaped like one.
	 *
	 * Guards against prose that has drifted into the type column of a `@type`
	 * tag, which would otherwise be emitted as a type PHPStan cannot parse.
	 *
	 * @param string $type Type as written in the docblock.
	 * @return string|null
	 */
	private function validate_type( string $type ): ?string {
		return $this->split_union( $type ) === null ? null : $type;
	}

	/**
	 * Splits a union type into its members, ignoring `|` inside brackets.
	 *
	 * @param string $type Type as written in the docblock.
	 * @return list<string>|null Members, or null if the type is not well formed.
	 */
	private function split_union( string $type ): ?array {
		$type = trim( $type );
		if ( preg_match( '#^[A-Za-z0-9_\\\\|<>{},:\'"\[\]\#\-\. ]+$#', $type ) !== 1 ) {
			return null;
		}

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
