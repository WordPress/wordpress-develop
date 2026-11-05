<?php

/**
 * Tests the metadata isolation provided by the WordPress test case.
 *
 * The outer test has no WordPress transaction. The inner test cases exercise
 * their real setup and teardown routines in sequence.
 *
 * @group phpunit
 * @group meta
 * @ticket 48300
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class Tests_Includes_RegisteredMeta extends PHPUnit_Adapter_TestCase {

	/**
	 * @dataProvider data_registered_metadata
	 *
	 * @param string $object_type    Metadata object type.
	 * @param string $object_subtype Metadata object subtype.
	 * @param bool   $unregister     Whether to remove rather than replace the registration.
	 */
	public function test_restores_metadata_registered_before_test_methods( $object_type, $object_subtype, $unregister ) {
		$key  = '_test_registered_meta';
		$args = array(
			'object_subtype'    => $object_subtype,
			'single'            => true,
			'default'           => 'Original default',
			'sanitize_callback' => 'sanitize_text_field',
			'auth_callback'     => '__return_true',
		);
		register_meta( $object_type, $key, $args );
		$expected = get_registered_meta_keys( $object_type, $object_subtype )[ $key ];
		$case     = $this->create_test_case();
		$class    = get_class( $case );
		$class::set_up_before_class();

		try {
			$case->set_up();
			try {
				if ( $unregister ) {
					unregister_meta_key( $object_type, $key, $object_subtype );
				} else {
					register_meta(
						$object_type,
						$key,
						array_merge(
							$args,
							array(
								'default'       => 'Changed default',
								'auth_callback' => '__return_false',
							)
						)
					);
				}
				register_meta( $object_type, '_test_temporary_meta', $args );
			} finally {
				$case->tear_down();
			}

			$next_case = new $class( 'test_second' );
			$next_case->set_up();
			try {
				$registered = get_registered_meta_keys( $object_type, $object_subtype );
				$this->assertArrayHasKey( $key, $registered );
				$this->assertSame( $expected, $registered[ $key ] );
				$this->assertSame( 'Example', sanitize_meta( $key, '<em>Example</em>', $object_type, $object_subtype ) );
				$hook = "auth_{$object_type}_meta_{$key}";
				if ( $object_subtype ) {
					$hook .= "_for_{$object_subtype}";
				}
				$this->assertTrue( apply_filters( $hook, false, $key, 1, 1, 'edit_post_meta', array() ) );
				$this->assertFalse( registered_meta_key_exists( $object_type, '_test_temporary_meta', $object_subtype ) );
			} finally {
				$next_case->tear_down();
			}
		} finally {
			$class::tear_down_after_class();
		}
	}

	/**
	 * @return array[]
	 */
	public static function data_registered_metadata() {
		// Each case boots WordPress separately. Cover the scopes and mutation modes
		// without repeating their full cross-product in isolated processes.
		return array(
			'post replaced'         => array( 'post', '', false ),
			'post subtype removed'  => array( 'post', 'page', true ),
			'term subtype replaced' => array( 'term', 'category', false ),
			'user removed'          => array( 'user', '', true ),
		);
	}

	public function test_restores_class_registrations_and_their_callbacks() {
		// Capture the ordinary hook baseline before the class fixture exists.
		$previous_case = $this->create_test_case();
		$previous_case->set_up();
		$previous_case->tear_down();

		register_post_meta( 'post', '_test_plugin_meta', array( 'default' => 'Plugin default' ) );
		$case  = new class( 'test_first' ) extends WP_UnitTestCase {
			public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
				register_post_meta(
					'post',
					'_test_class_meta',
					array(
						'default'           => 'Class default',
						'sanitize_callback' => 'sanitize_text_field',
						'auth_callback'     => '__return_true',
					)
				);
				register_post_meta( 'post', '_test_plugin_meta', array( 'default' => 'Class override' ) );
				// Old-style registrations have callbacks but no registry entry.
				register_meta( 'post', '_test_legacy_meta', 'sanitize_text_field' );
			}

			public function test_first() {}
			public function test_second() {}
		};
		$class = get_class( $case );
		$class::set_up_before_class();

		try {
			foreach ( array( $case, new $class( 'test_second' ) ) as $test_case ) {
				$test_case->set_up();
				try {
					$registered = get_registered_meta_keys( 'post', 'post' );
					$this->assertArrayHasKey( '_test_class_meta', $registered );
					$this->assertArrayHasKey( '_test_plugin_meta', $registered );
					$this->assertSame( 'Class default', $registered['_test_class_meta']['default'] );
					$this->assertSame( 'Class override', $registered['_test_plugin_meta']['default'] );
					$this->assertSame( 'Example', sanitize_meta( '_test_class_meta', '<em>Example</em>', 'post', 'post' ) );
					$this->assertSame( 'Example', sanitize_meta( '_test_legacy_meta', '<em>Example</em>', 'post' ) );
					unregister_post_meta( 'post', '_test_class_meta' );
					remove_filter( 'sanitize_post_meta__test_legacy_meta', 'sanitize_text_field' );
				} finally {
					$test_case->tear_down();
				}
			}
		} finally {
			$class::tear_down_after_class();
		}

		$this->assertFalse( registered_meta_key_exists( 'post', '_test_class_meta', 'post' ) );
		$registered = get_registered_meta_keys( 'post', 'post' );
		$this->assertArrayHasKey( '_test_plugin_meta', $registered );
		$this->assertSame( 'Plugin default', $registered['_test_plugin_meta']['default'] );
		$this->assertSame( '<em>Example</em>', sanitize_meta( '_test_legacy_meta', '<em>Example</em>', 'post' ) );
		$this->assertFalse( has_filter( 'sanitize_post_meta__test_class_meta_for_post' ) );
	}

	public function test_restores_defaults_sanitization_and_authorization_for_real_posts() {
		register_post_meta(
			'post',
			'_test_post_meta',
			array(
				'single'            => true,
				'default'           => 'Original default',
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => '__return_true',
			)
		);
		$case  = new class( 'test_first' ) extends WP_UnitTestCase {
			public static $author_id;
			public static $post_id;

			public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
				self::$author_id = $factory->user->create( array( 'role' => 'author' ) );
				self::$post_id   = $factory->post->create( array( 'post_author' => self::$author_id ) );
			}

			public function test_first() {}
			public function test_second() {}
		};
		$class = get_class( $case );
		$class::set_up_before_class();

		try {
			foreach ( array( $case, new $class( 'test_second' ) ) as $test_case ) {
				$test_case->set_up();
				try {
					wp_set_current_user( $class::$author_id );
					$this->assertTrue( current_user_can( 'edit_post_meta', $class::$post_id, '_test_post_meta' ) );
					$this->assertSame( 'Original default', get_post_meta( $class::$post_id, '_test_post_meta', true ) );
					update_post_meta( $class::$post_id, '_test_post_meta', '<em>Example</em>' );
					$this->assertSame( 'Example', get_post_meta( $class::$post_id, '_test_post_meta', true ) );
					register_post_meta( 'post', '_test_post_meta', array( 'auth_callback' => '__return_false' ) );
					$this->assertFalse( current_user_can( 'edit_post_meta', $class::$post_id, '_test_post_meta' ) );
				} finally {
					$test_case->tear_down();
				}
			}
		} finally {
			$class::tear_down_after_class();
		}
	}

	/**
	 * Creates a test case with valid method names for the real setup routines.
	 *
	 * @return WP_UnitTestCase
	 */
	private function create_test_case() {
		return new class( 'test_first' ) extends WP_UnitTestCase {
			public function test_first() {}
			public function test_second() {}
		};
	}
}
