<?php
/**
 * Tests that Twenty Twenty-Five only asks for different search keywords on search results.
 *
 * @group themes
 * @group ticket-63513
 */
class Tests_Theme_TwentyTwentyFiveNoResultsCopy extends WP_UnitTestCase {

	const SEARCH_COPY = 'Please try a search with different keywords.';

	/**
	 * @param string $file Path relative to the Twenty Twenty-Five theme directory.
	 * @return string File contents.
	 */
	private function get_theme_file( $file ) {
		return file_get_contents( ABSPATH . 'wp-content/themes/twentytwentyfive/' . $file );
	}

	/**
	 * @dataProvider data_non_search_patterns
	 *
	 * @param string $pattern Pattern file name.
	 */
	public function test_non_search_patterns_do_not_ask_for_different_keywords( $pattern ) {
		$this->assertStringNotContainsString( self::SEARCH_COPY, $this->get_theme_file( "patterns/$pattern.php" ) );
	}

	/**
	 * @return array[]
	 */
	public function data_non_search_patterns() {
		return array(
			'template-query-loop'                  => array( 'template-query-loop' ),
			'template-home-news-blog'              => array( 'template-home-news-blog' ),
			'template-home-with-sidebar-news-blog' => array( 'template-home-with-sidebar-news-blog' ),
			'template-home-posts-grid-news-blog'   => array( 'template-home-posts-grid-news-blog' ),
			'page-portfolio-home'                  => array( 'page-portfolio-home' ),
		);
	}

	public function test_search_template_keeps_the_search_copy() {
		$template = $this->get_theme_file( 'templates/search.html' );
		$this->assertStringContainsString( 'twentytwentyfive/template-query-loop-search', $template );
		$this->assertStringContainsString( self::SEARCH_COPY, $this->get_theme_file( 'patterns/template-query-loop-search.php' ) );
	}
}
