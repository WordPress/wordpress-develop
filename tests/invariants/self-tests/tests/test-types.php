<?php
/**
 * Self-tests of the types report: what it hooks, how it counts types, and how it reports them.
 *
 * The functions in ../fixtures/types/ are hooked as WordPress's functions are,
 * and this file is the test code which calls them.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class Tests_Invariants_Types extends TestCase {

	/**
	 * What was collected before each test, which the test replaces.
	 *
	 * @var array[]
	 */
	private $saved;

	public function setUp(): void {
		$this->saved = WP_Invariant_Types::profile();
		WP_Invariant_Types::set_profile( array() );
	}

	public function tearDown(): void {
		WP_Invariant_Types::set_profile( $this->saved );
	}

	public function test_functions_in_finds_and_marks_every_function_and_method() {
		$found = array();
		foreach ( WP_Invariant::functions_in( array( dirname( __DIR__ ) . '/fixtures/types' ) ) as $function ) {
			$markers = array( $function['visibility'] );
			foreach ( array( 'static', 'magic', 'private', 'deprecated' ) as $marker ) {
				if ( $function[ $marker ] ) {
					$markers[] = $marker;
				}
			}
			$found[ $function['target'] ] = implode( ' ', $markers );
		}
		ksort( $found );

		$expected = array(
			'wp_invariants_types_fixture'              => 'public',
			'wp_invariants_types_fixture_caller'       => 'public',
			'wp_invariants_types_fixture_extra'        => 'public',
			'wp_invariants_types_fixture_throws'       => 'public',
			'_wp_invariants_types_fixture_private'     => 'public private',
			'wp_invariants_types_fixture_documented_private' => 'public private deprecated',
			'wp_invariants_types_fixture_fallback'     => 'public',
			'wp_invariants_types_fixture_reference'    => 'public',
			'WP_Invariants_Types_Fixture::instance'    => 'public',
			'WP_Invariants_Types_Fixture::make'        => 'public static',
			'WP_Invariants_Types_Fixture::hidden'      => 'protected',
			'WP_Invariants_Types_Fixture::secret'      => 'private',
			'WP_Invariants_Types_Fixture::__get'       => 'public magic',
			'WP_Invariants_Types_Private_Fixture::run' => 'public private',
		);
		ksort( $expected );
		$this->assertSame( $expected, $found );
	}

	public function test_booleans_are_true_or_false_until_both_are_seen() {
		$slot = $this->slot( true, true );
		$this->assertTypes( array( 'true' => 2 ), $slot );

		WP_Invariant_Types::add_to_slot( $slot, false, WP_Invariant_Types::CORE );
		$this->assertTypes( array( 'bool' => 3 ), $slot );
	}

	public function test_ints_are_literals_until_five_distinct_ones_are_seen() {
		$values = array( 0, -1, 0, 7, 42 );
		$this->assertTypes(
			array(
				'0'  => 2,
				'-1' => 1,
				'7'  => 1,
				'42' => 1,
			),
			$this->slot( ...$values ),
			'Four distinct ints are literals.'
		);

		$values[] = 3;
		$this->assertTypes(
			array(
				'int [zero]'     => 2,
				'int [non-zero]' => 4,
			),
			$this->slot( ...$values ),
			'A fifth distinct int makes them all int, counted by whether they are zero.'
		);

		$slot = $this->slot( ...$values );
		WP_Invariant_Types::add_to_slot( $slot, 0, WP_Invariant_Types::CORE );
		$this->assertTypes(
			array(
				'int [zero]'     => 3,
				'int [non-zero]' => 4,
			),
			$slot,
			'Once widened, ints seen before are int too.'
		);
	}

	public function test_floats_are_never_literals() {
		$this->assertTypes(
			array(
				'float' => 3,
				'1'     => 1,
			),
			$this->slot( 1.0, 1.5, 1.0, 1 )
		);
	}

	public function test_strings_are_literals_until_there_are_too_many() {
		$values = array( 'post', 'page', 'post' );
		$this->assertTypes(
			array(
				"'post'" => 2,
				"'page'" => 1,
			),
			$this->slot( ...$values )
		);

		for ( $i = 0; $i < WP_Invariant_Types::STRING_LITERALS; $i++ ) {
			$values[] = "value-{$i}";
		}
		$this->assertTypes( array( 'string [non-empty]' => 13 ), $this->slot( ...$values ) );
	}

	public function test_strings_are_literals_until_one_is_too_long() {
		$this->assertTypes( array( 'string [non-empty]' => 2 ), $this->slot( 'short', str_repeat( 'x', WP_Invariant_Types::STRING_LENGTH ) ) );
		$this->assertTypes( array( "'" . str_repeat( 'x', 31 ) . "'" => 1 ), $this->slot( str_repeat( 'x', 31 ) ) );
	}

	/**
	 * @dataProvider data_string_properties
	 *
	 * @param string   $value    A string.
	 * @param string[] $expected Its tags.
	 */
	public function test_strings_are_tagged_by_their_most_specific_properties( $value, array $expected ) {
		self::load_html_api();

		$this->assertSame( $expected, WP_Invariant_Types_Report::tags( 'string', WP_Invariant_Types::string_properties( $value ) ) );
	}

	/**
	 * @return array[]
	 */
	public static function data_string_properties() {
		return array(
			'empty'                      => array( '', array( 'empty' ) ),
			'plain'                      => array( 'post', array( 'non-empty' ) ),
			'an integer'                 => array( '42', array( 'numeric' ) ),
			'a decimal'                  => array( '-1.5', array( 'numeric' ) ),
			'an exponent'                => array( '1e3', array( 'numeric' ) ),
			'a number with a space'      => array( ' 7', array( 'numeric' ) ),
			'hexadecimal'                => array( '0x1A', array( 'non-empty' ) ),
			'a number and letters'       => array( '12abc', array( 'non-empty' ) ),
			'a tag'                      => array( '<p>x</p>', array( 'html' ) ),
			'a comment'                  => array( 'a <!-- b --> c', array( 'html' ) ),
			'a less-than sign'           => array( 'a < b', array( 'non-empty' ) ),
			'a tag cut off'              => array( 'a <p', array( 'non-empty' ) ),
			'a character reference'      => array( 'x &amp; y', array( 'ref' ) ),
			'a legacy reference'         => array( 'x &amp y', array( 'ref' ) ),
			'an ampersand'               => array( 'AT&T', array( 'non-empty' ) ),
			'a quote'                    => array( "it's", array( 'slash' ) ),
			'a backslash'                => array( 'a\\b', array( 'unslash' ) ),
			'a URL'                      => array( 'https://example.org/', array( 'url' ) ),
			'a URL without an authority' => array( 'mailto:a@example.org', array( 'url' ) ),
			'a URL with a newline'       => array( "java\nscript:x", array( 'url' ) ),
			'a URL with a bad host'      => array( 'https://[nope/', array( 'non-empty' ) ),
			'a URL path'                 => array( '/wp-admin/', array( 'non-empty' ) ),
			'a file'                     => array( __FILE__, array( 'file' ) ),
			'a path to nothing'          => array( __DIR__ . '/missing.php', array( 'path' ) ),
			'a relative path'            => array( './missing.php', array( 'path' ) ),
			'a Windows path, and a URL'  => array( 'C:\\missing.php', array( 'url', 'path', 'unslash' ) ),
			'a stream wrapper'           => array( 'phar://' . __FILE__, array( 'url' ) ),
			'invalid UTF-8'              => array( "\xFF", array( '-utf8' ) ),
			'markup with a quote'        => array( "<p>it's</p>", array( 'html', 'slash' ) ),
			'markup with every property' => array( "<a href=\"x\">&amp;\\\xFF</a>", array( 'html', 'ref', 'unslash', '-utf8' ) ),
		);
	}

	public function test_a_path_is_looked_at_on_every_call() {
		$file = tempnam( sys_get_temp_dir(), 'types' );
		$this->assertSame( array( 'file' ), WP_Invariant_Types_Report::tags( 'string', WP_Invariant_Types::string_properties( $file ) ) );

		unlink( $file );
		$this->assertSame( array( 'path' ), WP_Invariant_Types_Report::tags( 'string', WP_Invariant_Types::string_properties( $file ) ), 'Whether a file exists is not remembered.' );
	}

	public function test_urls_with_opaque_paths_do_not_use_up_memory() {
		$before = memory_get_usage();
		for ( $i = 0; $i < 5000; $i++ ) {
			$this->assertSame( WP_Invariant_Types::STRING_URL, WP_Invariant_Types::string_properties( "mailto:{$i}" . str_repeat( 'x', WP_Invariant_Types::REMEMBERED_LENGTH ) ) );
		}

		$this->assertLessThan( 200000, memory_get_usage() - $before, 'PHP 8.5 keeps the memory of each URL with an opaque path which it parses.' );
	}

	public function test_classifying_strings_leaves_the_pcre_error_alone() {
		self::load_html_api();

		$limit = ini_get( 'pcre.backtrack_limit' );
		ini_set( 'pcre.backtrack_limit', '1' );
		preg_match( '/(?:\D+|<\d+>)*[!?]/', 'foobar foobar foobar' );
		ini_set( 'pcre.backtrack_limit', $limit );
		$this->assertSame( PREG_BACKTRACK_LIMIT_ERROR, preg_last_error() );

		WP_Invariant_Types::string_properties( '<p class="x">it&#39;s at https://example.org/</p>' . uniqid() );
		$this->assertSame( PREG_BACKTRACK_LIMIT_ERROR, preg_last_error() );
		preg_match( '/^/', '' );
	}

	public function test_widened_strings_are_counted_by_their_properties_from_the_first() {
		self::load_html_api();

		$values = array( '', '', '<b>', 'it\'s', 'post', '42' );
		for ( $i = 0; $i < WP_Invariant_Types::STRING_LITERALS; $i++ ) {
			$values[] = "value-{$i}";
		}

		$this->assertTypes(
			array(
				'string [empty]'     => 2,
				'string [html]'      => 1,
				'string [slash]'     => 1,
				'string [numeric]'   => 1,
				'string [non-empty]' => 11,
			),
			$this->slot( ...$values )
		);
	}

	public function test_scalars_objects_and_resources_are_their_types() {
		$this->assertTypes(
			array(
				'null'                        => 1,
				'1'                           => 1,
				'float'                       => 1,
				'Closure'                     => 1,
				'WP_Invariants_Types_Fixture' => 1,
				'resource'                    => 1,
			),
			$this->slot( null, 1, 1.5, static function () {}, new WP_Invariants_Types_Fixture(), STDERR )
		);
	}

	public function test_arrays_are_empty_lists_or_records() {
		$this->assertTypes(
			array(
				'array{a: int, b?: string|null}'    => 3,
				'array{}'                           => 1,
				'list<int|string>'                  => 1,
				'list<WP_Invariants_Types_Fixture>' => 1,
			),
			$this->slot(
				array(),
				array( 1, 'two' ),
				array( new WP_Invariants_Types_Fixture() ),
				array(
					'a' => 1,
					'b' => 'x',
				),
				array(
					'a' => 2,
					'b' => null,
				),
				array( 'a' => 3 )
			)
		);
	}

	public function test_records_widen_to_array_past_the_key_limit() {
		$slot = $this->slot( array( 'a' => 1 ) );
		$wide = array();
		for ( $i = 0; $i < WP_Invariant_Types::RECORD_KEYS; $i++ ) {
			$wide[ "key{$i}" ] = $i;
		}

		WP_Invariant_Types::add_to_slot( $slot, $wide, WP_Invariant_Types::CORE );
		$this->assertTypes( array( 'array' => 2 ), $slot );

		WP_Invariant_Types::add_to_slot( $slot, array( 'b' => 1 ), WP_Invariant_Types::CORE );
		$this->assertTypes( array( 'array' => 3 ), $slot, 'Once widened, later records are plain arrays too.' );
	}

	public function test_a_field_with_too_many_types_is_mixed() {
		$values = array( null, 1, 1.5, 'x', array(), array( 1 ), array( 'a' => 1 ), new stdClass(), static function () {} );
		$slot   = WP_Invariant_Types::empty_slot();
		foreach ( $values as $value ) {
			WP_Invariant_Types::add_to_slot( $slot, array( 'field' => $value ), WP_Invariant_Types::CORE );
		}

		$this->assertTypes( array( 'array{field: mixed}' => 9 ), $slot );
	}

	public function test_stdclass_is_a_record_of_its_properties() {
		$this->assertTypes(
			array( 'stdClass{ID: int, name?: string}' => 2 ),
			$this->slot(
				(object) array( 'ID' => 1 ),
				(object) array(
					'ID'   => 2,
					'name' => 'x',
				)
			)
		);
	}

	public function test_merging_follows_the_same_widening_rules() {
		$first  = $this->slot( 'a', 'b', 'c', 'd', 'e', 'f', array( 'x' => 1 ), true, 1, 2 );
		$second = $this->slot( 'g', 'h', 'i', 'j', 'k', 'a', array( 'y' => 'z' ), false, 3, 4, 5 );

		$this->assertTypes(
			array(
				'string [non-empty]'         => 12,
				'array{x?: int, y?: string}' => 2,
				'bool'                       => 2,
				'int [non-zero]'             => 5,
			),
			WP_Invariant_Types::merge_slots( $first, $second )
		);

		$this->assertTypes(
			array(
				'int [zero]'     => 2,
				'int [non-zero]' => 4,
			),
			WP_Invariant_Types::merge_slots( $this->slot( 0, 1, 2 ), $this->slot( 0, 3, 4 ) ),
			'Properties are counted across processes, and their literals widen together.'
		);

		$this->assertTypes(
			array(
				'1' => 2,
				'2' => 1,
				'3' => 1,
			),
			WP_Invariant_Types::merge_slots( $this->slot( 1, 2 ), $this->slot( 3, 1 ) ),
			'Ints stay literals while there are no more than four distinct ones.'
		);
	}

	public function test_calls_are_counted_from_core_and_from_tests() {
		wp_invariants_types_fixture( 'from a test', array( 'x' => 1 ) );
		wp_invariants_types_fixture_caller( 5 );
		wp_invariants_types_fixture_caller( 'a', array(), 'extra', 'more' );

		$profile = WP_Invariant_Types::profile()['wp_invariants_types_fixture'];
		$this->assertSame( array( 2, 1 ), $profile['calls'], 'Two calls from the fixture, which stands in for WordPress, and one from this test.' );
		$arguments = $profile['arguments'];
		ksort( $arguments );
		$this->assertSame(
			array(
				1 => array( 1, 0 ),
				2 => array( 0, 1 ),
				4 => array( 1, 0 ),
			),
			$arguments
		);
		$this->assertSame( array( 5 => array( 1, 0 ) ), $profile['slots'][0]['ints']['values'] );
		$this->assertSame(
			array(
				'from a test' => array( 0, 1 ),
				'a'           => array( 1, 0 ),
			),
			$profile['slots'][0]['strings']['values']
		);
		$this->assertArrayHasKey( 3, $profile['slots'], 'Every variadic argument is counted.' );
		$this->assertSame( array( 'from a test', 5, 'a' ), array_keys( $profile['slots']['return']['strings']['values'] ), 'A numeric string literal is an integer key, which the report turns back into a string.' );
	}

	public function test_methods_count_their_arguments_without_the_object() {
		( new WP_Invariants_Types_Fixture() )->instance( 1.5 );
		WP_Invariants_Types_Fixture::make( 'name' );

		$profile = WP_Invariant_Types::profile();
		$this->assertSame( array( 'float' => array( 0, 1 ) ), $profile['WP_Invariants_Types_Fixture::instance']['slots'][0]['types'] );
		$this->assertSame( array( 'float' => array( 0, 1 ) ), $profile['WP_Invariants_Types_Fixture::instance']['slots']['return']['types'] );
		$this->assertSame( array( 'name' => array( 0, 1 ) ), $profile['WP_Invariants_Types_Fixture::make']['slots'][0]['strings']['values'] );
		$this->assertSame( array( 'WP_Invariants_Types_Fixture' => array( 0, 1 ) ), $profile['WP_Invariants_Types_Fixture::make']['slots']['return']['types'] );
	}

	public function test_a_call_which_throws_has_its_arguments_counted_but_no_return_value() {
		try {
			wp_invariants_types_fixture_throws( 'reason' );
		} catch ( RuntimeException $e ) {
			// Expected.
			unset( $e );
		}

		$profile = WP_Invariant_Types::profile()['wp_invariants_types_fixture_throws'];
		$this->assertSame( array( 0, 1 ), $profile['calls'] );
		$this->assertArrayNotHasKey( 'return', $profile['slots'] );
	}

	public function test_doc_types_reads_phpdoc_and_phpstan_types() {
		$types = WP_Invariant_Types_Report::doc_types( ( new ReflectionFunction( 'wp_invariants_types_fixture' ) )->getDocComment() );

		$this->assertSame(
			array(
				'param'          => array(
					'value'   => 'string|int',
					'options' => 'array',
					'rest'    => 'mixed',
				),
				'phpstan-param'  => array(
					'value'   => 'non-empty-string|int',
					'options' => 'array{ x?: int, }',
				),
				'return'         => 'string',
				'phpstan-return' => 'non-empty-string',
			),
			$types
		);
	}

	public function test_report_lists_types_next_to_documented_types() {
		wp_invariants_types_fixture( 'post' );
		wp_invariants_types_fixture_caller( 'post', array( 'x' => 1 ) );
		wp_invariants_types_fixture_caller( null );
		wp_invariants_types_fixture_extra( 1, 'more' );

		$data = WP_Invariant_Types_Report::data( WP_Invariant_Types::hooked(), WP_Invariant_Types::profile() );
		$text = WP_Invariant_Types_Report::markdown( $data );

		$this->assertStringContainsString( 'wp_invariants_types_fixture()  tests/invariants/self-tests/fixtures/types/functions.php:27', $text );
		$this->assertStringContainsString( "    \$value                        @param string|int  @phpstan-param non-empty-string|int\n           2: 'post'                  (1 core, 1 tests)\n           1: null                    (1 core)", $text );
		$this->assertStringContainsString( "    \$options                      @param array  @phpstan-param array{ x?: int, }\n           2: (not passed)            (1 core, 1 tests)\n           1: array{x: int}           (1 core)", $text );
		$this->assertStringContainsString( "    (extra)\n           1: 'more'                  (1 tests)", $text );
		$this->assertStringContainsString( '_wp_invariants_types_fixture_private()', $text );
		$this->assertContains( 'WP_Invariants_Types_Fixture::secret()', $data['never_called'] );
		$this->assertSame(
			array(
				'core'  => 2,
				'tests' => 1,
			),
			$data['functions']['wp_invariants_types_fixture']['calls']
		);
	}

	/**
	 * Loads the HTML API, which WordPress would have loaded, for WP_Invariant_Types::string_properties().
	 */
	private static function load_html_api() {
		$directory = dirname( __DIR__, 4 ) . '/src/wp-includes';
		require_once "{$directory}/class-wp-token-map.php";
		require_once "{$directory}/html-api/html5-named-character-references.php";
		foreach ( array( 'class-wp-html-attribute-token.php', 'class-wp-html-span.php', 'class-wp-html-text-replacement.php', 'class-wp-html-decoder.php', 'class-wp-html-doctype-info.php', 'class-wp-html-tag-processor.php' ) as $file ) {
			require_once "{$directory}/html-api/{$file}";
		}
	}

	/**
	 * Counts values in a new slot, as calls from WordPress.
	 *
	 * @param mixed ...$values Values.
	 * @return array The slot.
	 */
	private function slot( ...$values ) {
		$slot = WP_Invariant_Types::empty_slot();
		foreach ( $values as $value ) {
			WP_Invariant_Types::add_to_slot( $slot, $value, WP_Invariant_Types::CORE );
		}
		return $slot;
	}

	/**
	 * Returns a slot's rows as type => total count, by type.
	 *
	 * @param array $slot A slot.
	 * @return int[]
	 */
	private function types( array $slot ) {
		$types = array();
		foreach ( WP_Invariant_Types_Report::rows( $slot ) as $row ) {
			$types[ $row['type'] ] = $row['core'] + $row['tests'];
		}
		ksort( $types );
		return $types;
	}

	/**
	 * Asserts the types a slot counted, in any order.
	 *
	 * @param int[]  $expected Type => total count.
	 * @param array  $slot     A slot.
	 * @param string $message  Message on failure.
	 */
	private function assertTypes( array $expected, array $slot, $message = '' ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Named as PHPUnit's assertions are.
		ksort( $expected );
		$this->assertSame( $expected, $this->types( $slot ), $message );
	}
}
