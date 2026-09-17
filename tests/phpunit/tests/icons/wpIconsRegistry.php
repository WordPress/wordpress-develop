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
	 * Provides valid namespaced icon names, including names that contain,
	 * start or end with digits, as well as underscores and hyphens.
	 *
	 * @return array<string, array{0: string}>
	 */
	public function data_valid_icon_names() {
		return array(
			'single character'                => array( 'test-collection/a' ),
			'simple name'                     => array( 'test-collection/icon' ),
			'digit at the start'              => array( 'test-collection/1icon' ),
			'digit in the name'               => array( 'test-collection/my1icon' ),
			'digit at the end'                => array( 'test-collection/icon1' ),
			'underscore in the name'          => array( 'test-collection/my_icon' ),
			'hyphen in the name'              => array( 'test-collection/my-icon' ),
			'digit adjacent to a hyphen'      => array( 'test-collection/my-1-icon' ),
			'digit adjacent to an underscore' => array( 'test-collection/my_1_icon' ),
		);
	}

	/**
	 * @ticket 64651
	 *
	 * @dataProvider data_valid_icon_names
	 *
	 * @covers ::register
	 *
	 * @param string $name Valid icon name candidate.
	 */
	public function test_register_icon( $name ) {
		$result = $this->registry->register(
			$name,
			array(
				'label'   => 'My Icon',
				'content' => '<svg></svg>',
			)
		);

		$this->assertTrue( $result );
		$this->assertTrue( $this->registry->is_registered( $name ) );
	}

	public function data_invalid_icon_names() {
		return array(
			'integer name'            => array( 1 ),
			'null name'               => array( null ),
			'boolean name'            => array( true ),
			'array name'              => array( array() ),
			'empty name'              => array( 'test-collection/' ),
			'uppercase at the start'  => array( 'test-collection/Icon' ),
			'uppercase in the name'   => array( 'test-collection/my-Icon' ),
			'uppercase at the end'    => array( 'test-collection/my-iconX' ),
			'underscore at the start' => array( 'test-collection/_my-icon' ),
			'underscore at the end'   => array( 'test-collection/my-icon_' ),
			'hyphen at the start'     => array( 'test-collection/-my-icon' ),
			'hyphen at the end'       => array( 'test-collection/my-icon-' ),
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
			'non-namespaced-icon',
			array(
				'label'   => 'Icon',
				'content' => '<svg></svg>',
			)
		);
		$this->assertFalse( $result );
		$this->assertFalse( $this->registry->is_registered( 'core/non-namespaced-icon' ) );
		$this->assertFalse( $this->registry->is_registered( 'non-namespaced-icon' ) );
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
	 * Should register an icon that provides its content through `file_path`.
	 *
	 * @ticket 64847
	 *
	 * @covers ::register
	 */
	public function test_register_icon_with_file_path() {
		$path = $this->create_temp_icon_file( '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"></svg>' );

		$result = $this->registry->register(
			'test-collection/file-path-icon',
			array(
				'label'     => 'Icon',
				'file_path' => $path,
			)
		);

		$this->assertTrue( $result );
		$this->assertTrue( $this->registry->is_registered( 'test-collection/file-path-icon' ) );

		$icon = $this->registry->get_registered_icon( 'test-collection/file-path-icon' );
		$this->assertStringContainsString( '<svg', $icon['content'] );
	}

	/**
	 * Should register an icon with its `content` sanitized.
	 *
	 * @ticket 64847
	 *
	 * @covers ::register
	 */
	public function test_register_icon_sanitizes_content() {
		$result = $this->registry->register(
			'test-collection/unsafe-content',
			array(
				'label'   => 'Icon',
				'content' => '<svg viewbox="0 0 24 24" onload="alert(1)"><path d="M0 0" /></svg>',
			)
		);

		$this->assertTrue( $result );

		$icon = $this->registry->get_registered_icon( 'test-collection/unsafe-content' );
		$this->assertSame( '<svg viewbox="0 0 24 24"><path d="M0 0" /></svg>', $icon['content'] );
	}

	/**
	 * Should fail to register an icon that provides both `content` and `file_path`.
	 *
	 * @ticket 64847
	 *
	 * @covers ::register
	 *
	 * @expectedIncorrectUsage WP_Icons_Registry::register
	 */
	public function test_register_icon_with_content_and_file_path() {
		$result = $this->registry->register(
			'test-collection/content-and-file-path',
			array(
				'label'     => 'Icon',
				'content'   => '<svg></svg>',
				'file_path' => '/path/to/icon.svg',
			)
		);
		$this->assertFalse( $result );
		$this->assertFalse( $this->registry->is_registered( 'test-collection/content-and-file-path' ) );
	}

	/**
	 * Should fail to register an icon that provides neither `content` nor `file_path`.
	 *
	 * @ticket 64847
	 *
	 * @covers ::register
	 *
	 * @expectedIncorrectUsage WP_Icons_Registry::register
	 */
	public function test_register_icon_without_content_or_file_path() {
		$result = $this->registry->register(
			'test-collection/no-content',
			array(
				'label' => 'Icon',
			)
		);
		$this->assertFalse( $result );
		$this->assertFalse( $this->registry->is_registered( 'test-collection/no-content' ) );
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

	/**
	 * Invokes the private WP_Icons_Registry::sanitize_inline_svg() method.
	 *
	 * @param string $html_containing_svg HTML fragment containing the SVG to sanitize.
	 * @return string The sanitized SVG content.
	 */
	private function sanitize_inline_svg( $html_containing_svg ) {
		$registry = WP_Icons_Registry::get_instance();
		$method   = new ReflectionMethod( $registry, 'sanitize_inline_svg' );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}
		return $method->invoke( $registry, $html_containing_svg );
	}

	/**
	 * @ticket 64651
	 *
	 * @dataProvider data_sanitize_inline_svg
	 * @covers ::sanitize_inline_svg
	 *
	 * @param string $input    The icon content to sanitize.
	 * @param string $expected The expected sanitized output.
	 */
	public function test_sanitize_inline_svg( $input, $expected ) {
		$sanitized = $this->sanitize_inline_svg( $input );
		$this->assertSame( $expected, $sanitized );
	}

	/**
	 * Data provider for test_sanitize_inline_svg.
	 *
	 * @return array<string, array{ input: string, expected: string }>
	 */
	public function data_sanitize_inline_svg() {
		$xlink = ' xmlns:xlink="http://www.w3.org/1999/xlink"';

		return array(
			// Root selection: exactly one SVG element in the SVG namespace.
			'rejects multiple top-level svg elements'      => array(
				'input'    => '<svg xmlns="http://www.w3.org/2000/svg"><path d="first"/></svg><svg xmlns="http://www.w3.org/2000/svg"><path d="second"/></svg>',
				'expected' => '',
			),
			'allows nested svg'                            => array(
				'input'    => '<svg xmlns="http://www.w3.org/2000/svg"><svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0" /></svg></svg>',
				'expected' => '<svg xmlns="http://www.w3.org/2000/svg"><svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0" /></svg></svg>',
			),
			'rejects svg in a foreign namespace'           => array(
				'input'    => '<math><svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0"/></svg></math>',
				'expected' => '',
			),
			'allows a self-closing svg root'               => array(
				'input'    => '<svg xmlns="http://www.w3.org/2000/svg" />',
				'expected' => '<svg xmlns="http://www.w3.org/2000/svg" />',
			),
			'rejects a sibling after a self-closing root'  => array(
				'input'    => '<svg xmlns="http://www.w3.org/2000/svg" /><svg xmlns="http://www.w3.org/2000/svg"><path d="second" /></svg>',
				'expected' => '',
			),
			'ignores markup after a self-closing root'     => array(
				'input'    => '<svg xmlns="http://www.w3.org/2000/svg" /><path d="orphan" />',
				'expected' => '<svg xmlns="http://www.w3.org/2000/svg" />',
			),

			// Content surrounding the SVG root is ignored.
			'ignores content preceding the svg'            => array(
				'input'    => '<p>before</p><svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0" /></svg>',
				'expected' => '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0" /></svg>',
			),
			'ignores content following the svg'            => array(
				'input'    => '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0" /></svg><p>after</p>',
				'expected' => '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0" /></svg>',
			),
			'ignores an xml declaration before the svg'    => array(
				'input'    => '<?xml version="1.0" encoding="UTF-8"?><svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0" /></svg>',
				'expected' => '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0" /></svg>',
			),
			'ignores a comment before the svg'             => array(
				'input'    => '<!-- Generator: some editor --><svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0" /></svg>',
				'expected' => '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0" /></svg>',
			),
			'ignores whitespace before the svg'            => array(
				'input'    => "  \n\t<svg xmlns=\"http://www.w3.org/2000/svg\"><path d=\"M0 0\" /></svg>",
				'expected' => '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0" /></svg>',
			),

			// Non-SVG or unparseable input returns an empty string.
			'returns empty for empty string'               => array(
				'input'    => '',
				'expected' => '',
			),
			'returns empty for whitespace only'            => array(
				'input'    => "   \n\t  ",
				'expected' => '',
			),
			'returns empty for plain text'                 => array(
				'input'    => 'plain text without svg',
				'expected' => '',
			),
			'returns empty for html without an svg'        => array(
				'input'    => '<div>not svg</div><p>content</p>',
				'expected' => '',
			),
			'returns empty for incomplete markup'          => array(
				'input'    => '<svg><path d="M0 0"',
				'expected' => '',
			),
			'returns empty for unsupported markup'         => array(
				'input'    => '<svg><foreignObject><table>TEXT NOT SUPPORTED HERE!',
				'expected' => '',
			),

			// Disallowed or dangerous content is stripped (wp_kses).
			'strips html-like tags inside svg'             => array(
				'input'    => '<svg xmlns="http://www.w3.org/2000/svg"><p>paragraph content</p><path d="M0 0h24v24H0z" /><div>div content</div></svg>',
				'expected' => '<svg xmlns="http://www.w3.org/2000/svg"></svg>',
			),
			'strips foreignObject but keeps text content'  => array(
				'input'    => '<svg xmlns="http://www.w3.org/2000/svg"><foreignObject><p>paragraph content</p><script>alert(1)</script></foreignObject><path d="M0 0h24v24H0z" /></svg>',
				'expected' => '<svg xmlns="http://www.w3.org/2000/svg">paragraph contentalert(1)<path d="M0 0h24v24H0z" /></svg>',
			),
			'strips script tags'                           => array(
				'input'    => '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><path d="M0 0h24v24H0z" /></svg>',
				'expected' => '<svg xmlns="http://www.w3.org/2000/svg">alert(1)<path d="M0 0h24v24H0z" /></svg>',
			),
			'strips event handler attributes'              => array(
				'input'    => '<svg xmlns="http://www.w3.org/2000/svg" onclick="alert(1)"><path d="M0 0h24v24H0z" onload="evil()" /></svg>',
				'expected' => '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0h24v24H0z" /></svg>',
			),
			'strips javascript protocol in href'           => array(
				'input'    => '<svg xmlns="http://www.w3.org/2000/svg"><use href="javascript:alert(1)" /></svg>',
				'expected' => '<svg xmlns="http://www.w3.org/2000/svg"><use href="alert(1)" /></svg>',
			),
			'strips data protocol in href'                 => array(
				'input'    => '<svg xmlns="http://www.w3.org/2000/svg"><use href="data:text/html,<script>alert(1)</script>" /></svg>',
				'expected' => '<svg xmlns="http://www.w3.org/2000/svg"><use href="text/html,&lt;script&gt;alert(1)&lt;/script&gt;" /></svg>',
			),
			'strips javascript protocol in xlink:href'     => array(
				'input'    => '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><a xlink:href="javascript:alert(1)"><path d="M0 0" /></a></svg>',
				'expected' => '<svg xmlns="http://www.w3.org/2000/svg"' . $xlink . '><a xlink:href="alert(1)"><path d="M0 0" /></a></svg>',
			),
			'strips animate and set elements'              => array(
				'input'    => '<svg xmlns="http://www.w3.org/2000/svg"><a href="#icon"><animate attributeName="href" to="javascript:alert(1)" dur="1s" /><set attributeName="href" to="javascript:alert(1)" begin="1s" /><path d="M0 0" /></a></svg>',
				'expected' => '<svg xmlns="http://www.w3.org/2000/svg"><a href="#icon"><path d="M0 0" /></a></svg>',
			),
			'strips disallowed tags'                       => array(
				'input'    => '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0"/><iframe src="evil"></iframe><object data="x" /></svg>',
				'expected' => '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0" /></svg>',
			),

			// Allowed SVG elements and attributes are preserved.
			'preserves the root svg element'               => array(
				'input'    => '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 24 24" preserveAspectRatio="xMidYMid meet" width="24" height="24" class="icon" aria-hidden="true"><path d="M0 0" fill="currentColor" /></svg>',
				'expected' => '<svg xmlns="http://www.w3.org/2000/svg"' . $xlink . ' viewbox="0 0 24 24" preserveaspectratio="xMidYMid meet" width="24" height="24" class="icon" aria-hidden="true"><path d="M0 0" fill="currentColor" /></svg>',
			),
			'preserves xmlns:xlink attribute'              => array(
				'input'    => '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><path d="M0 0h24v24H0z" /></svg>',
				'expected' => '<svg xmlns="http://www.w3.org/2000/svg"' . $xlink . '><path d="M0 0h24v24H0z" /></svg>',
			),
			'preserves fill and clip rules on svg'         => array(
				'input'    => '<svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" fill-rule="evenodd" clip-rule="evenodd"><path d="M0 0" /></svg>',
				'expected' => '<svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" fill-rule="evenodd" clip-rule="evenodd"><path d="M0 0" /></svg>',
			),
			'preserves stroke attributes and style on svg' => array(
				'input'    => '<svg xmlns="http://www.w3.org/2000/svg" style="fill: none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10" vector-effect="non-scaling-stroke"><path d="M0 0" /></svg>',
				'expected' => '<svg xmlns="http://www.w3.org/2000/svg" style="fill: none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10" vector-effect="non-scaling-stroke"><path d="M0 0" /></svg>',
			),
			'preserves clip rule, opacity and stroke on path' => array(
				'input'    => '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0" fill-rule="evenodd" clip-rule="evenodd" opacity="0.4" style="fill: none" stroke="currentColor" vector-effect="non-scaling-stroke" /></svg>',
				'expected' => '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0" fill-rule="evenodd" clip-rule="evenodd" opacity="0.4" style="fill: none" stroke="currentColor" vector-effect="non-scaling-stroke" /></svg>',
			),
			'preserves clip rule and stroke on polygon'    => array(
				'input'    => '<svg xmlns="http://www.w3.org/2000/svg"><polygon points="0,0 1,1" clip-rule="evenodd" stroke="currentColor" vector-effect="non-scaling-stroke" /></svg>',
				'expected' => '<svg xmlns="http://www.w3.org/2000/svg"><polygon points="0,0 1,1" clip-rule="evenodd" stroke="currentColor" vector-effect="non-scaling-stroke" /></svg>',
			),
			'preserves presentation attributes on rect'    => array(
				'input'    => '<svg xmlns="http://www.w3.org/2000/svg"><rect x="4" y="5" width="16" height="14" rx="2" ry="2" fill="currentColor" stroke="currentColor" transform="rotate(45)" vector-effect="non-scaling-stroke" /></svg>',
				'expected' => '<svg xmlns="http://www.w3.org/2000/svg"><rect x="4" y="5" width="16" height="14" rx="2" ry="2" fill="currentColor" stroke="currentColor" transform="rotate(45)" vector-effect="non-scaling-stroke" /></svg>',
			),
			'preserves presentation attributes on circle'  => array(
				'input'    => '<svg xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="3" fill="currentColor" fill-rule="evenodd" clip-rule="evenodd" transform="rotate(45)" vector-effect="non-scaling-stroke" /></svg>',
				'expected' => '<svg xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="3" fill="currentColor" fill-rule="evenodd" clip-rule="evenodd" transform="rotate(45)" vector-effect="non-scaling-stroke" /></svg>',
			),
			'preserves basic shape elements'               => array(
				'input'    => '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0h24v24H0z" /><circle cx="12" cy="12" r="10" /><ellipse cx="12" cy="12" rx="10" ry="8" /><line x1="0" y1="0" x2="24" y2="24" /><polygon points="0,0 24,0 12,24" /><polyline points="0,0 12,12 24,0" /><rect x="2" y="2" width="20" height="20" /></svg>',
				'expected' => '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0h24v24H0z" /><circle cx="12" cy="12" r="10" /><ellipse cx="12" cy="12" rx="10" ry="8" /><line x1="0" y1="0" x2="24" y2="24" /><polygon points="0,0 24,0 12,24" /><polyline points="0,0 12,12 24,0" /><rect x="2" y="2" width="20" height="20" /></svg>',
			),
			'preserves grouping and structural elements'   => array(
				'input'    => '<svg xmlns="http://www.w3.org/2000/svg"><defs><symbol id="icon" viewBox="0 0 24 24"><path d="M0 0h24v24H0z" /></symbol><clipPath id="clip"><circle cx="12" cy="12" r="10" /></clipPath><mask id="m"><rect fill="white" width="24" height="24" /></mask></defs><g><use href="#icon" /><use href="https://example.com/icon.svg#symbol" /><use href="#symbol" /></g></svg>',
				'expected' => '<svg xmlns="http://www.w3.org/2000/svg"><defs><symbol id="icon" viewbox="0 0 24 24"><path d="M0 0h24v24H0z" /></symbol><clipPath id="clip"><circle cx="12" cy="12" r="10" /></clipPath><mask id="m"><rect fill="white" width="24" height="24" /></mask></defs><g><use href="#icon" /><use href="https://example.com/icon.svg#symbol" /><use href="#symbol" /></g></svg>',
			),
			'preserves switch element'                     => array(
				'input'    => '<svg xmlns="http://www.w3.org/2000/svg"><switch><path d="M0 0h24v24H0z" /></switch></svg>',
				'expected' => '<svg xmlns="http://www.w3.org/2000/svg"><switch><path d="M0 0h24v24H0z" /></switch></svg>',
			),
			'preserves view element'                       => array(
				'input'    => '<svg xmlns="http://www.w3.org/2000/svg"><view id="v" viewBox="0 0 24 24" /><path d="M0 0h24v24H0z" /></svg>',
				'expected' => '<svg xmlns="http://www.w3.org/2000/svg"><view id="v" viewbox="0 0 24 24" /><path d="M0 0h24v24H0z" /></svg>',
			),
			'preserves linking element'                    => array(
				'input'    => '<svg xmlns="http://www.w3.org/2000/svg"><a href="https://example.com"><path d="M0 0h24v24H0z" /></a></svg>',
				'expected' => '<svg xmlns="http://www.w3.org/2000/svg"><a href="https://example.com"><path d="M0 0h24v24H0z" /></a></svg>',
			),
			'preserves gradient elements'                  => array(
				'input'    => '<svg xmlns="http://www.w3.org/2000/svg"><linearGradient id="lin"><stop offset="0%" stop-color="red" /><stop offset="100%" stop-color="blue" /></linearGradient><radialGradient id="rad"><stop offset="0%" stop-color="red" /><stop offset="100%" stop-color="blue" /></radialGradient><rect fill="url(#lin)" width="24" height="24" /></svg>',
				'expected' => '<svg xmlns="http://www.w3.org/2000/svg"><linearGradient id="lin"><stop offset="0%" stop-color="red" /><stop offset="100%" stop-color="blue" /></linearGradient><radialGradient id="rad"><stop offset="0%" stop-color="red" /><stop offset="100%" stop-color="blue" /></radialGradient><rect fill="url(#lin)" width="24" height="24" /></svg>',
			),
			'preserves pattern element'                    => array(
				'input'    => '<svg xmlns="http://www.w3.org/2000/svg"><pattern id="pat" width="4" height="4"><rect width="4" height="4" fill="currentColor" /></pattern><rect fill="url(#pat)" width="24" height="24" /></svg>',
				'expected' => '<svg xmlns="http://www.w3.org/2000/svg"><pattern id="pat" width="4" height="4"><rect width="4" height="4" fill="currentColor" /></pattern><rect fill="url(#pat)" width="24" height="24" /></svg>',
			),
			'preserves filter elements'                    => array(
				'input'    => '<svg xmlns="http://www.w3.org/2000/svg"><filter id="blur"><feGaussianBlur in="SourceGraphic" stdDeviation="1" /></filter><rect filter="url(#blur)" width="24" height="24" /></svg>',
				'expected' => '<svg xmlns="http://www.w3.org/2000/svg"><filter id="blur"><feGaussianBlur in="SourceGraphic" stddeviation="1" /></filter><rect filter="url(#blur)" width="24" height="24" /></svg>',
			),
			'preserves text elements'                      => array(
				'input'    => '<svg xmlns="http://www.w3.org/2000/svg"><path id="p" d="M0,20 Q12,0 24,20" /><text x="12" y="16" text-anchor="middle">A<tspan font-weight="bold">B</tspan></text><text><textPath href="#p">path</textPath></text></svg>',
				'expected' => '<svg xmlns="http://www.w3.org/2000/svg"><path id="p" d="M0,20 Q12,0 24,20" /><text x="12" y="16" text-anchor="middle">A<tspan font-weight="bold">B</tspan></text><text><textPath href="#p">path</textPath></text></svg>',
			),
			'preserves descriptive elements'               => array(
				'input'    => '<svg xmlns="http://www.w3.org/2000/svg"><title>Icon title</title><desc>Description</desc><metadata></metadata><path d="M0 0h24v24H0z" /></svg>',
				'expected' => '<svg xmlns="http://www.w3.org/2000/svg"><title>Icon title</title><desc>Description</desc><metadata></metadata><path d="M0 0h24v24H0z" /></svg>',
			),
			'preserves image element'                      => array(
				'input'    => '<svg xmlns="http://www.w3.org/2000/svg"><image href="https://example.com/icon.png" width="24" height="24" /></svg>',
				'expected' => '<svg xmlns="http://www.w3.org/2000/svg"><image href="https://example.com/icon.png" width="24" height="24" /></svg>',
			),
			'preserves marker element'                     => array(
				'input'    => '<svg xmlns="http://www.w3.org/2000/svg"><marker id="arrow" refX="10" refY="5"><path d="M0,0 L10,5 L0,10" /></marker><path d="M0,12 L24,12" marker-start="url(#arrow)" /></svg>',
				'expected' => '<svg xmlns="http://www.w3.org/2000/svg"><marker id="arrow" refx="10" refy="5"><path d="M0,0 L10,5 L0,10" /></marker><path d="M0,12 L24,12" marker-start="url(#arrow)" /></svg>',
			),
			'preserves animation elements'                 => array(
				'input'    => '<svg xmlns="http://www.w3.org/2000/svg"><animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="2s" /><path d="M0,0 L10,10"><animateMotion path="M0,0 L24,24" dur="1s" /></path></svg>',
				'expected' => '<svg xmlns="http://www.w3.org/2000/svg"><animateTransform attributename="transform" type="rotate" from="0 12 12" to="360 12 12" dur="2s" /><path d="M0,0 L10,10"><animateMotion path="M0,0 L24,24" dur="1s" /></path></svg>',
			),
		);
	}
}
