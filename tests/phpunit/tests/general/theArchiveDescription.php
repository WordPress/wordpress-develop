<?php

/**
 * Tests for `the_archive_description()` and `get_the_archive_description()`.
 *
 * @group general
 * @group template
 */
class Tests_General_TheArchiveDescription extends WP_UnitTestCase {

	const CPT_NAME = 'archive_cpt';

	public function set_up() {
		parent::set_up();

		register_post_type(
			self::CPT_NAME,
			array(
				'public'      => true,
				'has_archive' => true,
				'description' => 'Test CPT Archive description.',
			)
		);
	}

	/**
	 * Tests get_the_archive_description() on author archives.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_the_archive_description
	 */
	public function test_get_the_archive_description_on_author_archive() {
		$author_id = self::factory()->user->create(
			array(
				'description' => 'Author biography test.',
			)
		);

		$this->go_to( get_author_posts_url( $author_id ) );

		$this->assertTrue( is_author() );
		$this->assertSame( 'Author biography test.', get_the_archive_description() );
	}

	/**
	 * Tests get_the_archive_description() on post type archives.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_the_archive_description
	 */
	public function test_get_the_archive_description_on_post_type_archive() {
		$this->go_to( get_post_type_archive_link( self::CPT_NAME ) );

		$this->assertTrue( is_post_type_archive( self::CPT_NAME ) );
		$this->assertSame( "<p>Test CPT Archive description.</p>\n", get_the_archive_description() );
	}

	/**
	 * Tests get_the_archive_description() on term/category archives.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_the_archive_description
	 */
	public function test_get_the_archive_description_on_term_archive() {
		$term_id = self::factory()->category->create(
			array(
				'description' => 'Category archive description.',
			)
		);

		$this->go_to( get_category_link( $term_id ) );

		$this->assertTrue( is_category() );
		$this->assertSame( "<p>Category archive description.</p>\n", get_the_archive_description() );
	}

	/**
	 * Tests that the 'get_the_archive_description' filter is applied.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_the_archive_description
	 */
	public function test_get_the_archive_description_filter() {
		$author_id = self::factory()->user->create(
			array(
				'description' => 'Author bio.',
			)
		);

		$this->go_to( get_author_posts_url( $author_id ) );

		add_filter(
			'get_the_archive_description',
			static function ( $description ) {
				return 'Filtered: ' . $description;
			}
		);

		$this->assertSame( 'Filtered: Author bio.', get_the_archive_description() );
	}

	/**
	 * Tests that the_archive_description() echoes the description with before and after strings.
	 *
	 * @ticket 65819
	 *
	 * @covers ::the_archive_description
	 */
	public function test_the_archive_description_echoes_with_before_and_after() {
		$author_id = self::factory()->user->create(
			array(
				'description' => 'Author description.',
			)
		);

		$this->go_to( get_author_posts_url( $author_id ) );

		$this->expectOutputString( '<div class="archive-description">Author description.</div>' );
		the_archive_description( '<div class="archive-description">', '</div>' );
	}

	/**
	 * Tests that the_archive_description() echoes nothing when there is no description.
	 *
	 * @ticket 65819
	 *
	 * @covers ::the_archive_description
	 */
	public function test_the_archive_description_echoes_nothing_when_empty() {
		$author_id = self::factory()->user->create(
			array(
				'description' => '',
			)
		);

		$this->go_to( get_author_posts_url( $author_id ) );

		$this->expectOutputString( '' );
		the_archive_description( '<div class="archive-description">', '</div>' );
	}
}
