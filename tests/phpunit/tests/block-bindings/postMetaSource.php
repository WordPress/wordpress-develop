<?php
/**
 * Tests for Block Bindings API "core/post-meta" source.
 *
 * @package WordPress
 * @subpackage Blocks
 * @since 6.5.0
 *
 * @group blocks
 * @group block-bindings
 */
class Tests_Block_Bindings_Post_Meta_Source extends WP_UnitTestCase {
	protected static $post;
	protected static $wp_meta_keys_saved;

	/**
	 * Modify the post content.
	 *
	 * @param string $content The new content.
	 */
	private function get_modified_post_content( $content ) {
		$GLOBALS['post']->post_content = $content;
		return apply_filters( 'the_content', $GLOBALS['post']->post_content );
	}

	/**
	 * Sets up shared fixtures.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post               = $factory->post->create_and_get();
		self::$wp_meta_keys_saved = isset( $GLOBALS['wp_meta_keys'] ) ? $GLOBALS['wp_meta_keys'] : array();
	}

	/**
	 * Tear down after class.
	 */
	public static function wpTearDownAfterClass() {
		$GLOBALS['wp_meta_keys'] = self::$wp_meta_keys_saved;
	}

	/**
	 * Set up before each test.
	 *
	 * @since 6.5.0
	 */
	public function set_up() {
		parent::set_up();
		// Needed because tear_down() will reset it between tests.
		$GLOBALS['post'] = self::$post;
	}

	/**
	 * Tests that a block connected to a custom field renders its value.
	 *
	 * @ticket 60651
	 */
	public function test_custom_field_value_is_rendered() {
		register_meta(
			'post',
			'tests_custom_field',
			array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'string',
				'default'      => 'Custom field value',
			)
		);

		$content = $this->get_modified_post_content( '<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"core/post-meta","args":{"key":"tests_custom_field"}}}}} --><p>Fallback value</p><!-- /wp:paragraph -->' );
		$this->assertSame(
			'<p>Custom field value</p>',
			$content,
			'The post content should show the value of the custom field . '
		);
	}

	/**
	 * Tests that an html attribute connected to a custom field renders its value.
	 *
	 * @ticket 60651
	 */
	public function test_html_attribute_connected_to_custom_field_value_is_rendered() {
		register_meta(
			'post',
			'tests_url_custom_field',
			array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'string',
				'default'      => 'https://example.com/foo.png',
			)
		);

		$content = $this->get_modified_post_content( '<!-- wp:image {"metadata":{"bindings":{"url":{"source":"core/post-meta","args":{"key":"tests_url_custom_field"}}}}} --><figure class="wp-block-image"><img alt=""/></figure><!-- /wp:image -->' );
		$this->assertSame(
			'<figure class="wp-block-image"><img decoding="async" src="https://example.com/foo.png" alt=""/></figure>',
			$content,
			'The image src should point to the value of the custom field . '
		);
	}

	/**
	 * Tests that a blocks connected in a password protected post don't render the value.
	 *
	 * @ticket 60651
	 */
	public function test_custom_field_value_is_not_shown_in_password_protected_posts() {
		register_meta(
			'post',
			'tests_custom_field',
			array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'string',
				'default'      => 'Custom field value',
			)
		);

		add_filter( 'post_password_required', '__return_true' );

		$content = $this->get_modified_post_content( '<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"core/post-meta","args":{"key":"tests_custom_field"}}}}} --><p>Fallback value</p><!-- /wp:paragraph -->' );

		remove_filter( 'post_password_required', '__return_true' );

		$this->assertSame(
			'<p>Fallback value</p>',
			$content,
			'The post content should show the fallback value instead of the custom field value.'
		);
	}

	/**
	 * Tests that a blocks connected in a post that is not publicly viewable don't render the value.
	 *
	 * @ticket 60651
	 */
	public function test_custom_field_value_is_not_shown_in_non_viewable_posts() {
		register_meta(
			'post',
			'tests_custom_field',
			array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'string',
				'default'      => 'Custom field value',
			)
		);

		add_filter( 'is_post_status_viewable', '__return_false' );

		$content = $this->get_modified_post_content( '<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"core/post-meta","args":{"key":"tests_custom_field"}}}}} --><p>Fallback value</p><!-- /wp:paragraph -->' );

		remove_filter( 'is_post_status_viewable', '__return_false' );

		$this->assertSame(
			'<p>Fallback value</p>',
			$content,
			'The post content should show the fallback value instead of the custom field value.'
		);
	}

	/**
	 * Tests that a block connected to a meta key that doesn't exist renders the fallback.
	 *
	 * @ticket 60651
	 */
	public function test_binding_to_non_existing_meta_key() {
		$content = $this->get_modified_post_content( '<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"core/post-meta","args":{"key":"tests_non_existing_field"}}}}} --><p>Fallback value</p><!-- /wp:paragraph -->' );

		$this->assertSame(
			'<p>Fallback value</p>',
			$content,
			'The post content should show the fallback value.'
		);
	}

	/**
	 * Tests that a block connected without specifying the custom field renders the fallback.
	 *
	 * @ticket 60651
	 */
	public function test_binding_without_key_renders_the_fallback() {
		$content = $this->get_modified_post_content( '<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"core/post-meta"}}}} --><p>Fallback value</p><!-- /wp:paragraph -->' );

		$this->assertSame(
			'<p>Fallback value</p>',
			$content,
			'The post content should show the fallback value.'
		);
	}

	/**
	 * Tests that a block connected to a protected field doesn't show the value.
	 *
	 * @ticket 60651
	 */
	public function test_protected_field_value_is_not_shown() {
		register_meta(
			'post',
			'_tests_protected_field',
			array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'string',
				'default'      => 'Protected value',
			)
		);

		$content = $this->get_modified_post_content( '<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"core/post-meta","args":{"key":"_tests_protected_field"}}}}} --><p>Fallback value</p><!-- /wp:paragraph -->' );

		$this->assertSame(
			'<p>Fallback value</p>',
			$content,
			'The post content should show the fallback value instead of the protected value.'
		);
	}

	/**
	 * Tests that a block connected to a field not exposed in the REST API doesn't show the value.
	 *
	 * @ticket 60651
	 */
	public function test_custom_field_not_exposed_in_rest_api_is_not_shown() {
		register_meta(
			'post',
			'tests_show_in_rest_false_field',
			array(
				'show_in_rest' => false,
				'single'       => true,
				'type'         => 'string',
				'default'      => 'Protected value',
			)
		);

		$content = $this->get_modified_post_content( '<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"core/post-meta","args":{"key":"tests_show_in_rest_false_field"}}}}} --><p>Fallback value</p><!-- /wp:paragraph -->' );

		$this->assertSame(
			'<p>Fallback value</p>',
			$content,
			'The post content should show the fallback value instead of the protected value.'
		);
	}

	/**
	 * Tests that meta key with unsafe HTML is sanitized.
	 *
	 * @ticket 60651
	 */
	public function test_custom_field_with_unsafe_html_is_sanitized() {
		register_meta(
			'post',
			'tests_unsafe_html_field',
			array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'string',
				'default'      => '<script>alert("Unsafe HTML")</script>',
			)
		);

		$content = $this->get_modified_post_content( '<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"core/post-meta","args":{"key":"tests_unsafe_html_field"}}}}} --><p>Fallback value</p><!-- /wp:paragraph -->' );

		$this->assertSame(
			'<p>alert(&#8220;Unsafe HTML&#8221;)</p>',
			$content,
			'The post content should not include the script tag.'
		);
	}

	/**
	 * Tests that filter `block_bindings_source_value` is applied.
	 *
	 * @ticket 61181
	 */
	public function test_filter_block_bindings_source_value() {
		register_meta(
			'post',
			'tests_filter_field',
			array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'string',
				'default'      => 'Original value',
			)
		);

		$filter_value = function ( $value, $source_name, $source_args ) {
			if ( 'core/post-meta' !== $source_name ) {
				return $value;
			}
			return "Filtered value: {$source_args['key']}";
		};

		add_filter( 'block_bindings_source_value', $filter_value, 10, 3 );

		$content = $this->get_modified_post_content( '<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"core/post-meta","args":{"key":"tests_filter_field"}}}}} --><p>Fallback value</p><!-- /wp:paragraph -->' );

		remove_filter( 'block_bindings_source_value', $filter_value );

		$this->assertSame(
			'<p>Filtered value: tests_filter_field</p>',
			$content,
			'The post content should show the filtered value.'
		);
	}
}
