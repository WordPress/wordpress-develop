<?php

if ( is_multisite() ) :

	/**
	 * @group ms-site
	 * @group multisite
	 */
	class Tests_Multisite_wpCountSites extends WP_UnitTestCase {

		/**
		 * @ticket 37392
		 */
		public function test_wp_count_sites() {
			// Create a random number of sites with each status.
			$site_ids = array(
				'public'   => self::factory()->blog->create_many(
					random_int( 0, 5 ),
					array(
						'public' => 1,
					)
				),
				'archived' => self::factory()->blog->create_many(
					random_int( 0, 5 ),
					array(
						'public'   => 0,
						'archived' => 1,
					)
				),
				'mature'   => self::factory()->blog->create_many(
					random_int( 0, 5 ),
					array(
						'public' => 0,
						'mature' => 1,
					)
				),
				'spam'     => self::factory()->blog->create_many(
					random_int( 0, 5 ),
					array(
						'public' => 0,
						'spam'   => 1,
					)
				),
				'deleted'  => self::factory()->blog->create_many(
					random_int( 0, 5 ),
					array(
						'public'  => 0,
						'deleted' => 1,
					)
				),
			);

			$counts = wp_count_sites();

			$counts_by_status = array_map( 'count', $site_ids );
			$expected         = array_merge(
				array( 'all' => array_sum( $counts_by_status ) ),
				$counts_by_status
			);
			// Add 1 to all & public for the main site.
			$expected['all']    += 1;
			$expected['public'] += 1;

			$this->assertSame( $expected, $counts );
		}
	}

endif;
