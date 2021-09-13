<?php

/**
 * @group dependencies
 * @group scripts
 */
class Tests_Dependencies_jQuery extends WP_UnitTestCase {

	/**
	 * @covers WP_Scripts::query
	 */
	function test_location_of_jquery() {
		$scripts = new WP_Scripts;
		wp_default_scripts( $scripts );

		if ( SCRIPT_DEBUG ) {
			$jquery_scripts = array(
				'jquery-core'    => '/wp-includes/js/jquery/jquery.js',
				'jquery-migrate' => '/wp-includes/js/jquery/jquery-migrate.js',
			);
		} else {
			$jquery_scripts = array(
				'jquery-core'    => '/wp-includes/js/jquery/jquery.min.js',
				'jquery-migrate' => '/wp-includes/js/jquery/jquery-migrate.min.js',
			);
		}

		$object = $scripts->query( 'jquery', 'registered' );
		$this->assertInstanceOf( '_WP_Dependency', $object );

		// The following test is disabled in WP 5.5 as jQuery 1.12.4 is loaded without jQuery Migrate 1.4.1,
		// and reenabled in 5.6 when jQuery 3.5.1 is loaded with jQuery Migrate 3.3.1.
		$this->assertSameSets( $object->deps, array_keys( $jquery_scripts ) );
		foreach ( $object->deps as $dep ) {
			$o = $scripts->query( $dep, 'registered' );
			$this->assertInstanceOf( '_WP_Dependency', $object );
			$this->assertArrayHasKey( $dep, $jquery_scripts );
			$this->assertSame( $jquery_scripts[ $dep ], $o->src );
		}
	}

	/**
	 * @ticket 22896
	 *
	 * @expectedIncorrectUsage wp_deregister_script
	 *
	 * @covers ::wp_script_is
	 */
	function test_dont_allow_deregister_core_scripts_in_admin() {
		set_current_screen( 'edit.php' );
		$this->assertTrue( is_admin() );
		$libraries = array(
			'jquery',
			'jquery-core',
			'jquery-migrate',
			'jquery-ui-core',
			'jquery-ui-accordion',
			'jquery-ui-autocomplete',
			'jquery-ui-button',
			'jquery-ui-datepicker',
			'jquery-ui-dialog',
			'jquery-ui-draggable',
			'jquery-ui-droppable',
			'jquery-ui-menu',
			'jquery-ui-mouse',
			'jquery-ui-position',
			'jquery-ui-progressbar',
			'jquery-ui-resizable',
			'jquery-ui-selectable',
			'jquery-ui-slider',
			'jquery-ui-sortable',
			'jquery-ui-spinner',
			'jquery-ui-tabs',
			'jquery-ui-tooltip',
			'jquery-ui-widget',
			'backbone',
			'underscore',
		);

		foreach ( $libraries as $library ) {
			// Try to deregister the script, which should fail.
			wp_deregister_script( $library );
			$this->assertTrue( wp_script_is( $library, 'registered' ) );
		}
	}

	/**
	 * @ticket 28404
	 *
	 * @covers ::wp_script_is
	 */
	function test_wp_script_is_dep_enqueued() {
		wp_enqueue_script( 'jquery-ui-accordion' );

		$this->assertTrue( wp_script_is( 'jquery', 'enqueued' ) );
		$this->assertFalse( wp_script_is( 'underscore', 'enqueued' ) );

		unset( $GLOBALS['wp_scripts'] );
	}

	/**
	 * Test placing of jQuery in footer.
	 *
	 * @ticket 25247
	 *
	 * @covers WP_Scripts::do_items
	 */
	function test_jquery_in_footer() {
		$scripts = new WP_Scripts;
		$scripts->add( 'jquery', false, array( 'jquery-core', 'jquery-migrate' ) );
		$scripts->add( 'jquery-core', '/jquery.js', array() );
		$scripts->add( 'jquery-migrate', '/jquery-migrate.js', array() );

		$scripts->enqueue( 'jquery' );

		$jquery = $scripts->query( 'jquery' );
		$jquery->add_data( 'group', 1 );
		foreach ( $jquery->deps as $dep ) {
			$scripts->add_data( $dep, 'group', 1 );
		}

		// Match only one script tag for 5.5, revert to `{2}` for 5.6.
		$this->expectOutputRegex( '/^(?:<script[^>]+><\/script>\\n){2}$/' );

		$scripts->do_items( false, 0 );
		$this->assertNotContains( 'jquery', $scripts->done );
		$this->assertNotContains( 'jquery-core', $scripts->done, 'jquery-core should be in footer but is in head' );
		$this->assertNotContains( 'jquery-migrate', $scripts->done, 'jquery-migrate should be in footer but is in head' );

		$scripts->do_items( false, 1 );
		$this->assertContains( 'jquery', $scripts->done );

		// The following test is disabled in WP 5.5 as jQuery 1.12.4 is loaded without jQuery Migrate 1.4.1,
		// and reenabled in 5.6 when jQuery 3.5.1 is loaded with Migrate 3.3.1.
		$this->assertContains( 'jquery-core', $scripts->done, 'jquery-core in footer' );
		$this->assertContains( 'jquery-migrate', $scripts->done, 'jquery-migrate in footer' );
	}
}
