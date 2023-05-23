<?php

require_once __DIR__ . '/base.php';

/**
 * @group block-templates
 * @covers ::get_template_hierarchy
 */
class Tests_Block_Templates_GetTemplate_Hierarchy extends WP_Block_Templates_UnitTestCase {

	public function set_up() {
		parent::set_up();
		register_post_type(
			'custom_book',
			array(
				'public'       => true,
				'show_in_rest' => true,
			)
		);
		register_taxonomy( 'book_type', 'custom_book' );
		register_taxonomy( 'books', 'custom_book' );
	}

	public function tear_down() {
		unregister_post_type( 'custom_book' );
		unregister_taxonomy( 'book_type' );
		unregister_taxonomy( 'books' );
		parent::tear_down();
	}

	/**
	 * @dataProvider data_get_template_hierarchy
	 *
	 * @ticket 56467
	 *
	 * @param array $args     Test arguments.
	 * @param array $expected Expected results.
	 */
	public function test_get_template_hierarchy( array $args, array $expected ) {
		$this->assertSame( $expected, get_template_hierarchy( ...$args ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_get_template_hierarchy() {
		return array(
			'front-page'                               => array(
				'args'     => array( 'front-page' ),
				'expected' => array( 'front-page', 'home', 'index' ),
			),
			'custom template'                          => array(
				'args'     => array( 'whatever-slug', true ),
				'expected' => array( 'page', 'singular', 'index' ),
			),
			'page'                                     => array(
				'args'     => array( 'page' ),
				'expected' => array( 'page', 'singular', 'index' ),
			),
			'tag'                                      => array(
				'args'     => array( 'tag' ),
				'expected' => array( 'tag', 'archive', 'index' ),
			),
			'author'                                   => array(
				'args'     => array( 'author' ),
				'expected' => array( 'author', 'archive', 'index' ),
			),
			'date'                                     => array(
				'args'     => array( 'date' ),
				'expected' => array( 'date', 'archive', 'index' ),
			),
			'taxonomy'                                 => array(
				'args'     => array( 'taxonomy' ),
				'expected' => array( 'taxonomy', 'archive', 'index' ),
			),
			'attachment'                               => array(
				'args'     => array( 'attachment' ),
				'expected' => array( 'attachment', 'single', 'singular', 'index' ),
			),
			'singular'                                 => array(
				'args'     => array( 'singular' ),
				'expected' => array( 'singular', 'index' ),
			),
			'single'                                   => array(
				'args'     => array( 'single' ),
				'expected' => array( 'single', 'singular', 'index' ),
			),
			'archive'                                  => array(
				'args'     => array( 'archive' ),
				'expected' => array( 'archive', 'index' ),
			),
			'index'                                    => array(
				'args'     => array( 'index' ),
				'expected' => array( 'index' ),
			),
			'specific taxonomies'                      => array(
				'args'     => array( 'taxonomy-books', false, 'taxonomy-books' ),
				'expected' => array( 'taxonomy-books', 'taxonomy', 'archive', 'index' ),
			),
			'single word categories'                   => array(
				'args'     => array( 'category-fruits', false, 'category' ),
				'expected' => array( 'category-fruits', 'category', 'archive', 'index' ),
			),
			'single word categories no prefix'         => array(
				'args'     => array( 'category-fruits', false ),
				'expected' => array( 'category-fruits', 'category', 'archive', 'index' ),
			),
			'multi word categories'                    => array(
				'args'     => array( 'category-fruits-yellow', false, 'category' ),
				'expected' => array( 'category-fruits-yellow', 'category', 'archive', 'index' ),
			),
			'multi word categories no prefix'          => array(
				'args'     => array( 'category-fruits-yellow', false ),
				'expected' => array( 'category-fruits-yellow', 'category', 'archive', 'index' ),
			),
			'single word taxonomy and term'            => array(
				'args'     => array( 'taxonomy-books-action', false, 'taxonomy-books' ),
				'expected' => array( 'taxonomy-books-action', 'taxonomy-books', 'taxonomy', 'archive', 'index' ),
			),
			'single word taxonomy and term no prefix'  => array(
				'args'     => array( 'taxonomy-books-action', false ),
				'expected' => array( 'taxonomy-books-action', 'taxonomy-books', 'taxonomy', 'archive', 'index' ),
			),
			'single word taxonomy and multi word term' => array(
				'args'     => array( 'taxonomy-books-action-adventure', false, 'taxonomy-books' ),
				'expected' => array( 'taxonomy-books-action-adventure', 'taxonomy-books', 'taxonomy', 'archive', 'index' ),
			),
			'multi word taxonomy and term'             => array(
				'args'     => array( 'taxonomy-greek-books-action-adventure', false, 'taxonomy-greek-books' ),
				'expected' => array( 'taxonomy-greek-books-action-adventure', 'taxonomy-greek-books', 'taxonomy', 'archive', 'index' ),
			),
			'single word post type'                    => array(
				'args'     => array( 'single-book', false, 'single-book' ),
				'expected' => array( 'single-book', 'single', 'singular', 'index' ),
			),
			'multi word post type'                     => array(
				'args'     => array( 'single-art-project', false, 'single-art-project' ),
				'expected' => array( 'single-art-project', 'single', 'singular', 'index' ),
			),
			'single post with multi word post type'    => array(
				'args'     => array( 'single-art-project-imagine', false, 'single-art-project' ),
				'expected' => array( 'single-art-project-imagine', 'single-art-project', 'single', 'singular', 'index' ),
			),
			'single page'                              => array(
				'args'     => array( 'page-hi', false, 'page' ),
				'expected' => array( 'page-hi', 'page', 'singular', 'index' ),
			),
			'authors'                                  => array(
				'args'     => array( 'author-rigas', false, 'author' ),
				'expected' => array( 'author-rigas', 'author', 'archive', 'index' ),
			),
			'multiple word taxonomy no prefix'         => array(
				'args'     => array( 'taxonomy-book_type-adventure', false ),
				'expected' => array( 'taxonomy-book_type-adventure', 'taxonomy-book_type', 'taxonomy', 'archive', 'index' ),
			),
			'single post type no prefix'               => array(
				'args'     => array( 'single-custom_book', false ),
				'expected' => array(
					'single-custom_book',
					'single',
					'singular',
					'index',
				),
			),
			'single post and post type no prefix'      => array(
				'args'     => array( 'single-custom_book-book-1', false ),
				'expected' => array(
					'single-custom_book-book-1',
					'single-custom_book',
					'single',
					'singular',
					'index',
				),
			),
			'page no prefix'                           => array(
				'args'     => array( 'page-hi', false ),
				'expected' => array(
					'page-hi',
					'page',
					'singular',
					'index',
				),
			),
			'post type archive no prefix'              => array(
				'args'     => array( 'archive-book', false ),
				'expected' => array(
					'archive-book',
					'archive',
					'index',
				),
			),
		);
	}
}
