<?php
/**
 * Unit tests covering WP_Icon_Collections_Registry functionality.
 *
 * @package WordPress
 * @since 7.1.0
 *
 * @group icons
 *
 * @coversDefaultClass WP_Icon_Collections_Registry
 */
class Tests_Icons_WpIconCollectionsRegistry extends WP_UnitTestCase {

	/**
	 * @var WP_Icon_Collections_Registry
	 */
	protected $collections;

	public function set_up() {
		parent::set_up();
		$this->collections = WP_Icon_Collections_Registry::get_instance();
	}

	public function tear_down() {
		foreach ( array( 'plugin-a', 'plugin-b', 'my-collection' ) as $slug ) {
			if ( $this->collections->is_registered( $slug ) ) {
				$this->collections->unregister( $slug );
			}
		}
		parent::tear_down();
	}

	/**
	 * @ticket 64847
	 *
	 * @covers ::register
	 */
	public function test_register_collection() {
		$result = $this->collections->register(
			'my-collection',
			array(
				'label'       => 'My Collection',
				'description' => 'A collection.',
			)
		);

		$this->assertTrue( $result );
		$this->assertTrue( $this->collections->is_registered( 'my-collection' ) );

		$registered = $this->collections->get_registered( 'my-collection' );
		$this->assertSame( 'my-collection', $registered['slug'] );
		$this->assertSame( 'My Collection', $registered['label'] );
		$this->assertSame( 'A collection.', $registered['description'] );
	}

	/**
	 * @ticket 64847
	 *
	 * @dataProvider data_invalid_collection_slugs
	 *
	 * @covers ::register
	 *
	 * @expectedIncorrectUsage WP_Icon_Collections_Registry::register
	 *
	 * @param mixed $slug Invalid slug candidate.
	 */
	public function test_register_rejects_invalid_slug( $slug ) {
		$result = $this->collections->register( $slug, array( 'label' => 'X' ) );
		$this->assertFalse( $result );
	}

	/**
	 * Data provider for invalid collection slug candidates.
	 *
	 * Collection slugs must be strings that start with a lowercase letter
	 * and contain only lowercase letters and hyphens (no digits, no slashes,
	 * no uppercase characters).
	 *
	 * @return array[]
	 */
	public function data_invalid_collection_slugs() {
		return array(
			'non-string slug'      => array( 1 ),
			'contains slash'       => array( 'plugin/icons' ),
			'contains digits'      => array( 'plugin1' ),
			'uppercase characters' => array( 'Plugin' ),
			'leading hyphen'       => array( '-plugin' ),
		);
	}

	/**
	 * @ticket 64847
	 *
	 * @covers ::register
	 *
	 * @expectedIncorrectUsage WP_Icon_Collections_Registry::register
	 */
	public function test_register_twice_fails() {
		$this->assertTrue( $this->collections->register( 'my-collection', array( 'label' => 'A' ) ) );
		$this->assertFalse( $this->collections->register( 'my-collection', array( 'label' => 'A' ) ) );
	}

	/**
	 * @ticket 64847
	 *
	 * @covers ::register
	 *
	 * @expectedIncorrectUsage WP_Icon_Collections_Registry::register
	 */
	public function test_register_rejects_unknown_property() {
		$result = $this->collections->register(
			'my-collection',
			array(
				'label' => 'A',
				'bogus' => 'nope',
			)
		);
		$this->assertFalse( $result );
	}

	/**
	 * @ticket 64847
	 *
	 * @covers ::unregister
	 */
	public function test_unregister_collection_cascades_to_icons() {
		$this->collections->register( 'plugin-a', array( 'label' => 'A' ) );
		$this->collections->register( 'plugin-b', array( 'label' => 'B' ) );

		$icons = WP_Icons_Registry::get_instance();
		$icons->register(
			'alpha',
			array(
				'label'      => 'Alpha',
				'content'    => '<svg></svg>',
				'collection' => 'plugin-a',
			)
		);
		$icons->register(
			'beta',
			array(
				'label'      => 'Beta',
				'content'    => '<svg></svg>',
				'collection' => 'plugin-a',
			)
		);
		$icons->register(
			'gamma',
			array(
				'label'      => 'Gamma',
				'content'    => '<svg></svg>',
				'collection' => 'plugin-b',
			)
		);

		$this->assertTrue( $icons->is_registered( 'plugin-a/alpha' ) );
		$this->assertTrue( $icons->is_registered( 'plugin-a/beta' ) );

		$this->assertTrue( $this->collections->unregister( 'plugin-a' ) );

		$this->assertFalse( $icons->is_registered( 'plugin-a/alpha' ) );
		$this->assertFalse( $icons->is_registered( 'plugin-a/beta' ) );
		$this->assertTrue( $icons->is_registered( 'plugin-b/gamma' ) );

		$icons->unregister( 'gamma', 'plugin-b' );
	}

	/**
	 * @ticket 64847
	 *
	 * @covers ::unregister
	 *
	 * @expectedIncorrectUsage WP_Icon_Collections_Registry::unregister
	 */
	public function test_unregister_unknown_collection() {
		$this->assertFalse( $this->collections->unregister( 'ghost' ) );
	}
}
