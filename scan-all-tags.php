<?php

require_once __DIR__ . '/src/wp-load.php';

$html = file_get_contents( 'php://stdin' );
$p    = WP_HTML_Processor::create_fragment( $html );

set_error_handler( function( $err, $msg, $file, $line ) {
	echo "\e[31mError {$err}: {$msg}\e[90m at \e[33m{$file}\e[90m:\e[33m{$line}\e[m\n";
	die();
} );

$budget = PHP_INT_MAX;
while ( --$budget > 0 ) {
	if ( ! $p->next_tag() ) {
		if ( null === $p->get_last_error() ) {
			echo "\e[90mFinished document \e[34msuccessfully\e[m\n";
			die();
		}

		echo "\e[90mAborted document: \e[33m{$p->get_last_error()}\e[m\n";
		die();
	}

	$closer = $p->is_tag_closer() ? '/' : '';
	$voider = WP_HTML_Processor::is_void( $p->get_tag() ) ? '/' : '';
	$crumbs = [];
	$last_crumb = null;
	foreach ( $p->get_breadcrumbs() as $tag ) {
		if ( $tag !== $last_crumb ) {
			$crumbs[] = [ $tag, 1 ];
		} else {
			$crumb = array_pop( $crumbs );
			$crumb[1]++;
			$crumbs[] = $crumb;
		}

		$last_crumb = $tag;
	}
	foreach ( $crumbs as &$c ) {
		$c = $c[1] === 1 ? $c[0] : "{$c[0]} (x{$c[1]})";
	}
	$crumbs = implode( "\e[90m, \e[31m", $crumbs );

	$first_attribute = '';
	foreach ( $p->get_attribute_names_with_prefix( '' ) ?? array() as $name ) {
		$value = $p->get_attribute( $name );
		
		if ( true === $value ) {
			$first_attribute = " \e[38;2;102;153;0m{$name}\e[38;2;153;153;153m\e[m";
		} else {
			$value = str_replace( "\n", "␤", $value );
			if ( strlen( $value ) > 23 ) {
				$value = substr( $value, 0, 20 ) . "...";
			}
			$first_attribute = " \e[38;2;102;153;0m{$name}\e[38;2;153;153;153m=\"\e[38;2;0;119;170m{$value}\e[38;2;153;153;153m\"\e[m";
		}
	}

	$modifiable_text = str_replace( "\n", "␤", $p->get_modifiable_text() );
	if ( strlen( $modifiable_text ) > 30 ) {
		$modifiable_text = substr( $modifiable_text, 0, 27 ) . "...";
	}
	echo "\e[90mFound \e[36m{$closer}\e[32m{$p->get_tag()}\e[35m{$voider}\e[90m at \e[31m{$crumbs}{$first_attribute}\e[90m {$modifiable_text}\e[m\n";
}
