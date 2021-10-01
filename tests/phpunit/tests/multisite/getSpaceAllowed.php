<?php

if ( is_multisite() ) :

	/**
	 * Tests specific to `get_space_allowed()` in multisite.
	 *
	 * @group multisite
	 */
	class Tests_Multisite_GetSpaceAllowed extends WP_UnitTestCase {

		/**
		 * When no option exists for the site or the network, a fallback of
		 * 100 is expected.
		 */
		public function test_get_space_allowed_default() {
			delete_option( 'blog_upload_space' );
			delete_site_option( 'blog_upload_space' );

			$this->assertSame( 100, get_space_allowed() );
		}

		/**
		 * If an individual site's option is not available, the default network
		 * level option is used as a fallback.
		 */
		public function test_get_space_allowed_no_site_option_fallback_to_network_option() {
			delete_site_option( 'blog_upload_space' );
			update_site_option( 'blog_upload_space', 200 );

			$this->assertSame( 200, get_space_allowed() );
		}

		/**
		 * @dataProvider data_blog_upload_space
		 *
		 * @param mixed $site_option    Option to assign to the site's `blog_upload_space`.
		 * @param mixed $network_option Option to assign to the network's `blog_upload_space`.
		 * @param int   $expected       Expected return value.
		 */
		public function test_get_space_allowed( $site_option, $network_option, $expected ) {
			update_option( 'blog_upload_space', $site_option );
			update_site_option( 'blog_upload_space', $network_option );

			$this->assertSame( $expected, get_space_allowed() );
		}

		public function data_blog_upload_space() {
			return array(
				// A valid site option will be preferred over a network option.
				array( 111, 200, 111 ),
				array( -1, 200, -1 ),
				array( 222, 0, 222 ),

				// Non-numeric site options should result in a fallback to the network option.
				array( '', 333, 333 ),
				array( false, 444, 444 ),
				array( 'NAN', 555, 555 ),
				array( false, -10, -10 ),

				// If neither network or site options are valid, fallback to the default.
				array( false, false, 100 ),
				array( 'NAN', 'NAN', 100 ),

				// These effectively disable uploads.
				array( 0, 666, 0 ),
				array( false, 0, 0 ),
				array( 'NAN', 0, 0 ),
			);
		}

		public function test_get_space_allowed_filtered() {
			update_option( 'blog_upload_space', 777 );
			update_site_option( 'blog_upload_space', 888 );

			add_filter( 'get_space_allowed', array( $this, '_filter_space_allowed' ) );
			$space_allowed = get_space_allowed();
			remove_filter( 'get_space_allowed', array( $this, '_filter_space_allowed' ) );

			$this->assertSame( 999, $space_allowed );
		}

		public function _filter_space_allowed() {
			return 999;
		}
	}

endif;
