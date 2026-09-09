<?php

/**
 * @group import
 */
class Tests_Import_Import extends WP_UnitTestCase {
	/**
	 * @covers ::get_importers
	 */
	public function test_ordering_of_importers() {
		global $wp_importers;

		$_wp_importers = $wp_importers; // Preserve global state.
		$wp_importers  = array(
			'xyz1' => array( 'xyz1' ),
			'XYZ2' => array( 'XYZ2' ),
			'abc2' => array( 'abc2' ),
			'ABC1' => array( 'ABC1' ),
			'def1' => array( 'def1' ),
		);
		$this->assertSame(
			array(
				'ABC1' => array( 'ABC1' ),
				'abc2' => array( 'abc2' ),
				'def1' => array( 'def1' ),
				'xyz1' => array( 'xyz1' ),
				'XYZ2' => array( 'XYZ2' ),
			),
			get_importers()
		);

		$wp_importers = $_wp_importers; // Restore global state.
	}

	/**
	 * @covers ::wp_get_available_importers
	 */
	public function test_wp_get_available_importers_adds_popular_importers() {
		global $wp_importers;

		$_wp_importers = $wp_importers; // Preserve global state.
		$wp_importers  = array(
			'xyz1' => array( 'xyz1', 'Registered importer', '__return_null' ),
		);

		$popular_importers = array(
			'wordpress' => array(
				'name'        => 'WordPress',
				'description' => 'Import posts, pages, comments, custom fields, categories, and tags from a WordPress export file.',
				'plugin-slug' => 'wordpress-importer',
				'importer-id' => 'wordpress',
			),
		);

		$available_importers = wp_get_available_importers( $popular_importers );

		$this->assertArrayHasKey( 'xyz1', $available_importers );
		$this->assertSame( array( 'xyz1', 'Registered importer', '__return_null' ), $available_importers['xyz1'] );

		// phpcs:ignore WordPress.WP.CapitalPDangit.MisspelledInText -- Importer slugs are lowercase.
		$this->assertArrayHasKey( 'wordpress', $available_importers );
		$this->assertSame(
			array(
				'WordPress',
				'Import posts, pages, comments, custom fields, categories, and tags from a WordPress export file.',
				'install' => 'wordpress-importer',
			),
			$available_importers['wordpress']
		);

		$wp_importers = $_wp_importers; // Restore global state.
	}
}
