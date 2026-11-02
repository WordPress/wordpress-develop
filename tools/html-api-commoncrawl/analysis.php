<?php
/**
 * cc-analyzer analysis script which records whether the HTML Processor supports each document.
 *
 * For each unsupported document this records why the HTML Processor stopped:
 * the message passed to `WP_HTML_Processor::bail()` and the line in
 * `class-wp-html-processor.php` it was called from. Running out of bookmarks
 * stops the parser without an exception, so it is reported as
 * `exceeded-max-bookmarks`.
 *
 * It also records where the HTML Processor stopped: the byte offset and length
 * of the token it was processing, the token's raw text, and the breadcrumbs of
 * open elements at that point. The exception reports all of these; when the
 * parser stopped without one, only the breadcrumbs of its last token are known.
 * Up to 30 extended grapheme clusters on either side of the token are recorded
 * too, starting or ending with `…` where the document continues beyond them.
 *
 * The results are written to `analysis.csv` in the `CC_ANALYZER_OUTPUT_DIR`
 * directory.
 *
 * Usage:
 *
 *     HTML_API_BENCH_WP_SRC=path/to/src CC_ANALYZER_OUTPUT_DIR=path/to/out \
 *         php cc-analyzer.phar --workspace path/to/workspace batch run corpus analysis.php
 *
 * @package WordPress
 */

require __DIR__ . '/bootstrap.php';

$output_dir = getenv( 'CC_ANALYZER_OUTPUT_DIR' );
if ( ! is_dir( $output_dir ) ) {
	mkdir( $output_dir, 0777, true );
}

// How many extended grapheme clusters of the document to show on either side of the token where parsing stopped.
const HTML_API_BENCH_CONTEXT_GRAPHEMES = 30;

// Bytes read on either side of the token to find them in: enough for 30 clusters of even long emoji sequences.
const HTML_API_BENCH_CONTEXT_BYTES = 1024;

/**
 * Returns text from a crawled document, as valid UTF-8, keeping its start and end if it is long.
 *
 * @param string $text      Text to keep.
 * @param int    $max_bytes Most bytes to keep from each end of a long text.
 * @return string The text, or its start and end joined by `…`.
 */
function html_api_bench_excerpt( $text, $max_bytes = 150 ) {
	if ( strlen( $text ) > 2 * $max_bytes ) {
		$text = substr( $text, 0, $max_bytes ) . '…' . substr( $text, -$max_bytes );
	}

	return mb_scrub( $text, 'UTF-8' );
}

/**
 * Returns the extended grapheme clusters of a document next to a byte offset.
 *
 * @param string $html   Document.
 * @param int    $offset Byte offset of the boundary, at the start or end of a token.
 * @param bool   $before Whether to take the clusters before the offset, rather than after it.
 * @return string Up to HTML_API_BENCH_CONTEXT_GRAPHEMES clusters, marked with `…` on the side where the document continues.
 */
function html_api_bench_context( $html, $offset, $before ) {
	$start  = $before ? max( 0, $offset - HTML_API_BENCH_CONTEXT_BYTES ) : $offset;
	$window = mb_scrub( substr( $html, $start, $before ? $offset - $start : HTML_API_BENCH_CONTEXT_BYTES ), 'UTF-8' );
	$count  = HTML_API_BENCH_CONTEXT_GRAPHEMES;
	$text   = 1 === preg_match( $before ? "/\X{0,{$count}}\z/u" : "/\A\X{0,{$count}}/u", $window, $matches ) ? $matches[0] : '';

	if ( $before ) {
		return ( $start > 0 || strlen( $text ) < strlen( $window ) ? '…' : '' ) . $text;
	}

	return $text . ( $offset + HTML_API_BENCH_CONTEXT_BYTES < strlen( $html ) || strlen( $text ) < strlen( $window ) ? '…' : '' );
}

$csv = fopen( "{$output_dir}/analysis.csv", 'wb' );
fputcsv( $csv, array( 'key', 'url', 'bytes', 'last_error', 'reason', 'line', 'token_name', 'incomplete', 'token_at', 'token_length', 'token_text', 'breadcrumbs', 'context_before', 'context_after' ), ',', '"', '' );

return static function ( $document ) use ( $csv ) {
	$processor = WP_HTML_Processor::create_full_parser( $document->body );
	while ( $processor->next_token() ) {
		continue;
	}

	$last_error   = $processor->get_last_error();
	$reason       = '';
	$line         = '';
	$token_name   = '';
	$token_at     = '';
	$token_length = '';
	$token_text   = '';
	$breadcrumbs  = '';
	$before       = '';
	$after        = '';

	if ( WP_HTML_Processor::ERROR_UNSUPPORTED === $last_error ) {
		$exception    = $processor->get_unsupported_exception();
		$reason       = $exception->getMessage();
		$line         = $exception->getTrace()[0]['line'];
		$token_name   = $exception->token_name;
		$token_at     = $exception->token_at;
		$token_length = strlen( $exception->token );
		$token_text   = html_api_bench_excerpt( $exception->token );
		$breadcrumbs  = implode( ' > ', $exception->stack_of_open_elements );
		$before       = html_api_bench_context( $document->body, $token_at, true );
		$after        = html_api_bench_context( $document->body, $token_at + $token_length, false );
	} elseif ( null !== $last_error ) {
		$reason      = $last_error;
		$breadcrumbs = implode( ' > ', $processor->get_breadcrumbs() ?? array() );
	}

	fputcsv(
		$csv,
		array(
			"{$document->inputStateKey}|{$document->recordId}",
			$document->targetUri,
			strlen( $document->body ),
			$last_error ?? '',
			$reason,
			$line,
			$token_name,
			$processor->paused_at_incomplete_token() ? '1' : '0',
			$token_at,
			$token_length,
			$token_text,
			$breadcrumbs,
			$before,
			$after,
		),
		',',
		'"',
		''
	);
};
