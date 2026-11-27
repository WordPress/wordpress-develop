<?php
/**
 * cc-analyzer analysis script which measures how long it takes to process each document.
 *
 * Each document is timed three ways: fully parsed with the Tag Processor, fully
 * parsed with the HTML Processor, and filtered with `wp_kses( $html, 'post' )`.
 * Only those calls are timed. The results are written to `benchmark.csv` in the
 * `CC_ANALYZER_OUTPUT_DIR` directory.
 *
 * Usage:
 *
 *     HTML_API_BENCH_WP_SRC=path/to/src CC_ANALYZER_OUTPUT_DIR=path/to/out \
 *         php cc-analyzer.phar --workspace path/to/workspace batch run corpus benchmark.php
 *
 * @package WordPress
 */

require __DIR__ . '/bootstrap.php';

$output_dir = getenv( 'CC_ANALYZER_OUTPUT_DIR' );
if ( ! is_dir( $output_dir ) ) {
	mkdir( $output_dir, 0777, true );
}

$csv = fopen( "{$output_dir}/benchmark.csv", 'wb' );
fputcsv( $csv, array( 'key', 'url', 'bytes', 'tag_ns', 'html_ns', 'kses_ns' ), ',', '"', '' );

// Warm up each measurement so that the first document is not charged for loading and JIT work.
$warm_up = '<!DOCTYPE html><html><head><title>Warm &amp; up</title></head><body><p class=a>One<p>Two<table><tr><td>Three</table></body></html>';
for ( $i = 0; $i < 100; $i++ ) {
	$processor = new WP_HTML_Tag_Processor( $warm_up );
	while ( $processor->next_token() ) {
		continue;
	}

	$processor = WP_HTML_Processor::create_full_parser( $warm_up );
	while ( $processor->next_token() ) {
		continue;
	}

	wp_kses( $warm_up, 'post' );
}

return static function ( $document ) use ( $csv ) {
	$html = $document->body;

	$started_at = hrtime( true );
	$tag        = new WP_HTML_Tag_Processor( $html );
	while ( $tag->next_token() ) {
		continue;
	}
	$tag_ns = hrtime( true ) - $started_at;

	// Free the Tag Processor here so that its destruction is not timed with the HTML Processor.
	unset( $tag );

	$started_at = hrtime( true );
	$processor  = WP_HTML_Processor::create_full_parser( $html );
	while ( $processor->next_token() ) {
		continue;
	}
	$html_ns = hrtime( true ) - $started_at;

	// Free the HTML Processor here so that its destruction is not timed with wp_kses().
	unset( $processor );

	$started_at = hrtime( true );
	$kses       = wp_kses( $html, 'post' );
	$kses_ns    = hrtime( true ) - $started_at;

	unset( $kses );

	fputcsv(
		$csv,
		array(
			"{$document->inputStateKey}|{$document->recordId}",
			$document->targetUri,
			strlen( $html ),
			$tag_ns,
			$html_ns,
			$kses_ns,
		),
		',',
		'"',
		''
	);

	html_api_bench_progress( 'timing' );
};
