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
	 * Registers an icon in the icons registry.
	 *
	 * @param string $icon_name       Namespaced icon name (e.g. "plugin-a/alpha").
	 * @param array  $icon_properties Icon properties (label, content, file_path).
	 * @return bool True if the icon was registered successfully.
	 */
	private function register_icon( $icon_name, $icon_properties ) {
		return WP_Icons_Registry::get_instance()->register( $icon_name, $icon_properties );
	}

	/**
	 * Data provider for valid collection slug candidates.
	 *
	 * @return array[]
	 */
	public function data_valid_collection_slugs() {
		return array(
			'simple slug'            => array( 'mycollection' ),
			'digit at the start'     => array( '1-collection' ),
			'digit in the slug'      => array( 'my-1-collection' ),
			'digit at the end'       => array( 'collection1' ),
			'underscore in the slug' => array( 'my_collection' ),
			'hyphen in the slug'     => array( 'my-collection' ),
		);
	}

	/**
	 * Should register a collection with a valid slug.
	 *
	 * @ticket 64847
	 *
	 * @dataProvider data_valid_collection_slugs
	 *
	 * @covers ::register
	 */
	public function test_register_collection( $slug ) {
		$result = $this->collections->register( $slug, array( 'label' => 'My Collection' ) );

		$this->assertTrue( $result );
		$this->assertTrue( $this->collections->is_registered( $slug ) );
	}

	/**
	 * Data provider for invalid collection slug candidates.
	 *
	 * @return array[]
	 */
	public function data_invalid_collection_slugs() {
		return array(
			'non-string slug'         => array( 1 ),
			'contains slash'          => array( 'plugin/icons' ),
			'uppercase characters'    => array( 'Plugin' ),
			'underscore at the start' => array( '_my-plugin' ),
			'underscore at the end'   => array( 'my-plugin_' ),
			'hyphen at the start'     => array( '-my-plugin' ),
			'hyphen at the end'       => array( 'my-plugin-' ),
		);
	}

	/**
	 * Should fail to register a collection with an invalid slug.
	 *
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
	 * Should fail to register the same collection twice.
	 *
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
	 * Should fail to register a collection with an unknown property.
	 *
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
	 * Unregistering a collection should cascade and remove all icons
	 * belonging to it, while leaving icons from other collections intact.
	 *
	 * @ticket 64847
	 *
	 * @covers ::unregister
	 */
	public function test_unregister_collection_cascades_to_icons() {
		$this->collections->register( 'plugin-a', array( 'label' => 'A' ) );
		$this->collections->register( 'plugin-b', array( 'label' => 'B' ) );

		$icons = WP_Icons_Registry::get_instance();
		$this->register_icon(
			'plugin-a/alpha',
			array(
				'label'   => 'Alpha',
				'content' => '<svg></svg>',
			)
		);
		$this->register_icon(
			'plugin-a/beta',
			array(
				'label'   => 'Beta',
				'content' => '<svg></svg>',
			)
		);
		$this->register_icon(
			'plugin-b/gamma',
			array(
				'label'   => 'Gamma',
				'content' => '<svg></svg>',
			)
		);

		$this->assertTrue( $icons->is_registered( 'plugin-a/alpha' ) );
		$this->assertTrue( $icons->is_registered( 'plugin-a/beta' ) );

		$this->assertTrue( $this->collections->unregister( 'plugin-a' ) );

		$this->assertFalse( $icons->is_registered( 'plugin-a/alpha' ) );
		$this->assertFalse( $icons->is_registered( 'plugin-a/beta' ) );
		$this->assertTrue( $icons->is_registered( 'plugin-b/gamma' ) );

		$icons->unregister( 'plugin-b/gamma' );
	}

	/**
	 * Should fail to unregister a collection that was never registered.
	 *
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
