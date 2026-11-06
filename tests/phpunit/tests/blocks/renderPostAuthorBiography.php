<?php
/**
 * Tests for the server-side rendering of the Post Author Biography block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 * @group author
 *
 * @covers ::render_block_core_post_author_biography
 */
class Tests_Blocks_RenderPostAuthorBiography extends WP_UnitTestCase {

	/**
	 * Author of the posts, with a biography.
	 *
	 * @var int
	 */
	private static $author_id;

	/**
	 * ID of a post of the author.
	 *
	 * @var int
	 */
	private static $post_id;

	/**
	 * ID of a post of an author without a biography.
	 *
	 * @var int
	 */
	private static $post_without_bio_id;

	/**
	 * Original block to render of the block supports.
	 *
	 * @var array|null
	 */
	private $original_block_to_render;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$author_id           = $factory->user->create(
			array(
				'role'        => 'author',
				'description' => 'Writes about tests.',
			)
		);
		self::$post_id             = $factory->post->create( array( 'post_author' => self::$author_id ) );
		self::$post_without_bio_id = $factory->post->create( array( 'post_author' => $factory->user->create( array( 'role' => 'author' ) ) ) );
	}

	public function set_up() {
		parent::set_up();

		$this->original_block_to_render     = WP_Block_Supports::$block_to_render;
		WP_Block_Supports::$block_to_render = null;
	}

	public function tear_down() {
		WP_Block_Supports::$block_to_render = $this->original_block_to_render;

		parent::tear_down();
	}

	/**
	 * Renders the block.
	 *
	 * @param array $attributes Attributes of the block.
	 * @param array $context    Context of the block, with the post by default.
	 * @return string The rendered block.
	 */
	private function render( $attributes = array(), $context = null ) {
		$block = new WP_Block(
			array(
				'blockName'    => 'core/post-author-biography',
				'attrs'        => $attributes,
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);

		$block->context = null === $context ? array( 'postId' => self::$post_id ) : $context;

		return render_block_core_post_author_biography( $attributes, '', $block );
	}

	/**
	 * Tests the biography of the author of the post.
	 *
	 * @ticket 66540
	 */
	public function test_should_render_the_biography_of_the_author_of_the_post() {
		$this->assertSame( '<div >Writes about tests.</div>', $this->render() );
	}

	/**
	 * Tests that the author of the archive is used without a post.
	 *
	 * @ticket 66540
	 */
	public function test_should_use_the_author_of_the_archive_without_a_post() {
		$this->go_to( get_author_posts_url( self::$author_id ) );

		$this->assertSame( '<div >Writes about tests.</div>', $this->render( array(), array() ) );
	}

	/**
	 * Tests that nothing is rendered without an author or without a biography.
	 *
	 * @ticket 66540
	 */
	public function test_should_render_nothing_without_an_author_or_a_biography() {
		$this->go_to( home_url( '/' ) );

		$this->assertSame( '', $this->render( array(), array() ), 'There should be no author without a post or an archive.' );
		$this->assertSame( '', $this->render( array(), array( 'postId' => self::$post_without_bio_id ) ), 'There should be nothing without a biography.' );
	}

	/**
	 * Tests the class of the text alignment.
	 *
	 * @ticket 66540
	 *
	 * @dataProvider data_text_alignments
	 *
	 * @param array  $attributes Attributes of the block.
	 * @param string $expected   Expected start of the output.
	 */
	public function test_should_add_the_class_of_the_text_alignment( $attributes, $expected ) {
		$this->assertStringStartsWith( $expected, $this->render( $attributes ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_text_alignments() {
		return array(
			'no alignment'   => array( array(), '<div >' ),
			'an empty value' => array( array( 'textAlign' => '' ), '<div >' ),
			'center'         => array( array( 'textAlign' => 'center' ), '<div class="has-text-align-center">' ),
		);
	}

	/**
	 * Tests that the block is registered with the render callback.
	 *
	 * @ticket 66540
	 *
	 * @covers ::register_block_core_post_author_biography
	 */
	public function test_should_register_the_block_with_the_render_callback() {
		$registry       = WP_Block_Type_Registry::get_instance();
		$was_registered = $registry->is_registered( 'core/post-author-biography' );

		if ( $was_registered ) {
			unregister_block_type( 'core/post-author-biography' );
		}

		register_block_core_post_author_biography();
		$block_type = $registry->get_registered( 'core/post-author-biography' );

		if ( ! $was_registered ) {
			unregister_block_type( 'core/post-author-biography' );
		}

		$this->assertInstanceOf( WP_Block_Type::class, $block_type );
		$this->assertSame( 'render_block_core_post_author_biography', $block_type->render_callback );
	}
}
