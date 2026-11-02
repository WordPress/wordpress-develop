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

$csv = fopen( "{$output_dir}/analysis.csv", 'wb' );
fputcsv( $csv, array( 'key', 'url', 'bytes', 'last_error', 'reason', 'line', 'token_name', 'incomplete', 'token_at', 'token_length', 'token_text', 'breadcrumbs' ), ',', '"', '' );

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

	if ( WP_HTML_Processor::ERROR_UNSUPPORTED === $last_error ) {
		$exception    = $processor->get_unsupported_exception();
		$reason       = $exception->getMessage();
		$line         = $exception->getTrace()[0]['line'];
		$token_name   = $exception->token_name;
		$token_at     = $exception->token_at;
		$token_length = strlen( $exception->token );
		// The token comes from a crawled document; keep enough to recognize it, as valid UTF-8 for the report.
		$token_text  = mb_scrub( substr( $exception->token, 0, 300 ), 'UTF-8' );
		$breadcrumbs = implode( ' > ', $exception->stack_of_open_elements );
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
		),
		',',
		'"',
		''
	);
};
