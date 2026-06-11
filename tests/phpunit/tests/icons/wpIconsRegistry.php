<?php
/**
 * Unit tests covering WP_Icons_Registry functionality.
 *
 * @package WordPress
 * @since 7.1.0
 *
 * @group icons
 *
 * @coversDefaultClass WP_Icons_Registry
 */
class Tests_Icons_WpIconsRegistry extends WP_UnitTestCase {

	/**
	 * @var WP_Icons_Registry
	 */
	protected $registry;

	public function set_up() {
		parent::set_up();
		$this->registry = WP_Icons_Registry::get_instance();

		$collections = WP_Icon_Collections_Registry::get_instance();
		if ( ! $collections->is_registered( 'test-collection' ) ) {
			$collections->register( 'test-collection', array( 'label' => 'Test Plugin' ) );
		}
	}

	public function tear_down() {
		$reflection        = new ReflectionClass( WP_Icons_Registry::class );
		$instance_property = $reflection->getProperty( 'instance' );
		if ( PHP_VERSION_ID < 80100 ) {
			$instance_property->setAccessible( true );
		}
		$instance_property->setValue( null, null );

		$collections = WP_Icon_Collections_Registry::get_instance();
		if ( $collections->is_registered( 'test-collection' ) ) {
			$collections->unregister( 'test-collection' );
		}
		if ( $collections->is_registered( 'other-collection' ) ) {
			$collections->unregister( 'other-collection' );
		}

		$this->registry = null;
		parent::tear_down();
	}

	/**
	 * @ticket 64651
	 *
	 * @covers ::register
	 */
	public function test_register_icon() {
		$result = $this->registry->register(
			'my-icon',
			array(
				'label'      => 'My Icon',
				'content'    => '<svg></svg>',
				'collection' => 'test-collection',
			)
		);

		$this->assertTrue( $result );
		$this->assertTrue( $this->registry->is_registered( 'test-collection/my-icon' ) );
	}

	public function data_invalid_icon_names() {
		return array(
			'non-string name'      => array( 1 ),
			'contains slash'       => array( 'test-collection/plus' ),
			'uppercase characters' => array( 'Plus' ),
			'invalid characters'   => array( '_doing_it_wrong' ),
		);
	}

	/**
	 * @ticket 64651
	 *
	 * @covers ::register
	 *
	 * @expectedIncorrectUsage WP_Icons_Registry::register
	 */
	public function test_register_icon_twice() {
		$settings = array(
			'label'      => 'Icon',
			'content'    => '<svg></svg>',
			'collection' => 'test-collection',
		);

		$this->assertTrue( $this->registry->register( 'duplicate', $settings ) );
		$this->assertFalse( $this->registry->register( 'duplicate', $settings ) );
	}

	/**
	 * @ticket 64651
	 *
	 * @dataProvider data_invalid_icon_names
	 *
	 * @covers ::register
	 *
	 * @expectedIncorrectUsage WP_Icons_Registry::register
	 *
	 * @param mixed $name Invalid icon name candidate.
	 */
	public function test_register_invalid_name( $name ) {
		$result = $this->registry->register(
			$name,
			array(
				'label'      => 'Icon',
				'content'    => '<svg></svg>',
				'collection' => 'test-collection',
			)
		);
		$this->assertFalse( $result );
	}

	/**
	 * @ticket 64651
	 *
	 * @covers ::register
	 *
	 * @expectedIncorrectUsage WP_Icons_Registry::register
	 */
	public function test_register_requires_collection() {
		$result = $this->registry->register(
			'my-icon',
			array(
				'label'   => 'Icon',
				'content' => '<svg></svg>',
			)
		);
		$this->assertFalse( $result );
	}

	/**
	 * @ticket 64651
	 *
	 * @covers ::register
	 *
	 * @expectedIncorrectUsage WP_Icons_Registry::register
	 */
	public function test_register_rejects_non_string_collection() {
		$result = $this->registry->register(
			'my-icon',
			array(
				'label'      => 'Icon',
				'content'    => '<svg></svg>',
				'collection' => 123,
			)
		);
		$this->assertFalse( $result );
	}

	/**
	 * @ticket 64651
	 *
	 * @covers ::register
	 *
	 * @expectedIncorrectUsage WP_Icons_Registry::register
	 */
	public function test_register_rejects_unregistered_collection() {
		$result = $this->registry->register(
			'my-icon',
			array(
				'label'      => 'Icon',
				'content'    => '<svg></svg>',
				'collection' => 'unregistered-collection',
			)
		);
		$this->assertFalse( $result );
	}

	/**
	 * @ticket 64651
	 *
	 * @covers ::register
	 */
	public function test_same_name_across_collections_does_not_collide() {
		$collections = WP_Icon_Collections_Registry::get_instance();
		$collections->register( 'other-collection', array( 'label' => 'Other' ) );

		$this->assertTrue(
			$this->registry->register(
				'shared',
				array(
					'label'      => 'Shared A',
					'content'    => '<svg></svg>',
					'collection' => 'test-collection',
				)
			)
		);
		$this->assertTrue(
			$this->registry->register(
				'shared',
				array(
					'label'      => 'Shared B',
					'content'    => '<svg></svg>',
					'collection' => 'other-collection',
				)
			)
		);

		$this->assertTrue( $this->registry->is_registered( 'test-collection/shared' ) );
		$this->assertTrue( $this->registry->is_registered( 'other-collection/shared' ) );

		$icon_a = $this->registry->get_registered_icon( 'test-collection/shared' );
		$icon_b = $this->registry->get_registered_icon( 'other-collection/shared' );
		$this->assertSame( 'Shared A', $icon_a['label'] );
		$this->assertSame( 'Shared B', $icon_b['label'] );
	}

	/**
	 * @ticket 64651
	 *
	 * @covers ::unregister
	 */
	public function test_unregister_icon() {
		$this->registry->register(
			'my-icon',
			array(
				'label'      => 'Icon',
				'content'    => '<svg></svg>',
				'collection' => 'test-collection',
			)
		);

		$this->assertTrue( $this->registry->is_registered( 'test-collection/my-icon' ) );
		$this->assertTrue( $this->registry->unregister( 'my-icon', 'test-collection' ) );
		$this->assertFalse( $this->registry->is_registered( 'test-collection/my-icon' ) );
	}

	/**
	 * @ticket 64651
	 *
	 * @covers ::unregister
	 *
	 * @expectedIncorrectUsage WP_Icons_Registry::unregister
	 */
	public function test_unregister_unknown_icon() {
		$this->assertFalse( $this->registry->unregister( 'ghost', 'test-collection' ) );
	}
}
