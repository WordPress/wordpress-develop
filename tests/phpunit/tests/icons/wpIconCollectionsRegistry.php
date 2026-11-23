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
		foreach ( array( 'plugin-a', 'plugin-a-extra', 'plugin-b', 'my-collection' ) as $slug ) {
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
	public static function data_valid_collection_slugs() {
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
	public static function data_invalid_collection_slugs() {
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
	 * Unregistering a collection should not load the content of file-based icons.
	 *
	 * The icon below points at a file that does not exist, so reading its
	 * content would trigger an error.
	 *
	 * @ticket 66710
	 *
	 * @covers ::unregister
	 */
	public function test_unregister_collection_does_not_load_icon_files() {
		$this->collections->register( 'plugin-a', array( 'label' => 'A' ) );
		$this->register_icon(
			'plugin-a/from-file',
			array(
				'label'     => 'From File',
				'file_path' => trailingslashit( get_temp_dir() ) . 'wp-icon-does-not-exist-66710.svg',
			)
		);

		$error_action = new MockAction();
		add_action( 'wp_trigger_error_always_run', array( $error_action, 'action' ) );
		add_filter( 'wp_trigger_error_trigger_error', '__return_false' );

		$this->assertTrue( $this->collections->unregister( 'plugin-a' ), 'The collection should be unregistered.' );
		$this->assertFalse( WP_Icons_Registry::get_instance()->is_registered( 'plugin-a/from-file' ), 'The icon should be unregistered along with its collection.' );
		$this->assertSame( 0, $error_action->get_call_count(), 'Icon content should not be loaded when unregistering a collection.' );
	}

	/**
	 * Unregistering a collection should not remove icons from a collection
	 * whose slug starts with the same characters.
	 *
	 * @ticket 66710
	 *
	 * @covers ::unregister
	 */
	public function test_unregister_collection_does_not_affect_collections_sharing_a_prefix() {
		$this->collections->register( 'plugin-a', array( 'label' => 'A' ) );
		$this->collections->register( 'plugin-a-extra', array( 'label' => 'A Extra' ) );

		$icons = WP_Icons_Registry::get_instance();
		$this->register_icon(
			'plugin-a/alpha',
			array(
				'label'   => 'Alpha',
				'content' => '<svg></svg>',
			)
		);
		$this->register_icon(
			'plugin-a-extra/alpha',
			array(
				'label'   => 'Alpha Extra',
				'content' => '<svg></svg>',
			)
		);

		$this->assertTrue( $this->collections->unregister( 'plugin-a' ) );

		$this->assertFalse( $icons->is_registered( 'plugin-a/alpha' ) );
		$this->assertTrue( $icons->is_registered( 'plugin-a-extra/alpha' ) );
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

	/**
	 * Should register collections as public by default.
	 *
	 * @ticket 66087
	 *
	 * @covers ::register
	 */
	public function test_register_collection_defaults_to_public() {
		$this->collections->register( 'my-collection', array( 'label' => 'My Collection' ) );

		$this->assertTrue( $this->collections->get_registered( 'my-collection' )['public'] );
	}

	/**
	 * Should preserve explicitly configured collection visibility.
	 *
	 * @ticket 66087
	 *
	 * @dataProvider data_boolean_public_properties
	 *
	 * @covers ::register
	 *
	 * @param bool $is_public Whether the collection is public.
	 */
	public function test_register_collection_accepts_boolean_public_property( $is_public ) {
		$result = $this->collections->register(
			'my-collection',
			array(
				'label'  => 'My Collection',
				'public' => $is_public,
			)
		);

		$this->assertTrue( $result );
		$this->assertSame( $is_public, $this->collections->get_registered( 'my-collection' )['public'] );
	}

	/**
	 * Data provider for supported collection visibility values.
	 *
	 * @return array[]
	 */
	public static function data_boolean_public_properties() {
		return array(
			'public collection'     => array( true ),
			'non-public collection' => array( false ),
		);
	}

	/**
	 * Should reject collection visibility values that are not booleans.
	 *
	 * @ticket 66087
	 *
	 * @dataProvider data_non_boolean_public_properties
	 *
	 * @covers ::register
	 *
	 * @expectedIncorrectUsage WP_Icon_Collections_Registry::register
	 *
	 * @param mixed $is_public Invalid collection visibility value.
	 */
	public function test_register_collection_rejects_non_boolean_public_property( $is_public ) {
		$result = $this->collections->register(
			'my-collection',
			array(
				'label'  => 'My Collection',
				'public' => $is_public,
			)
		);

		$this->assertFalse( $result );
		$this->assertFalse( $this->collections->is_registered( 'my-collection' ) );
	}

	/**
	 * Data provider for unsupported collection visibility values.
	 *
	 * @return array[]
	 */
	public static function data_non_boolean_public_properties() {
		return array(
			'string'  => array( 'false' ),
			'integer' => array( 0 ),
			'array'   => array( array() ),
			'null'    => array( null ),
		);
	}
}
