<?php
global $is_iis7;
if ( ! $is_iis7 ) {
	/**
	 * Tests for the iis7_supports_permalinks function.
	 *
	 * @group Functions
	 *
	 * @covers ::iis7_supports_permalinks
	 */
	class Tests_Functions_iis7SupportsPermalinks extends WP_UnitTestCase {

		/**
		 * check it returns false if the server is not IIS 7.
		 * check the filter is called
		 *
		 * @ticket 60024
		 */
		public function test_iis7_supports_permalinks() {
			$filter = new MockAction();

			add_filter( 'iis7_supports_permalinks', array( $filter, 'filter' ) );

			$this->assertFalse( iis7_supports_permalinks() );

			$this->assertSame( 1, $filter->get_call_count() );
		}
	}
}
