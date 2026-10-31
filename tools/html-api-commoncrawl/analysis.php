<?php
/**
 * cc-analyzer analysis script which records whether the HTML Processor supports each document.
 *
 * For each unsupported document this records why the HTML Processor stopped:
 * the message passed to `WP_HTML_Processor::bail()` and the line in
 * `class-wp-html-processor.php` it was called from. Running out of bookmarks
 * stops the parser without an exception, so it is reported as
 * `exceeded-max-bookmarks`. The results are written to `analysis.csv` in the
 * `CC_ANALYZER_OUTPUT_DIR` directory.
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
fputcsv( $csv, array( 'key', 'url', 'bytes', 'last_error', 'reason', 'line', 'token_name', 'incomplete' ), ',', '"', '' );

return static function ( $document ) use ( $csv ) {
	$processor = WP_HTML_Processor::create_full_parser( $document->body );
	while ( $processor->next_token() ) {
		continue;
	}

	$last_error = $processor->get_last_error();
	$reason     = '';
	$line       = '';
	$token_name = '';

	if ( WP_HTML_Processor::ERROR_UNSUPPORTED === $last_error ) {
		$exception  = $processor->get_unsupported_exception();
		$reason     = $exception->getMessage();
		$line       = $exception->getTrace()[0]['line'];
		$token_name = $exception->token_name;
	} elseif ( null !== $last_error ) {
		$reason = $last_error;
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
		),
		',',
		'"',
		''
	);
};
