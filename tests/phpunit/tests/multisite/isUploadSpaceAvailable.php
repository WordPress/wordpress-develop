<?php

if ( is_multisite() ) :

	/**
	 * Tests specific to `is_upload_space_available()` in multisite.
	 *
	 * These tests filter `pre_get_space_used` so that we can ignore the local
	 * environment. Tests for `get_space_used()` are handled elsewhere.
	 *
	 * @group multisite
	 */
	class Tests_Multisite_IsUploadSpaceAvailable extends WP_UnitTestCase {

		public function set_up() {
			parent::set_up();
			update_site_option( 'upload_space_check_disabled', false );
		}

		/**
		 * A default of 100MB is used when no `blog_upload_space` option
		 * exists at the site or network level.
		 */
		public function test_is_upload_space_available_default() {
			delete_option( 'blog_upload_space' );
			delete_site_option( 'blog_upload_space' );

			add_filter( 'pre_get_space_used', array( $this, 'filter_space_used_small' ) );
			$available = is_upload_space_available();
			remove_filter( 'pre_get_space_used', array( $this, 'filter_space_used_small' ) );

			$this->assertTrue( $available );
		}

		public function test_is_upload_space_available_check_disabled() {
			update_site_option( 'blog_upload_space', 10 );
			update_site_option( 'upload_space_check_disabled', true );

			add_filter( 'pre_get_space_used', array( $this, 'filter_space_used_large' ) );
			$available = is_upload_space_available();
			remove_filter( 'pre_get_space_used', array( $this, 'filter_space_used_large' ) );

			$this->assertTrue( $available );
		}

		public function test_is_upload_space_available_space_used_is_less_then_allowed() {
			update_option( 'blog_upload_space', 350 );

			add_filter( 'pre_get_space_used', array( $this, 'filter_space_used_small' ) );
			$available = is_upload_space_available();
			remove_filter( 'pre_get_space_used', array( $this, 'filter_space_used_small' ) );

			$this->assertTrue( $available );
		}

		public function test_is_upload_space_available_space_used_is_more_than_allowed() {
			update_option( 'blog_upload_space', 350 );

			add_filter( 'pre_get_space_used', array( $this, 'filter_space_used_large' ) );
			$available = is_upload_space_available();
			remove_filter( 'pre_get_space_used', array( $this, 'filter_space_used_large' ) );

			$this->assertFalse( $available );
		}

		/**
		 * More comprehensive testing a 0 condition is handled in the tests
		 * for `get_space_allowed()`. We cover one scenario here.
		 */
		public function test_is_upload_space_available_upload_space_0_defaults_to_100() {
			update_option( 'blog_upload_space', 0 );

			add_filter( 'pre_get_space_used', array( $this, 'filter_space_used_small' ) );
			$available = is_upload_space_available();
			remove_filter( 'pre_get_space_used', array( $this, 'filter_space_used_small' ) );

			$this->assertFalse( $available );
		}

		public function test_is_upload_space_available_upload_space_negative() {
			update_site_option( 'blog_upload_space', -1 );

			add_filter( 'pre_get_space_used', array( $this, 'filter_space_used_small' ) );
			$available = is_upload_space_available();
			remove_filter( 'pre_get_space_used', array( $this, 'filter_space_used_small' ) );

			$this->assertFalse( $available );
		}

		public function filter_space_used_large() {
			return 10000000;
		}

		public function filter_space_used_small() {
			return 10;
		}
	}

endif;
