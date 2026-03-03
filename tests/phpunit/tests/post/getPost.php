<?php

/**
 * @group post
 * @group template
 *
 * @covers ::get_post
 */
class Tests_Post_GetPost extends WP_UnitTestCase {

	private static int $post_id;

	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		$post_id = self::factory()->post->create();
		assert( is_int( $post_id ) );
		self::$post_id = $post_id;

		global $wpdb;
		$wpdb->update(
			$wpdb->posts,
			array( 'post_title' => 'Test <script>console.log("Hello, World!")</script> Title' ),
			array( 'ID' => self::$post_id )
		);
		clean_post_cache( self::$post_id );
	}

	public function tear_down(): void {
		$GLOBALS['post'] = null;
		parent::tear_down();
	}

	/**
	 * Tests that the global $post is returned.
	 *
	 * @ticket 64238
	 */
	public function test_get_post_global(): void {
		global $post;
		$post = $this->get_test_post_instance();
		$this->assertSame( $post, get_post() );
		$this->assertSame( $post, get_post( null ) );
		$this->assertSame( $post, get_post( 0 ) );
		$this->assertSame( $post, get_post( '0' ) ); // @phpstan-ignore argument.type (Testing another value that is empty.)
		$this->assertSame( $post, get_post( '' ) ); // @phpstan-ignore argument.type (Testing another value that is empty.)
		$this->assertSame( $post, get_post( false ) ); // @phpstan-ignore argument.type (Testing another value that is empty.)
		$this->assertSame( $post->to_array(), get_post( null, ARRAY_A ) );
		$this->assertSame( array_values( $post->to_array() ), get_post( null, ARRAY_N ) );
	}

	/**
	 * Tests inputs and outputs.
	 *
	 * @ticket 64238
	 * @dataProvider data_provider_to_test_get_post
	 *
	 * @param callable(): mixed      $input       Input to get_post.
	 * @param string                 $output      The required return type.
	 * @param string                 $filter      Type of filter to apply.
	 * @param callable(): (int|null) $expected_id Expected ID of the returned post, or null if expecting null.
	 */
	public function test_get_post( callable $input, string $output, string $filter, callable $expected_id ): void {
		$input_val       = $input();
		$expected_id_val = $expected_id();

		$post = get_post( $input_val, $output, $filter );

		if ( null === $expected_id_val ) {
			$this->assertNull( $post );
			return;
		}

		if ( ARRAY_A === $output ) {
			$this->assertIsArray( $post );
			$this->assertArrayHasKey( 'ID', $post );
			$this->assertSame( $expected_id_val, $post['ID'] );
			$this->assertArrayHasKey( 'filter', $post );
			$this->assertSame( $filter, $post['filter'] );
		} elseif ( ARRAY_N === $output ) {
			$this->assertIsArray( $post );
			$this->assertContains( $expected_id_val, $post );
			$this->assertContains( $filter, $post );
		} else {
			$this->assertInstanceOf( WP_Post::class, $post );

			if ( 'raw' === $filter && $input_val instanceof WP_Post ) {
				$this->assertSame( $input_val, $post, 'Should return the same instance when input is a WP_Post and filter is raw.' );
			}

			$this->assertSame( $expected_id_val, $post->ID );
			$this->assertSame( $filter, $post->filter );
		}
	}

	/**
	 * Tests that sanitize_post() is called as expected.
	 *
	 * @ticket 64238
	 * @dataProvider data_provider_to_test_get_post_sanitization
	 *
	 * @param string $filter   Type of filter to apply.
	 * @param string $expected Expected sanitized post title.
	 */
	public function test_get_post_sanitization( string $filter, string $expected ): void {
		$post = get_post( self::$post_id, OBJECT, $filter );

		$this->assertInstanceOf( WP_Post::class, $post );
		$this->assertSame( $expected, $post->post_title );
		$this->assertSame( $filter, $post->filter );
	}

	/**
	 * Data provider for test_get_post_sanitization.
	 *
	 * @return array<string, array{
	 *     filter: string,
	 *     expected: string,
	 * }>
	 */
	public function data_provider_to_test_get_post_sanitization(): array {
		return array(
			'Raw filter'       => array(
				'filter'   => 'raw',
				'expected' => 'Test <script>console.log("Hello, World!")</script> Title',
			),
			'Edit filter'      => array(
				'filter'   => 'edit',
				'expected' => 'Test &lt;script&gt;console.log(&quot;Hello, World!&quot;)&lt;/script&gt; Title',
			),
			'Display filter'   => array(
				'filter'   => 'display',
				'expected' => 'Test <script>console.log("Hello, World!")</script> Title',
			),
			'Attribute filter' => array(
				'filter'   => 'attribute',
				'expected' => 'Test &lt;script&gt;console.log(&quot;Hello, World!&quot;)&lt;/script&gt; Title',
			),
			'JS filter'        => array(
				'filter'   => 'js',
				'expected' => 'Test &lt;script&gt;console.log(&quot;Hello, World!&quot;)&lt;/script&gt; Title',
			),
		);
	}

	/**
	 * Data provider for test_get_post.
	 *
	 * @return array<string, array{
	 *     input: Closure(): mixed,
	 *     output: string,
	 *     filter: string,
	 *     expected_id: Closure(): (int|null),
	 * }>
	 */
	public function data_provider_to_test_get_post(): array {
		return array(
			'Valid ID'                             => array(
				'input'       => fn() => self::$post_id,
				'output'      => OBJECT,
				'filter'      => 'raw',
				'expected_id' => fn() => self::$post_id,
			),
			'WP_Post instance'                     => array(
				'input'       => fn() => $this->get_test_post_instance(),
				'output'      => OBJECT,
				'filter'      => 'raw',
				'expected_id' => fn() => self::$post_id,
			),
			'Valid numeric string ID'              => array(
				'input'       => fn() => (string) self::$post_id,
				'output'      => OBJECT,
				'filter'      => 'raw',
				'expected_id' => fn() => self::$post_id,
			),
			'Object with raw filter'               => array(
				'input'       => fn() => (object) array(
					'ID'     => self::$post_id,
					'filter' => 'raw',
				),
				'output'      => OBJECT,
				'filter'      => 'raw',
				'expected_id' => fn() => self::$post_id,
			),
			'Object with non-raw filter and ID'    => array(
				'input'       => fn() => (object) array(
					'ID'     => self::$post_id,
					'filter' => 'edit',
				),
				'output'      => OBJECT,
				'filter'      => 'raw',
				'expected_id' => fn() => self::$post_id,
			),
			'Object with non-raw filter and NO ID' => array(
				'input'       => fn() => (object) array( 'filter' => 'edit' ),
				'output'      => OBJECT,
				'filter'      => 'raw',
				'expected_id' => fn() => null,
			),
			'Invalid ID'                           => array(
				'input'       => fn() => 9999999,
				'output'      => OBJECT,
				'filter'      => 'raw',
				'expected_id' => fn() => null,
			),
			'ARRAY_A output'                       => array(
				'input'       => fn() => self::$post_id,
				'output'      => ARRAY_A,
				'filter'      => 'raw',
				'expected_id' => fn() => self::$post_id,
			),
			'ARRAY_N output'                       => array(
				'input'       => fn() => self::$post_id,
				'output'      => ARRAY_N,
				'filter'      => 'raw',
				'expected_id' => fn() => self::$post_id,
			),
			'Display filter'                       => array(
				'input'       => fn() => self::$post_id,
				'output'      => OBJECT,
				'filter'      => 'display',
				'expected_id' => fn() => self::$post_id,
			),
			'Empty input and no global post'       => array(
				'input'       => fn() => null,
				'output'      => OBJECT,
				'filter'      => 'raw',
				'expected_id' => fn() => null,
			),
			'0 input and no global post'           => array(
				'input'       => fn() => 0,
				'output'      => OBJECT,
				'filter'      => 'raw',
				'expected_id' => fn() => null,
			),
			'Non-numeric string'                   => array(
				'input'       => fn() => 'not-a-post-id',
				'output'      => OBJECT,
				'filter'      => 'raw',
				'expected_id' => fn() => null,
			),
			'Boolean false'                        => array(
				'input'       => fn() => false,
				'output'      => OBJECT,
				'filter'      => 'raw',
				'expected_id' => fn() => null,
			),
			'Object with invalid ID'               => array(
				'input'       => fn() => (object) array(
					'ID'     => 9999999,
					'filter' => 'edit',
				),
				'output'      => OBJECT,
				'filter'      => 'raw',
				'expected_id' => fn() => null,
			),
			'Object with no filter'                => array(
				'input'       => fn() => (object) array(
					'ID'         => 123,
					'post_title' => 'Test',
					'extra'      => 'prop',
				),
				'output'      => OBJECT,
				'filter'      => 'raw',
				'expected_id' => fn() => 123,
			),
			'Invalid output type'                  => array(
				'input'       => fn() => self::$post_id,
				'output'      => 'invalid',
				'filter'      => 'raw',
				'expected_id' => fn() => self::$post_id,
			),
			'Invalid output value "WP_Post"'       => array(
				'input'       => fn() => self::$post_id,
				'output'      => 'WP_Post',
				'filter'      => 'raw',
				'expected_id' => fn() => self::$post_id,
			),
		);
	}

	/**
	 * Gets a test post instance.
	 *
	 * @return WP_Post Post object.
	 */
	private function get_test_post_instance(): WP_Post {
		$post = WP_Post::get_instance( self::$post_id );
		$this->assertInstanceOf( WP_Post::class, $post );
		return $post;
	}
}
