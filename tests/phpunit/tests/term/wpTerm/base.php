<?php

abstract class WP_Term_UnitTestCase extends WP_UnitTestCase {
	protected static $taxonomy = 'wptests_wp_term_tax';
	protected static $term_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		global $wpdb;

		register_taxonomy( static::$taxonomy, 'post' );

		// Ensure that there is a term with ID 1.
		if ( ! get_term( 1 ) ) {
			$wpdb->insert(
				$wpdb->terms,
				array(
					'term_id' => 1,
				)
			);

			$wpdb->insert(
				$wpdb->term_taxonomy,
				array(
					'term_id'  => 1,
					'taxonomy' => static::$taxonomy,
				)
			);

			clean_term_cache( 1, static::$taxonomy );
		}

		self::$term_id = $factory->term->create( array( 'taxonomy' => static::$taxonomy ) );
	}

	public function set_up() {
		parent::set_up();
		register_taxonomy( static::$taxonomy, 'post' );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_handle_dynamic_property() {
		$term_args = self::get_term_args();

		return array(
			'unknown null'       => array(
				'name'     => 'unknown',
				'value'    => null,
				'expected' => false,
			),
			'unknown with value' => array(
				'name'  => 'unknown_with_value',
				'value' => array( 'unknown', 'dynamic', 'property' ),
			),
			// Used by wp_update_term().
			'data'               => array(
				'name'  => 'data',
				'value' => array(
					'term_id'          => 2,
					'name'             => $term_args['name'],
					'slug'             => $term_args['slug'],
					'term_group'       => 0,
					'term_taxonomy_id' => 2,
					'taxonomy'         => self::$taxonomy,
					'description'      => $term_args['description'],
					'parent'           => 0,
					'count'            => 1,
					'filter'           => 'raw',
				),
			),
			// Added by WP_Term_Query::get_terms().
			'object_id'          => array(
				'name'  => 'object_id',
				'value' => 5,
			),
		);
	}

	/**
	 * Get term arguments.
	 *
	 * @return array
	 */
	protected static function get_term_args() {
		$unique_id = uniqid();
		$term_args = array(
			'name'        => 'Test Dynamic Property ' . $unique_id,
			'slug'        => 'test-dynamic-property-' . $unique_id,
			'taxonomy'    => self::$taxonomy,
			'description' => 'Term for testing dynamic property',
			'parent'      => 0,
		);
		return $term_args;
	}

	/**
	 * Get WP_Term::$dynamic_property.
	 *
	 * @param WP_Term $term Instance of the term.
	 * @return array Array of the dynamic properties.
	 */
	protected function get_actual_dynamic_property_value( $term ) {
		$dynamic_property = new ReflectionProperty( WP_Term::class, 'dynamic_properties' );
		$dynamic_property->setAccessible( true );

		$actual = $dynamic_property->getValue( $term );

		$dynamic_property->setAccessible( false );

		return $actual;
	}

	/**
	 * Set WP_Term::$dynamic_property.
	 *
	 * @param WP_Term $term  Instance of the term.
	 * @param string  $name  Name of the dynamic property.
	 * @param mixed   $value Value to set.
	 */
	protected function set_actual_dynamic_property_value( $term, $name, $value ) {
		$dynamic_property = new ReflectionProperty( WP_Term::class, 'dynamic_properties' );
		$dynamic_property->setAccessible( true );

		$actual          = $dynamic_property->getValue( $term );
		$actual[ $name ] = $value;
		$dynamic_property->setValue( $term, $actual );

		$dynamic_property->setAccessible( false );
	}
}
