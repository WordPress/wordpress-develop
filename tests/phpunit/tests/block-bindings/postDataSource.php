<?php
/**
 * Tests for Block Bindings API "core/post-data" source.
 *
 * @package WordPress
 * @subpackage Blocks
 * @since 6.9.0
 *
 * @group blocks
 * @group block-bindings
 */
class Tests_Block_Bindings_Post_Data_Source extends WP_UnitTestCase {

	/**
	 * Test post ID with modified date later than publish date.
	 *
	 * @var int
	 */
	protected static $post_id;

	/**
	 * Test post ID with modified date equal to publish date.
	 *
	 * @var int
	 */
	protected static $unmodified_post_id;

	/**
	 * Test private post ID.
	 *
	 * @var int
	 */
	protected static $private_post_id;

	/**
	 * Test password-protected post ID.
	 *
	 * @var int
	 */
	protected static $password_post_id;

	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	protected static $admin_id;

	/**
	 * Subscriber user ID.
	 *
	 * @var int
	 */
	protected static $subscriber_id;

	/**
	 * Sets up shared fixtures for the test class.
	 *
	 * @param WP_UnitTest_Factory $factory Unit test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id = $factory->post->create(
			array(
				'post_title'    => 'Test Post Data',
				'post_status'   => 'publish',
				'post_date'     => '2025-01-01 10:00:00',
				'post_date_gmt' => '2025-01-01 10:00:00',
			)
		);
		global $wpdb;

		$wpdb->update(
			$wpdb->posts,
			array(
				'post_modified'     => '2025-01-02 12:00:00',
				'post_modified_gmt' => '2025-01-02 12:00:00',
			),
			array(
				'ID' => self::$post_id,
			),
			array(
				'%s',
				'%s',
			),
			array(
				'%d',
			)
		);
		clean_post_cache( self::$post_id );

		self::$unmodified_post_id = $factory->post->create(
			array(
				'post_title'    => 'Unmodified Post Data',
				'post_status'   => 'publish',
				'post_date'     => '2025-01-01 10:00:00',
				'post_modified' => '2025-01-01 10:00:00',
			)
		);

		self::$private_post_id = $factory->post->create(
			array(
				'post_title'    => 'Private Post Data',
				'post_status'   => 'private',
				'post_date'     => '2025-01-03 09:00:00',
				'post_date_gmt' => '2025-01-03 09:00:00',
			)
		);

		self::$password_post_id = $factory->post->create(
			array(
				'post_title'    => 'Password Protected Post Data',
				'post_status'   => 'publish',
				'post_password' => 'secret-pass',
			)
		);

		self::$admin_id      = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$subscriber_id = $factory->user->create( array( 'role' => 'subscriber' ) );
	}

	/**
	 * Clean up after each test.
	 */
	public function tear_down() {
		wp_set_current_user( 0 );
		parent::tear_down();
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
	 * Tests that _block_bindings_post_data_get_value() returns null when both field and key args are missing or empty.
	 *
	 * @ticket 65819
	 *
	 * @covers ::_block_bindings_post_data_get_value
	 *
	 * @dataProvider data_missing_or_empty_field_args
	 *
	 * @param array $source_args Source arguments.
	 */
	public function test_get_value_returns_null_when_field_and_key_empty( array $source_args ) {
		$block = $this->create_block(
			'core/paragraph',
			array( 'postId' => self::$post_id )
		);

		$this->assertNull( _block_bindings_post_data_get_value( $source_args, $block ) );
	}

	/**
	 * Data provider for test_get_value_returns_null_when_field_and_key_empty.
	 *
	 * @return array
	 */
	public static function data_missing_or_empty_field_args() {
		return array(
			'missing args'           => array( array() ),
			'empty string field'     => array( array( 'field' => '' ) ),
			'null field'             => array( array( 'field' => null ) ),
			'empty string key'       => array( array( 'key' => '' ) ),
			'null key'               => array( array( 'key' => null ) ),
			'both empty field & key' => array(
				array(
					'field' => '',
					'key'   => '',
				),
			),
		);
	}

	/**
	 * Tests that _block_bindings_post_data_get_value() returns null when postId is missing or empty.
	 *
	 * @ticket 65819
	 *
	 * @covers ::_block_bindings_post_data_get_value
	 *
	 * @dataProvider data_missing_or_empty_post_id
	 *
	 * @param array $context Block context.
	 */
	public function test_get_value_returns_null_when_post_id_missing( array $context ) {
		$block = $this->create_block( 'core/paragraph', $context );

		$this->assertNull( _block_bindings_post_data_get_value( array( 'field' => 'date' ), $block ) );
	}

	/**
	 * Data provider for test_get_value_returns_null_when_post_id_missing.
	 *
	 * @return array
	 */
	public static function data_missing_or_empty_post_id() {
		return array(
			'missing postId context' => array( array() ),
			'empty string postId'    => array( array( 'postId' => '' ) ),
			'null postId'            => array( array( 'postId' => null ) ),
			'zero postId'            => array( array( 'postId' => 0 ) ),
		);
	}

	/**
	 * Tests that _block_bindings_post_data_get_value() returns null when post does not exist.
	 *
	 * @ticket 65819
	 *
	 * @covers ::_block_bindings_post_data_get_value
	 */
	public function test_get_value_returns_null_when_post_does_not_exist() {
		$block = $this->create_block(
			'core/paragraph',
			array( 'postId' => 999999 )
		);

		$this->assertNull( _block_bindings_post_data_get_value( array( 'field' => 'date' ), $block ) );
	}

	/**
	 * Tests that _block_bindings_post_data_get_value() returns null when post is not publicly viewable
	 * and user cannot read_post.
	 *
	 * @ticket 65819
	 *
	 * @covers ::_block_bindings_post_data_get_value
	 */
	public function test_get_value_returns_null_when_post_not_publicly_viewable_and_cannot_read() {
		$block = $this->create_block(
			'core/paragraph',
			array( 'postId' => self::$private_post_id )
		);

		// Unauthenticated user.
		wp_set_current_user( 0 );
		$this->assertNull( _block_bindings_post_data_get_value( array( 'field' => 'date' ), $block ) );

		// Subscriber user without read_post capability for this post.
		wp_set_current_user( self::$subscriber_id );
		$this->assertNull( _block_bindings_post_data_get_value( array( 'field' => 'date' ), $block ) );
	}

	/**
	 * Tests that _block_bindings_post_data_get_value() returns post data when user has read_post capability.
	 *
	 * @ticket 65819
	 *
	 * @covers ::_block_bindings_post_data_get_value
	 */
	public function test_get_value_returns_value_when_user_can_read_private_post() {
		$block = $this->create_block(
			'core/paragraph',
			array( 'postId' => self::$private_post_id )
		);

		wp_set_current_user( self::$admin_id );

		$this->assertSame(
			'2025-01-03T09:00:00+00:00',
			_block_bindings_post_data_get_value( array( 'field' => 'date' ), $block ),
			'Private post date should be accessible by user with read_post capability.'
		);
	}

	/**
	 * Tests that _block_bindings_post_data_get_value() returns null when post password is required.
	 *
	 * @ticket 65819
	 *
	 * @covers ::_block_bindings_post_data_get_value
	 */
	public function test_get_value_returns_null_when_post_password_required() {
		$block = $this->create_block(
			'core/paragraph',
			array( 'postId' => self::$password_post_id )
		);

		$this->assertNull(
			_block_bindings_post_data_get_value( array( 'field' => 'date' ), $block ),
			'Post requiring a password should return null.'
		);
	}

	/**
	 * Tests that _block_bindings_post_data_get_value() returns date field formatted in ISO 8601.
	 *
	 * @ticket 65819
	 *
	 * @covers ::_block_bindings_post_data_get_value
	 */
	public function test_get_value_returns_date_field() {
		$block = $this->create_block(
			'core/paragraph',
			array( 'postId' => self::$post_id )
		);

		$this->assertSame(
			'2025-01-01T10:00:00+00:00',
			_block_bindings_post_data_get_value( array( 'field' => 'date' ), $block ),
			'Date field should return the post date in ISO 8601 format.'
		);
	}

	/**
	 * Tests that _block_bindings_post_data_get_value() returns modified date when modified date is later than publish date.
	 *
	 * @ticket 65819
	 *
	 * @covers ::_block_bindings_post_data_get_value
	 */
	public function test_get_value_returns_modified_date_when_modified_later() {
		$block = $this->create_block(
			'core/paragraph',
			array( 'postId' => self::$post_id )
		);

		$this->assertSame(
			'2025-01-02T12:00:00+00:00',
			_block_bindings_post_data_get_value( array( 'field' => 'modified' ), $block ),
			'Modified date should be returned when later than publish date.'
		);
	}

	/**
	 * Tests that _block_bindings_post_data_get_value() returns empty string when modified date is not later than publish date.
	 *
	 * @ticket 65819
	 *
	 * @covers ::_block_bindings_post_data_get_value
	 */
	public function test_get_value_returns_empty_string_when_modified_date_not_later() {
		$block = $this->create_block(
			'core/paragraph',
			array( 'postId' => self::$unmodified_post_id )
		);

		$this->assertSame(
			'',
			_block_bindings_post_data_get_value( array( 'field' => 'modified' ), $block ),
			'Modified date should return empty string when equal to publish date.'
		);
	}

	/**
	 * Tests that _block_bindings_post_data_get_value() returns permalink for link field.
	 *
	 * @ticket 65819
	 *
	 * @covers ::_block_bindings_post_data_get_value
	 */
	public function test_get_value_returns_link_field() {
		$block = $this->create_block(
			'core/paragraph',
			array( 'postId' => self::$post_id )
		);

		$expected_link = esc_url( get_permalink( self::$post_id ) );
		$this->assertSame(
			$expected_link,
			_block_bindings_post_data_get_value( array( 'field' => 'link' ), $block ),
			'Link field should return esc_url( get_permalink() ).'
		);
	}

	/**
	 * Tests that _block_bindings_post_data_get_value() returns null when get_permalink() returns false.
	 *
	 * @ticket 65819
	 *
	 * @covers ::_block_bindings_post_data_get_value
	 */
	public function test_get_value_returns_null_when_permalink_is_false() {
		$block = $this->create_block(
			'core/paragraph',
			array( 'postId' => self::$post_id )
		);

		add_filter( 'post_link', '__return_false' );
		$value = _block_bindings_post_data_get_value( array( 'field' => 'link' ), $block );
		remove_filter( 'post_link', '__return_false' );

		$this->assertNull( $value, 'Link field should return null when get_permalink() returns false.' );
	}

	/**
	 * Tests that _block_bindings_post_data_get_value() escapes URL characters in the link field.
	 *
	 * @ticket 65819
	 *
	 * @covers ::_block_bindings_post_data_get_value
	 */
	public function test_get_value_link_escaping() {
		$block = $this->create_block(
			'core/paragraph',
			array( 'postId' => self::$post_id )
		);

		$filter = static function () {
			return 'https://example.com/test/?param=1&other=2';
		};

		add_filter( 'post_link', $filter );
		$value = _block_bindings_post_data_get_value( array( 'field' => 'link' ), $block );
		remove_filter( 'post_link', $filter );

		$this->assertSame(
			'https://example.com/test/?param=1&#038;other=2',
			$value,
			'Link field should be escaped with esc_url().'
		);
	}

	/**
	 * Tests backward compatibility with legacy `key` argument.
	 *
	 * @ticket 65819
	 *
	 * @covers ::_block_bindings_post_data_get_value
	 */
	public function test_get_value_backward_compatibility_with_key_arg() {
		$block = $this->create_block(
			'core/paragraph',
			array( 'postId' => self::$post_id )
		);

		$this->assertSame(
			'2025-01-01T10:00:00+00:00',
			_block_bindings_post_data_get_value( array( 'key' => 'date' ), $block ),
			'Legacy key argument should resolve as field.'
		);
	}

	/**
	 * Tests that _block_bindings_post_data_get_value() reads attributes for navigation-link blocks.
	 *
	 * @ticket 65819
	 *
	 * @covers ::_block_bindings_post_data_get_value
	 */
	public function test_get_value_navigation_link_attributes() {
		$block = $this->create_block(
			'core/navigation-link',
			array(),
			array( 'id' => self::$post_id )
		);

		$this->assertSame(
			'2025-01-01T10:00:00+00:00',
			_block_bindings_post_data_get_value( array( 'field' => 'date' ), $block ),
			'Navigation link block should read post ID from attributes.'
		);
	}

	/**
	 * Tests that _block_bindings_post_data_get_value() reads attributes for navigation-submenu blocks.
	 *
	 * @ticket 65819
	 *
	 * @covers ::_block_bindings_post_data_get_value
	 */
	public function test_get_value_navigation_submenu_attributes() {
		$block = $this->create_block(
			'core/navigation-submenu',
			array(),
			array( 'id' => self::$post_id )
		);

		$this->assertSame(
			'2025-01-01T10:00:00+00:00',
			_block_bindings_post_data_get_value( array( 'field' => 'date' ), $block ),
			'Navigation submenu block should read post ID from attributes.'
		);
	}

	/**
	 * Tests that _block_bindings_post_data_get_value() returns null for unsupported/unknown fields.
	 *
	 * @ticket 65819
	 *
	 * @covers ::_block_bindings_post_data_get_value
	 */
	public function test_get_value_returns_null_for_unsupported_field() {
		$block = $this->create_block(
			'core/paragraph',
			array( 'postId' => self::$post_id )
		);

		$this->assertNull(
			_block_bindings_post_data_get_value( array( 'field' => 'unsupported_field' ), $block ),
			'Unknown field should return null.'
		);
	}

	/**
	 * Tests that core/post-data source is registered with expected properties.
	 *
	 * @ticket 65819
	 *
	 * @covers ::_register_block_bindings_post_data_source
	 */
	public function test_source_registration_metadata() {
		$source = get_block_bindings_source( 'core/post-data' );

		$this->assertInstanceOf(
			'WP_Block_Bindings_Source',
			$source,
			'core/post-data should be registered in the block bindings registry.'
		);
		$this->assertSame(
			'core/post-data',
			$source->name,
			'Source name should match core/post-data.'
		);
		$this->assertSame(
			'Post Data',
			$source->label,
			'Source label should match Post Data.'
		);
		$this->assertSame(
			array( 'postId', 'postType' ),
			$source->uses_context,
			'Source uses_context should declare postId and postType.'
		);
	}

	/**
	 * Tests that value retrieval works through the registered source get_value() method.
	 *
	 * @ticket 65819
	 *
	 * @covers ::_register_block_bindings_post_data_source
	 * @covers ::_block_bindings_post_data_get_value
	 */
	public function test_source_get_value_through_registered_source() {
		$source = get_block_bindings_source( 'core/post-data' );
		$block  = $this->create_block(
			'core/paragraph',
			array( 'postId' => self::$post_id )
		);

		$this->assertSame(
			'2025-01-01T10:00:00+00:00',
			$source->get_value( array( 'field' => 'date' ), $block, 'content' ),
			'Registered source get_value() should return date field.'
		);
	}

	/**
	 * Tests that calling _register_block_bindings_post_data_source() registers the source.
	 *
	 * @ticket 65819
	 *
	 * @covers ::_register_block_bindings_post_data_source
	 */
	public function test_register_source() {
		unregister_block_bindings_source( 'core/post-data' );
		$this->assertNull( get_block_bindings_source( 'core/post-data' ) );

		_register_block_bindings_post_data_source();

		$source = get_block_bindings_source( 'core/post-data' );
		$this->assertInstanceOf( 'WP_Block_Bindings_Source', $source );
	}

	/**
	 * Tests that calling _register_block_bindings_post_data_source() when already registered triggers doing it wrong.
	 *
	 * @ticket 65819
	 *
	 * @covers ::_register_block_bindings_post_data_source
	 */
	public function test_register_source_already_registered_triggers_doing_it_wrong() {
		$this->setExpectedIncorrectUsage( 'WP_Block_Bindings_Registry::register' );

		_register_block_bindings_post_data_source();
	}
}
