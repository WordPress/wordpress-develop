<?php

define( 'WPROOT', '/src' );
define( 'WPINC', '/wp-includes' );

require __DIR__ . WPROOT . WPINC . '/html-api/class-wp-html-attribute-token.php';
require __DIR__ . WPROOT . WPINC . '/html-api/class-wp-html-span.php';
require __DIR__ . WPROOT . WPINC . '/html-api/class-wp-html-doctype-info.php';
require __DIR__ . WPROOT . WPINC . '/html-api/class-wp-html-text-replacement.php';
require __DIR__ . WPROOT . WPINC . '/html-api/class-wp-html-decoder.php';
require __DIR__ . WPROOT . WPINC . '/html-api/class-wp-html-tag-processor.php';
require __DIR__ . WPROOT . WPINC . '/html-api/class-wp-html-unsupported-exception.php';
require __DIR__ . WPROOT . WPINC . '/html-api/class-wp-html-active-formatting-elements.php';
require __DIR__ . WPROOT . WPINC . '/html-api/class-wp-html-open-elements.php';
require __DIR__ . WPROOT . WPINC . '/html-api/class-wp-html-token.php';
require __DIR__ . WPROOT . WPINC . '/html-api/class-wp-html-stack-event.php';
require __DIR__ . WPROOT . WPINC . '/html-api/class-wp-html-processor-state.php';
require __DIR__ . WPROOT . WPINC . '/html-api/class-wp-html-processor.php';

define( 'BACKSPACE', chr( 8 ) );
define( 'SHIFT_TAB', "\033[0;59" );

define( 'ERASE_TO_END_OF_LINE', "\033[0K" );
define( 'ERASE_FROM_START_OF_LINE', "\033[1K" );
define( 'ERASE_LINE', "\033[2K" );
define( 'ERASE_TO_BEGINNING_OF_SCREEN', "\033[1J" );
define( 'CLEAR_SCREEN', "\033[2J" );

define( 'INSTRUCTIONS', "\033[1mInteractive HTML API Console\n<SPACE>: Next token, <TAB>: Next tag, B: Set bookmark, 0..9: Jump to bookmark, <RETURN>: Exit\033[0m\n\n" );

$html = file_get_contents(
	__DIR__ . WPROOT . '/readme.html',
	false,
	null,
	0,
	1024
);

function highlight_current_token( $p ) {
	$colors      = array(
		'red'     => "\033[31m",
		'green'   => "\033[32m",
		'yellow'  => "\033[33m",
		'blue'    => "\033[34m",
		'magenta' => "\033[35m",
	);
	$reset_color = "\033[0m";

	$reflector = new \ReflectionClass( 'WP_HTML_Tag_Processor' );
	$html      = $reflector->getProperty( 'html' );
	$html->setAccessible( true );
	$markup = $html->getValue( $p );

	$token_starts_at = $reflector->getProperty( 'token_starts_at' );
	$token_starts_at->setAccessible( true );

	$token_length = $reflector->getProperty( 'token_length' );
	$token_length->setAccessible( true );

	$start  = $token_starts_at->getValue( $p );
	$length = $token_length->getValue( $p );

	$before = substr( $markup, 0, $start );
	$after  = substr( $markup, $start + $length );

	$highlighted = substr( $markup, $start, $length );

	$token_highlight_color = "\033[37;40m"; // White on black background.

	$bold       = "\033[1m";
	$reset_bold = "\033[22m";

	if ( ! $p->get_tag() ) {
		return $before . $token_highlight_color . $highlighted . $reset_color . $after;
	}

	$tag_name_starts_at = $reflector->getProperty( 'tag_name_starts_at' );
	$tag_name_starts_at->setAccessible( true );

	$tag_name_length = $reflector->getProperty( 'tag_name_length' );
	$tag_name_length->setAccessible( true );

	$tag_start  = $tag_name_starts_at->getValue( $p ) - $start;
	$tag_length = $tag_name_length->getValue( $p );

	$highlighted = substr( $highlighted, 0, $tag_start ) .
		$bold . substr( $highlighted, $tag_start, $tag_length ) . $reset_bold .
		substr( $highlighted, $tag_start + $tag_length );

	$markup = $before . $token_highlight_color . $highlighted . $reset_color . $after;

	$bookmarks = $reflector->getProperty( 'bookmarks' );
	$bookmarks->setAccessible( true );

	$offset = 0;
	foreach ( $bookmarks->getValue( $p ) as $name => $position ) {
		$color   = $colors[ array_keys( $colors )[ $offset % count( $colors ) ] ];
		$markup  = substr( $markup, 0, $position->start + $offset ) .
			$color . substr( $markup, $position->start + $offset, $position->length ) . $reset_color .
			substr( $markup, $position->start + $offset + $position->length );
		$offset += strlen( $color ) + strlen( $reset_color ); // FIXME: This doesn't work after $p->seek().
	}

	return $markup;
}

function print_html( $html ) {
	static $boomarks = 0;
	$p               = new WP_HTML_Tag_Processor( $html );

	/**
	 * overwrite the readline handler to do nothing
	 */
	readline_callback_handler_install( '', function () {} );

	echo CLEAR_SCREEN;
	echo INSTRUCTIONS;
	echo $html . "\r";

	/**
	 * read user input until <return>
	 */
	while ( true ) {
		/**
		 * read user keystroke from STDIN
		 */
		$keystroke = stream_get_contents( STDIN, 1 );

		/**
		 * on <return> break.
		 * ascii 10 = line feed
		 */
		if ( ord( $keystroke ) === 10 || 'q' === $keystroke ) {
			exit;
		}

		if ( $keystroke === SHIFT_TAB || ord( $keystroke ) === SHIFT_TAB || ord( $keystroke ) === 279091 ) {
			echo 'shift-tab';
			continue;
		}

		if ( ' ' === $keystroke ) {
			if ( ! $p->next_token() ) {
				exit;
			}

			fwrite( STDOUT, CLEAR_SCREEN ); // Was ERASE_LINE
			echo INSTRUCTIONS;
			echo highlight_current_token( $p ) . "\r";
			continue;
		}

		if ( "\t" === $keystroke ) {
			if ( ! $p->next_tag() ) {
				exit;
			}

			fwrite( STDOUT, CLEAR_SCREEN );
			echo INSTRUCTIONS;
			echo highlight_current_token( $p ) . "\r";
			continue;
		}

		if ( 'b' === $keystroke ) {
			$p->set_bookmark( 'bookmark' . $bookmarks++ );
			continue;
		}

		if ( in_array( $keystroke, range( '0', '9' ), true ) ) {
			$p->seek( 'bookmark' . $keystroke );
			fwrite( STDOUT, CLEAR_SCREEN );
			echo INSTRUCTIONS;
			echo highlight_current_token( $p ) . "\r";
			continue;
		}
	}
}

print_html( $html );
