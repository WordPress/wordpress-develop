<?php
/**
 * Invariants of PCRE use.
 *
 * These hook built-in PHP functions, which slows down every call to a built-in
 * function. Leave them out with WP_INVARIANTS_SKIP=pcre.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

/*
 * Where in the subject a regular expression's match or group starts, by group, when
 * that is where the HTML API finds HTML syntax, and the expression would not start
 * there without that syntax: `<p` or `href="` would not, but `^.`, `//`, or `\w+` at
 * a tag name, would.
 */
$on_syntax = static function ( $pattern, $subject, $offset, $limit ) {
	if ( ! is_string( $pattern ) || ! is_string( $subject ) || false === strpos( $subject, '<' ) ) {
		return null;
	}

	$boundaries = WP_Invariant_HTML_API::boundaries( $subject );
	if ( empty( $boundaries ) || ! preg_match_all( $pattern, $subject, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE, $offset ) ) {
		return null;
	}

	foreach ( $limit >= 0 ? array_slice( $matches, 0, $limit ) : $matches as $match ) {
		foreach ( $match as $group => $capture ) {
			$at = $capture[1];
			if ( $at < 0 || ! isset( $boundaries[ $at ] ) ) {
				continue;
			}

			/*
			 * The syntax is the `<` which starts a tag or comment, or what comes just before a tag name,
			 * an attribute or its value, such as `</` or `="`, but not the name or value itself.
			 */
			$kind    = $boundaries[ $at ];
			$neutral = $subject;
			$syntax  = in_array( $kind, array( 'tag', 'comment', 'DOCTYPE' ), true ) ? array( $at ) : array( $at - 1 );
			if ( 'tag name' === $kind && $at >= 2 && '<' === $subject[ $at - 2 ] ) {
				$syntax[] = $at - 2;
			}
			foreach ( $syntax as $byte ) {
				$neutral[ $byte ] = "\x01";
			}

			$still = array();
			if ( preg_match_all( $pattern, $neutral, $neutral_matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE, $offset ) ) {
				foreach ( $neutral_matches as $neutral_match ) {
					foreach ( $neutral_match as $neutral_capture ) {
						$still[ $neutral_capture[1] ] = true;
					}
				}
			}
			if ( ! isset( $still[ $at ] ) ) {
				return array( $group, $at, $kind );
			}
		}
	}

	return null;
};

return array(
	WP_Invariant::define( 'no-silent-failure' )
		->describe( 'No PCRE function fails: none runs out of backtracking or recursion, or is given invalid UTF-8 for a /u pattern. Those failures return null or false, which callers rarely check.' )
		->include_test_callers()
		->after_each(
			array( 'preg_match', 'preg_match_all', 'preg_replace', 'preg_replace_callback', 'preg_split' ),
			static function ( WP_Invariant_Call $call ) {
				// Read it first: nothing before this line calls a PCRE function.
				$error = preg_last_error();
				if ( PREG_NO_ERROR === $error ) {
					return;
				}

				$subject = $call->arg( 'subject' );
				$call->expect(
					false,
					'%s() failed: %s. The pattern was %s, and the subject %s.',
					$call->target(),
					preg_last_error_msg(),
					$call->arg( 'pattern' ),
					is_string( $subject ) ? strlen( $subject ) . ' bytes' : 'not a string'
				);
			}
		),

	WP_Invariant::define( 'matches-no-html-syntax' )
		->describe( 'Regular expressions in WordPress do not parse HTML: no match or group which a PCRE function returns starts where the HTML API finds a tag, a tag name, an attribute, an attribute value, a comment or a DOCTYPE, unless the pattern would start there without that syntax, as `^.` or `\w+` would. So no replacement or split starts there either. Each place in WordPress is reported once.' )
		->state(
			static function () {
				return array();
			},
			'run'
		)
		->after_each(
			array( 'preg_match', 'preg_match_all', 'preg_replace', 'preg_replace_callback', 'preg_replace_callback_array', 'preg_split' ),
			static function ( WP_Invariant_Call $call, array &$reported ) use ( $on_syntax ) {
				// A call which failed found nothing, and the error must stay for its caller to read.
				if ( PREG_NO_ERROR !== preg_last_error() ) {
					return;
				}

				// Without $matches, preg_match() returns no match, only whether there is one.
				$target = $call->target();
				if ( ( 'preg_match' === $target || 'preg_match_all' === $target ) && count( $call->args() ) < 3 ) {
					return;
				}

				$subject  = $call->arg( 'subject' );
				$subjects = array_slice( array_filter( (array) $subject, 'is_string' ), 0, 8 );
				$html     = false;
				foreach ( $subjects as $text ) {
					$html = $html || false !== strpos( $text, '<' );
				}
				if ( ! $html ) {
					return;
				}

				$caller = $call->caller();
				$site   = $caller['file'] . ':' . $caller['line'];
				if ( isset( $reported[ $site ] ) || ! WP_Invariant::is_core_file( $caller['file'] ) ) {
					return;
				}

				$replaces = in_array( $target, array( 'preg_replace', 'preg_replace_callback', 'preg_replace_callback_array' ), true );
				$offset   = in_array( $target, array( 'preg_match', 'preg_match_all' ), true ) ? (int) $call->arg( 'offset' ) : 0;
				$limit    = 'preg_match' === $target ? 1 : -1;
				if ( $replaces ) {
					$limit = (int) $call->arg( 'limit' ) > 0 ? (int) $call->arg( 'limit' ) : -1;
				} elseif ( 'preg_split' === $target ) {
					$limit = (int) $call->arg( 'limit' ) > 0 ? (int) $call->arg( 'limit' ) - 1 : -1;
				}

				$patterns     = 'preg_replace_callback_array' === $target ? array_keys( (array) $call->arg( 'pattern' ) ) : array_values( (array) $call->arg( 'pattern' ) );
				$replacements = 'preg_replace' === $target ? $call->arg( 'replacement' ) : null;
				$replacements = is_array( $replacements ) ? array_values( $replacements ) : $replacements;
				$found        = null;
				foreach ( $subjects as $text ) {
					foreach ( $patterns as $index => $pattern ) {
						$found = $on_syntax( $pattern, $text, $offset, $limit );
						// Later patterns see what earlier ones replaced, which only preg_replace() can work out again without running a callback.
						if ( null !== $found || 'preg_replace' !== $target || ! is_string( $text ) ) {
							break;
						}
						$replacement = is_array( $replacements ) ? ( isset( $replacements[ $index ] ) ? $replacements[ $index ] : '' ) : $replacements;
						$text        = preg_replace( $pattern, $replacement, $text, $limit );
					}
					if ( null !== $found ) {
						break;
					}
				}

				// Running the patterns again must leave no error for the code under test to find.
				if ( PREG_NO_ERROR !== preg_last_error() ) {
					preg_match( '/^/', '' );
				}
				if ( null === $found ) {
					return;
				}

				list( $group, $at, $kind ) = $found;
				$reported[ $site ]         = true;
				$stack                     = $call->stack( 1 );
				$call->expect(
					false,
					'%s() in %s matched HTML syntax with %s: %s starts at the %s at byte %d of %s',
					$target,
					$stack ? $stack[0] : $site,
					$pattern,
					0 === $group ? 'the match' : "group {$group}",
					$kind,
					$at,
					substr( $text, max( 0, $at - 40 ), 120 )
				);
			}
		),
);
