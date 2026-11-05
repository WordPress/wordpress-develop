<?php
/**
 * Writes the report of the types WordPress functions were given and returned.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

/**
 * Turns what WP_Invariant_Types collected into a JSON and a Markdown report.
 *
 * For each function and method it shows, for each parameter and the return
 * value, how often each type was seen, from WordPress and from tests, next to
 * the types its documentation declares: the native type, the PHPDoc `@param` or
 * `@return` type, and the PHPStan `@phpstan-param` or `@phpstan-return` type.
 * It does not decide whether they agree.
 */
final class WP_Invariant_Types_Report {

	/**
	 * Writes the report, after merging in what separate processes collected.
	 *
	 * @param string   $directory Directory for the report files.
	 * @param string   $name      Base name of the report files, such as `invariants-types`.
	 * @param array[]  $targets   The functions and methods which were hooked, from WP_Invariant::functions_in().
	 * @param array[]  $profile   What was collected in this process, from WP_Invariant_Types::profile().
	 * @param string[] $processes Files of what separate processes collected, which are removed once read.
	 * @return string A short summary in Markdown.
	 */
	public static function write( $directory, $name, array $targets, array $profile, array $processes = array() ) {
		foreach ( $processes as $file ) {
			$other = unserialize( file_get_contents( $file ), array( 'allowed_classes' => false ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- What WP_Invariant_Types wrote.
			if ( is_array( $other ) ) {
				$profile = WP_Invariant_Types::merge_profiles( $profile, $other );
			}
			unlink( $file );
		}

		$data = self::data( $targets, $profile, count( $processes ) );
		if ( ! is_dir( $directory ) ) {
			mkdir( $directory, 0777, true );
		}
		file_put_contents( "{$directory}/{$name}.json", json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE ) . "\n" );
		file_put_contents( "{$directory}/{$name}.md", self::markdown( $data ) );

		$summary = self::summary( $data, $name );
		file_put_contents( "{$directory}/{$name}-summary.md", $summary );
		return $summary;
	}

	/**
	 * Gathers what each target was given and returned, with its documented types.
	 *
	 * @param array[] $targets   The hooked functions and methods.
	 * @param array[] $profile   What was collected, by target.
	 * @param int     $processes How many separate processes were merged in.
	 * @return array
	 */
	public static function data( array $targets, array $profile, $processes = 0 ) {
		$functions = array();
		$never     = array();
		$calls     = 0;
		foreach ( $targets as $target ) {
			$name = $target['target'];
			if ( ! isset( $profile[ $name ] ) || 0 === array_sum( $profile[ $name ]['calls'] ) ) {
				$never[] = $name . '()';
				continue;
			}

			$functions[ $name ] = self::describe( $target, $profile[ $name ] );
			$calls             += $functions[ $name ]['calls']['core'] + $functions[ $name ]['calls']['tests'];
		}

		uksort( $functions, array( __CLASS__, 'compare_targets' ) );
		usort( $never, array( __CLASS__, 'compare_targets' ) );

		return array(
			'summary'      => array(
				'targets'      => count( $targets ),
				'called'       => count( $functions ),
				'never_called' => count( $never ),
				'calls'        => $calls,
				'processes'    => $processes,
			),
			'functions'    => $functions,
			'never_called' => $never,
		);
	}

	/**
	 * Describes one function or method: where it is, how it is marked, and what it was given and returned.
	 *
	 * @param array $target  From WP_Invariant::functions_in().
	 * @param array $profile What was collected for it.
	 * @return array
	 */
	private static function describe( array $target, array $profile ) {
		$reflection = self::reflect( $target );
		$documented = self::doc_types( $reflection ? (string) $reflection->getDocComment() : '' );
		$parameters = $reflection ? $reflection->getParameters() : array();
		$declared   = count( $parameters );
		$variadic   = $declared > 0 && $parameters[ $declared - 1 ]->isVariadic();

		// Arguments past the declared parameters are counted together: as the variadic parameter, or as extra arguments.
		$slots = array();
		foreach ( $profile['slots'] as $position => $slot ) {
			if ( 'return' === $position ) {
				continue;
			}
			if ( ! $reflection ) {
				$index = $position;
			} else {
				$index = $variadic ? min( $position, $declared - 1 ) : min( $position, $declared );
			}
			if ( ! isset( $slots[ $index ] ) ) {
				$slots[ $index ] = $slot;
			} else {
				$slots[ $index ] = WP_Invariant_Types::merge_slots( $slots[ $index ], $slot );
			}
		}

		$described = array();
		foreach ( $parameters as $index => $parameter ) {
			$rows = isset( $slots[ $index ] ) ? self::rows( $slots[ $index ] ) : array();
			if ( ! $parameter->isVariadic() ) {
				// A parameter was not passed when the call had no more arguments than its position.
				$missing = array( 0, 0 );
				foreach ( $profile['arguments'] as $count => $counts ) {
					if ( $count <= $index ) {
						$missing = array( $missing[0] + $counts[0], $missing[1] + $counts[1] );
					}
				}
				if ( $missing[0] + $missing[1] > 0 ) {
					$rows[] = self::row( '(not passed)', $missing );
				}
			}

			$name        = $parameter->getName();
			$described[] = array(
				'name'    => ( $parameter->isVariadic() ? '...$' : '$' ) . $name,
				'native'  => $parameter->hasType() ? (string) $parameter->getType() : null,
				'phpdoc'  => isset( $documented['param'][ $name ] ) ? $documented['param'][ $name ] : null,
				'phpstan' => isset( $documented['phpstan-param'][ $name ] ) ? $documented['phpstan-param'][ $name ] : null,
				'rows'    => self::sort_rows( $rows ),
			);
		}

		// Without reflection, arguments are only known by position.
		foreach ( $slots as $index => $slot ) {
			if ( $index >= $declared && ! ( $variadic && $index === $declared - 1 ) ) {
				$described[] = array(
					'name'    => $reflection ? '(extra)' : "#{$index}",
					'native'  => null,
					'phpdoc'  => null,
					'phpstan' => null,
					'rows'    => self::sort_rows( self::rows( $slot ) ),
				);
			}
		}

		$markers = array();
		if ( $target['private'] ) {
			$markers[] = 'private';
		}
		if ( 'public' !== $target['visibility'] ) {
			$markers[] = "{$target['visibility']} method";
		}
		foreach ( array( 'static', 'magic', 'deprecated' ) as $marker ) {
			if ( $target[ $marker ] ) {
				$markers[] = $marker;
			}
		}
		// The extension gives a generator no post hooks, so its return values are not counted.
		if ( $reflection && $reflection->isGenerator() ) {
			$markers[] = 'generator';
		}

		return array(
			'file'       => WP_Invariant::relative_path( $reflection ? $reflection->getFileName() : $target['file'] ),
			'line'       => $reflection ? $reflection->getStartLine() : $target['line'],
			'markers'    => $markers,
			'calls'      => array(
				'core'  => $profile['calls'][0],
				'tests' => $profile['calls'][1],
			),
			'parameters' => $described,
			'return'     => array(
				'native'  => $reflection && $reflection->hasReturnType() ? (string) $reflection->getReturnType() : null,
				'phpdoc'  => isset( $documented['return'] ) ? $documented['return'] : null,
				'phpstan' => isset( $documented['phpstan-return'] ) ? $documented['phpstan-return'] : null,
				'rows'    => isset( $profile['slots']['return'] ) ? self::sort_rows( self::rows( $profile['slots']['return'] ) ) : array(),
			),
		);
	}

	/**
	 * Returns the reflection of a function or method, if it is loaded.
	 *
	 * @param array $target From WP_Invariant::functions_in().
	 * @return ReflectionFunctionAbstract|null
	 */
	private static function reflect( array $target ) {
		if ( null === $target['class'] ) {
			return function_exists( $target['function'] ) ? new ReflectionFunction( $target['function'] ) : null;
		}

		$loaded = class_exists( $target['class'], false ) || interface_exists( $target['class'], false ) || trait_exists( $target['class'], false ) || enum_exists( $target['class'], false );
		return $loaded && method_exists( $target['class'], $target['function'] ) ? new ReflectionMethod( $target['class'], $target['function'] ) : null;
	}

	/**
	 * Returns the types a doc comment declares, as written.
	 *
	 * @param string $doc Doc comment.
	 * @return array With `param` and `phpstan-param`, by parameter name without `$`, and `return` and `phpstan-return`.
	 */
	public static function doc_types( $doc ) {
		$types = array(
			'param'         => array(),
			'phpstan-param' => array(),
		);
		if ( '' === $doc ) {
			return $types;
		}

		// Without the comment's delimiters and the asterisks which start its lines.
		$text = preg_replace( '~^\s*/\*\*|\*/\s*$~', '', $doc );
		$text = preg_replace( '~^[ \t]*\*[ \t]?~m', '', $text );

		preg_match_all( '~@(param|phpstan-param|return|phpstan-return)[ \t]+~', $text, $tags, PREG_SET_ORDER | PREG_OFFSET_CAPTURE );
		foreach ( $tags as $tag ) {
			$kind = $tag[1][0];
			$at   = $tag[0][1] + strlen( $tag[0][0] );
			$type = self::read_type( $text, $at );
			if ( '' === $type ) {
				continue;
			}

			if ( 'return' === $kind || 'phpstan-return' === $kind ) {
				if ( ! isset( $types[ $kind ] ) ) {
					$types[ $kind ] = $type;
				}
				continue;
			}

			if ( preg_match( '~\G\s*&?(?:\.\.\.)?\$(\w+)~', $text, $match, 0, $at ) && ! isset( $types[ $kind ][ $match[1] ] ) ) {
				$types[ $kind ][ $match[1] ] = $type;
			}
		}

		return $types;
	}

	/**
	 * Reads a type from a doc comment, which can span lines inside brackets, such as `array{a: int}`.
	 *
	 * @param string $text Doc comment text.
	 * @param int    $at   Where the type starts; set to where it ends.
	 * @return string
	 */
	private static function read_type( $text, &$at ) {
		$depth  = 0;
		$type   = '';
		$length = strlen( $text );
		for ( ; $at < $length; $at++ ) {
			$char = $text[ $at ];
			if ( false !== strpos( '<{([', $char ) ) {
				++$depth;
			} elseif ( false !== strpos( '>})]', $char ) && $depth > 0 && '-' !== substr( $type, -1 ) ) {
				--$depth;
			} elseif ( 0 === $depth && ctype_space( $char ) ) {
				// A callable's return type follows its colon, as in `callable(): void`.
				if ( ':' !== substr( $type, -1 ) ) {
					break;
				}
				continue;
			}
			$type .= $char;
		}

		return preg_replace( '~\s+~', ' ', trim( $type ) );
	}

	/**
	 * Returns the counted types of a slot as rows: the type, and how often it came from WordPress and from tests.
	 *
	 * @param array $slot From WP_Invariant_Types.
	 * @return array[]
	 */
	public static function rows( array $slot ) {
		$rows  = array();
		$types = $slot['types'];
		if ( isset( $types['true'], $types['false'] ) ) {
			$rows[] = self::row( 'bool', array( $types['true'][0] + $types['false'][0], $types['true'][1] + $types['false'][1] ) );
			unset( $types['true'], $types['false'] );
		}

		foreach ( $types as $type => $counts ) {
			if ( 'array{...}' === $type || 'stdClass{...}' === $type ) {
				$kind = substr( $type, 0, -5 );
				$type = self::record_type( $kind, $slot['records'][ $kind ] );
			}
			$rows[] = self::row( $type, $counts );
		}

		$strings = $slot['strings'];
		if ( $strings['widened'] ) {
			$rows[] = self::row( 'string', $strings['total'] );
		} else {
			foreach ( $strings['values'] as $value => $counts ) {
				$rows[] = self::row( var_export( (string) $value, true ), $counts );
			}
		}

		return $rows;
	}

	/**
	 * Formats a record of first-level keys as a shape, such as `array{post_type: string, paged?: int}`.
	 *
	 * A key is optional, with `?`, when some values in the record did not have it.
	 *
	 * @param string $kind   `array` or `stdClass`.
	 * @param array  $record From WP_Invariant_Types.
	 * @return string
	 */
	public static function record_type( $kind, array $record ) {
		$fields = array();
		foreach ( $record['keys'] as $key => $entry ) {
			$key      = is_int( $key ) || preg_match( '~^[A-Za-z_][A-Za-z0-9_]*$~', $key ) ? $key : var_export( (string) $key, true );
			$optional = $entry['present'] < $record['count'] ? '?' : '';
			$fields[] = "{$key}{$optional}: " . WP_Invariant_Types::join_types( array_keys( $entry['types'] ) );
		}

		return $kind . '{' . implode( ', ', $fields ) . '}';
	}

	/**
	 * Makes a row.
	 *
	 * @param string $type   Type.
	 * @param int[]  $counts From WordPress, and from tests.
	 * @return array
	 */
	private static function row( $type, array $counts ) {
		return array(
			'type'  => $type,
			'core'  => $counts[0],
			'tests' => $counts[1],
		);
	}

	/**
	 * Sorts rows by how often they were seen, most often first.
	 *
	 * @param array[] $rows Rows.
	 * @return array[]
	 */
	private static function sort_rows( array $rows ) {
		usort(
			$rows,
			static function ( $a, $b ) {
				$difference = ( $b['core'] + $b['tests'] ) - ( $a['core'] + $a['tests'] );
				return 0 !== $difference ? $difference : strcmp( $a['type'], $b['type'] );
			}
		);
		return $rows;
	}

	/**
	 * Orders functions before methods, then by name, ignoring case.
	 *
	 * @param string $a Target, perhaps followed by `()`.
	 * @param string $b Target, perhaps followed by `()`.
	 * @return int
	 */
	private static function compare_targets( $a, $b ) {
		$a_method = false !== strpos( $a, '::' );
		$b_method = false !== strpos( $b, '::' );
		if ( $a_method !== $b_method ) {
			return $a_method ? 1 : -1;
		}

		return strcasecmp( $a, $b );
	}

	/**
	 * Formats the report as Markdown, in the layout of a type listing.
	 *
	 * @param array $data What data() gathered.
	 * @return string
	 */
	public static function markdown( array $data ) {
		$summary = $data['summary'];
		$lines   = array(
			'# Argument and return types',
			'',
			sprintf(
				'%s of %s WordPress functions and methods were called, %s times in all. Each type shows how often it came from WordPress, then from tests, next to the types the function declares. Strings are shown as literals until there are more than %d distinct ones, or one of %d bytes or more; `true` and `false` are shown apart until both are seen.',
				number_format( $summary['called'] ),
				number_format( $summary['targets'] ),
				number_format( $summary['calls'] ),
				WP_Invariant_Types::STRING_LITERALS,
				WP_Invariant_Types::STRING_LENGTH
			),
		);

		// Functions come first, then the methods of each class, each under a heading.
		$heading = false;
		$block   = array();
		foreach ( $data['functions'] as $target => $function ) {
			$parts = explode( '::', $target );
			$group = count( $parts ) > 1 ? $parts[0] : null;
			if ( $group !== $heading ) {
				$lines   = array_merge( $lines, self::code_block( $block ) );
				$block   = array();
				$lines[] = '';
				$lines[] = null === $group ? '## Functions' : "## {$group}";
				$heading = $group;
			}

			$block = array_merge( $block, self::function_lines( $target, $function ) );
		}
		$lines = array_merge( $lines, self::code_block( $block ) );

		if ( array() !== $data['never_called'] ) {
			$lines[] = '';
			$lines[] = '## Never called';
			$lines[] = '';
			$lines[] = sprintf( '%s functions and methods were not called by the tests.', number_format( count( $data['never_called'] ) ) );
			$lines   = array_merge( $lines, self::code_block( $data['never_called'] ) );
		}

		return implode( "\n", $lines ) . "\n";
	}

	/**
	 * Formats one function or method as lines of the listing.
	 *
	 * @param string $target    Function or method.
	 * @param array  $described From describe().
	 * @return string[]
	 */
	private static function function_lines( $target, array $described ) {
		$markers = array() === $described['markers'] ? '' : '  [' . implode( ', ', $described['markers'] ) . ']';
		$lines   = array(
			"{$target}()  {$described['file']}:{$described['line']}{$markers}",
			'    calls: ' . self::counts( $described['calls']['core'], $described['calls']['tests'] ),
		);

		foreach ( $described['parameters'] as $parameter ) {
			$lines = array_merge( $lines, self::slot_lines( $parameter['name'], $parameter, '@param' ) );
		}
		if ( array() !== $described['return']['rows'] || null !== $described['return']['phpdoc'] ) {
			$lines = array_merge( $lines, self::slot_lines( 'return', $described['return'], '@return' ) );
		}
		$lines[] = '';

		return $lines;
	}

	/**
	 * Formats one parameter or return value: its documented types, then a line for each type seen.
	 *
	 * @param string $label  Parameter name, or `return`.
	 * @param array  $slot   With `native`, `phpdoc`, `phpstan` and `rows`.
	 * @param string $phpdoc `@param` or `@return`.
	 * @return string[]
	 */
	private static function slot_lines( $label, array $slot, $phpdoc ) {
		$documented = array();
		if ( null !== $slot['native'] ) {
			$documented[] = "native {$slot['native']}";
		}
		if ( null !== $slot['phpdoc'] ) {
			$documented[] = "{$phpdoc} {$slot['phpdoc']}";
		}
		if ( null !== $slot['phpstan'] ) {
			$documented[] = '@phpstan-' . substr( $phpdoc, 1 ) . " {$slot['phpstan']}";
		}

		$lines = array( rtrim( '    ' . str_pad( $label, 30 ) . implode( '  ', $documented ) ) );
		foreach ( $slot['rows'] as $row ) {
			$lines[] = sprintf( '%12s: %s  (%s)', number_format( $row['core'] + $row['tests'] ), str_pad( $row['type'], 22 ), self::counts( $row['core'], $row['tests'], false ) );
		}

		return $lines;
	}

	/**
	 * Formats counts from WordPress and from tests.
	 *
	 * @param int  $core  From WordPress.
	 * @param int  $tests From tests.
	 * @param bool $total Whether to start with the total.
	 * @return string
	 */
	private static function counts( $core, $tests, $total = true ) {
		$parts = array();
		if ( $core > 0 || 0 === $tests ) {
			$parts[] = number_format( $core ) . ' core';
		}
		if ( $tests > 0 ) {
			$parts[] = number_format( $tests ) . ' tests';
		}

		return $total ? sprintf( '%s (%s)', number_format( $core + $tests ), implode( ', ', $parts ) ) : implode( ', ', $parts );
	}

	/**
	 * Wraps lines in a code block, or returns nothing for no lines.
	 *
	 * @param string[] $lines Lines.
	 * @return string[]
	 */
	private static function code_block( array $lines ) {
		if ( array() === $lines ) {
			return array();
		}

		while ( '' === end( $lines ) ) {
			array_pop( $lines );
		}
		return array_merge( array( '', '```' ), $lines, array( '```' ) );
	}

	/**
	 * Returns a short summary of the report, for the summary of a CI run.
	 *
	 * @param array  $data What data() gathered.
	 * @param string $name Base name of the report files.
	 * @return string
	 */
	private static function summary( array $data, $name ) {
		$summary = $data['summary'];
		return implode(
			"\n",
			array(
				'## Argument and return types',
				'',
				sprintf(
					'%s of %s WordPress functions and methods were called, %s times in all%s. %s were never called. The full report is `%s.md` and `%s.json`, in the `invariant-report` artifact.',
					number_format( $summary['called'] ),
					number_format( $summary['targets'] ),
					number_format( $summary['calls'] ),
					$summary['processes'] ? sprintf( ', including %s separate processes', number_format( $summary['processes'] ) ) : '',
					number_format( $summary['never_called'] ),
					$name,
					$name
				),
			)
		) . "\n";
	}
}
