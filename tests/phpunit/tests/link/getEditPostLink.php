<?php
/**
 * @group link
 * @covers ::get_edit_post_link
 */
class Tests_Link_GetEditPostLink extends WP_UnitTestCase {
	const TEST_THEME = 'block-theme';

	private static $admin_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		// Create an admin user because get_edit_post_link() requires 'edit_post' capability.
		self::$admin_id = $factory->user->create( array( 'role' => 'administrator' ) );
	}

	public function set_up() {
		parent::set_up();
		wp_set_current_user( self::$admin_id );
		switch_theme( self::TEST_THEME );
	}

	public function test_get_edit_post_link() {
		$post                 = self::factory()->post->create_and_get(
			array(
				'post_type'   => 'post',
				'post_title'  => 'Test Post',
				'post_name'   => 'test-post',
				'post_status' => 'publish',
			)
		);
		$post_type_object     = get_post_type_object( $post->post_type );
		$link_default_context = admin_url( sprintf( $post_type_object->_edit_link . '&amp;action=edit', $post->ID ) );
		$link_custom_context  = admin_url( sprintf( $post_type_object->_edit_link . '&action=edit', $post->ID ) );

		$this->assertSame( $link_default_context, get_edit_post_link( $post ), 'Second argument `$context` has a default context of `"display"`.' );
		$this->assertSame( $link_custom_context, get_edit_post_link( $post, 'something-else' ), 'Pass non-default value in second argument.' );
	}

	public function test_get_edit_post_link_for_wp_template_post_type() {
		$template_post = self::factory()->post->create_and_get(
			array(
				'post_type'    => 'wp_template',
				'post_name'    => 'my_template',
				'post_title'   => 'My Template',
				'post_content' => 'Content',
				'post_excerpt' => 'Description of my template',
				'tax_input'    => array(
					'wp_theme' => array(
						self::TEST_THEME,
					),
				),
			)
		);

		wp_set_post_terms( $template_post->ID, self::TEST_THEME, 'wp_theme' );

		$post_type_object     = get_post_type_object( $template_post->post_type );
		$link_default_context = admin_url( sprintf( $post_type_object->_edit_link . '&amp;canvas=edit', get_stylesheet() . '//my_template' ) );
		$link_custom_context  = admin_url( sprintf( $post_type_object->_edit_link . '&canvas=edit', get_stylesheet() . '//my_template' ) );

		$this->assertSame( $link_default_context, get_edit_post_link( $template_post ), 'Second argument `$context` has a default context of `"display"`.' );
		$this->assertSame( $link_custom_context, get_edit_post_link( $template_post, 'something-else' ), 'Pass non-default value in second argument.' );
	}

	public function test_get_edit_post_link_for_wp_template_part_post_type() {
		$template_part_post = self::factory()->post->create_and_get(
			array(
				'post_type'    => 'wp_template_part',
				'post_name'    => 'my_template_part',
				'post_title'   => 'My Template Part',
				'post_content' => 'Content',
				'post_excerpt' => 'Description of my template part',
				'tax_input'    => array(
					'wp_theme'              => array(
						self::TEST_THEME,
					),
					'wp_template_part_area' => array(
						WP_TEMPLATE_PART_AREA_HEADER,
					),
				),
			)
		);

		wp_set_post_terms( $template_part_post->ID, WP_TEMPLATE_PART_AREA_HEADER, 'wp_template_part_area' );
		wp_set_post_terms( $template_part_post->ID, self::TEST_THEME, 'wp_theme' );

		$post_type_object     = get_post_type_object( $template_part_post->post_type );
		$link_default_context = admin_url( sprintf( $post_type_object->_edit_link . '&amp;canvas=edit', get_stylesheet() . '//my_template_part' ) );
		$link_custom_context  = admin_url( sprintf( $post_type_object->_edit_link . '&canvas=edit', get_stylesheet() . '//my_template_part' ) );

		$this->assertSame( $link_default_context, get_edit_post_link( $template_part_post ), 'Second argument `$context` has a default context of `"display"`.' );
		$this->assertSame( $link_custom_context, get_edit_post_link( $template_part_post, 'something-else' ), 'Pass non-default value in second argument.' );
	}
}
