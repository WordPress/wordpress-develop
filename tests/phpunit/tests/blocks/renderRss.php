<?php
/**
 * Tests for the server-side rendering of the RSS block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 * @group feed
 *
 * @covers ::render_block_core_rss
 */
class Tests_Blocks_RenderRss extends WP_UnitTestCase {

	const FEED_URL = 'https://feeds.example.net/news/';

	/**
	 * Original block to render of the block supports.
	 *
	 * @var array|null
	 */
	private $original_block_to_render;

	/**
	 * Body of the feed that is returned for the requests.
	 *
	 * @var string
	 */
	private $feed_body = '';

	public function set_up() {
		parent::set_up();

		$this->original_block_to_render     = WP_Block_Supports::$block_to_render;
		WP_Block_Supports::$block_to_render = null;

		update_option( 'gmt_offset', 0 );
		update_option( 'timezone_string', '' );
		update_option( 'date_format', 'Y-m-d' );

		add_filter(
			'pre_http_request',
			function ( $response, $args, $url ) {
				if ( 0 !== strpos( $url, 'https://feeds.example.net/' ) ) {
					return $response;
				}

				return array(
					'headers'  => array( 'content-type' => 'application/rss+xml' ),
					'body'     => $this->feed_body,
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'cookies'  => array(),
					'filename' => null,
				);
			},
			10,
			3
		);
	}

	public function tear_down() {
		WP_Block_Supports::$block_to_render = $this->original_block_to_render;

		parent::tear_down();
	}

	/**
	 * Builds the body of a feed.
	 *
	 * @param string[] $items XML of the items.
	 * @return string The feed.
	 */
	private function build_feed( $items ) {
		return '<?xml version="1.0" encoding="UTF-8"?>'
			. '<rss version="2.0" xmlns:dc="http://purl.org/dc/elements/1.1/"><channel>'
			. '<title>Feed</title><link>https://feeds.example.net/</link><description>The feed</description>'
			. implode( '', $items )
			. '</channel></rss>';
	}

	/**
	 * Builds the XML of an item.
	 *
	 * @param array $fields Fields of the item, as XML, keyed by tag name.
	 * @return string The XML.
	 */
	private function build_item( $fields ) {
		$xml = '';

		// An item without a link is not used, because it passes null to esc_url().
		if ( ! isset( $fields['link'] ) ) {
			$fields['link'] = 'https://example.com/default/';
		}

		foreach ( $fields as $tag => $value ) {
			$xml .= "<{$tag}>{$value}</{$tag}>";
		}

		return '<item>' . $xml . '</item>';
	}

	/**
	 * Renders the block with the defaults of the block, and the attributes that are passed.
	 *
	 * @param array  $items      XML of the items of the feed.
	 * @param array  $attributes Attributes of the block.
	 * @param string $feed_url   URL of the feed.
	 * @return string The rendered block.
	 */
	private function render( $items, $attributes = array(), $feed_url = self::FEED_URL ) {
		$this->feed_body = $this->build_feed( $items );

		return render_block_core_rss(
			array_merge(
				array(
					'feedURL'        => $feed_url,
					'itemsToShow'    => 5,
					'displayExcerpt' => false,
					'displayAuthor'  => false,
					'displayDate'    => false,
					'excerptLength'  => 55,
					'blockLayout'    => 'list',
					'columns'        => 2,
				),
				$attributes
			)
		);
	}

	/**
	 * Tests that nothing is rendered without a valid feed URL.
	 *
	 * @ticket 66525
	 *
	 * @dataProvider data_attributes_without_a_feed_url
	 *
	 * @param array $attributes Attributes of the block.
	 */
	public function test_should_render_nothing_without_a_feed_url( $attributes ) {
		$this->assertSame( '', render_block_core_rss( $attributes ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_attributes_without_a_feed_url() {
		return array(
			'no attributes' => array( array() ),
			'a null URL'    => array( array( 'feedURL' => null ) ),
			'an integer'    => array( array( 'feedURL' => 123 ) ),
			'an array'      => array( array( 'feedURL' => array( 'https://example.com/feed/' ) ) ),
			'false'         => array( array( 'feedURL' => false ) ),
		);
	}

	/**
	 * Tests that a feed of this site is not supported.
	 *
	 * @ticket 66525
	 *
	 * @dataProvider data_feed_urls_of_the_site
	 *
	 * @param string $function_name Name of the function that returns the URL.
	 * @param string $suffix   Suffix to add to the URL.
	 */
	public function test_should_render_an_error_for_a_feed_of_the_site( $function_name, $suffix ) {
		$output = render_block_core_rss( array( 'feedURL' => $function_name() . $suffix ) );

		$this->assertStringStartsWith( '<div class="components-placeholder"><div class="notice notice-error">', $output );
		$this->assertStringContainsString( 'Adding an RSS feed to this site’s homepage is not supported', $output );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_feed_urls_of_the_site() {
		return array(
			'the home URL'              => array( 'home_url', '' ),
			'the home URL with a slash' => array( 'home_url', '/' ),
			'the site URL'              => array( 'site_url', '' ),
			'the site URL with a slash' => array( 'site_url', '/' ),
		);
	}

	/**
	 * Tests that the home URL and the site URL are both blocked when they are different.
	 *
	 * @ticket 66525
	 */
	public function test_should_block_the_home_url_and_the_site_url_when_they_are_different() {
		update_option( 'home', 'http://home.example.net' );
		update_option( 'siteurl', 'http://site.example.net/wp' );

		$this->assertNotSame( home_url(), site_url(), 'The two URLs should be different.' );
		$this->assertStringContainsString( 'not supported', render_block_core_rss( array( 'feedURL' => home_url() ) ), 'The home URL should be blocked.' );
		$this->assertStringContainsString( 'not supported', render_block_core_rss( array( 'feedURL' => site_url() ) ), 'The site URL should be blocked.' );
	}

	/**
	 * Tests that a URL of another page of this site is fetched.
	 *
	 * @ticket 66525
	 */
	public function test_should_not_block_the_other_urls_of_the_site() {
		add_filter(
			'pre_http_request',
			static function () {
				return new WP_Error( 'http_request_failed', 'Not fetched.' );
			},
			20
		);

		$output = render_block_core_rss( array( 'feedURL' => home_url( '/feed-page/' ) ) );

		$this->assertStringContainsString( 'RSS Error:', $output, 'The feed should be fetched.' );
		$this->assertStringNotContainsString( 'not supported', $output );
	}

	/**
	 * Tests that an error of the feed is rendered, escaped.
	 *
	 * @ticket 66525
	 */
	public function test_should_render_the_error_of_the_feed_escaped() {
		add_filter(
			'pre_http_request',
			static function () {
				return new WP_Error( 'http_request_failed', 'Failed <b>badly</b> & early' );
			},
			20
		);

		$output = render_block_core_rss(
			array(
				'feedURL'     => self::FEED_URL,
				'itemsToShow' => 5,
			)
		);

		$this->assertStringStartsWith( '<div class="components-placeholder"><div class="notice notice-error"><strong>RSS Error:</strong> ', $output );
		$this->assertStringContainsString( 'Failed &lt;b&gt;badly&lt;/b&gt; &amp; early', $output );
	}

	/**
	 * Tests that an error is rendered when the feed has no items.
	 *
	 * @ticket 66525
	 */
	public function test_should_render_an_error_when_the_feed_has_no_items() {
		$this->assertStringContainsString(
			'An error has occurred, which probably means the feed is down. Try again later.',
			$this->render( array() )
		);
	}

	/**
	 * Tests that the items are rendered with a link.
	 *
	 * @ticket 66525
	 */
	public function test_should_render_the_items_with_a_link() {
		$output = $this->render(
			array(
				$this->build_item(
					array(
						'title' => 'First',
						'link'  => 'https://example.com/first/',
					)
				),
				$this->build_item(
					array(
						'title' => 'Second',
						'link'  => 'https://example.com/second/',
					)
				),
			)
		);

		$this->assertSame(
			"<ul ><li class='wp-block-rss__item'><div class='wp-block-rss__item-title'><a href='https://example.com/first/'>First</a></div></li>"
			. "<li class='wp-block-rss__item'><div class='wp-block-rss__item-title'><a href='https://example.com/second/'>Second</a></div></li></ul>",
			$output
		);
	}

	/**
	 * Tests that the number of items is limited.
	 *
	 * @ticket 66525
	 */
	public function test_should_limit_the_number_of_items() {
		$items = array();

		foreach ( array( 'One', 'Two', 'Three' ) as $title ) {
			$items[] = $this->build_item(
				array(
					'title' => $title,
					'link'  => 'https://example.com/' . strtolower( $title ) . '/',
				)
			);
		}

		$output = $this->render( $items, array( 'itemsToShow' => 2 ) );

		$this->assertSame( 2, substr_count( $output, "<li class='wp-block-rss__item'>" ) );
		$this->assertStringContainsString( '>One</a>', $output );
		$this->assertStringContainsString( '>Two</a>', $output );
		$this->assertStringNotContainsString( '>Three</a>', $output );
	}

	/**
	 * Tests the title of the items.
	 *
	 * @ticket 66525
	 *
	 * @dataProvider data_titles
	 *
	 * @param string $title    Title of the item, as XML.
	 * @param string $expected Expected title.
	 */
	public function test_should_clean_and_escape_the_title( $title, $expected ) {
		$output = $this->render(
			array(
				$this->build_item(
					array(
						'title' => $title,
						'link'  => 'https://example.com/post/',
					)
				),
			)
		);

		$this->assertStringContainsString( "<a href='https://example.com/post/'>" . $expected . '</a>', $output );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_titles() {
		return array(
			'a plain title'       => array( 'Plain', 'Plain' ),
			'an ampersand'        => array( 'Tom &amp; Jerry', 'Tom &amp; Jerry' ),
			'a title with spaces' => array( '  Spaced  ', 'Spaced' ),
			'a title with markup' => array( '<![CDATA[<b>Bold</b> title]]>', 'Bold title' ),
			'an escaped script'   => array( '&lt;script&gt;alert(1)&lt;/script&gt; Text', 'alert(1) Text' ),
			'an empty title'      => array( '', '(no title)' ),
			'only markup'         => array( '<![CDATA[<b></b>]]>', '(no title)' ),
		);
	}

	/**
	 * Tests that the URL of the link is escaped.
	 *
	 * @ticket 66525
	 */
	public function test_should_escape_the_url_of_the_link() {
		$output = $this->render(
			array(
				$this->build_item(
					array(
						'title' => 'Escaped',
						'link'  => 'https://example.com/?a=1&amp;b=2',
					)
				),
			)
		);

		$this->assertStringContainsString( "<a href='https://example.com/?a=1&#038;b=2'>", $output );
	}

	/**
	 * Tests the attributes of the link.
	 *
	 * @ticket 66525
	 *
	 * @dataProvider data_link_attributes
	 *
	 * @param array  $attributes Attributes of the block.
	 * @param string $expected   Expected attributes of the link.
	 */
	public function test_should_add_the_attributes_of_the_link( $attributes, $expected ) {
		$output = $this->render(
			array(
				$this->build_item(
					array(
						'title' => 'Item',
						'link'  => 'https://example.com/item/',
					)
				),
			),
			$attributes
		);

		$this->assertStringContainsString( "<a href='https://example.com/item/'" . $expected . '>Item</a>', $output );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_link_attributes() {
		return array(
			'none'                       => array( array(), '' ),
			'a new tab'                  => array( array( 'openInNewTab' => true ), ' target="_blank"' ),
			'not a new tab'              => array( array( 'openInNewTab' => false ), '' ),
			'a rel'                      => array( array( 'rel' => 'noopener nofollow' ), ' rel="noopener nofollow"' ),
			'a rel with spaces'          => array( array( 'rel' => '  noopener  ' ), ' rel="noopener"' ),
			'an empty rel'               => array( array( 'rel' => '' ), '' ),
			'a rel that is not a string' => array( array( 'rel' => 5 ), '' ),
			'an escaped rel'             => array( array( 'rel' => '"><script>' ), ' rel="&quot;&gt;&lt;script&gt;"' ),
			'a new tab and a rel'        => array(
				array(
					'openInNewTab' => true,
					'rel'          => 'noopener',
				),
				' target="_blank" rel="noopener"',
			),
		);
	}

	/**
	 * Tests the date of the items.
	 *
	 * @ticket 66525
	 */
	public function test_should_render_the_date_when_it_is_requested() {
		$items = array(
			$this->build_item(
				array(
					'title'   => 'Dated',
					'link'    => 'https://example.com/dated/',
					'pubDate' => 'Mon, 02 Jan 2023 10:30:00 +0000',
				)
			),
		);

		$this->assertStringNotContainsString( '<time', $this->render( $items ), 'The date should be hidden.' );
		$this->assertStringContainsString(
			'</div><time datetime="2023-01-02T10:30:00+00:00" class="wp-block-rss__item-publish-date">2023-01-02</time> </li>',
			$this->render( $items, array( 'displayDate' => true ) ),
			'The date should be rendered after the title.'
		);
	}

	/**
	 * Tests that no date is rendered for an item without a date.
	 *
	 * @ticket 66525
	 */
	public function test_should_not_render_a_date_for_an_item_without_a_date() {
		$output = $this->render( array( $this->build_item( array( 'title' => 'Undated' ) ) ), array( 'displayDate' => true ) );

		$this->assertStringNotContainsString( '<time', $output );
	}

	/**
	 * Tests the author of the items.
	 *
	 * @ticket 66525
	 */
	public function test_should_render_the_author_when_it_is_requested() {
		$items = array(
			$this->build_item(
				array(
					'title'      => 'By someone',
					'dc:creator' => 'Jane Doe',
				)
			),
		);

		$this->assertStringNotContainsString( 'wp-block-rss__item-author', $this->render( $items ), 'The author should be hidden.' );
		$this->assertStringContainsString(
			'<span class="wp-block-rss__item-author">by Jane Doe</span>',
			$this->render( $items, array( 'displayAuthor' => true ) ),
			'The author should be rendered.'
		);
	}

	/**
	 * Tests that no author is rendered for an item without an author.
	 *
	 * @ticket 66525
	 */
	public function test_should_not_render_an_author_for_an_item_without_an_author() {
		$output = $this->render( array( $this->build_item( array( 'title' => 'Anonymous' ) ) ), array( 'displayAuthor' => true ) );

		$this->assertStringNotContainsString( 'wp-block-rss__item-author', $output );
	}

	/**
	 * Tests the excerpt of the items.
	 *
	 * @ticket 66525
	 */
	public function test_should_render_the_excerpt_when_it_is_requested() {
		$items = array(
			$this->build_item(
				array(
					'title'       => 'With an excerpt',
					'description' => 'One two three four five six seven eight',
				)
			),
		);

		$this->assertStringNotContainsString( 'wp-block-rss__item-excerpt', $this->render( $items ), 'The excerpt should be hidden.' );
		$this->assertStringContainsString(
			'<div class="wp-block-rss__item-excerpt">One two three four five six seven eight</div>',
			$this->render( $items, array( 'displayExcerpt' => true ) ),
			'The excerpt should be rendered.'
		);
	}

	/**
	 * Tests the length of the excerpt.
	 *
	 * @ticket 66525
	 *
	 * @dataProvider data_excerpts
	 *
	 * @param string $description Description of the item.
	 * @param int    $length      Length of the excerpt.
	 * @param string $expected    Expected excerpt.
	 */
	public function test_should_trim_the_excerpt( $description, $length, $expected ) {
		$output = $this->render(
			array(
				$this->build_item(
					array(
						'title'       => 'Excerpt',
						'description' => $description,
					)
				),
			),
			array(
				'displayExcerpt' => true,
				'excerptLength'  => $length,
			)
		);

		$this->assertStringContainsString( '<div class="wp-block-rss__item-excerpt">' . $expected . '</div>', $output );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_excerpts() {
		return array(
			'a long description'       => array( 'One two three four five', 3, 'One two three [&hellip;]' ),
			'a short description'      => array( 'One two', 5, 'One two' ),
			'markup is removed'        => array( '&lt;p&gt;One &lt;b&gt;two&lt;/b&gt;&lt;/p&gt;', 5, 'One two' ),
			'a numeric entity'         => array( 'One&amp;#32;two three', 1, 'One [&hellip;]' ),
			'an old ellipsis'          => array( 'One two [...]', 5, 'One two [&hellip;]' ),
			'an old ellipsis and trim' => array( 'One two three four five six [...]', 2, 'One two [&hellip;]' ),
		);
	}

	/**
	 * Tests that no excerpt is rendered for an item without a description.
	 *
	 * @ticket 66525
	 */
	public function test_should_not_render_an_excerpt_for_an_item_without_a_description() {
		$output = $this->render( array( $this->build_item( array( 'title' => 'Empty' ) ) ), array( 'displayExcerpt' => true ) );

		$this->assertStringNotContainsString( 'wp-block-rss__item-excerpt', $output );
	}

	/**
	 * Tests that the date, the author and the excerpt are rendered in order.
	 *
	 * @ticket 66525
	 */
	public function test_should_render_the_parts_of_the_item_in_order() {
		$output = $this->render(
			array(
				$this->build_item(
					array(
						'title'       => 'Everything',
						'link'        => 'https://example.com/all/',
						'pubDate'     => 'Mon, 02 Jan 2023 10:30:00 +0000',
						'dc:creator'  => 'Jane',
						'description' => 'Short text',
					)
				),
			),
			array(
				'displayDate'    => true,
				'displayAuthor'  => true,
				'displayExcerpt' => true,
			)
		);

		$this->assertSame(
			"<ul class=\"has-dates has-authors has-excerpts\"><li class='wp-block-rss__item'>"
			. "<div class='wp-block-rss__item-title'><a href='https://example.com/all/'>Everything</a></div>"
			. '<time datetime="2023-01-02T10:30:00+00:00" class="wp-block-rss__item-publish-date">2023-01-02</time> '
			. '<span class="wp-block-rss__item-author">by Jane</span>'
			. '<div class="wp-block-rss__item-excerpt">Short text</div></li></ul>',
			$output
		);
	}

	/**
	 * Tests the classes of the list.
	 *
	 * @ticket 66525
	 *
	 * @dataProvider data_classes
	 *
	 * @param array  $attributes Attributes of the block.
	 * @param string $expected   Expected class attribute.
	 */
	public function test_should_add_the_classes_of_the_attributes( $attributes, $expected ) {
		$output = $this->render( array( $this->build_item( array( 'title' => 'Item' ) ) ), $attributes );

		$this->assertStringStartsWith( '<ul ' . $expected . '>', $output );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_classes() {
		return array(
			'a list'                  => array( array(), '' ),
			'a grid'                  => array( array( 'blockLayout' => 'grid' ), 'class="is-grid columns-2"' ),
			'a grid of three columns' => array(
				array(
					'blockLayout' => 'grid',
					'columns'     => 3,
				),
				'class="is-grid columns-3"',
			),
			'columns in a list'       => array( array( 'columns' => 4 ), '' ),
			'dates'                   => array( array( 'displayDate' => true ), 'class="has-dates"' ),
			'authors'                 => array( array( 'displayAuthor' => true ), 'class="has-authors"' ),
			'excerpts'                => array( array( 'displayExcerpt' => true ), 'class="has-excerpts"' ),
			'everything'              => array(
				array(
					'blockLayout'    => 'grid',
					'columns'        => 2,
					'displayDate'    => true,
					'displayAuthor'  => true,
					'displayExcerpt' => true,
				),
				'class="is-grid columns-2 has-dates has-authors has-excerpts"',
			),
		);
	}
}
