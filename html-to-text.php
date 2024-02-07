<?php

$ansi = true;

require_once __DIR__ . '/src/wp-load.php';

$opts     = getopt( 'b:' );
$base_url = isset( $opts['b'] ) ? $opts['b'] : null;

$html = file_get_contents( 'php://stdin' );

// Preprocess the input stream.
$html = str_replace( "\x00", '�', $html );
$html = str_replace( "\r\n", "\n", $html );
$html = str_replace( "\r", "\n", $html );

$p = new WP_HTML_Tag_Processor( $html );

$text_content  = '';
$in_pre        = false;
$needs_newline = false;
$text_buffer   = '';
$prev_was_tag  = false;
$prev_was_li   = false;
$has_seen_head = false;

while ( $p->next_token() ) {
	$node_name = $p->get_token_name();

	$node_text = html_entity_decode( $p->get_modifiable_text(), ENT_HTML5 | ENT_QUOTES );
	$tag_name  = '#tag' === $p->get_token_type()
		? ( ( $p->is_tag_closer() ? '-' : '+' ) . $node_name )
		: $node_name;

	if ( '#tag' === $p->get_token_type() && ! $p->is_tag_closer() && is_line_breaker( $node_name ) ) {
		$needs_newline = ! $prev_was_li;
	}

	if ( $ansi ) {
		if (
			'+MAIN' === $tag_name ||
			'main' === $p->get_attribute( 'role' ) ||
			'main-content' === $p->get_attribute( 'id' ) || // cloudflare.
			'hnmain' === $p->get_attribute( 'id' )    // Hackernews.
		) {
			$text_content .= "\e]1337;SetMark\x07";
		}

		switch ( $tag_name ) {
			case '+A':
				$href = $p->get_attribute( 'href' );
				if ( is_string( $href ) && preg_match( '~^https?://~', $href ) ) {
					// External link, probably.
					$text_content .= "\e[32m\e]8;;{$href}\x07";
				} elseif ( str_starts_with( $href, 'javascript:' ) ) {
					break;
				} else {
					// Internal link, probably.
					$text_content .= "\e[90m\e]8;;{$base_url}{$href}\x07";
				}
				break;

			case '-A':
				$text_content .= "\e]8;;\x07\e[m";
				break;

			case '+B':
			case '+STRONG':
				$text_content .= "\e[2m";
				break;

			case '-B':
			case '-STRONG':
				$text_content .= "\e[22m";
				break;

			case '+C-':
				$rgb = color_for_syntax_element( $p );
				if ( null !== $rgb ) {
					$text_content .= "\e[38;2;{$rgb[0]};{$rgb[1]};{$rgb[2]}m";
				}
				break;

			case '-C-':
				$text_content .= "\e[m";
				break;

			case '+I':
			case '+EM':
				$text_content .= "\e[3m";
				break;

			case '-I':
			case '-EM':
				$text_content .= "\e[23m";
				break;


			case 'TITLE':
				$text_content .= "\e]0;{$node_text}\x07";
				break;
		}
	}

	switch ( $tag_name ) {
		case '+LI':
			$text_content .= "\n - ";
			$needs_newline = false;
			break;

		case '+H1':
		case '+H2':
		case '+H3':
		case '+H4':
		case '+H5':
		case '+H6':
			$text_content .= "\n\n" . str_pad( '', intval( $node_name[1] ), '#' ) . ' ';
			$needs_newline = false;
			break;

		case '+CITE':
			$text_content .= ' «';
			break;

		case '-CITE':
			$text_content .= '»';
			break;

		case '+CODE':
		case '-CODE':
			if ( $ansi && ! $p->is_tag_closer() ) {
				$text_content .= "\e[90m";
			}
			if ( $in_pre ) {
				$text_content .= $p->is_tag_closer() ? "\n```" : "\n```\n";
			} else {
				$text_content .= '`';
			}
			if ( $ansi && $p->is_tag_closer() ) {
				$text_content .= "\e[m";
			}
			break;

		case '+DT':
			$text_content .= "\n\n✏️  ";
			$needs_newline = false;
			break;

		case '+DD':
			$text_content .= "\n  📝  ";
			$needs_newline = false;
			break;

		case '+IMG':
			$alt = $p->get_attribute( 'alt' );
			if ( is_string( $alt ) && ! empty( $alt ) ) {
				$text_content .= "[\e[31m{$alt}\e[m]";
			}
			break;

		case '+PRE':
		case '-PRE':
			$in_pre = ! $p->is_tag_closer();
			break;

		case '#text':
			if ( $needs_newline ) {
				$text_content .= "\n\n";
				$needs_newline = false;
			}
			$text_content .= $in_pre ? $node_text : preg_replace( '~[ \t\r\f\n]+~', ' ', $node_text );
	}

	$prev_was_li = '+LI' === $tag_name;
}

echo trim( $text_content );

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

function color_for_syntax_element( $processor ) {
	static $colors = [
		'a' => [0x99, 0x00, 0x55],
		'b' => [0x99, 0x00, 0x55],
		'c' => [0x70, 0x80, 0x90],
		'd' => [0x70, 0x80, 0x90],
		'e' => [0x00, 0x77, 0xaa],
		'f' => [0x66, 0x99, 0x00],
		'g' => [0x22, 0x22, 0x22],
		'k' => [0x99, 0x00, 0x55],
		'l' => [0x00, 0x00, 0x00],
		'm' => [0x00, 0x00, 0x00],
		'n' => [0x00, 0x77, 0xaa],
		'o' => [0x99, 0x99, 0x99],
		'p' => [0x99, 0x99, 0x99],
		's' => [0xa6, 0x7f, 0x59],
		't' => [0xa6, 0x7f, 0x59],
		'u' => [0xa6, 0x7f, 0x59],
		'cp' => [0x70, 0x80, 0x90],
		'c1' => [0x70, 0x80, 0x90],
		'cs' => [0x70, 0x80, 0x90],
		'kc' => [0x99, 0x00, 0x55],
		'kn' => [0x99, 0x00, 0x55],
		'kp' => [0x99, 0x00, 0x55],
		'kr' => [0x99, 0x00, 0x55],
		'ld' => [0x00, 0x00, 0x00],
		'nc' => [0x00, 0x77, 0xaa],
		'no' => [0x00, 0x77, 0xaa],
		'nd' => [0x00, 0x77, 0xaa],
		'ni' => [0x00, 0x77, 0xaa],
		'ne' => [0x00, 0x77, 0xaa],
		'nf' => [0x00, 0x77, 0xaa],
		'nl' => [0x00, 0x77, 0xaa],
		'nn' => [0x00, 0x77, 0xaa],
		'py' => [0x00, 0x77, 0xaa],
		'ow' => [0x99, 0x99, 0x99],
		'mb' => [0x00, 0x00, 0x00],
		'mf' => [0x00, 0x00, 0x00],
		'mh' => [0x00, 0x00, 0x00],
		'mi' => [0x00, 0x00, 0x00],
		'mo' => [0x00, 0x00, 0x00],
		'sb' => [0xa6, 0x7f, 0x59],
		'sc' => [0xa6, 0x7f, 0x59],
		'sd' => [0xa6, 0x7f, 0x59],
		'se' => [0xa6, 0x7f, 0x59],
		'sh' => [0xa6, 0x7f, 0x59],
		'si' => [0xa6, 0x7f, 0x59],
		'sx' => [0xa6, 0x7f, 0x59],
		'sr' => [0xa6, 0x7f, 0x59],
		'ss' => [0xa6, 0x7f, 0x59],
		'vc' => [0x00, 0x77, 0xaa],
		'vg' => [0x00, 0x77, 0xaa],
		'vi' => [0x00, 0x77, 0xaa],
		'il' => [0x00, 0x00, 0x00],
	];

	foreach ( $colors as $name => $rgb ) {
		if ( $processor->get_attribute( $name ) ) {
			return $rgb;
		}
	}

	return null;
}
