<?php

if ( is_multisite() ) :

	/**
	 * @group multisite
	 * @group ms-site
	 */
	class Tests_Multisite_WpSiteState extends WP_UnitTestCase {
		protected static $site_ids;
		protected static $network_id;

		public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
			self::$network_id = $factory->network->create();
			self::$site_ids   = array();

			for ( $i = 0; $i < 3; $i++ ) {
				self::$site_ids[] = $factory->blog->create_object(
					array(
						'domain'  => 'wordpress.org',
						'path'    => '/sites/' . $i,
						'site_id' => self::$network_id,
					)
				);
			}
		}

		/**
		 * Tests that the site state can be saved and restored.
		 *
		 * @ticket 37958
		 */
		public function test_site_state_save_and_restore() {
			$original_blog_id = get_current_blog_id();

			$state = get_site_state();

			switch_to_blog( self::$site_ids[0] );
			$this->assertEquals( self::$site_ids[0], get_current_blog_id() );

			switch_to_blog( self::$site_ids[1] );
			$this->assertEquals( self::$site_ids[1], get_current_blog_id() );

			$state->restore();
			$this->assertEquals( $original_blog_id, get_current_blog_id() );

			$this->assertFalse( ms_is_switched() );
		}

		/**
		 * Tests that the site state can be saved and restored in bulk operations.
		 *
		 * @ticket 37958
		 */
		public function test_site_state_in_bulk_operations() {
			$original_blog_id = get_current_blog_id();

			$state = get_site_state();

			foreach ( self::$site_ids as $site_id ) {
				switch_to_blog( $site_id );
				$this->assertEquals( $site_id, get_current_blog_id() );
			}

			$state->restore();
			$this->assertEquals( $original_blog_id, get_current_blog_id() );

			$this->assertFalse( ms_is_switched() );
		}

		/**
		 * Tests that the site state object maintains its properties.
		 *
		 * @ticket 37958
		 */
		public function test_site_state_maintains_properties() {
			$state = get_site_state();

			$this->assertEquals( get_current_blog_id(), $state->get_site_id() );
			$this->assertEquals( ms_is_switched(), $state->is_switched() );

			switch_to_blog( self::$site_ids[0] );
			$switched_state = get_site_state();
			$this->assertTrue( $switched_state->is_switched() );

			$switched_state->restore();
			$this->assertEquals( self::$site_ids[0], get_current_blog_id() );

			$state->restore();
			$this->assertFalse( ms_is_switched() );
		}
	}

endif;
