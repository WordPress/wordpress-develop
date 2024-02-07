<?php

require_once( __DIR__ . '/src/wp-load.php' );

function main() {
	global $argv;

	$opts = getopt( 'A:B:p:i:m:', [] );

	if ( ! isset( $opts['p'] ) ) {
		die( 'Please supply a search pattern with -p, e.g. `-p "[a-f0-9]+"`' );
	}

	if ( ! isset( $opts['i'] ) && ! in_array( '-', $argv, true ) ) {
		die( 'Please specify input filename with -i or use stdin with -, e.g. `-i file.html`' );
	}

	$lines_before = ctype_digit( $opts['B'] ?? '' ) ? intval( $opts['B'] ) : 0;
	$lines_after = ctype_digit( $opts['A'] ?? '' ) ? intval( $opts['A'] ) : 0;

	$max = ( isset( $opts['m'] ) && ctype_digit( $opts['m'] ) && (int) $opts['m'] > 0 )
		? (int) $opts['m']
		: 1;

	$input = in_array( '-', $argv, true ) ? 'php://stdin' : $opts['i'];
	Grepper::scan( $input, $opts['p'], $lines_before, $lines_after, $max );
}

class Debugger extends WP_HTML_Tag_Processor {
	public function h() {
		return $this->html;
	}

	public function extend( $line ) {
		$this->html .= $line;

		if (
			$this->parser_state === self::STATE_COMPLETE ||
			$this->parser_state === self::STATE_INCOMPLETE_INPUT
		) {
			$this->parser_state = self::STATE_READY;
		}
	}

	public function next_token() {
		$r = parent::next_token();
		$this->set_bookmark( 'here' );
		return $r;
	}

	public function at() {
		return $this->bookmarks['here'];
	}
}

class Grepper {
	public static function scan( $input, $pattern, $before, $after, $max ) {
		$f         = fopen( $input, 'r' );
		$c         = 0;
		$n         = 0;
		$lines     = [];
		$lc        = 1 + $before + $after;
		$o         = static function ( $s ) { return html_entity_decode( $s, ENT_HTML5 | ENT_QUOTES ); };
		$ws        = static function ( $s ) { return preg_replace( '~[ \r\f\t\n]+~', ' ', $s ); };
		$pre_depth = 0;
		$p         = new Debugger( '' );
		$t         = '';

		while ( false !== ( $line = fgets( $f ) ) ) {
			$n++;

			$p->extend( $line );
			while ( $p->next_token() ) {
				$at        = $p->at();
				$type      = $p->get_token_type();
				$node_text = $o( $p->get_modifiable_text() );
				$node_text = $pre_depth > 0 ? $node_text : $ws( $node_text );

				if ( '#tag' !== $type && '#text' !== $type ) {
					continue;
				}

				switch ( $p->get_token_name() ) {
					case 'PRE':
						$pre_depth += $p->is_tag_closer() ? -1 : 1;
						break;

					case '#text':
						$t .= $node_text;
				}

				if ( preg_match( $pattern, $t, $match, PREG_OFFSET_CAPTURE ) ) {
					$h = (
						"\e[32m" .
						ltrim( substr( $t, 0, $match[0][1] ) ) .
						"\e[33m" .
						$match[0][0] .
						"\e[32m" .
						rtrim( substr( $t, $match[0][1] + strlen( $match[0][0] ) ) ) .
						"\e[90m"
					);

					for ( $i = 0; $i < $after; $i++ ) {
						$line = fgets( $f );
						if ( false !== $line ) {
							$p->extend( $line );
						}
					}

					$cb = substr( $p->h(), 0, $at->start );
					$cc = substr( $p->h(), $at->start, $at->length );
					$ca = substr( $p->h(), $at->start + $at->length );

					// Limit context to N lines preview
					$cb = explode( "\n", $cb );
					$cb = array_slice( $cb, -$before );
					$cb = substr( implode( "\n", $cb ), -$before * 80 );

					// Limit context to N lines preview
					$ca = explode( "\n", $ca );
					$ca = array_slice( $ca, 0, $after );
					$ca = substr( implode( "\n", $ca ), 0, $after * 80 );

					// If contained in last node.
					$tt = $p->get_modifiable_text();
					if ( preg_match( $pattern, $tt, $mm, PREG_OFFSET_CAPTURE ) ) {
						$cc = (
							"\e[90m" .
							substr( $tt, 0, $mm[0][1] ) .
							"\e[33m" .
							$mm[0][0] .
							"\e[90m" .
							substr( $tt, $mm[0][1] + strlen( $mm[0][0] ) )
						);
					}

					echo "\n\e[32m{$n}\e[90m: \e[31m{$p->get_token_name()} \e[90m{$h}\e[m\n";
					echo "\e[90m{$cb}\e[33m{$cc}\e[90m{$ca}\e[m";

					if ( ++$c >= $max ) {
						fclose( $f );
						exit;
					}

					$t = '';
				}

				$t = substr( $t, -100 );
			}

		}
	}

	public static function indent( $lines ) {
		return implode( "\n", array_map(
			static function ( $line ) { return '    ' . $line; },
			explode( "\n", $lines )
		) );
	}
}

main();

function is_line_breaker( $tag_name ) {
	switch ( $tag_name ) {
		case 'BLOCKQUOTE':
		case 'BR':
		case 'DD':
		case 'DIV':
		case 'DL':
		case 'DT':
		case 'H1':
		case 'H2':
		case 'H3':
		case 'H4':
		case 'H5':
		case 'H6':
		case 'HR':
		case 'LI':
		case 'OL':
		case 'P':
		case 'UL':
			return true;
	}

	return false;
}
