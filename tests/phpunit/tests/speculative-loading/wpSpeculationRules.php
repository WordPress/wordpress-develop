<?php
/**
 * Tests for the WP_Speculation_Rules class.
 *
 * @package WordPress
 * @subpackage Speculative Loading
 */

/**
 * @group speculative-loading
 * @coversDefaultClass WP_Speculation_Rules
 */
class Tests_Speculative_Loading_wpSpeculationRules extends WP_UnitTestCase {

	/**
	 * Tests that adding a speculation rule is subject to the expected validation.
	 *
	 * @ticket 62503
	 * @covers ::add_rule
	 * @dataProvider data_add_rule
	 */
	public function test_add_rule( string $mode, string $id, array $rule, bool $expected ) {
		$speculation_rules = new WP_Speculation_Rules();

		if ( ! $expected ) {
			$this->setExpectedIncorrectUsage( 'WP_Speculation_Rules::add_rule' );
		}

		$result = $speculation_rules->add_rule( $mode, $id, $rule );
		if ( $expected ) {
			$this->assertTrue( $result );
		} else {
			$this->assertFalse( $result );
		}
	}

	/**
	 * Tests that adding a speculation rule with a duplicate ID results in the expected behavior.
	 *
	 * @ticket 62503
	 * @covers ::add_rule
	 */
	public function test_add_rule_with_duplicate() {
		$speculation_rules = new WP_Speculation_Rules();

		$this->assertTrue( $speculation_rules->add_rule( 'prerender', 'my-custom-rule', array( 'where' => array( 'href_matches' => '/*' ) ) ) );

		// It should be possible to add a rule of the same ID for another mode.
		$this->assertTrue( $speculation_rules->add_rule( 'prefetch', 'my-custom-rule', array( 'where' => array( 'href_matches' => '/*' ) ) ) );

		// But it should not be possible to add a rule of the same ID to a mode where it's already present.
		$this->setExpectedIncorrectUsage( 'WP_Speculation_Rules::add_rule' );
		$this->assertFalse( $speculation_rules->add_rule( 'prerender', 'my-custom-rule', array( 'urls' => array( 'https://important-url.com/' ) ) ) );
	}

	public static function data_add_rule(): array {
		return array(
			'basic-prefetch'               => array(
				'prefetch',
				'test-rule-1',
				array(
					'source'    => 'document',
					'where'     => array( 'selector_matches' => '.prefetch' ),
					'eagerness' => 'eager',
				),
				true,
			),
			'basic-prefetch-no-source'     => array(
				'prefetch',
				'test-rule-2',
				array(
					'where'     => array( 'selector_matches' => '.prefetch' ),
					'eagerness' => 'eager',
				),
				true,
			),
			'basic-prefetch-no-eagerness'  => array(
				'prefetch',
				'test-rule-3',
				array(
					'source' => 'document',
					'where'  => array( 'selector_matches' => '.prefetch' ),
				),
				true,
			),
			'basic-prerender'              => array(
				'prerender',
				'test-rule-1',
				array(
					'source'    => 'list',
					'urls'      => array( 'https://example.org/high-priority-url/', 'https://example.org/another-high-priority-url/' ),
					'eagerness' => 'eager',
				),
				true,
			),
			'basic-prerender-no-source'    => array(
				'prerender',
				'test-rule-2',
				array(
					'urls'      => array( 'https://example.org/high-priority-url/', 'https://example.org/another-high-priority-url/' ),
					'eagerness' => 'eager',
				),
				true,
			),
			'basic-prerender-no-eagerness' => array(
				'prerender',
				'test-rule-3',
				array(
					'source' => 'list',
					'urls'   => array( 'https://example.org/high-priority-url/', 'https://example.org/another-high-priority-url/' ),
				),
				true,
			),
			'invalid-mode'                 => array(
				'load-fast', // Only 'prefetch' and 'prerender' are allowed.
				'test-rule-1',
				array(
					'source'    => 'document',
					'where'     => array( 'selector_matches' => '.prefetch' ),
					'eagerness' => 'eager',
				),
				false,
			),
			'invalid-id-characters'        => array(
				'prefetch',
				'test rule 1', // Spaces are not allowed.
				array(
					'source'    => 'document',
					'where'     => array( 'selector_matches' => '.prefetch' ),
					'eagerness' => 'eager',
				),
				false,
			),
			'invalid-id-start'             => array(
				'prefetch',
				'1_test_rule', // The first character must be a lower-case letter.
				array(
					'source'    => 'document',
					'where'     => array( 'selector_matches' => '.prefetch' ),
					'eagerness' => 'eager',
				),
				false,
			),
			'invalid-source'               => array(
				'prerender',
				'test-rule-1',
				array(
					'source'    => 'magic', // Only 'list' and 'document' are allowed.
					'where'     => array( 'selector_matches' => '.prerender' ),
					'eagerness' => 'eager',
				),
				false,
			),
			'missing-keys'                 => array(
				'prefetch',
				'test-rule-1',
				array(), // The minimum requirements are presence of either a 'where' or 'urls' key.
				false,
			),
			'conflicting-keys'             => array(
				'prefetch',
				'test-rule-1',
				array( // Only 'where' or 'urls' is allowed, but not both.
					'where' => array( 'selector_matches' => '.prefetch' ),
					'urls'  => array( 'https://example.org/high-priority-url/', 'https://example.org/another-high-priority-url/' ),
				),
				false,
			),
			'conflicting-list-source'      => array(
				'prefetch',
				'test-rule-1',
				array(
					'source'    => 'list', // Source 'list' can only be used with key 'urls', but not 'where'.
					'where'     => array( 'selector_matches' => '.prefetch' ),
					'eagerness' => 'eager',
				),
				false,
			),
			'conflicting-document-source'  => array(
				'prefetch',
				'test-rule-1',
				array(
					'source'    => 'document', // Source 'document' can only be used with key 'where', but not 'urls'.
					'urls'      => array( 'https://example.org/high-priority-url/', 'https://example.org/another-high-priority-url/' ),
					'eagerness' => 'eager',
				),
				false,
			),
			'invalid-eagerness'            => array(
				'prefetch',
				'test-rule-1',
				array(
					'source'    => 'document',
					'where'     => array( 'selector_matches' => '.prefetch' ),
					'eagerness' => 'fast', // Only 'immediate', 'eager, 'moderate', and 'conservative' are allowed.
				),
				false,
			),
			'immediate-eagerness-list'     => array(
				'prefetch',
				'test-rule-1',
				array(
					'source'    => 'list',
					'urls'      => array( 'https://example.org/high-priority-url/', 'https://example.org/another-high-priority-url/' ),
					'eagerness' => 'immediate',
				),
				true,
			),
			// 'immediate' is a valid eagerness, but for safety WordPress does not allow it for document-level rules.
			'immediate-eagerness-document' => array(
				'prefetch',
				'test-rule-1',
				array(
					'source'    => 'document',
					'where'     => array( 'selector_matches' => '.prefetch' ),
					'eagerness' => 'immediate',
				),
				false,
			),
		);
	}

	/**
	 * Tests that checking for existence of a rule works as expected.
	 *
	 * @ticket 62503
	 * @covers ::has_rule
	 */
	public function test_has_rule() {
		$speculation_rules = new WP_Speculation_Rules();

		$this->assertFalse( $speculation_rules->has_rule( 'prerender', 'my-custom-rule' ), 'Custom rule should not be marked as present before it is added' );

		$speculation_rules->add_rule( 'prerender', 'my-custom-rule', array( 'urls' => array( 'https://url-to-prerender.com/' ) ) );
		$this->assertTrue( $speculation_rules->has_rule( 'prerender', 'my-custom-rule' ), 'Custom rule should be marked as present after it has been added' );
		$this->assertFalse( $speculation_rules->has_rule( 'prefetch', 'my-custom-rule' ), 'Custom rule should not be marked as present for different mode even after it has been added' );
	}

	/**
	 * Tests that transforming a speculation rules object into JSON-encodable data works as expected.
	 *
	 * @ticket 62503
	 * @covers ::jsonSerialize
	 */
	public function test_jsonSerialize() {
		$prefetch_rule_1  = array( 'where' => array( 'href_matches' => '/*' ) );
		$prefetch_rule_2  = array( 'where' => array( 'selector_matches' => '.prefetch-opt-in' ) );
		$prerender_rule_1 = array( 'urls' => array( 'https://example.org/high-priority-url/', 'https://example.org/another-high-priority-url/' ) );
		$prerender_rule_2 = array(
			'where'     => array(
				'or' => array(
					array( 'selector_matches' => '.prerender-opt-in' ),
					array( 'selector_matches' => '.prerender-fast' ),
				),
			),
			'eagerness' => 'moderate',
		);

		$speculation_rules = new WP_Speculation_Rules();
		$this->assertSame( array(), $speculation_rules->jsonSerialize(), 'Speculation rules JSON data should be empty before adding any rules' );

		$speculation_rules->add_rule( 'prefetch', 'prefetch-rule-1', $prefetch_rule_1 );
		$this->assertSame(
			array(
				'prefetch' => array( $prefetch_rule_1 ),
			),
			$speculation_rules->jsonSerialize(),
			'Speculation rules JSON data should only contain a single "prefetch" entry when only that rule is added'
		);

		$speculation_rules->add_rule( 'prefetch', 'prefetch-rule-2', $prefetch_rule_2 );
		$speculation_rules->add_rule( 'prerender', 'prerender-rule-1', $prerender_rule_1 );
		$speculation_rules->add_rule( 'prerender', 'prerender-rule-2', $prerender_rule_2 );
		$this->assertSame(
			array(
				'prefetch'  => array(
					$prefetch_rule_1,
					$prefetch_rule_2,
				),
				'prerender' => array(
					$prerender_rule_1,
					$prerender_rule_2,
				),
			),
			$speculation_rules->jsonSerialize(),
			'Speculation rules JSON data should contain all added rules'
		);
	}

	/**
	 * Tests that the mode validation method correctly identifies valid and invalid values.
	 *
	 * @ticket 62503
	 * @covers ::is_valid_mode
	 * @dataProvider data_is_valid_mode
	 */
	public function test_is_valid_mode( $mode, $expected ) {
		if ( $expected ) {
			$this->assertTrue( WP_Speculation_Rules::is_valid_mode( $mode ) );
		} else {
			$this->assertFalse( WP_Speculation_Rules::is_valid_mode( $mode ) );
		}
	}

	public static function data_is_valid_mode(): array {
		return array(
			'prefetch'     => array( 'prefetch', true ),
			'prerender'    => array( 'prerender', true ),
			'auto'         => array( 'auto', false ),
			'none'         => array( 'none', false ),
			'42'           => array( 42, false ),
			'empty string' => array( '', false ),
		);
	}

	/**
	 * Tests that the eagerness validation method correctly identifies valid and invalid values.
	 *
	 * @ticket 62503
	 * @covers ::is_valid_eagerness
	 * @dataProvider data_is_valid_eagerness
	 */
	public function test_is_valid_eagerness( $eagerness, $expected ) {
		if ( $expected ) {
			$this->assertTrue( WP_Speculation_Rules::is_valid_eagerness( $eagerness ) );
		} else {
			$this->assertFalse( WP_Speculation_Rules::is_valid_eagerness( $eagerness ) );
		}
	}

	public static function data_is_valid_eagerness(): array {
		return array(
			'conservative' => array( 'conservative', true ),
			'moderate'     => array( 'moderate', true ),
			'eager'        => array( 'eager', true ),
			'immediate'    => array( 'immediate', true ),
			'auto'         => array( 'auto', false ),
			'none'         => array( 'none', false ),
			'42'           => array( 42, false ),
			'empty string' => array( '', false ),
		);
	}

	/**
	 * Tests that the source validation method correctly identifies valid and invalid values.
	 *
	 * @ticket 62503
	 * @covers ::is_valid_source
	 * @dataProvider data_is_valid_source
	 */
	public function test_is_valid_source( $source, $expected ) {
		if ( $expected ) {
			$this->assertTrue( WP_Speculation_Rules::is_valid_source( $source ) );
		} else {
			$this->assertFalse( WP_Speculation_Rules::is_valid_source( $source ) );
		}
	}

	public static function data_is_valid_source(): array {
		return array(
			'list'         => array( 'list', true ),
			'document'     => array( 'document', true ),
			'auto'         => array( 'auto', false ),
			'none'         => array( 'none', false ),
			'42'           => array( 42, false ),
			'empty string' => array( '', false ),
		);
	}
}
