<?php
/**
 * Fuzz tests covering HTML API parsing.
 *
 * @package WordPress
 * @subpackage HTML-API
 *
 * @since 7.1.0
 */

/**
 * @group html-api-fuzzing
 *
 * @coversDefaultClass WP_HTML_Processor
 */
class Tests_HtmlApi_ParserFuzzing extends WP_UnitTestCase {
	private $seed;

	private $end_at;

	public function set_up() {
		parent::set_up();

		$this->seed = intval( getenv( 'FUZZ_SEED' ) );
		$duration   = intval( getenv( 'FUZZ_DURATION' ) );

		if ( 0 === $this->seed && $duration <= 0 ) {
			$this->markTestSkipped( 'Fuzz test only runs when given a positive duration or specific seed value.' );
		}

		$this->end_at = time() + $duration;
	}

	/**
	 * Runs the fuzzing suite to verify HTML API parsing.
	 *
	 * @dataProvider data_seeds
	 *
	 * @param int $seed Randomization seed for reproducing test.
	 */
	public function test_run_fuzzer( int $seed ): void {
		static $test_catalog = self::fuzz_tests();

		srand( $seed );

		$fuzz_test    = $test_catalog[ array_rand( $test_catalog ) ];
		call_user_func( $fuzz_test );
	}

	public static function data_seeds(): Generator {
		$end_at = time() + intval( getenv( 'FUZZ_DURATION' ) );
		$budget = 5;

		while ( time() < $end_at && --$budget ) {
			$seed = random_int( 0, 4294967296 );
			yield $seed => array( $seed );
		}
	}

	private static function fuzz_tests(): array {
		return array(
			'self::fuzz_parses_tag',
		);
	}

	/**
	 * Verifies parsing of tag openers.
	 *
	 * @param string $tag_opener Complete HTML snippet with an opening tag.
	 */
	public function fuzz_parses_tag(): void {
		$tag_opener = self::make_tag_opener();
		$dom        = self::dom( $tag_opener );

//		fwrite( STDERR, "\n\e[33mAnalyzing \e[32m{$tag_opener}\e[m" );

		/** @var DOM\HtmlElement $tag */
		$tag = $dom->childNodes[0];

		$processor = WP_HTML_Processor::create_fragment( $tag_opener );
//		$processor = new WP_HTML_Tag_Processor( $tag_opener );
		$this->assertTrue(
			$processor->next_token(),
			'Should have found a complete token in the test input'
		);

		$this->assertSame(
			'#tag',
			$processor->get_token_type(),
			'Should have found a tag as the test input token.'
		);

		$this->assertSame(
			$tag->nodeName,
			$processor->get_tag(),
			'Should have read expected tag name from input.'
		);

		foreach ( $tag->getAttributeNames() as $attribute_name ) {
			$expected = $tag->getAttribute( $attribute_name );
			$actual   = $processor->get_attribute( $attribute_name );

			$this->assertTrue(
				isset( $actual ),
				"Expected to find attribute '{$attribute_name}' on test input."
			);

			if ( '' === $expected ) {
				$this->assertTrue(
					$actual,
					"Should have found boolean attribute '{$attribute_name}'."
				);
			} else {
				$this->assertSame(
					$expected,
					$actual,
					"Should have detected and properly decoded attribute '{$attribute_name}'."
				);
			}
		}
	}

	public static function make_tag_opener(): string {
		$tag_name  = self::make_html_tag_name();
		$arg_count = random_int( 0, 8 );

		if ( 0 === $arg_count ) {
			return "<{$tag_name}>";
		}

		$tag   = "<{$tag_name}";
		$attrs = array();
		for ( $i = 0; $i < $arg_count; $i++ ) {
			$name    = self::make_attribute_name();
			$attrs[] = self::make_ws() . $name;

			if ( random_int( 0, 100 ) < 10 ) {
				$attrs[] = self::make_ws() . $name;
				if ( random_int( 0, 100 ) < 50 ) {
					$attrs[] = self::make_ws() . strtoupper( $name );
				}
			}
		}

		shuffle( $attrs );
		$tag .= implode( '', $attrs );

		$ws     = random_int( 0, 100 ) < 10 ? self::make_ws() : '';
		$closer = random_int( 0, 100 ) < 20 ? '/' : '';

		return "{$tag}{$ws}{$closer}>";
	}

	public static function make_ws(): string {
		$length = random_int( 1, 4 );
		$ws     = '';

		for ( $i = 0; $i < $length; $i++ ) {
			$ws .= " \t\r\n\f"[ random_int( 0, 4 ) ];
		}

		return $ws;
	}

	public static function make_attribute_name(): string {
		$choice = random_int( 0, 100 );

		if ( $choice < 80 ) {
			return self::make_typical_attribute_name();
		}

		return self::make_random_attribute_name();
	}

	public static function make_typical_attribute_name(): string {
		$attribute_names = array(
			'alt',
			'id',
			'src',
			'source',
			'class',
			'data-post-id',
		);

		return $attribute_names[ array_rand( $attribute_names ) ];
	}

	public static function make_random_attribute_name(): string {
		$forbidden = "=/> \t\f\r\n";

		$name   = '';
		$length = random_int( 1, 14 );
		for ( $i = 0; $i < $length; $i++ ) {
			$c = mb_chr( random_int( 0, 1114111 ) );
			if ( str_contains( $forbidden, $c ) ) {
				--$i;
				continue;
			}

			$name .= $c;
		}

		return $name;
	}

	public static function make_html_tag_name(): string {
		$html_tag_names = array(
			'a',
			'abbr',
			'article',
			'b',
			'div',
			'em',
			'i',
			'link',
			'main',
			'strong',
			'template',
			'section',
		);

		return $html_tag_names[ array_rand( $html_tag_names ) ];
	}

	private static function dom( string $html ) {
		if ( ! ( class_exists( 'DOM\HTMLDocument' ) && defined( 'LIBXML_NOERROR' ) && defined( 'LIBXML_HTML_NOIMPLIED' ) ) ) {
			throw new Error( 'Cannot run tests without DOM support.' );
		}

		return DOM\HTMLDocument::createFromString( $html, LIBXML_NOERROR | LIBXML_HTML_NOIMPLIED );
	}
}
