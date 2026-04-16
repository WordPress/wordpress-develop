<?php

/**
 * @group general
 * @group template
 * @covers ::paginate_links
 */
class Tests_General_PaginateLinks extends WP_UnitTestCase {

	private int $i18n_count = 0;

	/**
	 * Set up shared fixtures.
	 *
	 * @param WP_UnitTest_Factory $factory Factory instance.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void {
		$category_id = $factory->term->create(
			array(
				'taxonomy' => 'category',
				'name'     => 'Categorized',
			)
		);
		self::assertIsInt( $category_id );

		$post_ids = $factory->post->create_many( 10 );
		foreach ( $post_ids as $post_id ) {
			self::assertIsInt( $post_id );
			self::assertIsArray( wp_set_post_categories( $post_id, array( $category_id ) ) );
		}
	}

	public function set_up() {
		parent::set_up();

		$this->go_to( home_url( '/' ) );
	}

	public function test_defaults() {
		$page2  = get_pagenum_link( 2 );
		$page3  = get_pagenum_link( 3 );
		$page50 = get_pagenum_link( 50 );

		$expected = <<<EXPECTED
<span aria-current="page" class="page-numbers current">1</span>
<a class="page-numbers" href="$page2">2</a>
<a class="page-numbers" href="$page3">3</a>
<span class="page-numbers dots">&hellip;</span>
<a class="page-numbers" href="$page50">50</a>
<a class="next page-numbers" href="$page2">Next &raquo;</a>
EXPECTED;

		$links = paginate_links( array( 'total' => 50 ) );
		$this->assertSameIgnoreEOL( $expected, $links );
	}

	/**
	 * Test the format parameter behaves as expected.
	 *
	 * @dataProvider data_format
	 *
	 * @param string $format Format to test.
	 * @param string $page2  Expected URL for page 2.
	 * @param string $page3  Expected URL for page 3.
	 * @param string $page50 Expected URL for page 50.
	 */
	public function test_format( $format, $page2, $page3, $page50 ) {
		$expected = <<<EXPECTED
<span aria-current="page" class="page-numbers current">1</span>
<a class="page-numbers" href="$page2">2</a>
<a class="page-numbers" href="$page3">3</a>
<span class="page-numbers dots">&hellip;</span>
<a class="page-numbers" href="$page50">50</a>
<a class="next page-numbers" href="$page2">Next &raquo;</a>
EXPECTED;

		$links = paginate_links(
			array(
				'total'  => 50,
				'format' => $format,
			)
		);
		$this->assertSameIgnoreEOL( $expected, $links );
	}

	/**
	 * Data provider for test_format.
	 *
	 * @return array[] Data provider.
	 */
	public function data_format() {
		return array(
			'pretty permalinks'                => array( 'page/%#%/', home_url( '/page/2/' ), home_url( '/page/3/' ), home_url( '/page/50/' ) ),
			'plain permalinks'                 => array( '?page=%#%', home_url( '/?page=2' ), home_url( '/?page=3' ), home_url( '/?page=50' ) ),
			'custom format - html extension'   => array( 'page/%#%.html', home_url( '/page/2.html' ), home_url( '/page/3.html' ), home_url( '/page/50.html' ) ),
			'custom format - hyphen separated' => array( 'page-%#%', home_url( '/page-2' ), home_url( '/page-3' ), home_url( '/page-50' ) ),
			'custom format - fragment'         => array( '#%#%', home_url( '/#2' ), home_url( '/#3' ), home_url( '/#50' ) ),
		);
	}

	public function test_prev_next_false() {
		$home   = home_url( '/' );
		$page3  = get_pagenum_link( 3 );
		$page4  = get_pagenum_link( 4 );
		$page50 = get_pagenum_link( 50 );

		$expected = <<<EXPECTED
<a class="page-numbers" href="$home">1</a>
<span aria-current="page" class="page-numbers current">2</span>
<a class="page-numbers" href="$page3">3</a>
<a class="page-numbers" href="$page4">4</a>
<span class="page-numbers dots">&hellip;</span>
<a class="page-numbers" href="$page50">50</a>
EXPECTED;

		$links = paginate_links(
			array(
				'total'     => 50,
				'prev_next' => false,
				'current'   => 2,
			)
		);
		$this->assertSameIgnoreEOL( $expected, $links );
	}

	public function test_prev_next_true() {
		$home   = home_url( '/' );
		$page3  = get_pagenum_link( 3 );
		$page4  = get_pagenum_link( 4 );
		$page50 = get_pagenum_link( 50 );

		$expected = <<<EXPECTED
<a class="prev page-numbers" href="$home">&laquo; Previous</a>
<a class="page-numbers" href="$home">1</a>
<span aria-current="page" class="page-numbers current">2</span>
<a class="page-numbers" href="$page3">3</a>
<a class="page-numbers" href="$page4">4</a>
<span class="page-numbers dots">&hellip;</span>
<a class="page-numbers" href="$page50">50</a>
<a class="next page-numbers" href="$page3">Next &raquo;</a>
EXPECTED;

		$links = paginate_links(
			array(
				'total'     => 50,
				'prev_next' => true,
				'current'   => 2,
			)
		);
		$this->assertSameIgnoreEOL( $expected, $links );
	}

	public function increment_i18n_count() {
		$this->i18n_count += 1;
	}

	/**
	 * @ticket 25735
	 */
	public function test_paginate_links_number_format() {
		$this->i18n_count = 0;
		add_filter( 'number_format_i18n', array( $this, 'increment_i18n_count' ) );
		paginate_links(
			array(
				'total'     => 100,
				'current'   => 50,
				'show_all'  => false,
				'prev_next' => true,
				'end_size'  => 1,
				'mid_size'  => 1,
			)
		);
		// The links should be:
		// < Previous 1 ... 49 50 51 ... 100 Next >
		$this->assertSame( 5, $this->i18n_count );
		remove_filter( 'number_format_i18n', array( $this, 'increment_i18n_count' ) );
	}

	/**
	 * @ticket 24606
	 */
	public function test_paginate_links_base_value() {

		// Current page: 2.
		$links = paginate_links(
			array(
				'current'  => 2,
				'total'    => 5,
				'end_size' => 1,
				'mid_size' => 1,
				'type'     => 'array',
			)
		);

		$expected_attributes = array(
			array(
				'href'  => home_url( '/' ),
				'class' => 'prev page-numbers',
			),
			array(
				'href'  => home_url( '/' ),
				'class' => 'page-numbers',
			),
		);

		$document                     = new DOMDocument();
		$document->preserveWhiteSpace = false;

		// The first two links should link to page 1.
		foreach ( $expected_attributes as $link_idx => $attributes ) {

			$document->loadHTML( $links[ $link_idx ] );
			$tag = $document->getElementsByTagName( 'a' )->item( 0 );

			$this->assertNotNull( $tag );

			$href  = $tag->attributes->getNamedItem( 'href' )->value;
			$class = $tag->attributes->getNamedItem( 'class' )->value;

			$this->assertSame( $attributes['href'], $href );
			$this->assertSame( $attributes['class'], $class );
		}

		// Current page: 1.
		$links = paginate_links(
			array(
				'current'  => 1,
				'total'    => 5,
				'end_size' => 1,
				'mid_size' => 1,
				'type'     => 'array',
			)
		);

		$document->loadHTML( $links[0] );
		$tag = $document->getElementsByTagName( 'span' )->item( 0 );
		$this->assertNotNull( $tag );

		$class = $tag->attributes->getNamedItem( 'class' )->value;
		$this->assertSame( 'page-numbers current', $class );

		$document->loadHTML( $links[1] );
		$tag = $document->getElementsByTagName( 'a' )->item( 0 );
		$this->assertNotNull( $tag );

		$href = $tag->attributes->getNamedItem( 'href' )->value;
		$this->assertSame( get_pagenum_link( 2 ), $href );
	}

	public function add_query_arg( $url ) {
		return add_query_arg(
			array(
				'foo' => 'bar',
				's'   => 'search+term',
			),
			$url
		);
	}

	/**
	 * @ticket 29636
	 */
	public function test_paginate_links_query_args() {
		add_filter( 'get_pagenum_link', array( $this, 'add_query_arg' ) );
		$links = paginate_links(
			array(
				'current'  => 2,
				'total'    => 5,
				'end_size' => 1,
				'mid_size' => 1,
				'type'     => 'array',
			)
		);
		remove_filter( 'get_pagenum_link', array( $this, 'add_query_arg' ) );

		$document                     = new DOMDocument();
		$document->preserveWhiteSpace = false;

		// All links should have foo=bar arguments and be escaped:
		$data = array(
			0 => home_url( '/?foo=bar&s=search+term' ),
			1 => home_url( '/?foo=bar&s=search+term' ),
			3 => home_url( '/?paged=3&foo=bar&s=search+term' ),
			5 => home_url( '/?paged=5&foo=bar&s=search+term' ),
			6 => home_url( '/?paged=3&foo=bar&s=search+term' ),
		);

		foreach ( $data as $index => $expected_href ) {
			$document->loadHTML( $links[ $index ] );
			$tag = $document->getElementsByTagName( 'a' )->item( 0 );
			$this->assertNotNull( $tag );

			$href = $tag->attributes->getNamedItem( 'href' )->value;
			$this->assertSame( $expected_href, $href );
		}
	}

	/**
	 * @ticket 30831
	 */
	public function test_paginate_links_with_custom_query_args() {
		add_filter( 'get_pagenum_link', array( $this, 'add_query_arg' ) );
		$links = paginate_links(
			array(
				'current'  => 2,
				'total'    => 5,
				'end_size' => 1,
				'mid_size' => 1,
				'type'     => 'array',
				'add_args' => array(
					'baz' => 'qux',
				),
			)
		);
		remove_filter( 'get_pagenum_link', array( $this, 'add_query_arg' ) );

		$document                     = new DOMDocument();
		$document->preserveWhiteSpace = false;

		$data = array(
			0 => home_url( '/?baz=qux&foo=bar&s=search+term' ),
			1 => home_url( '/?baz=qux&foo=bar&s=search+term' ),
			3 => home_url( '/?paged=3&baz=qux&foo=bar&s=search+term' ),
			5 => home_url( '/?paged=5&baz=qux&foo=bar&s=search+term' ),
			6 => home_url( '/?paged=3&baz=qux&foo=bar&s=search+term' ),
		);

		foreach ( $data as $index => $expected_href ) {
			$document->loadHTML( $links[ $index ] );
			$tag = $document->getElementsByTagName( 'a' )->item( 0 );
			$this->assertNotNull( $tag );

			$href = $tag->attributes->getNamedItem( 'href' )->value;
			$this->assertSame( $expected_href, $href );
		}
	}

	/**
	 * @ticket 30831
	 */
	public function test_paginate_links_should_allow_non_default_format_without_add_args() {
		// Fake the query params.
		$request_uri            = $_SERVER['REQUEST_URI'];
		$_SERVER['REQUEST_URI'] = add_query_arg( 'foo', 3, home_url() );

		$links = paginate_links(
			array(
				'base'    => add_query_arg( 'foo', '%#%' ),
				'format'  => '',
				'total'   => 5,
				'current' => 3,
				'type'    => 'array',
			)
		);

		$this->assertStringContainsString( '?foo=1', $links[1] );
		$this->assertStringContainsString( '?foo=2', $links[2] );
		$this->assertStringContainsString( '?foo=4', $links[4] );
		$this->assertStringContainsString( '?foo=5', $links[5] );

		$_SERVER['REQUEST_URI'] = $request_uri;
	}

	/**
	 * @ticket 30831
	 */
	public function test_paginate_links_should_allow_add_args_to_be_bool_false() {
		// Fake the query params.
		$request_uri            = $_SERVER['REQUEST_URI'];
		$_SERVER['REQUEST_URI'] = add_query_arg( 'foo', 3, home_url() );

		$links = paginate_links(
			array(
				'add_args' => false,
				'base'     => add_query_arg( 'foo', '%#%' ),
				'format'   => '',
				'total'    => 5,
				'current'  => 3,
				'type'     => 'array',
			)
		);

		$this->assertContains( '<span aria-current="page" class="page-numbers current">3</span>', $links );
	}

	/**
	 * @ticket 31939
	 */
	public function test_custom_base_query_arg_should_be_stripped_from_current_url_before_generating_pag_links() {
		// Fake the current URL: example.com?foo.
		$request_uri            = $_SERVER['REQUEST_URI'];
		$_SERVER['REQUEST_URI'] = add_query_arg( 'foo', '', $request_uri );

		$links = paginate_links(
			array(
				'base'    => add_query_arg( 'foo', '%_%', home_url() ),
				'format'  => '%#%',
				'total'   => 5,
				'current' => 1,
				'type'    => 'array',
			)
		);

		$page_2_url = home_url() . '?foo=2';
		$this->assertContains( "<a class=\"page-numbers\" href=\"$page_2_url\">2</a>", $links );
	}

	/**
	 * Ensures pagination links include trailing slashes when the permalink structure includes them.
	 *
	 * @ticket 61393
	 */
	public function test_permalinks_with_trailing_slash_produce_links_with_trailing_slashes(): void {
		update_option( 'posts_per_page', 2 );
		$this->set_permalink_structure( '/%postname%/' );

		$this->go_to( '/category/categorized/page/2/' );

		// `current` needs to be passed as it's not picked up from the query vars set by `go_to()` above.
		$links = paginate_links( array( 'current' => 2 ) );

		$processor   = new WP_HTML_Tag_Processor( $links );
		$found_links = 0;
		while ( $processor->next_tag( 'A' ) ) {
			++$found_links;
			$href = (string) $processor->get_attribute( 'href' );
			$this->assertStringEndsWith( '/', $href, "Pagination links should end with a trailing slash, found: $href" );
		}
		$this->assertGreaterThan( 0, $found_links, 'There should be pagination links found.' );
	}

	/**
	 * Ensures pagination links do not include trailing slashes when the permalink structure doesn't include them.
	 *
	 * @ticket 61393
	 */
	public function test_permalinks_without_trailing_slash_produce_links_without_trailing_slashes(): void {
		update_option( 'posts_per_page', 2 );
		$this->set_permalink_structure( '/%postname%' );

		$this->go_to( '/category/categorized/page/2' );

		// `current` needs to be passed as it's not picked up from the query vars set by `go_to()` above.
		$links = paginate_links( array( 'current' => 2 ) );

		$processor   = new WP_HTML_Tag_Processor( $links );
		$found_links = 0;
		while ( $processor->next_tag( 'A' ) ) {
			++$found_links;
			$href = (string) $processor->get_attribute( 'href' );
			$this->assertStringEndsNotWith( '/', $href, "Pagination links should not end with a trailing slash, found: $href" );
		}
		$this->assertGreaterThan( 0, $found_links, 'There should be pagination links found.' );
	}

	/**
	 * Ensures pagination links do not include trailing slashes when the permalink structure is plain.
	 *
	 * @ticket 61393
	 */
	public function test_plain_permalinks_are_not_modified_with_trailing_slash(): void {
		update_option( 'posts_per_page', 2 );
		$this->set_permalink_structure( '' );

		$term = get_category_by_slug( 'categorized' );
		$this->assertInstanceOf( WP_Term::class, $term );
		$category_id = $term->term_id;
		$this->go_to( "/?cat={$category_id}&paged=2" );

		// `current` needs to be passed as it's not picked up from the query vars set by `go_to()` above.
		$links = paginate_links( array( 'current' => 2 ) );

		$expected_links = array(
			home_url( "?cat={$category_id}" ), // Previous
			home_url( "?cat={$category_id}" ), // Page 1
			home_url( "?paged=3&cat={$category_id}" ), // Page 3
			home_url( "?paged=4&cat={$category_id}" ), // Page 4
			home_url( "?paged=5&cat={$category_id}" ), // Page 5
			home_url( "?paged=3&cat={$category_id}" ), // Next
		);

		$processor   = new WP_HTML_Tag_Processor( $links );
		$found_links = 0;
		while ( $processor->next_tag( 'A' ) ) {
			$expected_link = $expected_links[ $found_links ] ?? '';
			++$found_links;
			$href = (string) $processor->get_attribute( 'href' );
			$this->assertSame( $expected_link, $href, "Pagination links should include the category query string, found: $href" );
		}
		$this->assertSame( count( $expected_links ), $found_links, 'There should be this number of pagination links found.' );
	}

	/**
	 * Ensures the pagination links do not modify query strings (permalinks with trailing slash).
	 *
	 * @ticket 61393
	 * @ticket 63123
	 *
	 * @dataProvider data_query_strings
	 *
	 * @param string $query_string Query string.
	 */
	public function test_permalinks_with_trailing_slash_do_not_modify_query_strings( string $query_string ): void {
		update_option( 'posts_per_page', 2 );
		$this->set_permalink_structure( '/%postname%/' );

		$this->go_to( "/page/2/?{$query_string}" );

		// `current` needs to be passed as it's not picked up from the query vars set by `go_to()` above.
		$links = paginate_links( array( 'current' => 2 ) );

		$processor   = new WP_HTML_Tag_Processor( $links );
		$found_links = 0;
		while ( $processor->next_tag( 'A' ) ) {
			++$found_links;
			$href = (string) $processor->get_attribute( 'href' );
			$this->assertStringEndsWith( "/?{$query_string}", $href, "Pagination links should not modify the query string, found: $href" );
		}
		$this->assertGreaterThan( 0, $found_links, 'There should be pagination links found.' );
	}

	/**
	 * Ensures the pagination links do not modify query strings (permalinks without trailing slash).
	 *
	 * @ticket 61393
	 * @ticket 63123
	 *
	 * @dataProvider data_query_strings
	 *
	 * @param string $query_string Query string.
	 */
	public function test_permalinks_without_trailing_slash_do_not_modify_query_strings( string $query_string ): void {
		update_option( 'posts_per_page', 2 );
		$this->set_permalink_structure( '/%postname%' );

		$this->go_to( "/page/2?{$query_string}" );

		// `current` needs to be passed as it's not picked up from the query vars set by `go_to()` above.
		$links = paginate_links( array( 'current' => 2 ) );

		$processor   = new WP_HTML_Tag_Processor( $links );
		$found_links = 0;
		while ( $processor->next_tag( 'A' ) ) {
			++$found_links;
			$href = (string) $processor->get_attribute( 'href' );
			$this->assertStringEndsWith( "?{$query_string}", $href, "Pagination links should not modify the query string, found: $href" );
			$this->assertStringEndsNotWith( "/?{$query_string}", $href, "Pagination links should not be slashed before the query string, found: $href" );
		}
		$this->assertGreaterThan( 0, $found_links, 'There should be pagination links found.' );
	}

	/**
	 * Data provider.
	 *
	 * @see self::test_permalinks_without_trailing_slash_do_not_modify_query_strings()
	 * @see self::test_permalinks_with_trailing_slash_do_not_modify_query_strings()
	 *
	 * @return array<string, array{ 0: string }> Data provider.
	 */
	public function data_query_strings(): array {
		return array(
			'single query var' => array( 'foo=bar' ),
			'multi query vars' => array( 'foo=bar&pen=pencil' ),
		);
	}
}
