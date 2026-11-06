<?php
/**
 * Tests for the server-side rendering of the Site Title block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 *
 * @covers ::render_block_core_site_title
 */
class Tests_Blocks_RenderSiteTitle extends WP_UnitTestCase {

	/**
	 * Original block to render of the block supports.
	 *
	 * @var array|null
	 */
	private $original_block_to_render;

	public function set_up() {
		parent::set_up();

		$this->original_block_to_render     = WP_Block_Supports::$block_to_render;
		WP_Block_Supports::$block_to_render = null;

		update_option( 'blogname', 'My Site' );
	}

	public function tear_down() {
		WP_Block_Supports::$block_to_render = $this->original_block_to_render;

		parent::tear_down();
	}

	/**
	 * Tests that nothing is rendered without a title.
	 *
	 * @ticket 66517
	 *
	 * @dataProvider data_empty_titles
	 *
	 * @param string $title Title of the site.
	 */
	public function test_should_render_nothing_without_a_title( $title ) {
		update_option( 'blogname', $title );

		$this->assertSame( '', render_block_core_site_title( array( 'isLink' => false ) ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_empty_titles() {
		return array(
			'an empty title'     => array( '' ),
			'spaces'             => array( '   ' ),
			'tabs and new lines' => array( "\t\n " ),
		);
	}

	/**
	 * Tests that the title is rendered in a heading by default.
	 *
	 * @ticket 66517
	 */
	public function test_should_render_the_title_in_a_heading_level_1_by_default() {
		$this->assertSame( '<h1 >My Site</h1>', render_block_core_site_title( array( 'isLink' => false ) ) );
	}

	/**
	 * Tests that the tag depends on the level.
	 *
	 * @ticket 66517
	 *
	 * @dataProvider data_levels
	 *
	 * @param mixed  $level    Level of the block.
	 * @param string $tag_name Expected tag.
	 */
	public function test_should_use_the_tag_of_the_level( $level, $tag_name ) {
		$this->assertSame(
			"<{$tag_name} >My Site</{$tag_name}>",
			render_block_core_site_title(
				array(
					'isLink' => false,
					'level'  => $level,
				)
			)
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_levels() {
		return array(
			'zero is a paragraph' => array( 0, 'p' ),
			'one'                 => array( 1, 'h1' ),
			'two'                 => array( 2, 'h2' ),
			'six'                 => array( 6, 'h6' ),
			'a numeric string'    => array( '3', 'h3' ),
		);
	}

	/**
	 * Tests that the title is escaped.
	 *
	 * @ticket 66517
	 *
	 * @dataProvider data_links
	 *
	 * @param bool   $is_link  Whether the title is a link.
	 * @param string $expected Text that is expected in the output.
	 */
	public function test_should_escape_the_title( $is_link, $expected ) {
		// The option is escaped when it is saved, so the title is filtered to get an unescaped one.
		add_filter(
			'pre_option_blogname',
			static function () {
				return 'Tom & "Jerry" <b>Co</b>';
			}
		);

		$this->assertStringContainsString( $expected, render_block_core_site_title( array( 'isLink' => $is_link ) ) );
		$this->assertStringNotContainsString( '<b>', render_block_core_site_title( array( 'isLink' => $is_link ) ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_links() {
		return array(
			'plain text' => array( false, 'Tom &amp; &quot;Jerry&quot; &lt;b&gt;Co&lt;/b&gt;' ),
			'a link'     => array( true, '>Tom &amp; &quot;Jerry&quot; &lt;b&gt;Co&lt;/b&gt;</a>' ),
		);
	}

	/**
	 * Tests the classes of the block.
	 *
	 * @ticket 66517
	 *
	 * @dataProvider data_classes
	 *
	 * @param array  $attributes Attributes of the block.
	 * @param string $expected   Expected class attribute.
	 */
	public function test_should_add_the_classes_of_the_attributes( $attributes, $expected ) {
		$this->assertSame(
			'<h1 ' . $expected . '>My Site</h1>',
			render_block_core_site_title( array_merge( array( 'isLink' => false ), $attributes ) )
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_classes() {
		$link_color = array( 'elements' => array( 'link' => array( 'color' => array( 'text' => '#ff0000' ) ) ) );

		return array(
			'no classes'                  => array( array(), '' ),
			'an empty text alignment'     => array( array( 'textAlign' => '' ), '' ),
			'a text alignment'            => array( array( 'textAlign' => 'center' ), 'class="has-text-align-center"' ),
			'a link color'                => array( array( 'style' => $link_color ), 'class="has-link-color"' ),
			'a text alignment and a link' => array(
				array(
					'textAlign' => 'right',
					'style'     => $link_color,
				),
				'class="has-text-align-right has-link-color"',
			),
			'another element'             => array( array( 'style' => array( 'elements' => array( 'heading' => array( 'color' => array( 'text' => '#ff0000' ) ) ) ) ), '' ),
		);
	}

	/**
	 * Tests that the title links to the home page.
	 *
	 * @ticket 66517
	 */
	public function test_should_link_the_title_to_the_home_page() {
		$this->go_to( '/?p=1' );

		$this->assertSame(
			'<h1 ><a href="' . home_url() . '" target="_self" rel="home">My Site</a></h1>',
			render_block_core_site_title( array( 'isLink' => true ) )
		);
	}

	/**
	 * Tests that the URL of the link is escaped.
	 *
	 * @ticket 66517
	 */
	public function test_should_escape_the_url_of_the_link() {
		add_filter(
			'home_url',
			static function () {
				return 'https://example.org/?a=1&b=2';
			}
		);

		$this->assertStringContainsString( ' href="https://example.org/?a=1&#038;b=2" ', render_block_core_site_title( array( 'isLink' => true ) ) );
	}

	/**
	 * Tests the target of the link.
	 *
	 * @ticket 66517
	 *
	 * @dataProvider data_link_targets
	 *
	 * @param array  $attributes Attributes of the block.
	 * @param string $expected   Expected target.
	 */
	public function test_should_use_the_link_target( $attributes, $expected ) {
		$this->go_to( '/?p=1' );

		$this->assertStringContainsString(
			' target="' . $expected . '" ',
			render_block_core_site_title( array_merge( array( 'isLink' => true ), $attributes ) )
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_link_targets() {
		return array(
			'no target'      => array( array(), '_self' ),
			'an empty value' => array( array( 'linkTarget' => '' ), '_self' ),
			'a blank target' => array( array( 'linkTarget' => '_blank' ), '_blank' ),
			'an escaped one' => array( array( 'linkTarget' => '"><script>' ), '&quot;&gt;&lt;script&gt;' ),
		);
	}

	/**
	 * Tests that the current page is marked on the front page only.
	 *
	 * @ticket 66517
	 */
	public function test_should_mark_the_front_page_as_the_current_page() {
		$this->go_to( home_url( '/' ) );

		$this->assertStringContainsString( ' rel="home" aria-current="page">', render_block_core_site_title( array( 'isLink' => true ) ), 'The front page should be marked.' );

		$this->go_to( '/?p=1' );

		$this->assertStringNotContainsString( 'aria-current', render_block_core_site_title( array( 'isLink' => true ) ), 'A single post should not be marked.' );
	}

	/**
	 * Tests that a paged front page is not marked as the current page.
	 *
	 * @ticket 66517
	 */
	public function test_should_not_mark_a_paged_front_page_as_the_current_page() {
		self::factory()->post->create_many( 3 );
		update_option( 'posts_per_page', 1 );

		$this->go_to( home_url( '/?paged=2' ) );

		$this->assertTrue( is_paged(), 'The page should be paged.' );
		$this->assertTrue( is_front_page(), 'The page should be the front page.' );
		$this->assertStringNotContainsString( 'aria-current', render_block_core_site_title( array( 'isLink' => true ) ) );
	}

	/**
	 * Tests the current page when the posts page is not the front page.
	 *
	 * @ticket 66517
	 */
	public function test_should_not_mark_the_posts_page_as_the_current_page_when_it_is_not_the_front_page() {
		$front_page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$posts_page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );

		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $front_page_id );
		update_option( 'page_for_posts', $posts_page_id );

		$this->go_to( get_permalink( $posts_page_id ) );

		$this->assertTrue( is_home(), 'The posts page should be the home.' );
		$this->assertStringNotContainsString( 'aria-current', render_block_core_site_title( array( 'isLink' => true ) ), 'The posts page should not be marked.' );

		$this->go_to( get_permalink( $front_page_id ) );

		$this->assertStringContainsString( 'aria-current="page"', render_block_core_site_title( array( 'isLink' => true ) ), 'The front page should be marked.' );
	}

	/**
	 * Tests that the title is not marked as the current page when it is not a link.
	 *
	 * @ticket 66517
	 */
	public function test_should_not_mark_a_title_that_is_not_a_link() {
		$this->go_to( home_url( '/' ) );

		$this->assertStringNotContainsString( 'aria-current', render_block_core_site_title( array( 'isLink' => false ) ) );
	}
}
