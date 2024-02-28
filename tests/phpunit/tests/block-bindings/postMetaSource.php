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

	/**
	 * Sets up each test method.
	 */
	public function set_up() {
		global $post;

		parent::set_up();

		$post = self::factory()->post->create_and_get( array() );
		setup_postdata( $post );
	}

	/**
	 * Tear down each test method.
	 */
	public function tear_down() {
		// Removes custom fields registered by test cases.
		$meta_keys = get_registered_meta_keys( 'post', '' );
		foreach ( $meta_keys as $meta_key_name => $meta_key_value ) {
			if ( str_contains( $meta_key_name, 'tests' ) ) {
				unregister_meta_key( 'post', $meta_key_name );
			}
		}

		parent::tear_down();
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

		$content = apply_filters( 'the_content', '<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"core/post-meta","args":{"key":"tests_custom_field"}}}}} --><p>Fallback value</p><!-- /wp:paragraph -->' );

		$this->assertSame(
			'<p>Custom field value</p>',
			trim( $content ),
			'The post content should show the value of the custom field.'
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

		add_filter(
			'post_password_required',
			function () {
				return true;
			}
		);

		$content = apply_filters( 'the_content', '<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"core/post-meta","args":{"key":"tests_custom_field"}}}}} --><p>Fallback value</p><!-- /wp:paragraph -->' );

		$this->assertSame(
			'<p>Fallback value</p>',
			trim( $content ),
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

		add_filter(
			'is_post_status_viewable',
			function () {
				return false;
			}
		);

		$content = apply_filters( 'the_content', '<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"core/post-meta","args":{"key":"tests_custom_field"}}}}} --><p>Fallback value</p><!-- /wp:paragraph -->' );

		$this->assertSame(
			'<p>Fallback value</p>',
			trim( $content ),
			'The post content should show the fallback value instead of the custom field value.'
		);
	}

	/**
	 * Tests that a block connected without specifying the custom field renders the fallback.
	 *
	 * @ticket 60651
	 */
	public function test_binding_without_key_renders_the_fallback() {
		$content = apply_filters( 'the_content', '<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"core/post-meta"}}}} --><p>Fallback value</p><!-- /wp:paragraph -->' );

		$this->assertSame(
			'<p>Fallback value</p>',
			trim( $content ),
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

		$content = apply_filters( 'the_content', '<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"core/post-meta","args":{"key":"_tests_protected_field"}}}}} --><p>Fallback value</p><!-- /wp:paragraph -->' );

		$this->assertSame(
			'<p>Fallback value</p>',
			trim( $content ),
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

		$content = apply_filters( 'the_content', '<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"core/post-meta","args":{"key":"tests_show_in_rest_false_field"}}}}} --><p>Fallback value</p><!-- /wp:paragraph -->' );

		$this->assertSame(
			'<p>Fallback value</p>',
			trim( $content ),
			'The post content should show the fallback value instead of the protected value.'
		);
	}
}
