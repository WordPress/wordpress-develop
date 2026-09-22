<?php

if ( is_multisite() ) :

	/**
	 * Test the call_for_blog() function.
	 *
	 * @group ms-blogs
	 * @group multisite
	 */
	class Tests_Multisite_CallForBlog extends WP_UnitTestCase {
		protected static $site_ids;
		protected $current_site_id;

		public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
			self::$site_ids = array(
				'test1' => $factory->blog->create(),
				'test2' => $factory->blog->create(),
			);
		}

		public static function wpTearDownAfterClass() {
			foreach ( self::$site_ids as $site_id ) {
				wpmu_delete_blog( $site_id, true );
			}
		}

		public function set_up() {
			parent::set_up();
			$this->current_site_id = get_current_blog_id();
		}

		public function tear_down() {
			if ( get_current_blog_id() !== $this->current_site_id ) {
				switch_to_blog( $this->current_site_id );
			}
			parent::tear_down();
		}

		/**
		 * Tests that call_for_blog() switches to the specified blog and
		 * restores the original blog after the callback.
		 *
		 * @ticket 60366
		 */
		public function test_call_for_blog_switches_and_restores() {
			$test_site_id     = self::$site_ids['test1'];
			$original_site_id = get_current_blog_id();

			call_for_blog(
				$test_site_id,
				function () use ( $test_site_id, $original_site_id ) {
					$this->assertEquals( $test_site_id, get_current_blog_id(), 'Blog should be switched inside callback' );
				}
			);

			$this->assertEquals( $original_site_id, get_current_blog_id(), 'Blog should be restored after callback' );
		}

		/**
		 * Tests that call_for_blog() handles exceptions by restoring the original blog.
		 *
		 * @ticket 60366
		 */
		public function test_call_for_blog_handles_exceptions() {
			$test_site_id     = self::$site_ids['test1'];
			$original_site_id = get_current_blog_id();

			try {
				call_for_blog(
					$test_site_id,
					function () {
						throw new Exception( 'Test exception' );
					}
				);
			} catch ( Exception $e ) {
				$this->assertEquals( 'Test exception', $e->getMessage(), 'Exception should be thrown' );
			}

			$this->assertEquals( $original_site_id, get_current_blog_id(), 'Blog should be restored even after exception' );
		}

		/**
		 * Tests that call_for_blog() returns the value of the callback.
		 *
		 * @ticket 60366
		 */
		public function test_call_for_blog_returns_callback_value() {
			$test_site_id = self::$site_ids['test1'];
			$expected     = 'test value';

			$result = call_for_blog(
				$test_site_id,
				function () use ( $expected ) {
					return $expected;
				}
			);

			$this->assertEquals( $expected, $result, 'Return value should match callback return' );
		}

		/**
		 * Tests that call_for_blog() passes arguments to the callback.
		 *
		 * @ticket 60366
		 */
		public function test_call_for_blog_passes_arguments() {
			$test_site_id = self::$site_ids['test1'];
			$arg1         = 'test1';
			$arg2         = 'test2';

			$result = call_for_blog(
				$test_site_id,
				function ( $a, $b ) {
					return $a . $b;
				},
				$arg1,
				$arg2
			);

			$this->assertEquals( $arg1 . $arg2, $result, 'Arguments should be passed to callback' );
		}

		/**
		 * Tests that call_for_blog() does not switch to the specified blog if the current blog ID matches.
		 *
		 * @ticket 60366
		 */
		public function test_call_for_blog_with_current_blog() {
			$current_site_id = get_current_blog_id();
			$switched        = false;

			call_for_blog(
				$current_site_id,
				function () use ( &$switched ) {
					$switched = ms_is_switched();
				}
			);

			$this->assertFalse( $switched, 'Should not switch when called with current blog ID' );
		}
	}

endif;
