<?php
/**
 * Shared statistics, formatting, seeding, and cleanup utilities for sync storage benchmarks.
 *
 * PHP equivalents of the functions in tests/performance/utils.js
 * (median, standardDeviation, medianAbsoluteDeviation).
 *
 * @package WordPress
 */

/**
 * Computes the median of an array of numbers.
 *
 * @param float[] $arr Array of numbers.
 * @return float Median value.
 */
function sync_perf_median( array $arr ): float {
	sort( $arr );
	$count = count( $arr );
	$mid   = (int) floor( $count / 2 );
	return ( $count % 2 === 0 )
		? ( $arr[ $mid - 1 ] + $arr[ $mid ] ) / 2
		: $arr[ $mid ];
}

/**
 * Computes the standard deviation of an array of numbers.
 *
 * @param float[] $arr Array of numbers.
 * @return float Standard deviation.
 */
function sync_perf_sd( array $arr ): float {
	$count  = count( $arr );
	$mean   = array_sum( $arr ) / $count;
	$sum_sq = 0.0;
	foreach ( $arr as $v ) {
		$sum_sq += ( $v - $mean ) ** 2;
	}
	return sqrt( $sum_sq / $count );
}

/**
 * Computes the median absolute deviation of an array of numbers.
 *
 * @param float[] $arr Array of numbers.
 * @return float Median absolute deviation.
 */
function sync_perf_mad( array $arr ): float {
	$med        = sync_perf_median( $arr );
	$deviations = array_map( fn( $v ) => abs( $v - $med ), $arr );
	return sync_perf_median( $deviations );
}

/**
 * Computes the 95th percentile of an array of numbers.
 *
 * @param float[] $arr Array of numbers.
 * @return float 95th percentile value.
 */
function sync_perf_p95( array $arr ): float {
	sort( $arr );
	$index = (int) ceil( 0.95 * count( $arr ) ) - 1;
	return $arr[ max( 0, $index ) ];
}

/**
 * Computes median, P95, standard deviation, and MAD.
 *
 * @param float[] $times Array of durations in milliseconds.
 * @return array{ median: float, p95: float, sd: float, mad: float }
 */
function sync_perf_compute_stats( array $times ): array {
	return array(
		'median' => sync_perf_median( $times ),
		'p95'    => sync_perf_p95( $times ),
		'sd'     => sync_perf_sd( $times ),
		'mad'    => sync_perf_mad( $times ),
	);
}

/**
 * Runs warm-up iterations, then measures $measured iterations of $callback.
 *
 * @param callable $callback Function to benchmark.
 * @param int      $measured Number of measured iterations.
 * @param int      $warmup  Number of warm-up iterations (discarded).
 * @return array{ median: float, p95: float, sd: float, mad: float }
 */
function sync_perf_stats( callable $callback, int $measured, int $warmup = 5 ): array {
	for ( $i = 0; $i < $warmup; $i++ ) {
		$callback();
	}

	$times = array();
	for ( $i = 0; $i < $measured; $i++ ) {
		$start   = microtime( true );
		$callback();
		$times[] = ( microtime( true ) - $start ) * 1000;
	}

	return sync_perf_compute_stats( $times );
}

/**
 * Runs EXPLAIN on a SQL query and returns result rows.
 *
 * @param string $sql The query to explain.
 * @return array EXPLAIN result rows.
 */
function sync_perf_explain( string $sql ): array {
	global $wpdb;
	return $wpdb->get_results( "EXPLAIN {$sql}", ARRAY_A );
}

/**
 * Formats a millisecond value with unit suffix.
 *
 * @param float $value Duration in milliseconds.
 * @return string Formatted value, e.g. "0.04 ms".
 */
function sync_perf_format_ms( float $value ): string {
	return sprintf( '%.2f ms', $value );
}

/**
 * Converts an EXPLAIN result set into a one-line prose summary.
 *
 * @param array $row Single EXPLAIN result row (associative array).
 * @return string Prose summary.
 */
function sync_perf_explain_access( array $row ): string {
	$extra       = $row['Extra'] ?? $row['extra'] ?? '';
	$index       = $row['key'] ?? $row['Key'] ?? null;
	$access_type = $row['type'] ?? $row['Type'] ?? null;
	$estimated   = $row['rows'] ?? $row['Rows'] ?? null;

	if ( false !== stripos( $extra, 'Select tables optimized away' ) || null === $access_type ) {
		return 'Optimized away (no table access)';
	}

	return sprintf( '%s (%s), ~%s rows', $index, $access_type, $estimated );
}

/**
 * Seeds the wp_collaboration table via bulk INSERT.
 *
 * @param int $total_rows Total rows to insert.
 * @param int $rooms      Number of rooms to distribute across.
 */
function sync_perf_seed_table( int $total_rows, int $rooms ): void {
	global $wpdb;

	$wpdb->query( "TRUNCATE TABLE {$wpdb->collaboration}" );

	$rows_per_room = (int) ceil( $total_rows / $rooms );
	$batch_size    = 500;
	$now           = gmdate( 'Y-m-d H:i:s' );
	$inserted      = 0;

	for ( $r = 1; $r <= $rooms; $r++ ) {
		$room       = "postType/post:{$r}";
		$room_count = min( $rows_per_room, $total_rows - $inserted );

		for ( $offset = 0; $offset < $room_count; $offset += $batch_size ) {
			$chunk  = min( $batch_size, $room_count - $offset );
			$values = array();

			for ( $i = 0; $i < $chunk; $i++ ) {
				$json     = $wpdb->prepare( '%s', wp_json_encode( array(
					'client_id' => wp_generate_uuid4(),
					'type'      => 'sync_step1',
					'data'      => 'AQLsAxgC',
				) ) );
				$room_esc = $wpdb->prepare( '%s', $room );
				$now_esc  = $wpdb->prepare( '%s', $now );
				$values[] = "({$room_esc}, {$json}, {$now_esc})";
			}

			$wpdb->query(
				"INSERT INTO {$wpdb->collaboration} (room, update_value, created_at) VALUES " . implode( ',', $values )
			);

			$inserted += $chunk;
		}
	}
}

/**
 * Collects EXPLAIN analysis at a given scale and returns structured results.
 *
 * Runs ANALYZE TABLE first to ensure the optimizer has up-to-date statistics
 * after bulk INSERT seeding.
 *
 * @param string $target_room Room to query against.
 * @param int    $scale       Total row count.
 * @param int    $rooms       Number of rooms.
 * @return array[] EXPLAIN entries with label, sql, and access summary.
 */
function sync_perf_collect_explains( string $target_room, int $scale, int $rooms ): array {
	global $wpdb;

	sync_perf_seed_table( $scale, $rooms );
	$wpdb->query( "ANALYZE TABLE {$wpdb->collaboration}" );

	$table_max_id = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COALESCE( MAX( id ), 0 ) FROM {$wpdb->collaboration} WHERE room = %s",
		$target_room
	) );

	$queries = array(
		array(
			'label' => 'Idle poll (MAX cursor)',
			'sql'   => "SELECT COALESCE(MAX(id), 0) FROM {$wpdb->collaboration} WHERE room = %s",
			'args'  => array( $target_room ),
		),
		array(
			'label' => 'Idle poll (COUNT)',
			'sql'   => "SELECT COUNT(*) FROM {$wpdb->collaboration} WHERE room = %s AND id <= %d",
			'args'  => array( $target_room, $table_max_id ),
		),
		array(
			'label' => 'Catch-up poll (SELECT)',
			'sql'   => "SELECT update_value FROM {$wpdb->collaboration} WHERE room = %s AND id > %d AND id <= %d ORDER BY id ASC",
			'args'  => array( $target_room, 0, $table_max_id ),
		),
		array(
			'label' => 'Compaction (DELETE)',
			'sql'   => "DELETE FROM {$wpdb->collaboration} WHERE room = %s AND id < %d",
			'args'  => array( $target_room, $table_max_id ),
		),
		array(
			'label' => 'LIKE prefix scan',
			'sql'   => "SELECT id, room FROM {$wpdb->collaboration} WHERE room LIKE %s ORDER BY room, id ASC",
			'args'  => array( 'postType/post:%' ),
		),
	);

	$explains = array();
	foreach ( $queries as $query ) {
		$prepared = $wpdb->prepare( $query['sql'], ...$query['args'] );
		$rows     = sync_perf_explain( $prepared );

		$explains[] = array(
			'Query'  => $query['label'],
			'Access' => ! empty( $rows ) ? sync_perf_explain_access( $rows[0] ) : 'No EXPLAIN output',
		);
	}

	return $explains;
}

/**
 * Builds the result rows for a benchmark section as format_items-compatible arrays.
 *
 * @param array $op_results Results for this operation keyed by [$scale].
 * @param int[] $scales     Scale values.
 * @param int   $rooms      Rooms per scale.
 * @return array[] Rows with 'Rows per room', 'Median', 'P95', 'STD', 'MAD' keys.
 */
function sync_perf_build_section_rows( array $op_results, array $scales, int $rooms ): array {
	$rows = array();

	foreach ( $scales as $scale ) {
		$per_room = (int) ceil( $scale / $rooms );
		$stats    = $op_results[ $scale ];

		$rows[] = array(
			'Rows per room' => number_format( $per_room ),
			'Median'        => sync_perf_format_ms( $stats['median'] ),
			'P95'           => sync_perf_format_ms( $stats['p95'] ),
			'STD'           => sync_perf_format_ms( $stats['sd'] ),
			'MAD'           => sync_perf_format_ms( $stats['mad'] ),
		);
	}

	return $rows;
}

/**
 * Prints all benchmark results using WP-CLI formatting.
 *
 * @param array  $results      Benchmark results keyed by operation/scale.
 * @param array  $explain_data Return value from sync_perf_collect_explains().
 * @param array  $config       Benchmark configuration.
 * @param int[]  $scales       Scale values.
 */
function sync_perf_print_output( array $results, array $explain_data, array $config, array $scales ): void {
	global $wp_version, $wpdb;

	$fields    = array( 'Rows per room', 'Median', 'P95', 'STD', 'MAD' );
	$separator = str_repeat( '─', 60 );

	WP_CLI::log( '' );
	WP_CLI::log( WP_CLI::colorize( '%_Sync Storage Performance%n' ) );
	WP_CLI::log( sprintf(
		'WordPress %s, MySQL %s, PHP %s, Docker (local dev)',
		$wp_version,
		$wpdb->db_version(),
		phpversion()
	) );
	WP_CLI::log( sprintf(
		'%d measured iterations (%d warm-up discarded), fresh instance per iteration',
		$config['measured_iterations'],
		$config['warmup_iterations']
	) );

	$sections = array(
		'idle_poll'    => array(
			'title' => 'Idle Poll',
			'desc'  => 'Checks for new updates when none exist. Called every second per open editor tab.',
		),
		'catchup_poll' => array(
			'title' => 'Catch-up Poll',
			'desc'  => 'Fetches all updates from cursor 0. Called when an editor opens or reconnects.',
		),
		'compaction'   => array(
			'title' => 'Compaction',
			'desc'  => sprintf(
				'Removes old updates. Deletes ~80%% of rows (%d measured iterations, re-seeded each).',
				$config['compaction_iterations']
			),
		),
	);

	foreach ( $sections as $op_key => $section ) {
		WP_CLI::log( '' );
		WP_CLI::log( $separator );
		WP_CLI::log( WP_CLI::colorize( "%_{$section['title']}%n" ) );
		WP_CLI::log( $separator );
		WP_CLI::log( $section['desc'] );
		WP_CLI::log( '' );

		$rows = sync_perf_build_section_rows( $results[ $op_key ], $scales, $config['rooms'] );
		WP_CLI\Utils\format_items( 'table', $rows, $fields );
	}

	WP_CLI::log( '' );
	WP_CLI::log( $separator );
	WP_CLI::log( WP_CLI::colorize( '%_MySQL EXPLAIN Analysis%n' ) );
	WP_CLI::log( $separator );
	WP_CLI::log( '' );

	WP_CLI\Utils\format_items( 'table', $explain_data, array( 'Query', 'Access' ) );

	WP_CLI::log( '' );
	WP_CLI::success( 'Benchmark complete.' );
}
