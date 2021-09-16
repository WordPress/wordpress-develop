<?php
// phpcs:ignoreFile
declare( strict_types=1 );

require_once dirname( __DIR__, 2 ) . '/src/wp-load.php';

function apply_filters_old( $tag, $value ) {
	global $wp_filter, $wp_current_filter;

	$args = func_get_args();

	// Do 'all' actions first.
	if ( isset( $wp_filter['all'] ) ) {
		$wp_current_filter[] = $tag;
		_wp_call_all_hook( $args );
	}

	if ( ! isset( $wp_filter[ $tag ] ) ) {
		if ( isset( $wp_filter['all'] ) ) {
			array_pop( $wp_current_filter );
		}
		return $value;
	}

	if ( ! isset( $wp_filter['all'] ) ) {
		$wp_current_filter[] = $tag;
	}

	// Don't pass the tag name to WP_Hook.
	array_shift( $args );

	$filtered = $wp_filter[ $tag ]->apply_filters( $value, $args );

	array_pop( $wp_current_filter );

	return $filtered;
}

class ApplyFiltersBench {
	/**
	 * @Revs(100000)
	 * @Iterations(10)
	 * @Warmup(2)
	 */
	public function bench_apply_filters() {
		// Run with no filter:
		apply_filters( 'my_filter', 'my_value', 1, 2, 3 );

		add_filter( 'my_filter', array( $this, 'filter_callback' ) );

		// Run with a filter:
		apply_filters( 'my_filter', 'my_value', 1, 2, 3 );

		remove_filter( 'my_filter', array( $this, 'filter_callback' ) );
	}

	public function filter_callback( $value ) {
		return $value;
	}
}
