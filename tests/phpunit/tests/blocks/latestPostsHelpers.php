<?php
/**
 * Tests for the small functions of the Latest Posts block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 */
class Tests_Blocks_LatestPostsHelpers extends WP_UnitTestCase {

	/**
	 * Original excerpt length of the block.
	 *
	 * @var mixed
	 */
	private $original_excerpt_length;

	public function set_up() {
		parent::set_up();

		global $block_core_latest_posts_excerpt_length;

		$this->original_excerpt_length = $block_core_latest_posts_excerpt_length;
	}

	public function tear_down() {
		global $block_core_latest_posts_excerpt_length;

		$block_core_latest_posts_excerpt_length = $this->original_excerpt_length;

		parent::tear_down();
	}

	/**
	 * Tests the excerpt length of the block.
	 *
	 * @ticket 66600
	 *
	 * @covers ::block_core_latest_posts_get_excerpt_length
	 *
	 * @dataProvider data_excerpt_lengths
	 *
	 * @param mixed $length The excerpt length.
	 */
	public function test_get_excerpt_length( $length ) {
		global $block_core_latest_posts_excerpt_length;

		$block_core_latest_posts_excerpt_length = $length;

		$this->assertSame( $length, block_core_latest_posts_get_excerpt_length() );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_excerpt_lengths() {
		return array(
			'zero'   => array( 0 ),
			'number' => array( 55 ),
			'string' => array( '12' ),
		);
	}

	/**
	 * Tests that the categories that are a string are changed to a list of categories.
	 *
	 * @ticket 66600
	 *
	 * @covers ::block_core_latest_posts_migrate_categories
	 *
	 * @dataProvider data_categories_to_migrate
	 *
	 * @param string $categories The categories attribute of the block.
	 * @param array  $expected   The expected categories attribute.
	 */
	public function test_migrate_categories_changes_a_string_to_a_list( $categories, $expected ) {
		$block = array(
			'blockName' => 'core/latest-posts',
			'attrs'     => array(
				'categories'  => $categories,
				'postsToShow' => 3,
			),
		);

		$this->assertSame(
			array(
				'blockName' => 'core/latest-posts',
				'attrs'     => array(
					'categories'  => $expected,
					'postsToShow' => 3,
				),
			),
			block_core_latest_posts_migrate_categories( $block )
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_categories_to_migrate() {
		return array(
			'ID' => array( '12', array( array( 'id' => 12 ) ) ),
		);
	}

	/**
	 * Tests that the blocks that do not need to change are returned as they are.
	 *
	 * @ticket 66600
	 *
	 * @covers ::block_core_latest_posts_migrate_categories
	 *
	 * @dataProvider data_blocks_to_leave
	 *
	 * @param array $block The block.
	 */
	public function test_migrate_categories_leaves_other_blocks_as_they_are( $block ) {
		$this->assertSame( $block, block_core_latest_posts_migrate_categories( $block ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_blocks_to_leave() {
		return array(
			'list of categories'   => array(
				array(
					'blockName' => 'core/latest-posts',
					'attrs'     => array( 'categories' => array( array( 'id' => 5 ) ) ),
				),
			),
			'number'               => array(
				array(
					'blockName' => 'core/latest-posts',
					'attrs'     => array( 'categories' => 5 ),
				),
			),
			'empty string'         => array(
				array(
					'blockName' => 'core/latest-posts',
					'attrs'     => array( 'categories' => '' ),
				),
			),
			'zero string'          => array(
				array(
					'blockName' => 'core/latest-posts',
					'attrs'     => array( 'categories' => '0' ),
				),
			),
			'empty list'           => array(
				array(
					'blockName' => 'core/latest-posts',
					'attrs'     => array( 'categories' => array() ),
				),
			),
			'no categories'        => array(
				array(
					'blockName' => 'core/latest-posts',
					'attrs'     => array( 'postsToShow' => 3 ),
				),
			),
			'no attributes'        => array(
				array(
					'blockName' => 'core/latest-posts',
					'attrs'     => array(),
				),
			),
			'another block'        => array(
				array(
					'blockName' => 'core/categories',
					'attrs'     => array( 'categories' => '5' ),
				),
			),
			'block without a name' => array(
				array(
					'blockName' => null,
					'attrs'     => array( 'categories' => '5' ),
				),
			),
		);
	}

	/**
	 * Tests that the function is hooked to the data of the blocks that are rendered.
	 *
	 * @ticket 66600
	 *
	 * @covers ::block_core_latest_posts_migrate_categories
	 */
	public function test_migrate_categories_is_hooked_to_the_block_data_filter() {
		$this->assertSame( 10, has_filter( 'render_block_data', 'block_core_latest_posts_migrate_categories' ) );
	}

	/**
	 * Tests the migration when a block is rendered.
	 *
	 * @ticket 66600
	 *
	 * @covers ::block_core_latest_posts_migrate_categories
	 */
	public function test_migrate_categories_when_the_block_is_rendered() {
		$category_id = self::factory()->category->create( array( 'name' => 'Migrated' ) );
		$other_id    = self::factory()->category->create( array( 'name' => 'Other' ) );

		self::factory()->post->create(
			array(
				'post_title'    => 'In the category',
				'post_category' => array( $category_id ),
			)
		);
		self::factory()->post->create(
			array(
				'post_title'    => 'In another category',
				'post_category' => array( $other_id ),
			)
		);

		$output = do_blocks( '<!-- wp:latest-posts {"categories":"' . $category_id . '"} /-->' );

		$this->assertStringContainsString( 'In the category', $output );
		$this->assertStringNotContainsString( 'In another category', $output );
	}
}
