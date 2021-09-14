<?php
declare( strict_types=1 );

require_once dirname( __DIR__, 2 ) . '/src/wp-load.php';

class ApplyFiltersBench {
	/**
	 * @Revs(10000)
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
