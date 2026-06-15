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

	/**
	 * Path to a temporary icon file created during a test, removed in tear_down.
	 *
	 * @var string|null
	 */
	private $temp_file = null;

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

		if ( $this->temp_file && file_exists( $this->temp_file ) ) {
			unlink( $this->temp_file );
		}
		$this->temp_file = null;

		$this->registry = null;
		parent::tear_down();
	}

	/**
	 * Builds a unique temporary icon file path with the given extension.
	 *
	 * @param string|null $contents  File contents, or null to leave the file uncreated.
	 * @param string      $extension File extension, without the leading dot.
	 * @return string Absolute path to the temporary file.
	 */
	private function create_temp_icon_file( $contents, $extension = 'svg' ) {
		$dir             = get_temp_dir();
		$this->temp_file = trailingslashit( $dir ) . wp_unique_filename( $dir, uniqid() . '.' . $extension );
		if ( null !== $contents ) {
			file_put_contents( $this->temp_file, $contents );
		}
		return $this->temp_file;
	}

	/**
	 * @ticket 64651
	 *
	 * @covers ::register
	 */
	public function test_register_icon() {
		$result = $this->registry->register(
			'test-collection/my-icon',
			array(
				'label'   => 'My Icon',
				'content' => '<svg></svg>',
			)
		);

		$this->assertTrue( $result );
		$this->assertTrue( $this->registry->is_registered( 'test-collection/my-icon' ) );
	}

	public function data_invalid_icon_names() {
		return array(
			'non-string name'        => array( 1 ),
			'non-namespaced name'    => array( 'plus' ),
			'empty unqualified name' => array( 'test-collection/' ),
			'uppercase characters'   => array( 'test-collection/Plus' ),
			'invalid characters'     => array( 'test-collection/_doing_it_wrong' ),
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
			'label'   => 'Icon',
			'content' => '<svg></svg>',
		);

		$this->assertTrue( $this->registry->register( 'test-collection/duplicate', $settings ) );
		$this->assertFalse( $this->registry->register( 'test-collection/duplicate', $settings ) );
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
				'label'   => 'Icon',
				'content' => '<svg></svg>',
			)
		);
		$this->assertFalse( $result );
	}

	/**
	 * Should reject a non-namespaced name, since the collection is derived from
	 * the namespaced icon name in the form "collection/icon-name".
	 *
	 * @ticket 64651
	 *
	 * @covers ::register
	 *
	 * @expectedIncorrectUsage WP_Icons_Registry::register
	 */
	public function test_register_rejects_non_namespaced_name() {
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
	 * Should reject `collection` passed as an icon property, since the collection
	 * is derived from the namespaced icon name instead.
	 *
	 * @ticket 64651
	 *
	 * @covers ::register
	 *
	 * @expectedIncorrectUsage WP_Icons_Registry::register
	 */
	public function test_register_rejects_collection_property() {
		$result = $this->registry->register(
			'test-collection/my-icon',
			array(
				'label'      => 'Icon',
				'content'    => '<svg></svg>',
				'collection' => 'test-collection',
			)
		);
		$this->assertFalse( $result );
	}

	/**
	 * Should fail when the name references a collection that is not registered.
	 *
	 * @ticket 64651
	 *
	 * @covers ::register
	 *
	 * @expectedIncorrectUsage WP_Icons_Registry::register
	 */
	public function test_register_rejects_unregistered_collection() {
		$result = $this->registry->register(
			'unregistered-collection/my-icon',
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
	 */
	public function test_same_name_across_collections_does_not_collide() {
		$collections = WP_Icon_Collections_Registry::get_instance();
		$collections->register( 'other-collection', array( 'label' => 'Other' ) );

		$this->assertTrue(
			$this->registry->register(
				'test-collection/shared',
				array(
					'label'   => 'Shared A',
					'content' => '<svg></svg>',
				)
			)
		);
		$this->assertTrue(
			$this->registry->register(
				'other-collection/shared',
				array(
					'label'   => 'Shared B',
					'content' => '<svg></svg>',
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
			'test-collection/my-icon',
			array(
				'label'   => 'Icon',
				'content' => '<svg></svg>',
			)
		);

		$this->assertTrue( $this->registry->is_registered( 'test-collection/my-icon' ) );
		$this->assertTrue( $this->registry->unregister( 'test-collection/my-icon' ) );
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
		$this->assertFalse( $this->registry->unregister( 'test-collection/ghost' ) );
	}

	/**
	 * @ticket 64651
	 *
	 * @covers ::get_content
	 */
	public function test_get_content_reads_from_valid_file_path() {
		$path = $this->create_temp_icon_file( '<svg><path d="M0 0"/></svg>' );

		$this->registry->register(
			'test-collection/from-file',
			array(
				'label'     => 'From File',
				'file_path' => $path,
			)
		);

		$icon = $this->registry->get_registered_icon( 'test-collection/from-file' );
		$this->assertStringContainsString( '<path', $icon['content'] );
	}

	/**
	 * Provides icon files that cannot yield valid content.
	 *
	 * @return array<string, array{0: string|null, 1: string}> Data sets of [ $contents, $extension ].
	 */
	public function data_invalid_icon_files() {
		return array(
			'missing file'        => array( null, 'svg' ),
			'non-svg extension'   => array( '<svg><path d="M0 0"/></svg>', 'txt' ),
			'invalid svg content' => array( '', 'svg' ),
		);
	}

	/**
	 * @ticket 64651
	 *
	 * @dataProvider data_invalid_icon_files
	 *
	 * @covers ::get_content
	 *
	 * @param string|null $contents  File contents, or null to leave the file uncreated.
	 * @param string      $extension File extension, without the leading dot.
	 */
	public function test_get_content_returns_null_for_invalid_file( $contents, $extension ) {
		$path = $this->create_temp_icon_file( $contents, $extension );

		$this->registry->register(
			'test-collection/invalid-file',
			array(
				'label'     => 'Invalid File',
				'file_path' => $path,
			)
		);

		add_filter( 'wp_trigger_error_trigger_error', '__return_false' );
		$icon = $this->registry->get_registered_icon( 'test-collection/invalid-file' );
		remove_filter( 'wp_trigger_error_trigger_error', '__return_false' );

		$this->assertNull( $icon['content'] );
	}
}
