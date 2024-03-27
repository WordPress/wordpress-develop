<?php

class WP_Token_Set {
	const MAX_LENGTH = 256;

	private $key_length = 2;

	/**
	 * Stores an optimized form of the word set, where words are grouped
	 * by first two letters and then collapsed into a string.
	 *
	 * @var array
	 */
	private $large_words = array();

	/**
	 * Stores an optimized row of short words, where every entry is two
	 * bytes long and zero-extended if the word is only a single byte.
	 *
	 * @var string
	 */
	private $small_words = '';

	public static function from_array( $words, $key_length = 2 ) {
		$set             = new WP_Token_Set();
		$set->key_length = $key_length;

		// Start by grouping words.

		$groups = array();
		$shorts = array();
		foreach ( $words as $word ) {
			if ( ! is_string( $word ) || self::MAX_LENGTH <= strlen( $word ) ) {
				return null;
			}

			$length = strlen( $word );

			if ( $key_length >= $length ) {
				$shorts[] = $word;
			} else {
				$group = substr( $word, 0, $key_length );

				if ( ! isset( $groups[ $group ] ) ) {
					$groups[ $group ] = array();
				}

				$groups[ $group ][] = substr( $word, $key_length );
			}
		}

		// Sort the words by longest-first, then alphabetical.

		usort( $shorts, array( self::class, 'longest_first_then_alphabetical' ) );
		foreach ( $groups as $group_key => $group ) {
			usort( $groups[ $group_key ], array( self::class, 'longest_first_then_alphabetical' ) );
		}

		// Finally construct the optimized lookups.

		foreach ( $shorts as $word ) {
			$set->small_words .= str_pad( $word, $key_length, "\x00" );
		}

		foreach ( $groups as $group => $group_words ) {
			$group_string = '';

			foreach ( $group_words as $word ) {
				$group_string .= chr( strlen( $word ) ) . $word;
			}

			$set->large_words[ $group ] = $group_string;
		}

		return $set;
	}

	public static function from_precomputed_table( $key_length, $large_words, $small_words ) {
		$set = new WP_Token_Set();

		$set->key_length  = $key_length;
		$set->large_words = $large_words;
		$set->small_words = $small_words;

		return $set;
	}

	public function contains( $word ) {
		if ( $this->key_length >= strlen( $word ) ) {
			return str_contains( $this->small_words, str_pad( $word, $this->key_length, "\x00" ) );
		}

		$group_key = substr( $word, 0, $this->key_length );
		if ( ! isset( $this->large_words[ $group_key ] ) ) {
			return false;
		}

		$group  = $this->large_words[ $group_key ];
		$slug   = substr( $word, $this->key_length );
		$length = strlen( $slug );
		$at     = 0;
		while ( $at < strlen( $group ) ) {
			$token_length = ord( $group[ $at++ ] );
			if ( $token_length === $length && 0 === substr_compare( $group, $slug, $at, $token_length ) ) {
				return true;
			}

			$at += $token_length;
		}

		return false;
	}

	public function read_token( $text, $offset ) {
		$text_length = strlen( $text );

		// Search for a long word first, if the text is long enough, and if that fails, a short one.
		if ( $this->key_length < $text_length ) {
			$group_key = substr( $text, $offset, $this->key_length );

			if ( ! isset( $this->large_words[ $group_key ] ) ) {
				return false;
			}

			$group        = $this->large_words[ $group_key ];
			$group_length = strlen( $group );
			$at           = 0;
			while ( $at < $group_length ) {
				$token_length = ord( $group[ $at++ ] );
				$token        = substr( $group, $at, $token_length );

				if ( 0 === substr_compare( $text, $token, $offset + $this->key_length, $token_length ) ) {
					return $group_key . $token;
				}

				$at += $token_length;
			}
		}

		// Perhaps a short word then.
		$small_text = str_pad( substr( $text, $offset, $this->key_length ), $this->key_length, "\x00" );
		$at         = strpos( $this->small_words, $small_text );

		return false !== $at
			? rtrim( substr( $this->small_words, $at, $this->key_length ), "\x00" )
			: false;
	}

	public function to_array() {
		$tokens = array();

		$at = 0;
		while ( $at < strlen( $this->small_words ) ) {
			$tokens[] = rtrim( substr( $this->small_words, $at, $this->key_length ), "\x00" );
			$at      += $this->key_length;
		}

		foreach ( $this->large_words as $prefix => $group ) {
			$at = 0;
			while ( $at < strlen( $group ) ) {
				$length   = ord( $group[ $at++ ] );
				$tokens[] = $prefix . rtrim( substr( $group, $at, $length ), "\x00" );
				$at      += $length;
			}
		}

		return $tokens;
	}

	public function precomputed_php_source_table( $indent = "\t" ) {
		$i1 = $indent;
		$i2 = $indent . $indent;

		$output  = self::class . "::from_precomputed_table(\n";
		$output .= "{$i1}{$this->key_length},\n";
		$output .= "{$i1}array(\n";

		$prefixes = array_keys( $this->large_words );
		sort( $prefixes );
		foreach ( $prefixes as $prefix ) {
			$group        = $this->large_words[ $prefix ];
			$comment_line = "{$i2}//";
			$data_line    = "{$i2}'{$prefix}' => \"";
			$at           = 0;
			while ( $at < strlen( $group ) ) {
				$length = ord( $group[ $at++ ] );
				$digits = str_pad( dechex( $length ), 2, '0', STR_PAD_LEFT );
				$token  = substr( $group, $at, $length );
				$at    += $length;

				$comment_line .= " &{$prefix}{$token}";
				$data_line    .= "\\x{$digits}{$token}";
			}
			$comment_line .= "\n";
			$data_line    .= "\",\n";

			$output .= $comment_line;
			$output .= $data_line;
		}

		$output .= "{$i1}),\n";

		$small_words = array();
		$at          = 0;
		while ( $at < strlen( $this->small_words ) ) {
			$small_words[] = substr( $this->small_words, $at, $this->key_length );
			$at           += $this->key_length;
		}
		sort( $small_words );

		$small_text = str_replace( "\x00", '\x00', implode( '', $small_words ) );
		$output    .= "{$i1}\"{$small_text}\"\n";
		$output    .= ");\n";

		return $output;
	}

	private static function longest_first_then_alphabetical( $a, $b ) {
		if ( $a === $b ) {
			return 0;
		}

		$la = strlen( $a );
		$lb = strlen( $b );

		// Longer strings are less-than for comparison's sake.
		if ( $la !== $lb ) {
			return $lb - $la;
		}

		return strcmp( $a, $b );
	}
}
