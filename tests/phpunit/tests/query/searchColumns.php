<?php
/**
 * Testing the search columns support in `WP_Query`.
 *
 * @package WordPress\UnitTests
 * @since 6.2.0
 */

/**
 * Test cases for the search columns feature.
 *
 * @group query
 * @group search
 *
 * @covers WP_Query::parse_search
 *
 * @since 6.2.0
 */
class Tests_Query_SearchColumns extends WP_UnitTestCase {
	/**
	 * The post ID of the first fixture post.
	 *
	 * @since 6.2.0
	 * @var int $pid1
	 */
	protected static $pid1;

	/**
	 * The post ID of the second fixture post.
	 *
	 * @since 6.2.0
	 * @var int $pid2
	 */
	protected static $pid2;

	/**
	 * The post ID of the third fixture post.
	 *
	 * @since 6.2.0
	 * @var int $pid3
	 */
	protected static $pid3;

	/**
	 * The post ID of the fixture post used for slug search tests.
	 *
	 * @since 7.1.0
	 * @var int $pid_slug
	 */
	protected static $pid_slug;

	/**
	 * Create posts fixtures.
	 *
	 * @param WP_UnitTest_Factory $factory The factory instance.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$pid1 = $factory->post->create(
			array(
				'post_status'  => 'publish',
				'post_title'   => 'foo title',
				'post_excerpt' => 'foo excerpt',
				'post_content' => 'foo content',
			)
		);
		self::$pid2 = $factory->post->create(
			array(
				'post_status'  => 'publish',
				'post_title'   => 'bar title',
				'post_excerpt' => 'foo bar excerpt',
				'post_content' => 'foo bar content',
			)
		);

		self::$pid3 = $factory->post->create(
			array(
				'post_status'  => 'publish',
				'post_title'   => 'baz title',
				'post_excerpt' => 'baz bar excerpt',
				'post_content' => 'baz bar foo content',
			)
		);

		self::$pid_slug = $factory->post->create(
			array(
				'post_status'  => 'publish',
				'post_title'   => 'Taco',
				'post_name'    => 'burrito',
				'post_content' => 'Enchilada',
				'post_excerpt' => 'Torta',
			)
		);
	}

	/**
	 * Tests that search uses default search columns when search columns are empty.
	 *
	 * @ticket 43867
	 */
	public function test_s_should_use_default_search_columns_when_empty_search_columns() {
		$q = new WP_Query(
			array(
				's'              => 'foo',
				'search_columns' => array(),
				'fields'         => 'ids',
			)
		);

		$this->assertStringContainsString( 'post_title', $q->request, 'SQL request should contain post_title string.' );
		$this->assertStringContainsString( 'post_excerpt', $q->request, 'SQL request should contain post_excerpt string.' );
		$this->assertStringContainsString( 'post_content', $q->request, 'SQL request should contain post_content string.' );
		$this->assertSameSets( array( self::$pid1, self::$pid2, self::$pid3 ), $q->posts, 'Query results should be equal to the set.' );
	}

	/**
	 * Tests that search supports the `post_title` search column.
	 *
	 * @ticket 43867
	 */
	public function test_s_should_support_post_title_search_column() {
		$q = new WP_Query(
			array(
				's'              => 'foo',
				'search_columns' => array( 'post_title' ),
				'fields'         => 'ids',
			)
		);

		$this->assertSameSets( array( self::$pid1 ), $q->posts );
	}

	/**
	 * Tests that search supports the `post_excerpt` search column.
	 *
	 * @ticket 43867
	 */
	public function test_s_should_support_post_excerpt_search_column() {
		$q = new WP_Query(
			array(
				's'              => 'foo',
				'search_columns' => array( 'post_excerpt' ),
				'fields'         => 'ids',
			)
		);

		$this->assertSameSets( array( self::$pid1, self::$pid2 ), $q->posts );
	}

	/**
	 * Tests that search supports the `post_content` search column.
	 *
	 * @ticket 43867
	 */
	public function test_s_should_support_post_content_search_column() {
		$q = new WP_Query(
			array(
				's'              => 'foo',
				'search_columns' => array( 'post_content' ),
				'fields'         => 'ids',
			)
		);
		$this->assertSameSets( array( self::$pid1, self::$pid2, self::$pid3 ), $q->posts );
	}

	/**
	 * Tests that search supports the `post_title` and `post_excerpt` search columns together.
	 *
	 * @ticket 43867
	 */
	public function test_s_should_support_post_title_and_post_excerpt_search_columns() {
		$q = new WP_Query(
			array(
				's'              => 'foo',
				'search_columns' => array( 'post_title', 'post_excerpt' ),
				'fields'         => 'ids',
			)
		);

		$this->assertSameSets( array( self::$pid1, self::$pid2 ), $q->posts );
	}

	/**
	 * Tests that search supports the `post_title` and `post_content` search columns together.
	 *
	 * @ticket 43867
	 */
	public function test_s_should_support_post_title_and_post_content_search_columns() {
		$q = new WP_Query(
			array(
				's'              => 'foo',
				'search_columns' => array( 'post_title', 'post_content' ),
				'fields'         => 'ids',
			)
		);

		$this->assertSameSets( array( self::$pid1, self::$pid2, self::$pid3 ), $q->posts );
	}

	/**
	 * Tests that search supports the `post_excerpt` and `post_content` search columns together.
	 *
	 * @ticket 43867
	 */
	public function test_s_should_support_post_excerpt_and_post_content_search_columns() {
		$q = new WP_Query(
			array(
				's'              => 'foo',
				'search_columns' => array( 'post_excerpt', 'post_content' ),
				'fields'         => 'ids',
			)
		);

		$this->assertSameSets( array( self::$pid1, self::$pid2, self::$pid3 ), $q->posts );
	}

	/**
	 * Tests that search supports the `post_title`, `post_excerpt` and `post_content` search columns together.
	 *
	 * @ticket 43867
	 */
	public function test_s_should_support_post_title_and_post_excerpt_and_post_content_search_columns() {
		$q = new WP_Query(
			array(
				's'              => 'foo',
				'search_columns' => array( 'post_title', 'post_excerpt', 'post_content' ),
				'fields'         => 'ids',
			)
		);

		$this->assertSameSets( array( self::$pid1, self::$pid2, self::$pid3 ), $q->posts );
	}

	/**
	 * Tests that search uses default search columns when using a non-existing search column.
	 *
	 * @ticket 43867
	 */
	public function test_s_should_use_default_search_columns_when_using_non_existing_search_column() {
		$q = new WP_Query(
			array(
				's'              => 'foo',
				'search_columns' => array( 'post_non_existing_column' ),
				'fields'         => 'ids',
			)
		);

		$this->assertStringContainsString( 'post_title', $q->request, 'SQL request should contain post_title string.' );
		$this->assertStringContainsString( 'post_excerpt', $q->request, 'SQL request should contain post_excerpt string.' );
		$this->assertStringContainsString( 'post_content', $q->request, 'SQL request should contain post_content string.' );
		$this->assertSameSets( array( self::$pid1, self::$pid2, self::$pid3 ), $q->posts, 'Query results should be equal to the set.' );
	}

	/**
	 * Tests that search ignores a non-existing search column when used together with a supported one.
	 *
	 * @ticket 43867
	 */
	public function test_s_should_ignore_non_existing_search_column_when_used_with_supported_one() {
		$q = new WP_Query(
			array(
				's'              => 'foo',
				'search_columns' => array( 'post_title', 'post_non_existing_column' ),
				'fields'         => 'ids',
			)
		);

		$this->assertSameSets( array( self::$pid1 ), $q->posts );
	}

	/**
	 * Tests that search supports search columns when searching multiple terms.
	 *
	 * @ticket 43867
	 */
	public function test_s_should_support_search_columns_when_searching_multiple_terms() {
		$q = new WP_Query(
			array(
				's'              => 'foo bar',
				'search_columns' => array( 'post_content' ),
				'fields'         => 'ids',
			)
		);

		$this->assertSameSets( array( self::$pid2, self::$pid3 ), $q->posts );
	}

	/**
	 * Tests that search supports search columns when searching for a sentence.
	 *
	 * @ticket 43867
	 */
	public function test_s_should_support_search_columns_when_sentence_true() {
		$q = new WP_Query(
			array(
				's'              => 'bar foo',
				'search_columns' => array( 'post_content' ),
				'sentence'       => true,
				'fields'         => 'ids',
			)
		);

		$this->assertSameSets( array( self::$pid3 ), $q->posts );
	}

	/**
	 * Tests that search supports search columns when searching for a sentence.
	 *
	 * @ticket 43867
	 */
	public function test_s_should_support_search_columns_when_sentence_false() {
		$q = new WP_Query(
			array(
				's'              => 'bar foo',
				'search_columns' => array( 'post_content' ),
				'sentence'       => false,
				'fields'         => 'ids',
			)
		);

		$this->assertSameSets( array( self::$pid2, self::$pid3 ), $q->posts );
	}

	/**
	 * Tests that search supports search columns when using term exclusion.
	 *
	 * @ticket 43867
	 */
	public function test_s_should_support_search_columns_when_searching_with_term_exclusion() {
		$q = new WP_Query(
			array(
				's'              => 'bar -baz',
				'search_columns' => array( 'post_excerpt', 'post_content' ),
				'fields'         => 'ids',
			)
		);

		$this->assertSameSets( array( self::$pid2 ), $q->posts );
	}

	/**
	 * Tests that search columns is filterable with the `post_search_columns` filter.
	 *
	 * @ticket 43867
	 */
	public function test_search_columns_should_be_filterable() {
		add_filter( 'post_search_columns', array( $this, 'post_supported_search_column' ), 10, 3 );
		$q = new WP_Query(
			array(
				's'      => 'foo',
				'fields' => 'ids',
			)
		);

		$this->assertSameSets( array( self::$pid1 ), $q->posts );
	}

	/**
	 * Filter callback that sets a supported search column.
	 *
	 * @param  string[] $search_columns Array of column names to be searched.
	 * @param  string   $search         Text being searched.
	 * @param  WP_Query $wp_query       The current WP_Query instance.
	 * @return string[] $search_columns Array of column names to be searched.
	 */
	public function post_supported_search_column( $search_columns, $search, $wp_query ) {
		$search_columns = array( 'post_title' );
		return $search_columns;
	}

	/**
	 * Tests that search columns ignores non-supported search columns from the `post_search_columns` filter.
	 *
	 * @ticket 43867
	 * @ticket 20044
	 */
	public function test_search_columns_should_not_be_filterable_with_non_supported_search_columns() {
		add_filter( 'post_search_columns', array( $this, 'post_non_supported_search_column' ), 10, 3 );
		$q = new WP_Query(
			array(
				's'      => 'foo',
				'fields' => 'ids',
			)
		);

		$this->assertStringNotContainsString( 'post_author', $q->request, "SQL request shouldn't contain post_author string." );
		$this->assertSameSets( array( self::$pid1, self::$pid2, self::$pid3 ), $q->posts, 'Query results should be equal to the set.' );
	}

	/**
	 * Filter callback that sets an existing but non-supported search column.
	 *
	 * @param  string[] $search_columns Array of column names to be searched.
	 * @param  string   $search         Text being searched.
	 * @param  WP_Query $wp_query       The current WP_Query instance.
	 * @return string[] $search_columns Array of column names to be searched.
	 */
	public function post_non_supported_search_column( $search_columns, $search, $wp_query ) {
		$search_columns = array( 'post_author' );
		return $search_columns;
	}

	/**
	 * Tests that search columns ignores non-existing search columns from the `post_search_columns` filter.
	 *
	 * @ticket 43867
	 */
	public function test_search_columns_should_not_be_filterable_with_non_existing_search_column() {
		add_filter( 'post_search_columns', array( $this, 'post_non_existing_search_column' ), 10, 3 );
		$q = new WP_Query(
			array(
				's'      => 'foo',
				'fields' => 'ids',
			)
		);

		$this->assertStringNotContainsString( 'post_non_existing_column', $q->request, "SQL request shouldn't contain post_non_existing_column string." );
		$this->assertSameSets( array( self::$pid1, self::$pid2, self::$pid3 ), $q->posts, 'Query results should be equal to the set.' );
	}

	/**
	 * Filter callback that sets a non-existing search column.
	 *
	 * @param  string[] $search_columns Array of column names to be searched.
	 * @param  string   $search         Text being searched.
	 * @param  WP_Query $wp_query       The current WP_Query instance.
	 * @return string[] $search_columns Array of column names to be searched.
	 */
	public function post_non_existing_search_column( $search_columns, $search, $wp_query ) {
		$search_columns = array( 'post_non_existing_column' );
		return $search_columns;
	}

	/**
	 * Tests that `post_name` is not searched by default.
	 *
	 * @ticket 20044
	 */
	public function test_s_should_not_search_post_name_by_default() {
		$q = new WP_Query(
			array(
				's'      => 'burrito',
				'fields' => 'ids',
			)
		);

		$this->assertSame( array(), $q->posts );
	}

	/**
	 * Tests that search supports the `post_name` search column via the `post_search_columns` filter.
	 *
	 * @ticket 20044
	 */
	public function test_s_should_support_post_name_search_column_via_filter() {
		add_filter( 'post_search_columns', array( $this, 'filter_add_post_name_search_column' ) );
		$q = new WP_Query(
			array(
				's'      => 'burrito',
				'fields' => 'ids',
			)
		);
		remove_filter( 'post_search_columns', array( $this, 'filter_add_post_name_search_column' ) );

		$this->assertSame( array( self::$pid_slug ), $q->posts );
	}

	/**
	 * Tests that search supports the `post_name` search column via the `search_columns` query var.
	 *
	 * @ticket 20044
	 */
	public function test_s_should_support_post_name_search_column_via_query_var() {
		$q = new WP_Query(
			array(
				's'              => 'burrito',
				'fields'         => 'ids',
				'search_columns' => array( 'post_name' ),
			)
		);

		$this->assertSame( array( self::$pid_slug ), $q->posts );
	}

	/**
	 * Filter callback that adds `post_name` to the search columns.
	 *
	 * @param  string[] $search_columns Array of column names to be searched.
	 * @return string[] $search_columns Array of column names to be searched.
	 */
	public function filter_add_post_name_search_column( $search_columns ) {
		$search_columns[] = 'post_name';
		return $search_columns;
	}
}
