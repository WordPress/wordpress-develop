<?php
/**
 * Tests for update_ignored_hooked_blocks_postmeta
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @since 6.6.0
 *
 * @group blocks
 * @covers ::update_ignored_hooked_blocks_postmeta
 */
class Tests_Blocks_UpdateIgnoredHookedBlocksPostMeta extends WP_UnitTestCase {
	/**
	 * Post object.
	 *
	 * @var object
	 */
	protected static $navigation_post;

	/**
	 * Post object.
	 *
	 * @var object
	 */
	protected static $template_part_post;

	/**
	 * Setup method.
	 */
	public static function wpSetUpBeforeClass() {
		self::$navigation_post    = self::factory()->post->create_and_get(
			array(
				'post_type'    => 'wp_navigation',
				'post_title'   => 'Navigation Menu',
				'post_content' => 'Original content',
			)
		);
		self::$template_part_post = self::factory()->post->create_and_get(
			array(
				'post_type'    => 'wp_template_part',
				'post_title'   => 'Template Part block',
				'post_content' => 'Original content',
			)
		);
	}

	/**
	 * Tear down each test method.
	 */
	public function tear_down() {
		$registry = WP_Block_Type_Registry::get_instance();

		if ( $registry->is_registered( 'tests/my-block' ) ) {
			$registry->unregister( 'tests/my-block' );
		}

		parent::tear_down();
	}

	/**
	 * @ticket 60759
	 */
	public function test_update_ignored_hooked_blocks_postmeta_preserves_entities() {
		register_block_type(
			'tests/my-block',
			array(
				'block_hooks' => array(
					'core/navigation' => 'last_child',
				),
			)
		);

		$original_markup    = '<!-- wp:navigation-link {"label":"News & About","type":"page","id":2,"url":"http://localhost:8888/?page_id=2","kind":"post-type"} /-->';
		$post               = new stdClass();
		$post->ID           = self::$navigation_post->ID;
		$post->post_content = $original_markup;
		$post->post_type    = 'wp_navigation';

		$post = update_ignored_hooked_blocks_postmeta( $post );

		// We expect the '&' character to be replaced with its unicode representation.
		$expected_markup = str_replace( '&', '\u0026', $original_markup );

		$this->assertSame(
			$expected_markup,
			$post->post_content,
			'Post content did not match expected markup with entities escaped.'
		);
		$this->assertSame(
			array( 'tests/my-block' ),
			json_decode( $post->meta_input['_wp_ignored_hooked_blocks'], true ),
			'Block was not added to ignored hooked blocks metadata.'
		);
	}

	/**
	 * @ticket 60759
	 */
	public function test_update_ignored_hooked_blocks_postmeta_dont_modify_no_post_id() {
		register_block_type(
			'tests/my-block',
			array(
				'block_hooks' => array(
					'core/navigation' => 'last_child',
				),
			)
		);

		$original_markup    = '<!-- wp:navigation-link {"label":"News","type":"page","id":2,"url":"http://localhost:8888/?page_id=2","kind":"post-type"} /-->';
		$post               = new stdClass();
		$post->post_content = $original_markup;
		$post->post_type    = 'wp_navigation';

		$post = update_ignored_hooked_blocks_postmeta( $post );

		$this->assertSame(
			$original_markup,
			$post->post_content,
			'Post content did not match the original markup.'
		);
	}

	/**
	 * @ticket 60759
	 */
	public function test_update_ignored_hooked_blocks_postmeta_retains_content_if_not_set() {
		register_block_type(
			'tests/my-block',
			array(
				'block_hooks' => array(
					'core/navigation' => 'last_child',
				),
			)
		);

		$post             = new stdClass();
		$post->ID         = self::$navigation_post->ID;
		$post->post_title = 'Navigation Menu with changes';
		$post->post_type  = 'wp_navigation';

		$post = update_ignored_hooked_blocks_postmeta( $post );

		$this->assertSame(
			'Navigation Menu with changes',
			$post->post_title,
			'Post title was changed.'
		);

		$this->assertFalse(
			isset( $post->post_content ),
			'Post content should not be set.'
		);
	}

	/**
	 * @ticket 60759
	 */
	public function test_update_ignored_hooked_blocks_postmeta_dont_modify_if_incompatible_post_type() {
		register_block_type(
			'tests/my-block',
			array(
				'block_hooks' => array(
					'core/navigation' => 'last_child',
				),
			)
		);

		$original_markup    = '<!-- wp:navigation-link {"label":"News","type":"page","id":2,"url":"http://localhost:8888/?page_id=2","kind":"post-type"} /-->';
		$post               = new stdClass();
		$post->ID           = self::$navigation_post->ID;
		$post->post_content = $original_markup;
		$post->post_type    = 'post';

		$post = update_ignored_hooked_blocks_postmeta( $post );

		$this->assertSame(
			$original_markup,
			$post->post_content,
			'Post content did not match the original markup.'
		);
		$this->assertNull(
			json_decode( get_post_meta( self::$template_part_post->ID, '_wp_ignored_hooked_blocks', true ), true ),
			'Block should not have been added to ignored hooked blocks post meta.'
		);
	}

	/**
	 * @ticket 60759
	 * @ticket 60854
	 */
	public function test_update_ignored_hooked_blocks_postmeta_imply_tempate_part_if_no_post_type() {
		register_block_type(
			'tests/my-block',
			array(
				'block_hooks' => array(
					'core/template-part' => 'last_child',
				),
			)
		);

		$original_markup    = '<!-- wp:paragraph --><p>test paragraph</p><!-- /wp:paragraph -->';
		$post               = new stdClass();
		$post->ID           = self::$template_part_post->ID;
		$post->post_content = $original_markup;

		$post = update_ignored_hooked_blocks_postmeta( $post );

		$this->assertSame(
			array( 'tests/my-block' ),
			json_decode( get_post_meta( self::$template_part_post->ID, '_wp_ignored_hooked_blocks', true ), true ),
			'Block was not added to ignored hooked blocks metadata.'
		);
	}
}
