<?php
/**
 * Compares the benchmark and analysis results of two checkouts and prints a Markdown report.
 *
 * Each results directory contains one `round-N/benchmark.csv` per timing round
 * and one `analysis/analysis.csv`, as written by `benchmark.php` and
 * `analysis.php`. A document's parse time is its median across rounds.
 *
 * Usage:
 *
 *     php report.php path/to/base-results path/to/merge-results > report.md
 *
 * Environment variables:
 *
 *     HTML_API_BENCH_SMALL_MAX_BYTES  Documents smaller than this are "small". Default 32768.
 *     HTML_API_BENCH_LARGE_MIN_BYTES  Documents at least this large are "large". Default 262144.
 *     HTML_API_BENCH_BASE_SHA         Commit of the target branch, shown in the report.
 *     HTML_API_BENCH_MERGE_SHA        Commit of the PR merged into the target branch, shown in the report.
 *     HTML_API_BENCH_RUN_URL          Link to the workflow run, shown in the report.
 *
 * @package WordPress
 */

const HTML_API_BENCH_REPORT_MARKER = '<!-- html-api-commoncrawl-report -->';
const HTML_API_BENCH_EXAMPLES      = 10;

// How many example documents to show for each reason the HTML Processor stopped.
const HTML_API_BENCH_REASON_EXAMPLES = 3;

// The smallest share of documents a size bin at either end of the chart needs to be drawn.
const HTML_API_BENCH_CHART_MIN_BIN_SHARE = 0.02;

$small_max_bytes = (int) ( getenv( 'HTML_API_BENCH_SMALL_MAX_BYTES' ) ? getenv( 'HTML_API_BENCH_SMALL_MAX_BYTES' ) : 32768 );
$large_min_bytes = (int) ( getenv( 'HTML_API_BENCH_LARGE_MIN_BYTES' ) ? getenv( 'HTML_API_BENCH_LARGE_MIN_BYTES' ) : 262144 );

/**
 * Reads a CSV file with a header row into a list of associative arrays.
 *
 * @param string $path Path to the CSV file.
 * @return array[] Rows keyed by column name.
 */
function read_csv( $path ) {
	$handle = fopen( $path, 'rb' );
	$header = fgetcsv( $handle, null, ',', '"', '' );
	$rows   = array();
	$row    = fgetcsv( $handle, null, ',', '"', '' );
	while ( false !== $row ) {
		$rows[] = array_combine( $header, $row );
		$row    = fgetcsv( $handle, null, ',', '"', '' );
	}
	fclose( $handle );

	return $rows;
}

/**
 * Returns the median of a list of numbers.
 *
 * @param array $values Numbers.
 * @return float Median.
 */
function median( $values ) {
	sort( $values );
	$count  = count( $values );
	$middle = intdiv( $count, 2 );

	return 0 === $count % 2 ? ( $values[ $middle - 1 ] + $values[ $middle ] ) / 2 : $values[ $middle ];
}

/**
 * Returns the given percentile of a list of numbers using the nearest-rank method.
 *
 * @param array $values     Numbers.
 * @param int   $percentile Percentile from 1 to 100.
 * @return float Value at the percentile.
 */
function percentile( $values, $percentile ) {
	sort( $values );

	return $values[ max( 0, (int) ceil( $percentile / 100 * count( $values ) ) - 1 ) ];
}

/**
 * Loads one checkout's results, keyed by document.
 *
 * @param string $dir Results directory.
 * @return array[] Each document's URL, size, median parse times, and support details.
 */
function load_results( $dir ) {
	$timings = array();
	foreach ( glob( "{$dir}/round-*/benchmark.csv" ) as $path ) {
		foreach ( read_csv( $path ) as $row ) {
			$timings[ $row['key'] ]['url']       = $row['url'];
			$timings[ $row['key'] ]['bytes']     = (int) $row['bytes'];
			$timings[ $row['key'] ]['tag_ns'][]  = (int) $row['tag_ns'];
			$timings[ $row['key'] ]['html_ns'][] = (int) $row['html_ns'];
			$timings[ $row['key'] ]['kses_ns'][] = (int) $row['kses_ns'];
		}
	}

	$documents = array();
	foreach ( read_csv( "{$dir}/analysis/analysis.csv" ) as $row ) {
		$timing = $timings[ $row['key'] ];
		if ( $timing['url'] !== $row['url'] ) {
			throw new Exception( "Benchmark and analysis disagree on the URL for {$row['key']}." );
		}

		$documents[ $row['key'] ] = array(
			'url'        => $row['url'],
			'bytes'      => $timing['bytes'],
			'tag_ns'     => median( $timing['tag_ns'] ),
			'html_ns'    => median( $timing['html_ns'] ),
			'kses_ns'    => median( $timing['kses_ns'] ),
			'supported'  => '' === $row['last_error'],
			'reason'     => $row['reason'],
			'line'       => $row['line'],
			'incomplete' => '1' === $row['incomplete'],
		);
	}

	if ( count( $documents ) !== count( $timings ) ) {
		throw new Exception( "Benchmark and analysis cover different documents in {$dir}." );
	}

	return $documents;
}

/**
 * Formats a duration in nanoseconds with a unit suited to its size.
 *
 * @param float $ns      Duration in nanoseconds.
 * @param bool  $signed  Whether to always show the sign.
 * @return string Formatted duration.
 */
function format_ns( $ns, $signed = false ) {
	$sign = $ns < 0 ? '−' : ( $signed && $ns > 0 ? '+' : '' );
	$ns   = abs( $ns );

	if ( $ns >= 1e9 ) {
		return sprintf( '%s%.2f s', $sign, $ns / 1e9 );
	}
	if ( $ns >= 1e6 ) {
		return sprintf( '%s%.2f ms', $sign, $ns / 1e6 );
	}
	if ( $ns >= 1e3 ) {
		return sprintf( '%s%.1f µs', $sign, $ns / 1e3 );
	}

	return sprintf( '%s%d ns', $sign, $ns );
}

/**
 * Formats a percentage change.
 *
 * @param float $percent Percentage.
 * @return string Formatted percentage.
 */
function format_percent( $percent ) {
	return sprintf( '%s%.2f%%', $percent < 0 ? '−' : ( $percent > 0 ? '+' : '' ), abs( $percent ) );
}

/**
 * Formats a byte count.
 *
 * @param int $bytes Byte count.
 * @return string Formatted size.
 */
function format_bytes( $bytes ) {
	if ( $bytes >= 1048576 ) {
		return sprintf( '%.1f MiB', $bytes / 1048576 );
	}
	if ( $bytes >= 1024 ) {
		return sprintf( '%d KiB', round( $bytes / 1024 ) );
	}

	return "{$bytes} B";
}

/**
 * Formats a count with its share of a total.
 *
 * @param int $count Count.
 * @param int $total Total.
 * @return string Formatted count.
 */
function format_share( $count, $total ) {
	return 0 === $total ? '0' : sprintf( '%s (%.1f%%)', number_format( $count ), 100 * $count / $total );
}

/**
 * Formats a signed count.
 *
 * @param int $count Count.
 * @return string Formatted count.
 */
function format_delta( $count ) {
	return $count > 0 ? '+' . number_format( $count ) : ( $count < 0 ? '−' . number_format( -$count ) : '0' );
}

/**
 * Formats text from a crawled document for safe display as inline code in a Markdown table.
 *
 * @param string $text       Text to display.
 * @param int    $max_length Longest text to show before truncating.
 * @return string Inline code.
 */
function format_code( $text, $max_length = 100 ) {
	if ( mb_strlen( $text ) > $max_length ) {
		$text = mb_substr( $text, 0, $max_length - 1 ) . '…';
	}

	return '`' . str_replace( array( '`', '|', "\n", "\r" ), array( "'", '\|', ' ', ' ' ), $text ) . '`';
}

/**
 * Renders a Markdown table.
 *
 * @param string[] $header Column headings.
 * @param array[]  $rows   Table cells.
 * @return string Markdown table.
 */
function table( $header, $rows ) {
	$lines   = array();
	$lines[] = '| ' . implode( ' | ', $header ) . ' |';
	$lines[] = '|' . str_repeat( ' --- |', count( $header ) );
	foreach ( $rows as $row ) {
		$lines[] = '| ' . implode( ' | ', $row ) . ' |';
	}

	return implode( "\n", $lines ) . "\n";
}

/**
 * Renders a table comparing parse times for each size group.
 *
 * @param array[] $pairs  Documents, each with `base` and `merge` results.
 * @param array   $groups Group names mapped to a predicate on document size.
 * @param string  $parser One of `tag_ns`, `html_ns`, or `kses_ns`.
 * @return string Markdown table.
 */
function timing_table( $pairs, $groups, $parser ) {
	$rows = array();
	foreach ( $groups as $label => $in_group ) {
		$base_total  = 0;
		$merge_total = 0;
		$deltas      = array();
		$percents    = array();
		foreach ( $pairs as $pair ) {
			if ( ! $in_group( $pair['base']['bytes'] ) ) {
				continue;
			}

			$base         = $pair['base'][ $parser ];
			$merge        = $pair['merge'][ $parser ];
			$base_total  += $base;
			$merge_total += $merge;
			$deltas[]     = $merge - $base;
			$percents[]   = 100 * ( $merge - $base ) / $base;
		}

		if ( array() === $deltas ) {
			$rows[] = array( $label, '0', '', '', '', '', '', '', '', '' );
			continue;
		}

		$rows[] = array(
			$label,
			number_format( count( $deltas ) ),
			format_ns( $base_total ),
			format_ns( $merge_total ),
			format_ns( $merge_total - $base_total, true ),
			'**' . format_percent( 100 * ( $merge_total - $base_total ) / $base_total ) . '**',
			format_ns( array_sum( $deltas ) / count( $deltas ), true ),
			format_ns( median( $deltas ), true ),
			format_percent( median( $percents ) ),
			format_ns( percentile( $deltas, 95 ), true ),
		);
	}

	return table(
		array( 'Group', 'Docs', 'Target total', 'PR total', 'Δ total', 'Δ total %', 'Mean Δ/doc', 'Median Δ/doc', 'Median Δ%/doc', 'p95 Δ/doc' ),
		$rows
	);
}

/**
 * Renders a Mermaid chart of median parse time by document size for both checkouts.
 *
 * Documents are binned by size in powers of two, with every document under 2 KiB in the first bin.
 * Bins at either end with too few documents for a meaningful median are left out, as otherwise
 * a handful of very large documents sets the scale and hides the differences everywhere else.
 *
 * @param array[] $pairs  Documents, each with `base` and `merge` results.
 * @param string  $parser One of `tag_ns`, `html_ns`, or `kses_ns`.
 * @param string  $title  Chart title.
 * @return string Mermaid code block.
 */
function size_chart( $pairs, $parser, $title ) {
	$bins = array();
	foreach ( $pairs as $pair ) {
		$bin                     = max( 10, (int) floor( log( max( 1, $pair['base']['bytes'] ), 2 ) ) );
		$bins[ $bin ]['base'][]  = $pair['base'][ $parser ] / 1e6;
		$bins[ $bin ]['merge'][] = $pair['merge'][ $parser ] / 1e6;
	}
	ksort( $bins );

	$min_count = HTML_API_BENCH_CHART_MIN_BIN_SHARE * count( $pairs );
	$left_out  = 0;
	foreach ( array( 'array_key_first', 'array_key_last' ) as $end ) {
		while ( count( $bins[ $end( $bins ) ]['base'] ) < $min_count ) {
			$left_out += count( $bins[ $end( $bins ) ]['base'] );
			unset( $bins[ $end( $bins ) ] );
		}
	}

	$labels = array();
	$base   = array();
	$merge  = array();
	foreach ( $bins as $bin => $times ) {
		$labels[] = 10 === $bin ? '"0"' : ( $bin < 20 ? '"' . 2 ** ( $bin - 10 ) . 'K"' : '"' . 2 ** ( $bin - 20 ) . 'M"' );
		$base[]   = round( median( $times['base'] ), 3 );
		$merge[]  = round( median( $times['merge'] ), 3 );
	}

	return implode(
		"\n",
		array(
			'```mermaid',
			'%%{init: {"themeVariables": {"xyChart": {"plotColorPalette": "#2271b1, #d63638"}}}}%%',
			'xychart-beta',
			"    title \"{$title}\"",
			'    x-axis "Document size (bytes, at least)" [' . implode( ', ', $labels ) . ']',
			'    y-axis "Median time (ms)"',
			'    line [' . implode( ', ', $base ) . ']',
			'    line [' . implode( ', ', $merge ) . ']',
			'```',
			'',
			'<sub>Blue: target branch. Red: PR merged into the target branch.' . (
				$left_out > 0
					? sprintf( ' Sizes at either end with fewer than %d%% of documents are not drawn (%s documents).', 100 * HTML_API_BENCH_CHART_MIN_BIN_SHARE, number_format( $left_out ) )
					: ''
			) . '</sub>',
			'',
		)
	);
}

/**
 * Renders the documents whose parse time grew the most.
 *
 * @param array[] $pairs  Documents, each with `base` and `merge` results.
 * @param string  $parser One of `tag_ns`, `html_ns`, or `kses_ns`.
 * @return string Markdown table.
 */
function slowdowns_table( $pairs, $parser ) {
	usort(
		$pairs,
		static function ( $a, $b ) use ( $parser ) {
			return ( $b['merge'][ $parser ] - $b['base'][ $parser ] ) <=> ( $a['merge'][ $parser ] - $a['base'][ $parser ] );
		}
	);

	$rows = array();
	foreach ( array_slice( $pairs, 0, HTML_API_BENCH_EXAMPLES ) as $pair ) {
		$base   = $pair['base'][ $parser ];
		$merge  = $pair['merge'][ $parser ];
		$rows[] = array(
			format_code( $pair['base']['url'] ),
			format_bytes( $pair['base']['bytes'] ),
			format_ns( $base ),
			format_ns( $merge ),
			format_ns( $merge - $base, true ),
			format_percent( 100 * ( $merge - $base ) / $base ),
		);
	}

	return table( array( 'URL', 'Size', 'Target', 'PR', 'Δ', 'Δ%' ), $rows );
}

/**
 * Renders example documents whose support changed.
 *
 * @param array[] $pairs Documents, each with `base` and `merge` results.
 * @return string Markdown table.
 */
function examples_table( $pairs ) {
	$rows = array();
	foreach ( array_slice( $pairs, 0, HTML_API_BENCH_EXAMPLES ) as $pair ) {
		$rows[] = array(
			format_code( $pair['base']['url'] ),
			format_bytes( $pair['base']['bytes'] ),
			$pair['base']['supported'] ? 'supported' : format_code( "L{$pair['base']['line']}: {$pair['base']['reason']}" ),
			$pair['merge']['supported'] ? 'supported' : format_code( "L{$pair['merge']['line']}: {$pair['merge']['reason']}" ),
		);
	}

	return table( array( 'URL', 'Size', 'Target', 'PR' ), $rows );
}

/**
 * Renders a few example documents for each reason the HTML Processor stopped.
 *
 * Examples come from the PR, or from the target branch for a reason only it
 * stops for. They are the smallest documents, which are the easiest to inspect,
 * taking at most one from each host.
 *
 * @param array[]  $reasons    Reasons, in report order, each with `base` and `merge` counts, call sites, and documents.
 * @param callable $source_url Returns the link to `class-wp-html-processor.php` at a commit, or null.
 * @param string   $base_sha   Commit of the target branch.
 * @param string   $merge_sha  Commit of the PR merged into the target branch.
 * @return string Markdown table.
 */
function reason_examples_table( $reasons, $source_url, $base_sha, $merge_sha ) {
	$rows = array();
	foreach ( $reasons as $reason => $sides ) {
		$side      = isset( $sides['merge']['documents'] ) ? 'merge' : 'base';
		$documents = $sides[ $side ]['documents'];
		$url       = $source_url( 'merge' === $side ? $merge_sha : $base_sha );
		usort(
			$documents,
			static function ( $a, $b ) {
				return $a['bytes'] <=> $b['bytes'];
			}
		);

		$hosts = array();
		foreach ( $documents as $document ) {
			$host = strtolower( (string) parse_url( $document['url'], PHP_URL_HOST ) );
			if ( isset( $hosts[ $host ] ) ) {
				continue;
			}

			$hosts[ $host ] = true;
			$line           = $document['line'];
			$rows[]         = array(
				1 === count( $hosts ) ? format_code( $reason, 120 ) : '',
				format_code( $document['url'] ),
				format_bytes( $document['bytes'] ),
				'' === $line ? '' : ( $url ? "[L{$line}]({$url}#L{$line})" : "L{$line}" ) . ( 'base' === $side ? ' (target)' : '' ),
			);

			if ( count( $hosts ) === HTML_API_BENCH_REASON_EXAMPLES ) {
				break;
			}
		}
	}

	return table( array( 'Reason', 'URL', 'Size', 'Call site' ), $rows );
}

$base_results  = load_results( $argv[1] );
$merge_results = load_results( $argv[2] );

$pairs = array();
foreach ( $base_results as $key => $base ) {
	$pairs[ $key ] = array(
		'base'  => $base,
		'merge' => $merge_results[ $key ],
	);
}

if ( count( $pairs ) !== count( $merge_results ) ) {
	throw new Exception( 'The target and PR results cover different documents.' );
}

$supported_in_both = array_filter(
	$pairs,
	static function ( $pair ) {
		return $pair['base']['supported'] && $pair['merge']['supported'];
	}
);

$groups = array(
	'All'                                                => static function () {
		return true;
	},
	'Small (< ' . format_bytes( $small_max_bytes ) . ')' => static function ( $bytes ) use ( $small_max_bytes ) {
		return $bytes < $small_max_bytes;
	},
	'Medium'                                             => static function ( $bytes ) use ( $small_max_bytes, $large_min_bytes ) {
		return $bytes >= $small_max_bytes && $bytes < $large_min_bytes;
	},
	'Large (≥ ' . format_bytes( $large_min_bytes ) . ')' => static function ( $bytes ) use ( $large_min_bytes ) {
		return $bytes >= $large_min_bytes;
	},
);

$rounds = count( glob( "{$argv[1]}/round-*/benchmark.csv" ) );
preg_match( '~/crawl-data/([^/]+)/~', array_key_first( $pairs ), $crawl );
$base_sha  = getenv( 'HTML_API_BENCH_BASE_SHA' );
$merge_sha = getenv( 'HTML_API_BENCH_MERGE_SHA' );
$run_url   = getenv( 'HTML_API_BENCH_RUN_URL' );

$report  = HTML_API_BENCH_REPORT_MARKER . "\n";
$report .= "## HTML API CommonCrawl analysis\n\n";
$report .= sprintf(
	"Parsed %s documents from CommonCrawl `%s` with the target branch%s and with this PR merged into it%s. Each time is the median of %d rounds. Negative deltas mean the PR is faster.%s\n\n",
	number_format( count( $pairs ) ),
	$crawl[1],
	$base_sha ? ' (`' . substr( $base_sha, 0, 10 ) . '`)' : '',
	$merge_sha ? ' (`' . substr( $merge_sha, 0, 10 ) . '`)' : '',
	$rounds,
	$run_url ? " [Workflow run]({$run_url})." : ''
);

$report .= "### HTML Processor parse time\n\n";
$report .= "All documents, including those where parsing stopped early on unsupported markup:\n\n";
$report .= timing_table( $pairs, $groups, 'html_ns' ) . "\n";
$report .= sprintf( "Documents supported by both the target branch and the PR (%s):\n\n", number_format( count( $supported_in_both ) ) );
$report .= timing_table( $supported_in_both, $groups, 'html_ns' ) . "\n";
$report .= size_chart( $pairs, 'html_ns', 'HTML Processor: median parse time by document size' ) . "\n";
$report .= "<details><summary>Largest HTML Processor slowdowns</summary>\n\n" . slowdowns_table( $pairs, 'html_ns' ) . "\n</details>\n\n";

$report .= "### Tag Processor parse time\n\n";
$report .= timing_table( $pairs, $groups, 'tag_ns' ) . "\n";
$report .= size_chart( $pairs, 'tag_ns', 'Tag Processor: median parse time by document size' ) . "\n";
$report .= "<details><summary>Largest Tag Processor slowdowns</summary>\n\n" . slowdowns_table( $pairs, 'tag_ns' ) . "\n</details>\n\n";

$report .= "### wp_kses() time\n\n";
$report .= "Time for `wp_kses( \$html, 'post' )`. Only the files it needs are loaded, without WordPress's default filters such as those on `pre_kses`, so times differ from a full site.\n\n";
$report .= timing_table( $pairs, $groups, 'kses_ns' ) . "\n";
$report .= size_chart( $pairs, 'kses_ns', 'wp_kses(): median time by document size' ) . "\n";
$report .= "<details><summary>Largest wp_kses() slowdowns</summary>\n\n" . slowdowns_table( $pairs, 'kses_ns' ) . "\n</details>\n\n";

$report .= "### HTML Processor support\n\n";
$rows    = array();
foreach ( $groups as $label => $in_group ) {
	$count             = 0;
	$base_unsupported  = 0;
	$merge_unsupported = 0;
	$base_incomplete   = 0;
	$merge_incomplete  = 0;
	foreach ( $pairs as $pair ) {
		if ( ! $in_group( $pair['base']['bytes'] ) ) {
			continue;
		}

		++$count;
		$base_unsupported  += $pair['base']['supported'] ? 0 : 1;
		$merge_unsupported += $pair['merge']['supported'] ? 0 : 1;
		$base_incomplete   += $pair['base']['incomplete'] ? 1 : 0;
		$merge_incomplete  += $pair['merge']['incomplete'] ? 1 : 0;
	}

	$rows[] = array(
		$label,
		number_format( $count ),
		format_share( $base_unsupported, $count ),
		format_share( $merge_unsupported, $count ),
		'**' . format_delta( $merge_unsupported - $base_unsupported ) . '**',
		number_format( $base_incomplete ),
		number_format( $merge_incomplete ),
	);
}
$report .= table( array( 'Group', 'Docs', 'Unsupported (target)', 'Unsupported (PR)', 'Δ', 'Incomplete (target)', 'Incomplete (PR)' ), $rows ) . "\n";
$report .= "<sub>Incomplete documents end inside a token, usually because the crawler truncated them. They are not counted as unsupported.</sub>\n\n";

$reasons = array();
foreach ( $pairs as $pair ) {
	foreach ( array( 'base', 'merge' ) as $side ) {
		$result = $pair[ $side ];
		if ( $result['supported'] ) {
			continue;
		}

		$reasons[ $result['reason'] ][ $side ]['count']       = ( $reasons[ $result['reason'] ][ $side ]['count'] ?? 0 ) + 1;
		$reasons[ $result['reason'] ][ $side ]['documents'][] = array(
			'url'   => $result['url'],
			'bytes' => $result['bytes'],
			'line'  => $result['line'],
		);
		if ( '' !== $result['line'] ) {
			$reasons[ $result['reason'] ][ $side ]['lines'][ $result['line'] ] = ( $reasons[ $result['reason'] ][ $side ]['lines'][ $result['line'] ] ?? 0 ) + 1;
		}
	}
}
uasort(
	$reasons,
	static function ( $a, $b ) {
		return max( $b['base']['count'] ?? 0, $b['merge']['count'] ?? 0 ) <=> max( $a['base']['count'] ?? 0, $a['merge']['count'] ?? 0 );
	}
);

/*
 * Links call sites to the source when running on GitHub Actions, which provides
 * the repository. Both the target and the merge commits are in the repository.
 */
$source_url   = static function ( $sha ) {
	$server     = getenv( 'GITHUB_SERVER_URL' );
	$repository = getenv( 'GITHUB_REPOSITORY' );

	return $server && $repository && $sha
		? "{$server}/{$repository}/blob/{$sha}/src/wp-includes/html-api/class-wp-html-processor.php"
		: null;
};
$format_lines = static function ( $lines, $url ) {
	ksort( $lines );
	$parts = array();
	foreach ( $lines as $line => $count ) {
		$parts[] = $url ? "[L{$line}]({$url}#L{$line}) ({$count})" : "L{$line} ({$count})";
	}

	return implode( ', ', $parts );
};

$rows = array();
foreach ( $reasons as $reason => $sides ) {
	$base_count  = $sides['base']['count'] ?? 0;
	$merge_count = $sides['merge']['count'] ?? 0;
	$rows[]      = array(
		format_code( $reason, 120 ),
		number_format( $base_count ),
		number_format( $merge_count ),
		format_delta( $merge_count - $base_count ),
		$format_lines( $sides['base']['lines'] ?? array(), $source_url( $base_sha ) ),
		$format_lines( $sides['merge']['lines'] ?? array(), $source_url( $merge_sha ) ),
	);
}
$report .= "Unsupported documents by the reason the HTML Processor stopped. Call sites are lines in `class-wp-html-processor.php` of each checkout.\n\n";
$report .= table( array( 'Reason', 'Target', 'PR', 'Δ', 'Target call sites', 'PR call sites' ), $rows ) . "\n";
if ( array() !== $reasons ) {
	$report .= "<details><summary>Example documents for each reason</summary>\n\n" . reason_examples_table( $reasons, $source_url, $base_sha, $merge_sha ) . "\n</details>\n\n";
}

$newly_unsupported = array_values(
	array_filter(
		$pairs,
		static function ( $pair ) {
			return $pair['base']['supported'] && ! $pair['merge']['supported'];
		}
	)
);
$newly_supported   = array_values(
	array_filter(
		$pairs,
		static function ( $pair ) {
			return ! $pair['base']['supported'] && $pair['merge']['supported'];
		}
	)
);
$changed_reason    = array_values(
	array_filter(
		$pairs,
		static function ( $pair ) {
			return ! $pair['base']['supported'] && ! $pair['merge']['supported'] && $pair['base']['reason'] !== $pair['merge']['reason'];
		}
	)
);

foreach (
	array(
		'Newly unsupported with this PR'                  => $newly_unsupported,
		'Newly supported with this PR'                    => $newly_supported,
		'Unsupported for a different reason with this PR' => $changed_reason,
	) as $label => $changed
) {
	$report .= sprintf( "- **%s:** %s\n", $label, number_format( count( $changed ) ) );
}
$report .= "\n";

foreach (
	array(
		'Newly unsupported examples' => $newly_unsupported,
		'Newly supported examples'   => $newly_supported,
		'Different-reason examples'  => $changed_reason,
	) as $label => $changed
) {
	if ( array() !== $changed ) {
		$report .= "<details><summary>{$label}</summary>\n\n" . examples_table( $changed ) . "\n</details>\n\n";
	}
}

// GitHub rejects comments longer than this.
if ( mb_strlen( $report ) > 65536 ) {
	throw new Exception( sprintf( 'The report is %d characters, which is too long for a GitHub comment.', mb_strlen( $report ) ) );
}

echo $report;
