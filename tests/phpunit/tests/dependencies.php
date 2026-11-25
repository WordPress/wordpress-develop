<?php
/**
 * @group dependencies
 * @group scripts
 */
class Tests_Dependencies extends WP_UnitTestCase {
	public function test_add() {
		$dep = new WP_Dependencies();

		$this->assertTrue( $dep->add( 'one', '' ) );
		$this->assertTrue( $dep->add( 'two', '' ) );

		$this->assertInstanceOf( '_WP_Dependency', $dep->query( 'one' ) );
		$this->assertInstanceOf( '_WP_Dependency', $dep->query( 'two' ) );

		// Cannot reuse names.
		$this->assertFalse( $dep->add( 'one', '' ) );
	}

	public function test_remove() {
		$dep = new WP_Dependencies();

		$this->assertTrue( $dep->add( 'one', '' ) );
		$this->assertTrue( $dep->add( 'two', '' ) );

		$dep->remove( 'one' );

		$this->assertFalse( $dep->query( 'one' ) );
		$this->assertInstanceOf( '_WP_Dependency', $dep->query( 'two' ) );
	}

	public function test_enqueue() {
		$dep = new WP_Dependencies();

		$this->assertTrue( $dep->add( 'one', '' ) );
		$this->assertTrue( $dep->add( 'two', '' ) );

		$this->assertFalse( $dep->query( 'one', 'queue' ) );
		$dep->enqueue( 'one' );
		$this->assertTrue( $dep->query( 'one', 'queue' ) );
		$this->assertFalse( $dep->query( 'two', 'queue' ) );

		$dep->enqueue( 'two' );
		$this->assertTrue( $dep->query( 'one', 'queue' ) );
		$this->assertTrue( $dep->query( 'two', 'queue' ) );
	}

	public function test_dequeue() {
		$dep = new WP_Dependencies();

		$this->assertTrue( $dep->add( 'one', '' ) );
		$this->assertTrue( $dep->add( 'two', '' ) );

		$dep->enqueue( 'one' );
		$dep->enqueue( 'two' );
		$this->assertTrue( $dep->query( 'one', 'queue' ) );
		$this->assertTrue( $dep->query( 'two', 'queue' ) );

		$dep->dequeue( 'one' );
		$this->assertFalse( $dep->query( 'one', 'queue' ) );
		$this->assertTrue( $dep->query( 'two', 'queue' ) );

		$dep->dequeue( 'two' );
		$this->assertFalse( $dep->query( 'one', 'queue' ) );
		$this->assertFalse( $dep->query( 'two', 'queue' ) );
	}

	public function test_enqueue_args() {
		$dep = new WP_Dependencies();

		$this->assertTrue( $dep->add( 'one', '' ) );
		$this->assertTrue( $dep->add( 'two', '' ) );

		$this->assertFalse( $dep->query( 'one', 'queue' ) );
		$dep->enqueue( 'one?arg' );
		$this->assertTrue( $dep->query( 'one', 'queue' ) );
		$this->assertFalse( $dep->query( 'two', 'queue' ) );
		$this->assertSame( 'arg', $dep->args['one'] );

		$dep->enqueue( 'two?arg' );
		$this->assertTrue( $dep->query( 'one', 'queue' ) );
		$this->assertTrue( $dep->query( 'two', 'queue' ) );
		$this->assertSame( 'arg', $dep->args['two'] );
	}

	public function test_dequeue_args() {
		$dep = new WP_Dependencies();

		$this->assertTrue( $dep->add( 'one', '' ) );
		$this->assertTrue( $dep->add( 'two', '' ) );

		$dep->enqueue( 'one?arg' );
		$dep->enqueue( 'two?arg' );
		$this->assertTrue( $dep->query( 'one', 'queue' ) );
		$this->assertTrue( $dep->query( 'two', 'queue' ) );
		$this->assertSame( 'arg', $dep->args['one'] );
		$this->assertSame( 'arg', $dep->args['two'] );

		$dep->dequeue( 'one' );
		$this->assertFalse( $dep->query( 'one', 'queue' ) );
		$this->assertTrue( $dep->query( 'two', 'queue' ) );
		$this->assertArrayNotHasKey( 'one', $dep->args );

		$dep->dequeue( 'two' );
		$this->assertFalse( $dep->query( 'one', 'queue' ) );
		$this->assertFalse( $dep->query( 'two', 'queue' ) );
		$this->assertArrayNotHasKey( 'two', $dep->args );
	}

	/**
	 * @ticket 21741
	 */
	public function test_query_and_registered_enqueued() {
		$dep = new WP_Dependencies();

		$this->assertTrue( $dep->add( 'one', '' ) );
		$this->assertInstanceOf( '_WP_Dependency', $dep->query( 'one' ) );
		$this->assertInstanceOf( '_WP_Dependency', $dep->query( 'one', 'registered' ) );
		$this->assertInstanceOf( '_WP_Dependency', $dep->query( 'one', 'scripts' ) );

		$this->assertFalse( $dep->query( 'one', 'enqueued' ) );
		$this->assertFalse( $dep->query( 'one', 'queue' ) );

		$dep->enqueue( 'one' );

		$this->assertTrue( $dep->query( 'one', 'enqueued' ) );
		$this->assertTrue( $dep->query( 'one', 'queue' ) );

		$dep->dequeue( 'one' );

		$this->assertFalse( $dep->query( 'one', 'queue' ) );
		$this->assertInstanceOf( '_WP_Dependency', $dep->query( 'one' ) );

		$dep->remove( 'one' );
		$this->assertFalse( $dep->query( 'one' ) );
	}

	public function test_enqueue_before_register() {
		$dep = new WP_Dependencies();

		$this->assertArrayNotHasKey( 'one', $dep->registered );

		$dep->enqueue( 'one' );

		$this->assertNotContains( 'one', $dep->queue );

		$this->assertTrue( $dep->add( 'one', '' ) );

		$this->assertContains( 'one', $dep->queue );
	}

	/**
	 * Data provider for test_get_etag.
	 *
	 * @return array[]
	 */
	public static function data_provider_get_etag() {
		return array(
			'should accept one dependency'              => array(
				'load'               => array(
					'abcd' => '1.0.2',
				),
				'hash_source_string' => 'WP:6.7;abcd:1.0.2;',
				'expected'           => 'W/"8145d7e3c41d5a9cc2bccba4afa861fc"',
			),
			'should accept empty array of dependencies' => array(
				'load'               => array(),
				'hash_source_string' => 'WP:6.7;',
				'expected'           => 'W/"7ee896c19250a3d174f11469a4ad0b1e"',
			),
		);
	}

	/**
	 * Tests get_etag method for WP_Scripts.
	 *
	 * @ticket 58433
	 * @ticket 61485
	 * @ticket 57548
	 *
	 * @covers WP_Dependencies::get_etag
	 *
	 * @expectedDeprecated WP_Dependencies::get_etag
	 *
	 * @dataProvider data_provider_get_etag
	 *
	 * @param array  $load               List of scripts to load.
	 * @param string $hash_source_string Hash source string.
	 * @param string $expected           Expected etag.
	 */
	public function test_get_etag_scripts( $load, $hash_source_string, $expected ) {
		global $wp_version;
		// Modify global to avoid tests needing to change with each new version of WordPress.
		$original_wp_version = $wp_version;
		$wp_version          = '6.7';
		$instance            = wp_scripts();

		foreach ( $load as $handle => $ver ) {
			// The src should not be empty.
			wp_enqueue_script( $handle, 'https://example.org', array(), $ver );
		}

		$result = $instance->get_etag( array_keys( $load ) );

		// Restore global prior to making assertions.
		$wp_version = $original_wp_version;

		$this->assertSame( $expected, $result, "Expected MD hash: $expected for $hash_source_string, but got: $result." );
	}

	/**
	 * Tests get_etag method for WP_Styles.
	 *
	 * @ticket 58433
	 * @ticket 61485
	 * @ticket 57548
	 *
	 * @covers WP_Dependencies::get_etag
	 *
	 * @expectedDeprecated WP_Dependencies::get_etag
	 *
	 * @dataProvider data_provider_get_etag
	 *
	 * @param array  $load               List of styles to load.
	 * @param string $hash_source_string Hash source string.
	 * @param string $expected           Expected etag.
	 */
	public function test_get_etag_styles( $load, $hash_source_string, $expected ) {
		global $wp_version;
		// Modify global to avoid tests needing to change with each new version of WordPress.
		$original_wp_version = $wp_version;
		$wp_version          = '6.7';
		$instance            = wp_styles();

		foreach ( $load as $handle => $ver ) {
			// The src should not be empty.
			wp_enqueue_style( $handle, 'https://example.cdn', array(), $ver );
		}

		$result = $instance->get_etag( array_keys( $load ) );

		// Restore global prior to making assertions.
		$wp_version = $original_wp_version;

		$this->assertSame( $expected, $result, "Expected MD hash: $expected for $hash_source_string, but got: $result." );
	}

	/**
	 * Tests that a handle depending on itself does not recurse infinitely.
	 *
	 * @ticket 46089
	 *
	 * @covers WP_Dependencies::all_deps
	 */
	public function test_all_deps_handles_self_dependency() {
		$dependencies = new WP_Dependencies();
		$dependencies->add( 'one', '', array( 'one' ) );

		$this->assertTrue( $dependencies->all_deps( 'one' ) );
		$this->assertSame( array( 'one' ), $dependencies->to_do );
	}

	/**
	 * Tests that two handles depending on each other do not recurse infinitely.
	 *
	 * @ticket 46089
	 *
	 * @covers WP_Dependencies::all_deps
	 */
	public function test_all_deps_handles_circular_dependency() {
		$dependencies = new WP_Dependencies();
		$dependencies->add( 'one', '', array( 'two' ) );
		$dependencies->add( 'two', '', array( 'one' ) );

		$this->assertTrue( $dependencies->all_deps( 'one' ) );
		$this->assertSame( array( 'two', 'one' ), $dependencies->to_do );
	}

	/**
	 * Tests that a circular dependency spanning several handles does not recurse infinitely.
	 *
	 * @ticket 46089
	 *
	 * @covers WP_Dependencies::all_deps
	 */
	public function test_all_deps_handles_indirect_circular_dependency() {
		$dependencies = new WP_Dependencies();
		$dependencies->add( 'one', '', array( 'two' ) );
		$dependencies->add( 'two', '', array( 'three' ) );
		$dependencies->add( 'three', '', array( 'one' ) );

		$this->assertTrue( $dependencies->all_deps( 'one' ) );
		$this->assertSame( array( 'three', 'two', 'one' ), $dependencies->to_do );
	}

	/**
	 * Tests that a dependency shared by several handles is still resolved once.
	 *
	 * @ticket 46089
	 *
	 * @covers WP_Dependencies::all_deps
	 */
	public function test_all_deps_still_resolves_shared_dependencies() {
		$dependencies = new WP_Dependencies();
		$dependencies->add( 'one', '', array( 'two', 'three' ) );
		$dependencies->add( 'two', '', array( 'three' ) );
		$dependencies->add( 'three', '' );

		$this->assertTrue( $dependencies->all_deps( 'one' ) );
		$this->assertSame( array( 'three', 'two', 'one' ), $dependencies->to_do );
	}

	/**
	 * Tests that a circular dependency with a missing dependency terminates without queueing the handle.
	 *
	 * @ticket 46089
	 *
	 * @covers WP_Dependencies::all_deps
	 */
	public function test_all_deps_circular_dependency_with_missing_dependency_fails() {
		$this->setExpectedIncorrectUsage( 'WP_Dependencies::add' );

		$dependencies = new WP_Dependencies();
		$dependencies->add( 'one', '', array( 'two' ) );
		$dependencies->add( 'two', '', array( 'one', 'missing' ) );

		$dependencies->all_deps( 'one' );

		$this->assertSame( array(), $dependencies->to_do );
	}
}
