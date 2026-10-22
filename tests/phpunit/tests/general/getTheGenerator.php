<?php
/**
 * Tests for get_the_generator().
 *
 * @group general
 * @group template
 *
 * @covers ::get_the_generator
 */
class Tests_General_GetTheGenerator extends WP_UnitTestCase {

	/**
	 * Tests that an unsupported generator type returns null.
	 *
	 * @ticket 65817
	 */
	public function test_should_return_null_for_unsupported_type() {
		$this->assertNull( get_the_generator( 'custom' ) );
	}

	/**
	 * Tests that a custom generator can still be supplied by a filter.
	 *
	 * @ticket 65817
	 */
	public function test_should_filter_unsupported_type() {
		add_filter(
			'get_the_generator_custom',
			function ( $generator, $type ) {
				$this->assertNull( $generator );
				$this->assertSame( 'custom', $type );

				return '<generator>Custom generator</generator>';
			},
			10,
			2
		);

		$this->assertSame( '<generator>Custom generator</generator>', get_the_generator( 'custom' ) );
	}

	/**
	 * Tests that an empty type without a current filter returns null.
	 *
	 * @ticket 65817
	 */
	public function test_should_return_null_without_type_or_current_filter() {
		$this->assertNull( get_the_generator() );
	}

	/**
	 * Tests that an empty type on an unrecognized hook returns null.
	 *
	 * @ticket 65817
	 */
	public function test_should_return_null_on_unrecognized_hook() {
		add_filter(
			'custom_generator_hook',
			static function () {
				return get_the_generator();
			}
		);

		$this->assertNull( apply_filters( 'custom_generator_hook', '' ) );
	}

	/**
	 * Tests the markup for supported generator types.
	 *
	 * @ticket 65817
	 * @dataProvider data_supported_types
	 *
	 * @param string $type     Generator type.
	 * @param string $expected Expected markup, with a placeholder for the version.
	 */
	public function test_should_return_markup_for_supported_type( $type, $expected ) {
		$this->assertSame( sprintf( $expected, get_bloginfo( 'version' ) ), get_the_generator( $type ) );
	}

	/**
	 * Data provider for supported generator types.
	 *
	 * @return array[]
	 */
	public static function data_supported_types() {
		return array(
			'html'    => array( 'html', '<meta name="generator" content="WordPress %s">' ),
			'xhtml'   => array( 'xhtml', '<meta name="generator" content="WordPress %s" />' ),
			'atom'    => array( 'atom', '<generator uri="https://wordpress.org/" version="%s">WordPress</generator>' ),
			'rss2'    => array( 'rss2', '<generator>https://wordpress.org/?v=%s</generator>' ),
			'rdf'     => array( 'rdf', '<admin:generatorAgent rdf:resource="https://wordpress.org/?v=%s" />' ),
			'comment' => array( 'comment', '<!-- generator="WordPress/%s" -->' ),
		);
	}

	/**
	 * Tests that export markup includes the WordPress version and creation date.
	 *
	 * @ticket 65817
	 */
	public function test_should_return_export_markup() {
		$this->assertMatchesRegularExpression(
			'/^<!-- generator="WordPress\/' . preg_quote( get_bloginfo( 'version' ), '/' ) . '" created="\d{4}-\d{2}-\d{2} \d{2}:\d{2}" -->$/',
			get_the_generator( 'export' )
		);
	}

	/**
	 * Tests that the generator type is inferred from the current feed hook.
	 *
	 * @ticket 65817
	 * @dataProvider data_feed_hooks
	 *
	 * @param string $hook Feed hook.
	 * @param string $type Expected generator type.
	 */
	public function test_should_infer_type_from_feed_hook( $hook, $type ) {
		remove_action( $hook, 'the_generator' );

		add_filter(
			$hook,
			static function () {
				return get_the_generator();
			}
		);

		$this->assertSame( get_the_generator( $type ), apply_filters( $hook, '' ) );
	}

	/**
	 * Data provider for feed hooks and their generator types.
	 *
	 * @return array[]
	 */
	public static function data_feed_hooks() {
		return array(
			'rss2_head'          => array( 'rss2_head', 'rss2' ),
			'commentsrss2_head'  => array( 'commentsrss2_head', 'rss2' ),
			'rss_head'           => array( 'rss_head', 'comment' ),
			'opml_head'          => array( 'opml_head', 'comment' ),
			'rdf_header'         => array( 'rdf_header', 'rdf' ),
			'atom_head'          => array( 'atom_head', 'atom' ),
			'comments_atom_head' => array( 'comments_atom_head', 'atom' ),
			'app_head'           => array( 'app_head', 'atom' ),
		);
	}
}
