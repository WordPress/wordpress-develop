<?php
/**
 * Tests for Block Bindings API "core/term-data" source.
 *
 * @package WordPress
 * @subpackage Blocks
 * @since 6.9.0
 *
 * @group blocks
 * @group block-bindings
 */
class Tests_Block_Bindings_Term_Data_Source extends WP_UnitTestCase {

	/**
	 * Test category term ID.
	 *
	 * @var int
	 */
	protected static $category_id;

	/**
	 * Test post tag term ID.
	 *
	 * @var int
	 */
	protected static $tag_id;

	/**
	 * Test parent category term ID.
	 *
	 * @var int
	 */
	protected static $parent_category_id;

	/**
	 * Test category term ID with HTML markup and entities.
	 *
	 * @var int
	 */
	protected static $markup_category_id;

	/**
	 * Sets up shared fixtures for the test class.
	 *
	 * @param WP_UnitTest_Factory $factory Unit test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$parent_category_id = $factory->category->create(
			array(
				'name'        => 'Parent Category',
				'slug'        => 'parent-category',
				'description' => 'Parent Category Description',
			)
		);

		self::$category_id = $factory->category->create(
			array(
				'name'        => 'Test Category',
				'slug'        => 'test-category',
				'description' => 'Test Category Description',
				'parent'      => self::$parent_category_id,
			)
		);

		self::$tag_id = $factory->tag->create(
			array(
				'name'        => 'Test Tag',
				'slug'        => 'test-tag',
				'description' => 'Test Tag Description',
			)
		);

		// Temporarily remove pre-save sanitization filters to allow markup and entities in the fixture.
		remove_filter( 'pre_term_name', 'sanitize_text_field' );
		remove_filter( 'pre_term_name', 'wp_filter_kses' );
		remove_filter( 'pre_term_name', '_wp_specialchars', 30 );
		remove_filter( 'pre_term_description', 'wp_filter_kses' );

		self::$markup_category_id = $factory->category->create(
			array(
				'name'        => 'Category <tag> & "quotes"',
				'slug'        => 'category-markup-entities',
				'description' => '<p>Allowed HTML</p> <script>alert("xss");</script> & "quotes"',
			)
		);

		add_filter( 'pre_term_name', 'sanitize_text_field' );
		add_filter( 'pre_term_name', 'wp_filter_kses' );
		add_filter( 'pre_term_name', '_wp_specialchars', 30 );
		add_filter( 'pre_term_description', 'wp_filter_kses' );
	}

	/**
	 * Helper method to create a WP_Block instance.
	 *
	 * @param string $name       Block name.
	 * @param array  $context    Block context.
	 * @param array  $attributes Block attributes.
	 * @return WP_Block
	 */
	private function create_block( $name = 'core/paragraph', array $context = array(), array $attributes = array() ) {
		$parsed_block = array(
			'blockName'    => $name,
			'attrs'        => $attributes,
			'innerBlocks'  => array(),
			'innerHTML'    => '',
			'innerContent' => array(),
		);

		$block          = new WP_Block( $parsed_block );
		$block->context = $context;

		return $block;
	}

	/**
	 * Tests that _block_bindings_term_data_get_value() returns null when field arg is missing or empty.
	 *
	 * @covers ::_block_bindings_term_data_get_value
	 *
	 * @dataProvider data_missing_or_empty_field_args
	 *
	 * @param array $source_args Source arguments.
	 */
	public function test_get_value_returns_null_when_field_empty( array $source_args ) {
		$block = $this->create_block(
			'core/paragraph',
			array(
				'termId'   => self::$category_id,
				'taxonomy' => 'category',
			)
		);

		$this->assertNull( _block_bindings_term_data_get_value( $source_args, $block ) );
	}

	/**
	 * Data provider for test_get_value_returns_null_when_field_empty.
	 *
	 * @return array
	 */
	public function data_missing_or_empty_field_args() {
		return array(
			'missing field' => array( array() ),
			'empty string'  => array( array( 'field' => '' ) ),
			'null field'    => array( array( 'field' => null ) ),
			'false field'   => array( array( 'field' => false ) ),
		);
	}

	/**
	 * Tests that _block_bindings_term_data_get_value() returns null when termId or taxonomy context is missing.
	 *
	 * @covers ::_block_bindings_term_data_get_value
	 *
	 * @dataProvider data_missing_or_empty_context
	 *
	 * @param array $context Context array.
	 */
	public function test_get_value_returns_null_when_context_empty( array $context ) {
		$block = $this->create_block( 'core/paragraph', $context );

		$this->assertNull( _block_bindings_term_data_get_value( array( 'field' => 'name' ), $block ) );
	}

	/**
	 * Data provider for test_get_value_returns_null_when_context_empty.
	 *
	 * @return array
	 */
	public function data_missing_or_empty_context() {
		return array(
			'empty context'    => array( array() ),
			'missing taxonomy' => array( array( 'termId' => 123 ) ),
			'missing termId'   => array( array( 'taxonomy' => 'category' ) ),
			'empty termId'     => array(
				array(
					'termId'   => 0,
					'taxonomy' => 'category',
				),
			),
			'empty taxonomy'   => array(
				array(
					'termId'   => 123,
					'taxonomy' => '',
				),
			),
			'null identifiers' => array(
				array(
					'termId'   => null,
					'taxonomy' => null,
				),
			),
		);
	}

	/**
	 * Tests that _block_bindings_term_data_get_value() returns null when term does not exist.
	 *
	 * @covers ::_block_bindings_term_data_get_value
	 */
	public function test_get_value_returns_null_when_term_not_found() {
		$block = $this->create_block(
			'core/paragraph',
			array(
				'termId'   => 999999,
				'taxonomy' => 'category',
			)
		);

		$this->assertNull( _block_bindings_term_data_get_value( array( 'field' => 'name' ), $block ) );
	}

	/**
	 * Tests that _block_bindings_term_data_get_value() returns null when taxonomy does not exist.
	 *
	 * @covers ::_block_bindings_term_data_get_value
	 */
	public function test_get_value_returns_null_when_taxonomy_invalid() {
		$block = $this->create_block(
			'core/paragraph',
			array(
				'termId'   => self::$category_id,
				'taxonomy' => 'non_existent_tax',
			)
		);

		$this->assertNull( _block_bindings_term_data_get_value( array( 'field' => 'name' ), $block ) );
	}

	/**
	 * Tests that _block_bindings_term_data_get_value() returns null for unknown fields.
	 *
	 * @covers ::_block_bindings_term_data_get_value
	 */
	public function test_get_value_returns_null_for_unsupported_field() {
		$block = $this->create_block(
			'core/paragraph',
			array(
				'termId'   => self::$category_id,
				'taxonomy' => 'category',
			)
		);

		$this->assertNull( _block_bindings_term_data_get_value( array( 'field' => 'non_existent_field' ), $block ) );
	}

	/**
	 * Tests that _block_bindings_term_data_get_value() returns expected values for all supported fields.
	 *
	 * @covers ::_block_bindings_term_data_get_value
	 *
	 * @dataProvider data_supported_fields
	 *
	 * @param string $field    Field name.
	 * @param string $expected Expected field value.
	 */
	public function test_get_value_returns_supported_fields( $field, $expected ) {
		$block = $this->create_block(
			'core/paragraph',
			array(
				'termId'   => self::$category_id,
				'taxonomy' => 'category',
			)
		);

		$this->assertSame( $expected, _block_bindings_term_data_get_value( array( 'field' => $field ), $block ) );
	}

	/**
	 * Data provider for test_get_value_returns_supported_fields.
	 *
	 * @return array
	 */
	public function data_supported_fields() {
		return array(
			'name'        => array( 'name', 'Test Category' ),
			'slug'        => array( 'slug', 'test-category' ),
			'description' => array( 'description', 'Test Category Description' ),
			'count'       => array( 'count', '0' ),
		);
	}

	/**
	 * Tests that _block_bindings_term_data_get_value() returns the correct link field.
	 *
	 * @covers ::_block_bindings_term_data_get_value
	 */
	public function test_get_value_link_field() {
		$block = $this->create_block(
			'core/paragraph',
			array(
				'termId'   => self::$category_id,
				'taxonomy' => 'category',
			)
		);

		$expected_url = esc_url( get_term_link( self::$category_id, 'category' ) );
		$this->assertSame( $expected_url, _block_bindings_term_data_get_value( array( 'field' => 'link' ), $block ) );
	}

	/**
	 * Tests that _block_bindings_term_data_get_value() returns correct id and parent values.
	 *
	 * @covers ::_block_bindings_term_data_get_value
	 */
	public function test_get_value_id_and_parent_fields() {
		$block = $this->create_block(
			'core/paragraph',
			array(
				'termId'   => self::$category_id,
				'taxonomy' => 'category',
			)
		);

		$this->assertSame( (string) self::$category_id, _block_bindings_term_data_get_value( array( 'field' => 'id' ), $block ) );
		$this->assertSame( (string) self::$parent_category_id, _block_bindings_term_data_get_value( array( 'field' => 'parent' ), $block ) );
	}

	/**
	 * Tests that _block_bindings_term_data_get_value() escapes term names containing markup and entities.
	 *
	 * @ticket 65819
	 *
	 * @covers ::_block_bindings_term_data_get_value
	 */
	public function test_get_value_name_escaping() {
		$block = $this->create_block(
			'core/paragraph',
			array(
				'termId'   => self::$markup_category_id,
				'taxonomy' => 'category',
			)
		);

		$this->assertSame(
			'Category &lt;tag&gt; &amp; &quot;quotes&quot;',
			_block_bindings_term_data_get_value( array( 'field' => 'name' ), $block ),
			'Term name should be escaped with esc_html().'
		);
	}

	/**
	 * Tests that _block_bindings_term_data_get_value() sanitizes term descriptions with wp_kses_post().
	 *
	 * @ticket 65819
	 *
	 * @covers ::_block_bindings_term_data_get_value
	 */
	public function test_get_value_description_sanitization() {
		$block = $this->create_block(
			'core/paragraph',
			array(
				'termId'   => self::$markup_category_id,
				'taxonomy' => 'category',
			)
		);

		$this->assertEqualHTML(
			'<p>Allowed HTML</p>  &amp; "quotes"',
			_block_bindings_term_data_get_value( array( 'field' => 'description' ), $block ),
			'<body>',
			'Term description should be sanitized with wp_kses_post(), stripping disallowed tags like <script>.'
		);
	}

	/**
	 * Tests that _block_bindings_term_data_get_value() reads attributes for navigation-link blocks.
	 *
	 * @covers ::_block_bindings_term_data_get_value
	 */
	public function test_get_value_navigation_link_attributes() {
		$block = $this->create_block(
			'core/navigation-link',
			array(),
			array(
				'id'   => self::$category_id,
				'type' => 'category',
			)
		);

		$this->assertSame( 'Test Category', _block_bindings_term_data_get_value( array( 'field' => 'name' ), $block ) );
	}

	/**
	 * Tests that _block_bindings_term_data_get_value() reads attributes for navigation-submenu blocks.
	 *
	 * @covers ::_block_bindings_term_data_get_value
	 */
	public function test_get_value_navigation_submenu_attributes() {
		$block = $this->create_block(
			'core/navigation-submenu',
			array(),
			array(
				'id'   => self::$category_id,
				'type' => 'category',
			)
		);

		$this->assertSame( 'Test Category', _block_bindings_term_data_get_value( array( 'field' => 'name' ), $block ) );
	}

	/**
	 * Tests that navigation blocks map the 'tag' shorthand type to 'post_tag'.
	 *
	 * @covers ::_block_bindings_term_data_get_value
	 */
	public function test_get_value_navigation_link_maps_tag_type_to_post_tag() {
		$block = $this->create_block(
			'core/navigation-link',
			array(),
			array(
				'id'   => self::$tag_id,
				'type' => 'tag',
			)
		);

		$this->assertSame( 'Test Tag', _block_bindings_term_data_get_value( array( 'field' => 'name' ), $block ) );
	}

	/**
	 * Tests that navigation blocks return null when attributes are missing.
	 *
	 * @covers ::_block_bindings_term_data_get_value
	 */
	public function test_get_value_navigation_link_missing_attributes() {
		$block = $this->create_block( 'core/navigation-link' );

		$this->assertNull( _block_bindings_term_data_get_value( array( 'field' => 'name' ), $block ) );
	}

	/**
	 * Tests that non-publicly queryable taxonomy returns null for users without read capability.
	 *
	 * @covers ::_block_bindings_term_data_get_value
	 */
	public function test_get_value_non_publicly_queryable_taxonomy_unauthenticated() {
		register_taxonomy(
			'wptests_private_tax',
			'post',
			array(
				'publicly_queryable' => false,
			)
		);

		$term = wp_insert_term( 'Secret Term', 'wptests_private_tax' );
		$this->assertNotWPError( $term );

		$block = $this->create_block(
			'core/paragraph',
			array(
				'termId'   => $term['term_id'],
				'taxonomy' => 'wptests_private_tax',
			)
		);

		// Ensure no user is logged in.
		wp_set_current_user( 0 );

		$this->assertNull( _block_bindings_term_data_get_value( array( 'field' => 'name' ), $block ) );

		_unregister_taxonomy( 'wptests_private_tax' );
	}

	/**
	 * Tests that non-publicly queryable taxonomy returns value for users with read capability.
	 *
	 * @covers ::_block_bindings_term_data_get_value
	 */
	public function test_get_value_non_publicly_queryable_taxonomy_with_read_capability() {
		register_taxonomy(
			'wptests_private_tax',
			'post',
			array(
				'publicly_queryable' => false,
			)
		);

		$term = wp_insert_term( 'Secret Term', 'wptests_private_tax' );
		$this->assertNotWPError( $term );

		$block = $this->create_block(
			'core/paragraph',
			array(
				'termId'   => $term['term_id'],
				'taxonomy' => 'wptests_private_tax',
			)
		);

		$subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$this->assertSame( 'Secret Term', _block_bindings_term_data_get_value( array( 'field' => 'name' ), $block ) );

		_unregister_taxonomy( 'wptests_private_tax' );
	}

	/**
	 * Tests that _register_block_bindings_term_data_source registers the core/term-data source.
	 *
	 * @covers ::_register_block_bindings_term_data_source
	 */
	public function test_register_block_bindings_term_data_source() {
		$source = get_block_bindings_source( 'core/term-data' );

		$this->assertInstanceOf( 'WP_Block_Bindings_Source', $source );
		$this->assertSame( 'core/term-data', $source->name );
		$this->assertSame( 'Term Data', $source->label );
		$this->assertSame( array( 'termId', 'taxonomy' ), $source->uses_context );
	}

	/**
	 * Tests that calling _register_block_bindings_term_data_source when already registered does nothing.
	 *
	 * @covers ::_register_block_bindings_term_data_source
	 */
	public function test_register_block_bindings_term_data_source_idempotent() {
		$source_before = get_block_bindings_source( 'core/term-data' );

		// Call registration function again.
		_register_block_bindings_term_data_source();

		$source_after = get_block_bindings_source( 'core/term-data' );

		$this->assertSame( $source_before, $source_after );
	}

	/**
	 * Tests that core/term-data source works through get_value() method.
	 *
	 * @covers WP_Block_Bindings_Source::get_value
	 */
	public function test_source_get_value_integration() {
		$source = get_block_bindings_source( 'core/term-data' );
		$block  = $this->create_block(
			'core/paragraph',
			array(
				'termId'   => self::$category_id,
				'taxonomy' => 'category',
			)
		);

		$value = $source->get_value( array( 'field' => 'name' ), $block, 'content' );
		$this->assertSame( 'Test Category', $value );
	}
}
