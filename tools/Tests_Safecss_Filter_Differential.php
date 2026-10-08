<?php
/**
 * Differential: the legacy safecss_filter_attr() path against the parser-based default.
 *
 * The legacy path is selected through the `wp_kses_force_legacy_css_parser` filter.
 *
 * Run: WP_TESTS_SKIP_INSTALL=1 vendor/bin/phpunit tools/Tests_Safecss_Filter_Differential.php
 *
 * Prints every data-provider input whose outputs differ, with a classification:
 * - serialization: legacy and parser re-parse to the same declarations.
 * - narrowing: the parser drops everything legacy kept.
 * - widening: the parser keeps a declaration legacy dropped.
 * - defect: anything else; inspect by hand.
 */

require_once __DIR__ . '/../tests/phpunit/tests/kses.php';

class Tests_Safecss_Filter_Differential extends WP_UnitTestCase {
	public function test_differential() {
		$kses   = new Tests_Kses( 'test_differential' );
		$inputs = array();
		foreach ( array( 'data_safecss_filter_attr', 'data_kses_style_attr_with_url' ) as $provider ) {
			foreach ( $kses->$provider() as $row ) {
				$row = array_values( $row );
				$inputs[ $row[0] ] = $row[1];
			}
		}

		$rows   = array();
		$counts = array(
			'same'          => 0,
			'serialization' => 0,
			'narrowing'     => 0,
			'widening'      => 0,
			'defect'        => 0,
		);
		foreach ( $inputs as $input => $expected ) {
			$legacy    = self::legacy( $input );
			$prototype = safecss_filter_attr( $input );

			if ( $legacy === $prototype ) {
				++$counts['same'];
				continue;
			}

			$legacy_decls    = self::declarations( $legacy );
			$prototype_decls = self::declarations( $prototype );

			if ( '' === $prototype && '' !== $legacy ) {
				$class = 'narrowing';
			} elseif ( $legacy_decls === $prototype_decls ) {
				$class = 'serialization';
			} elseif ( array() === array_diff_key( $legacy_decls, $prototype_decls ) && self::same_shared( $legacy_decls, $prototype_decls ) ) {
				$class = 'widening';
			} else {
				$class = 'defect';
			}
			++$counts[ $class ];
			$rows[] = array( $class, $input, $legacy, $prototype );
		}

		usort( $rows, static fn( $a, $b ) => strcmp( $a[0], $b[0] ) );

		$out = "| class | input | legacy | parser |\n|---|---|---|---|\n";
		foreach ( $rows as $row ) {
			$out .= '| ' . implode( ' | ', array_map( static fn( $c ) => str_replace( array( '|', "\n" ), array( '\\|', '\\n' ), '`' . $c . '`' ), $row ) ) . " |\n";
		}
		$out .= "\n" . json_encode( $counts ) . "\n";

		// Idempotence: filtering the output again changes nothing.
		$not_idempotent = array();
		foreach ( $inputs as $input => $expected ) {
			$once  = safecss_filter_attr( $input );
			$twice = safecss_filter_attr( $once );
			if ( $once !== $twice ) {
				$not_idempotent[] = array( $input, $once, $twice );
			}
		}
		$out .= 'not idempotent: ' . count( $not_idempotent ) . "\n";
		foreach ( $not_idempotent as $row ) {
			$out .= '  ' . json_encode( $row ) . "\n";
		}

		fwrite( STDOUT, "\n" . $out );
		$this->assertTrue( true );
	}

	/**
	 * Runs the legacy path of safecss_filter_attr().
	 */
	private static function legacy( string $css ): string {
		add_filter( 'wp_kses_force_legacy_css_parser', '__return_true' );
		$output = safecss_filter_attr( $css );
		remove_filter( 'wp_kses_force_legacy_css_parser', '__return_true' );
		return $output;
	}

	/**
	 * Normalizes a declaration list to name => [normalized value, important] for comparison.
	 */
	private static function declarations( string $css ): array {
		$processor = WP_HTML_Style_Attribute_Processor::create( $css );
		$decls     = array();
		while ( $processor->next_declaration() ) {
			$value = implode( '', array_map( static fn( $t ) => substr( $css, $t['start'], $t['length'] ), $processor->get_value_tokens() ) );
			$value = preg_replace( '/\s+/', ' ', $value );
			$value = str_replace( array( '"', "'" ), '"', $value );
			$value = preg_replace_callback( '/url\(\s*"([^"]*)"\s*\)/i', static fn( $m ) => 'url("' . $m[1] . '")', $value );
			$value = preg_replace_callback( '/url\(\s*([^"\s)]*)\s*\)/i', static fn( $m ) => 'url("' . $m[1] . '")', $value );
			$decls[ $processor->get_property_name() . '#' . count( $decls ) ] = array( strtolower( $value ), $processor->is_important() );
		}
		return $decls;
	}

	private static function same_shared( array $a, array $b ): bool {
		foreach ( $a as $k => $v ) {
			if ( ! isset( $b[ $k ] ) || $b[ $k ] !== $v ) {
				return false;
			}
		}
		return true;
	}
}
