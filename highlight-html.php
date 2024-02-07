<?php

require_once __DIR__ . '/src/wp-load.php';

define( 'C_TEXT', "\e[m" );
define( 'C_REF', "\e[38;2;154;110;58m" );
define( 'C_COMMENT', "\e[38;2;112;128;144m" );
define( 'C_SYNTAX', "\e[38;2;153;153;153m" );
define( 'C_TAGNAME', "\e[38;2;153;0;85m" );
define( 'C_ANAME', "\e[38;2;102;153;0m" );
define( 'C_AVALUE', "\e[38;2;0;119;170m" );

$options = getopt( 'fu:' ); // How rude!
$do_format = isset( $options['f'] );

$uri = 'php://stdin';
if ( isset( $options['u'] ) ) {
	$uri = $options['u'];
	if ( ! preg_match( '~^https?://~', $uri ) ) {
		$uri = "https://{$uri}";
	}
}

$html = file_get_contents( $uri );

$p = new WP_HTML_Tag_Processor( $html );
while ( $p->next_token() ) {
	switch ( $p->get_token_type() ) {
	case '#comment':
		echo C_COMMENT . '<!--' . $p->get_modifiable_text() . '-->';
		break;

	case '#doctype':
		echo C_SYNTAX . '<!DOCTYPE' . $p->get_modifiable_text() . '>';
		break;

	case '#tag':
		print_tag( $p );
		break;

	case '#text':
		print_text( $p );
		break;

	default:
		die( "Unsupported syntax: {$p->get_token_type()}" );
	}
}

echo "\e[m\n";

function print_text( $p ) {
	$text = $p->get_modifiable_text();
	$text = preg_replace_callback(
		'~&(?:#\d+|#x[a-f0-9]+|[a-z]+);?~i',
		fn ( $m ) => C_REF . $m[0] . C_TEXT,
		$text
	);

	echo C_TEXT . $text;;
}

function print_tag( $p ) {
	global $do_format;

	static $depth = 0;

	$tag_name  = $p->get_tag();
	$is_closer = $p->is_tag_closer();
	$closer    = $is_closer ? '/' : '';
	$is_void   = WP_HTML_Processor::is_void( $tag_name );
	$voider    = $is_void ? '/' : '';

	if ( $is_closer && in_array( $tag_name, [ 'HEAD', 'BODY', 'OL', 'UL', 'DIV' ], true ) ) {
		$depth--;
	}

	$indent = str_pad( '', $depth * 2, ' ' );

	if ( $do_format && (
		(
			! $is_closer && in_array( $tag_name, [
				'DIV', 'P', 'UL', 'OL', 'DETAILS', 'SVG', 'PATH', 'G',
				'LINK', 'META', 'HTML', 'HEAD', 'BODY', 'TITLE', 'TEXTAREA',
				'PRE', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'HGROUP',
				'PICTURE', 'SOURCE', 'FIGURE', 'FORM', 'TABLE', 'TR',
				'FIGCAPTION', 'BLOCKQUOTE', 'OBJECT', 'EMBED', 'IFRAME',
				'SCRIPT', 'STYLE', 'NOSCRIPT', 'NAV', 'LI'
			], true )
		) || (
			$is_closer && in_array( $tag_name, [
				'HEAD', 'HTML', 'BODY', 'PICTURE', 'FIGURE', 'TABLE'
			], true  )
		)
	) ) {
		echo "\n{$indent}";
	}
	echo C_SYNTAX . '<' . $closer;

	echo C_TAGNAME . strtolower( $p->get_tag() );
	$attributes = $p->get_attribute_names_with_prefix( '' ) ?? array();

	foreach( $attributes as $name ) {
		$value = $p->get_attribute( $name );

		echo ' ' . C_ANAME . $name;
		if ( true === $value ) {
			continue;
		}

		echo C_SYNTAX . '="';
		echo C_AVALUE . str_replace( '"', '&quot;', $value );
		echo C_SYNTAX . '"';
	}
	echo C_SYNTAX . '>';

	$text = $p->get_modifiable_text();
	if ( ! empty( $text ) ) {
		echo 'TITLE' === $p->get_tag() ? C_TEXT : C_COMMENT;

		$add_newlines = (
			$do_format &&
			strlen( trim( $text ) ) > 0 &&
			(
				'SCRIPT' === $tag_name ||
				'STYLE' === $tag_name ||
				'TEXTAREA' === $tag_name ||
				'PRE' === $tag_name
			)
		);

		if ( $add_newlines ) {
			echo "\n" . trim( $text, "\n" ) . "\n";
		} else {
			echo $text;
		}

		echo C_SYNTAX . '</' . C_TAGNAME . strtolower( $p->get_tag() ) . C_SYNTAX . '>';
	} elseif ( in_array( $tag_name, [ 'SCRIPT', 'STYLE', 'TEXTAREA', 'PRE' ], true ) ) {
		echo C_SYNTAX . '</' . C_TAGNAME . strtolower( $p->get_tag() ) . C_SYNTAX . '>';
	}

	if ( ! $is_closer && in_array( $tag_name, [ 'HEAD', 'BODY', 'OL', 'UL', 'DIV' ], true ) ) {
		$depth++;
	}
}
