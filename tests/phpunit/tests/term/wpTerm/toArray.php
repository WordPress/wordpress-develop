<?php

/**
 * @group term
 * @group taxonomy
 *
 * @covers WP_Term::to_array
 */
class Tests_Term_WpTerm_ToArray extends WP_Term_UnitTestCase {
	private $term;
	private $term_args;

	public function set_up() {
		parent::set_up();

		$this->term_args = self::get_term_args();
		self::$term_id   = self::factory()->term->create( $this->term_args );
		$this->term      = WP_Term::get_instance( self::$term_id, self::$taxonomy );
	}

	/**
	 * @ticket 61890
	 */
	public function test_should_include_public_properties_when_no_dynamic_properties() {
		$actual   = $this->term->to_array();
		$expected = array(
			'term_id'          => self::$term_id,
			'name'             => $this->term_args['name'],
			'slug'             => $this->term_args['slug'],
			'term_group'       => 0,
			'term_taxonomy_id' => $this->term->term_taxonomy_id,
			'taxonomy'         => self::$taxonomy,
			'description'      => $this->term_args['description'],
			'parent'           => 0,
			'count'            => 0,
			'filter'           => 'raw',
		);

		$this->assertEmpty( $this->get_actual_dynamic_property_value( $this->term ), 'There should be no dynamic properties. WP_Term::$dynamic_properties should be empty' );
		$this->assertSame( $expected, $actual, 'WP_Term::to_array() should only include public declared properties' );
		$this->assertArrayNotHasKey( 'dynamic_properties', $actual, 'WP_Term::to_array() should not include WP_Term::$dynamic_properties' );
	}

	/**
	 * @ticket 61890
	 */
	public function test_array_cast_should_match_to_array_when_no_dynamic_properties() {
		$actual_type_cast = (array) $this->term;
		$actual_to_array  = $this->term->to_array();

		$this->assertEmpty( $this->get_actual_dynamic_property_value( $this->term ), 'There should be no dynamic properties. WP_Term::$dynamic_properties should be empty' );
		$this->assertNotSame( $actual_to_array, $actual_type_cast, 'Type casting WP_Term object to array should match WP_Term::to_array()' );
	}

	public function test_should_include_public_and_dynamic_properties() {
		// Add a dynamic property.
		$this->term->test = 'test dynamic property';

		$actual   = $this->term->to_array();
		$expected = array(
			'term_id'          => self::$term_id,
			'name'             => $this->term_args['name'],
			'slug'             => $this->term_args['slug'],
			'term_group'       => 0,
			'term_taxonomy_id' => $this->term->term_taxonomy_id,
			'taxonomy'         => self::$taxonomy,
			'description'      => $this->term_args['description'],
			'parent'           => 0,
			'count'            => 0,
			'filter'           => 'raw',
			'test'             => 'test dynamic property',
		);

		$this->assertSame( $expected, $actual, 'WP_Term::to_array() should only include public declared properties' );
		$this->assertArrayNotHasKey( 'dynamic_properties', $actual, 'WP_Term::to_array() should not include WP_Term::$dynamic_properties' );
	}
}
