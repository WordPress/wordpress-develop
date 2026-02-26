<?php
/**
 * Tests for sanitize_term().
 *
 * @group taxonomy
 * @group term
 *
 * @covers ::sanitize_term
 */
class Tests_Term_SanitizeTerm extends WP_UnitTestCase {

	/**
	 * Taxonomy to test with.
	 *
	 * @var string
	 */
	protected static $taxonomy = 'wptests_tax';

	/**
	 * Create taxonomy and terms before tests.
	 *
	 * @param WP_UnitTest_Factory $factory Helper that we call to create fake data.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		register_taxonomy( self::$taxonomy, 'post' );
		$factory->term->create( array( 'taxonomy' => self::$taxonomy ) );
	}

	/**
	 * Unregister taxonomy after tests.
	 */
	public static function wpTearDownAfterClass() {
		unregister_taxonomy( self::$taxonomy );
	}

	/**
	 * Test sanitize_term() with an invalid context.
	 *
	 * @ticket 50568
	 */
	public function test_sanitize_term_invalid_context() {
		$t = array(
			'term_id'          => 1,
			'name'             => 'My Term',
			'slug'             => 'my-term',
			'term_group'       => 0,
			'term_taxonomy_id' => 1,
			'taxonomy'         => self::$taxonomy,
			'description'      => '',
			'parent'           => 0,
			'count'            => 0,
			'filter'           => 'raw',
		);

		$expected           = $t;
		$expected['filter'] = 'invalid-context';
		$this->assertSame( sanitize_term( $t, self::$taxonomy, 'invalid-context' ), $expected );
	}

	/**
	 * Tests sanitize_term() with 'edit' context.
	 *
	 * @ticket 50568
	 *
	 * @dataProvider data_sanitize_term_edit
	 *
	 * @param array $term_data Term data to sanitize.
	 * @param array $expected Expected term data.
	 */
	public function test_sanitize_term_edit( $term_data, $expected ) {

		$taxonomy = self::$taxonomy;
		// array in array out
		$actual = sanitize_term( $term_data, $taxonomy, 'edit' );
		$this->assertSame( $expected, $actual );

		// Object in object out
		$term           = (object) $term_data;
		$expected_oject = (object) $expected;
		$actual         = sanitize_term( $term, $taxonomy, 'edit' );
		$this->assertEquals( $expected_oject, $actual );
	}

	/**
	 * Data provider for test_sanitize_term_edit().
	 *
	 * @ticket 50568
	 *
	 * @return array
	 */
	public function data_sanitize_term_edit() {
		return array(
			'valid term'              => array(
				array(
					'term_id'          => 1,
					'name'             => 'My Term',
					'slug'             => 'my-term',
					'term_group'       => 0,
					'term_taxonomy_id' => 1,
					'taxonomy'         => self::$taxonomy,
					'description'      => '',
					'parent'           => 0,
					'count'            => 0,
					'filter'           => 'raw',
				),
				array(
					'term_id'          => 1,
					'name'             => 'My Term',
					'slug'             => 'my-term',
					'term_group'       => 0,
					'term_taxonomy_id' => 1,
					'taxonomy'         => self::$taxonomy,
					'description'      => '',
					'parent'           => 0,
					'count'            => 0,
					'filter'           => 'edit',
				),
			),
			'name with html'          => array(
				array(
					'term_id'          => 1,
					'name'             => '<p>My Term</p>',
					'slug'             => 'my-term',
					'term_group'       => 0,
					'term_taxonomy_id' => 1,
					'taxonomy'         => self::$taxonomy,
					'description'      => '',
					'parent'           => 0,
					'count'            => 0,
					'filter'           => 'raw',
				),
				array(
					'term_id'          => 1,
					'name'             => '&lt;p&gt;My Term&lt;/p&gt;',
					'slug'             => 'my-term',
					'term_group'       => 0,
					'term_taxonomy_id' => 1,
					'taxonomy'         => self::$taxonomy,
					'description'      => '',
					'parent'           => 0,
					'count'            => 0,
					'filter'           => 'edit',
				),
			),
			'name with javascript'    => array(
				array(
					'term_id'          => 1,
					'name'             => '<script>alert("XSS")</script>',
					'slug'             => 'my-term',
					'term_group'       => 0,
					'term_taxonomy_id' => 1,
					'taxonomy'         => self::$taxonomy,
					'description'      => '',
					'parent'           => 0,
					'count'            => 0,
					'filter'           => 'raw',
				),
				array(
					'term_id'          => 1,
					'name'             => '&lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;',
					'slug'             => 'my-term',
					'term_group'       => 0,
					'term_taxonomy_id' => 1,
					'taxonomy'         => self::$taxonomy,
					'description'      => '',
					'parent'           => 0,
					'count'            => 0,
					'filter'           => 'edit',
				),
			),
			'slug with uppercase'     => array(
				array(
					'term_id'          => 1,
					'name'             => 'My Term',
					'slug'             => 'MY-TERM',
					'term_group'       => 0,
					'term_taxonomy_id' => 1,
					'taxonomy'         => self::$taxonomy,
					'description'      => '',
					'parent'           => 0,
					'count'            => 0,
					'filter'           => 'raw',
				),
				array(
					'term_id'          => 1,
					'name'             => 'My Term',
					'slug'             => 'my-term',
					'term_group'       => 0,
					'term_taxonomy_id' => 1,
					'taxonomy'         => self::$taxonomy,
					'description'      => '',
					'parent'           => 0,
					'count'            => 0,
					'filter'           => 'edit',
				),
			),
			'slug with special char'  => array(
				array(
					'term_id'          => 1,
					'name'             => 'My Term',
					'slug'             => 'MY-!@#$%',
					'term_group'       => 0,
					'term_taxonomy_id' => 1,
					'taxonomy'         => self::$taxonomy,
					'description'      => '',
					'parent'           => 0,
					'count'            => 0,
					'filter'           => 'raw',
				),
				array(
					'term_id'          => 1,
					'name'             => 'My Term',
					'slug'             => 'my',
					'term_group'       => 0,
					'term_taxonomy_id' => 1,
					'taxonomy'         => self::$taxonomy,
					'description'      => '',
					'parent'           => 0,
					'count'            => 0,
					'filter'           => 'edit',
				),
			),
			'description with script' => array(
				array(
					'term_id'          => 1,
					'name'             => 'My Term',
					'slug'             => 'my-term',
					'term_group'       => 0,
					'term_taxonomy_id' => 1,
					'taxonomy'         => self::$taxonomy,
					'description'      => '<script>alert("XSS")</script>',
					'parent'           => 0,
					'count'            => 0,
					'filter'           => 'raw',
				),
				array(
					'term_id'          => 1,
					'name'             => 'My Term',
					'slug'             => 'my-term',
					'term_group'       => 0,
					'term_taxonomy_id' => 1,
					'taxonomy'         => self::$taxonomy,
					'description'      => '&lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;',
					'parent'           => 0,
					'count'            => 0,
					'filter'           => 'edit',
				),
			),
			'description with html'   => array(
				array(
					'term_id'          => 1,
					'name'             => 'My Term',
					'slug'             => 'my-term',
					'term_group'       => 0,
					'term_taxonomy_id' => 1,
					'taxonomy'         => self::$taxonomy,
					'description'      => '<p>My description</p>',
					'parent'           => 0,
					'count'            => 0,
					'filter'           => 'raw',
				),
				array(
					'term_id'          => 1,
					'name'             => 'My Term',
					'slug'             => 'my-term',
					'term_group'       => 0,
					'term_taxonomy_id' => 1,
					'taxonomy'         => self::$taxonomy,
					'description'      => '&lt;p&gt;My description&lt;/p&gt;',
					'parent'           => 0,
					'count'            => 0,
					'filter'           => 'edit',
				),
			),

		);
	}

	/**
	 * Tests sanitize_term() with 'edit' context.
	 *
	 * @ticket 50568
	 *
	 * @dataProvider data_sanitize_term_db
	 *
	 * @param array $term_data Term data to sanitize.
	 * @param array $expected Expected term data.
	 */
	public function test_sanitize_term_db( $term_data, $expected ) {
		$taxonomy = self::$taxonomy;
		// array in array out
		$actual = sanitize_term( $term_data, $taxonomy, 'db' );
		$this->assertSame( $expected, $actual );

		// Object in object out
		$term           = (object) $term_data;
		$expected_oject = (object) $expected;
		$actual         = sanitize_term( $term, $taxonomy, 'db' );
		$this->assertEquals( $expected_oject, $actual );
	}

	/**
	 * Data provider for test_sanitize_term_db().
	 *
	 * @ticket 50568
	 *
	 * @return array
	 */
	public function data_sanitize_term_db() {
		return array(
			'valid term'              => array(
				array(
					'term_id'          => 1,
					'name'             => 'My Term',
					'slug'             => 'my-term',
					'term_group'       => 0,
					'term_taxonomy_id' => 1,
					'taxonomy'         => self::$taxonomy,
					'description'      => '',
					'parent'           => 0,
					'count'            => 0,
					'filter'           => 'raw',
				),
				array(
					'term_id'          => 1,
					'name'             => 'My Term',
					'slug'             => 'my-term',
					'term_group'       => 0,
					'term_taxonomy_id' => 1,
					'taxonomy'         => self::$taxonomy,
					'description'      => '',
					'parent'           => 0,
					'count'            => 0,
					'filter'           => 'db',
				),
			),
			'name with html'          => array(
				array(
					'term_id'          => 1,
					'name'             => '<p>My Term</p>',
					'slug'             => 'my-term',
					'term_group'       => 0,
					'term_taxonomy_id' => 1,
					'taxonomy'         => self::$taxonomy,
					'description'      => '',
					'parent'           => 0,
					'count'            => 0,
					'filter'           => 'raw',
				),
				array(
					'term_id'          => 1,
					'name'             => 'My Term',
					'slug'             => 'my-term',
					'term_group'       => 0,
					'term_taxonomy_id' => 1,
					'taxonomy'         => self::$taxonomy,
					'description'      => '',
					'parent'           => 0,
					'count'            => 0,
					'filter'           => 'db',
				),
			),
			'name with javascript'    => array(
				array(
					'term_id'          => 1,
					'name'             => '<script>alert("XSS")</script>',
					'slug'             => 'my-term',
					'term_group'       => 0,
					'term_taxonomy_id' => 1,
					'taxonomy'         => self::$taxonomy,
					'description'      => '',
					'parent'           => 0,
					'count'            => 0,
					'filter'           => 'raw',
				),
				array(
					'term_id'          => 1,
					'name'             => '',
					'slug'             => 'my-term',
					'term_group'       => 0,
					'term_taxonomy_id' => 1,
					'taxonomy'         => self::$taxonomy,
					'description'      => '',
					'parent'           => 0,
					'count'            => 0,
					'filter'           => 'db',
				),
			),
			'slug with uppercase'     => array(
				array(
					'term_id'          => 1,
					'name'             => 'My Term',
					'slug'             => 'MY-TERM',
					'term_group'       => 0,
					'term_taxonomy_id' => 1,
					'taxonomy'         => self::$taxonomy,
					'description'      => '',
					'parent'           => 0,
					'count'            => 0,
					'filter'           => 'raw',
				),
				array(
					'term_id'          => 1,
					'name'             => 'My Term',
					'slug'             => 'my-term',
					'term_group'       => 0,
					'term_taxonomy_id' => 1,
					'taxonomy'         => self::$taxonomy,
					'description'      => '',
					'parent'           => 0,
					'count'            => 0,
					'filter'           => 'db',
				),
			),
			'slug with special char'  => array(
				array(
					'term_id'          => 1,
					'name'             => 'My Term',
					'slug'             => 'MY-!@#$%',
					'term_group'       => 0,
					'term_taxonomy_id' => 1,
					'taxonomy'         => self::$taxonomy,
					'description'      => '',
					'parent'           => 0,
					'count'            => 0,
					'filter'           => 'raw',
				),
				array(
					'term_id'          => 1,
					'name'             => 'My Term',
					'slug'             => 'my',
					'term_group'       => 0,
					'term_taxonomy_id' => 1,
					'taxonomy'         => self::$taxonomy,
					'description'      => '',
					'parent'           => 0,
					'count'            => 0,
					'filter'           => 'db',
				),
			),
			'description with script' => array(
				array(
					'term_id'          => 1,
					'name'             => 'My Term',
					'slug'             => 'my-term',
					'term_group'       => 0,
					'term_taxonomy_id' => 1,
					'taxonomy'         => self::$taxonomy,
					'description'      => '<script>alert("XSS")</script>',
					'parent'           => 0,
					'count'            => 0,
					'filter'           => 'raw',
				),
				array(
					'term_id'          => 1,
					'name'             => 'My Term',
					'slug'             => 'my-term',
					'term_group'       => 0,
					'term_taxonomy_id' => 1,
					'taxonomy'         => self::$taxonomy,
					'description'      => 'alert(\"XSS\")',
					'parent'           => 0,
					'count'            => 0,
					'filter'           => 'db',
				),
			),
			'description with html'   => array(
				array(
					'term_id'          => 1,
					'name'             => 'My Term',
					'slug'             => 'my-term',
					'term_group'       => 0,
					'term_taxonomy_id' => 1,
					'taxonomy'         => self::$taxonomy,
					'description'      => '<p>My description</p>',
					'parent'           => 0,
					'count'            => 0,
					'filter'           => 'raw',
				),
				array(
					'term_id'          => 1,
					'name'             => 'My Term',
					'slug'             => 'my-term',
					'term_group'       => 0,
					'term_taxonomy_id' => 1,
					'taxonomy'         => self::$taxonomy,
					'description'      => 'My description',
					'parent'           => 0,
					'count'            => 0,
					'filter'           => 'db',
				),
			),

		);
	}

	/**
	 * Tests sanitize_term() with 'display' context.
	 *
	 * @ticket 50568
	 *
	 * @dataProvider data_sanitize_term_rss
	 *
	 * @param array $term_data Term data to sanitize.
	 * @param array $expected Expected term data.
	 */
	public function test_sanitize_term_rss( $term_data, $expected ) {
		$taxonomy = self::$taxonomy;
		// array in array out
		$actual = sanitize_term( $term_data, $taxonomy, 'rss' );
		$this->assertSame( $expected, $actual );

		// Object in object out
		$term           = (object) $term_data;
		$expected_oject = (object) $expected;
		$actual         = sanitize_term( $term, $taxonomy, 'rss' );
		$this->assertEquals( $expected_oject, $actual );
	}

	/**
	 * Data provider for test_sanitize_term_rss().
	 *
	 * @return array
	 */
	public function data_sanitize_term_rss() {
		return array(
			'valid term'              => array(
				array(
					'term_id'          => 1,
					'name'             => 'My Term',
					'slug'             => 'my-term',
					'term_group'       => 0,
					'term_taxonomy_id' => 1,
					'taxonomy'         => self::$taxonomy,
					'description'      => '',
					'parent'           => 0,
					'count'            => 0,
					'filter'           => 'raw',
				),
				array(
					'term_id'          => 1,
					'name'             => 'My Term',
					'slug'             => 'my-term',
					'term_group'       => 0,
					'term_taxonomy_id' => 1,
					'taxonomy'         => self::$taxonomy,
					'description'      => '',
					'parent'           => 0,
					'count'            => 0,
					'filter'           => 'rss',
				),
			),
			'name with html'          => array(
				array(
					'term_id'          => 1,
					'name'             => '<p>My Term</p>',
					'slug'             => 'my-term',
					'term_group'       => 0,
					'term_taxonomy_id' => 1,
					'taxonomy'         => self::$taxonomy,
					'description'      => '',
					'parent'           => 0,
					'count'            => 0,
					'filter'           => 'raw',
				),
				array(
					'term_id'          => 1,
					'name'             => '<p>My Term</p>',
					'slug'             => 'my-term',
					'term_group'       => 0,
					'term_taxonomy_id' => 1,
					'taxonomy'         => self::$taxonomy,
					'description'      => '',
					'parent'           => 0,
					'count'            => 0,
					'filter'           => 'rss',
				),
			),
			'name with javascript'    => array(
				array(
					'term_id'          => 1,
					'name'             => '<script>alert("XSS")</script>',
					'slug'             => 'my-term',
					'term_group'       => 0,
					'term_taxonomy_id' => 1,
					'taxonomy'         => self::$taxonomy,
					'description'      => '',
					'parent'           => 0,
					'count'            => 0,
					'filter'           => 'raw',
				),
				array(
					'term_id'          => 1,
					'name'             => '<script>alert("XSS")</script>',
					'slug'             => 'my-term',
					'term_group'       => 0,
					'term_taxonomy_id' => 1,
					'taxonomy'         => self::$taxonomy,
					'description'      => '',
					'parent'           => 0,
					'count'            => 0,
					'filter'           => 'rss',
				),
			),
			'slug with uppercase'     => array(
				array(
					'term_id'          => 1,
					'name'             => 'My Term',
					'slug'             => 'MY-TERM',
					'term_group'       => 0,
					'term_taxonomy_id' => 1,
					'taxonomy'         => self::$taxonomy,
					'description'      => '',
					'parent'           => 0,
					'count'            => 0,
					'filter'           => 'raw',
				),
				array(
					'term_id'          => 1,
					'name'             => 'My Term',
					'slug'             => 'my-term',
					'term_group'       => 0,
					'term_taxonomy_id' => 1,
					'taxonomy'         => self::$taxonomy,
					'description'      => '',
					'parent'           => 0,
					'count'            => 0,
					'filter'           => 'rss',
				),
			),
			'slug with special char'  => array(
				array(
					'term_id'          => 1,
					'name'             => 'My Term',
					'slug'             => 'MY-!@#$%',
					'term_group'       => 0,
					'term_taxonomy_id' => 1,
					'taxonomy'         => self::$taxonomy,
					'description'      => '',
					'parent'           => 0,
					'count'            => 0,
					'filter'           => 'raw',
				),
				array(
					'term_id'          => 1,
					'name'             => 'My Term',
					'slug'             => 'my',
					'term_group'       => 0,
					'term_taxonomy_id' => 1,
					'taxonomy'         => self::$taxonomy,
					'description'      => '',
					'parent'           => 0,
					'count'            => 0,
					'filter'           => 'rss',
				),
			),
			'description with script' => array(
				array(
					'term_id'          => 1,
					'name'             => 'My Term',
					'slug'             => 'my-term',
					'term_group'       => 0,
					'term_taxonomy_id' => 1,
					'taxonomy'         => self::$taxonomy,
					'description'      => '<script>alert("XSS")</script>',
					'parent'           => 0,
					'count'            => 0,
					'filter'           => 'raw',
				),
				array(
					'term_id'          => 1,
					'name'             => 'My Term',
					'slug'             => 'my-term',
					'term_group'       => 0,
					'term_taxonomy_id' => 1,
					'taxonomy'         => self::$taxonomy,
					'description'      => '<script>alert("XSS")</script>',
					'parent'           => 0,
					'count'            => 0,
					'filter'           => 'rss',
				),
			),
			'description with html'   => array(
				array(
					'term_id'          => 1,
					'name'             => 'My Term',
					'slug'             => 'my-term',
					'term_group'       => 0,
					'term_taxonomy_id' => 1,
					'taxonomy'         => self::$taxonomy,
					'description'      => '<p>My description</p>',
					'parent'           => 0,
					'count'            => 0,
					'filter'           => 'raw',
				),
				array(
					'term_id'          => 1,
					'name'             => 'My Term',
					'slug'             => 'my-term',
					'term_group'       => 0,
					'term_taxonomy_id' => 1,
					'taxonomy'         => self::$taxonomy,
					'description'      => '<p>My description</p>',
					'parent'           => 0,
					'count'            => 0,
					'filter'           => 'rss',
				),
			),

		);
	}

	/**
	 * Tests sanitize_term() with 'display' context.
	 *
	 * @ticket 50568
	 *
	 * @dataProvider data_sanitize_term_display
	 *
	 * @param array $term_data Term data to sanitize.
	 * @param array $expected Expected term data.
	 */
	public function test_sanitize_term_display( $term_data, $expected ) {
		$taxonomy = self::$taxonomy;
		// array in array out
		$actual = sanitize_term( $term_data, $taxonomy, 'display' );
		$this->assertSame( $expected, $actual );

		// Object in object out
		$term           = (object) $term_data;
		$expected_oject = (object) $expected;
		$actual         = sanitize_term( $term, $taxonomy, 'display' );
		$this->assertEquals( $expected_oject, $actual );
	}

	/**
	 * Data provider for test_sanitize_term_display().
	 *
	 * @return array
	 */
	public function data_sanitize_term_display() {
		return array(
			'valid term'              => array(
				array(
					'term_id'          => 1,
					'name'             => 'My Term',
					'slug'             => 'my-term',
					'term_group'       => 0,
					'term_taxonomy_id' => 1,
					'taxonomy'         => self::$taxonomy,
					'description'      => '',
					'parent'           => 0,
					'count'            => 0,
					'filter'           => 'raw',
				),
				array(
					'term_id'          => 1,
					'name'             => 'My Term',
					'slug'             => 'my-term',
					'term_group'       => 0,
					'term_taxonomy_id' => 1,
					'taxonomy'         => self::$taxonomy,
					'description'      => '',
					'parent'           => 0,
					'count'            => 0,
					'filter'           => 'display',
				),
			),
			'name with html'          => array(
				array(
					'term_id'          => 1,
					'name'             => '<p>My Term</p>',
					'slug'             => 'my-term',
					'term_group'       => 0,
					'term_taxonomy_id' => 1,
					'taxonomy'         => self::$taxonomy,
					'description'      => '',
					'parent'           => 0,
					'count'            => 0,
					'filter'           => 'raw',
				),
				array(
					'term_id'          => 1,
					'name'             => '&lt;p&gt;My Term&lt;/p&gt;',
					'slug'             => 'my-term',
					'term_group'       => 0,
					'term_taxonomy_id' => 1,
					'taxonomy'         => self::$taxonomy,
					'description'      => '',
					'parent'           => 0,
					'count'            => 0,
					'filter'           => 'display',
				),
			),
			'name with javascript'    => array(
				array(
					'term_id'          => 1,
					'name'             => '<script>alert("XSS")</script>',
					'slug'             => 'my-term',
					'term_group'       => 0,
					'term_taxonomy_id' => 1,
					'taxonomy'         => self::$taxonomy,
					'description'      => '',
					'parent'           => 0,
					'count'            => 0,
					'filter'           => 'raw',
				),
				array(
					'term_id'          => 1,
					'name'             => '&lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;',
					'slug'             => 'my-term',
					'term_group'       => 0,
					'term_taxonomy_id' => 1,
					'taxonomy'         => self::$taxonomy,
					'description'      => '',
					'parent'           => 0,
					'count'            => 0,
					'filter'           => 'display',
				),
			),
			'slug with uppercase'     => array(
				array(
					'term_id'          => 1,
					'name'             => 'My Term',
					'slug'             => 'MY-TERM',
					'term_group'       => 0,
					'term_taxonomy_id' => 1,
					'taxonomy'         => self::$taxonomy,
					'description'      => '',
					'parent'           => 0,
					'count'            => 0,
					'filter'           => 'raw',
				),
				array(
					'term_id'          => 1,
					'name'             => 'My Term',
					'slug'             => 'my-term',
					'term_group'       => 0,
					'term_taxonomy_id' => 1,
					'taxonomy'         => self::$taxonomy,
					'description'      => '',
					'parent'           => 0,
					'count'            => 0,
					'filter'           => 'display',
				),
			),
			'slug with special char'  => array(
				array(
					'term_id'          => 1,
					'name'             => 'My Term',
					'slug'             => 'MY-!@#$%',
					'term_group'       => 0,
					'term_taxonomy_id' => 1,
					'taxonomy'         => self::$taxonomy,
					'description'      => '',
					'parent'           => 0,
					'count'            => 0,
					'filter'           => 'raw',
				),
				array(
					'term_id'          => 1,
					'name'             => 'My Term',
					'slug'             => 'my',
					'term_group'       => 0,
					'term_taxonomy_id' => 1,
					'taxonomy'         => self::$taxonomy,
					'description'      => '',
					'parent'           => 0,
					'count'            => 0,
					'filter'           => 'display',
				),
			),
			'description with script' => array(
				array(
					'term_id'          => 1,
					'name'             => 'My Term',
					'slug'             => 'my-term',
					'term_group'       => 0,
					'term_taxonomy_id' => 1,
					'taxonomy'         => self::$taxonomy,
					'description'      => '<script>alert("XSS")</script>',
					'parent'           => 0,
					'count'            => 0,
					'filter'           => 'raw',
				),
				array(
					'term_id'          => 1,
					'name'             => 'My Term',
					'slug'             => 'my-term',
					'term_group'       => 0,
					'term_taxonomy_id' => 1,
					'taxonomy'         => self::$taxonomy,
					'description'      => '<p><script>alert("XSS")</script></p>
',
					'parent'           => 0,
					'count'            => 0,
					'filter'           => 'display',
				),
			),
			'description with html'   => array(
				array(
					'term_id'          => 1,
					'name'             => 'My Term',
					'slug'             => 'my-term',
					'term_group'       => 0,
					'term_taxonomy_id' => 1,
					'taxonomy'         => self::$taxonomy,
					'description'      => '<p>My description</p>',
					'parent'           => 0,
					'count'            => 0,
					'filter'           => 'raw',
				),
				array(
					'term_id'          => 1,
					'name'             => 'My Term',
					'slug'             => 'my-term',
					'term_group'       => 0,
					'term_taxonomy_id' => 1,
					'taxonomy'         => self::$taxonomy,
					'description'      => '<p>My description</p>
',
					'parent'           => 0,
					'count'            => 0,
					'filter'           => 'display',
				),
			),

		);
	}
}
