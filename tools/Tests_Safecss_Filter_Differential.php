<?php
/**
 * Differential: legacy safecss_filter_attr() against the prototype list filter.
 *
 * Run: WP_TESTS_SKIP_INSTALL=1 vendor/bin/phpunit tools/Tests_Safecss_Filter_Differential.php
 *
 * Prints every data-provider input whose outputs differ, with a classification:
 * - serialization: legacy and prototype re-parse to the same declarations.
 * - narrowing: the prototype drops everything legacy kept.
 * - widening: the prototype keeps a declaration legacy dropped.
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
			$legacy    = safecss_filter_attr( $input );
			$prototype = wp_filter_style_declaration_list( $input );

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

		$out = "| class | input | legacy | prototype |\n|---|---|---|---|\n";
		foreach ( $rows as $row ) {
			$out .= '| ' . implode( ' | ', array_map( static fn( $c ) => str_replace( array( '|', "\n" ), array( '\\|', '\\n' ), '`' . $c . '`' ), $row ) ) . " |\n";
		}
		$out .= "\n" . json_encode( $counts ) . "\n";

		// Idempotence: filtering the output again changes nothing.
		$not_idempotent = array();
		foreach ( $inputs as $input => $expected ) {
			$once  = wp_filter_style_declaration_list( $input );
			$twice = wp_filter_style_declaration_list( $once );
			if ( $once !== $twice ) {
				$not_idempotent[] = array( $input, $once, $twice );
			}
		}
		$out .= 'not idempotent: ' . count( $not_idempotent ) . "\n";
		foreach ( $not_idempotent as $row ) {
			$out .= '  ' . json_encode( $row ) . "\n";
		}

		// Value access: the processor's token view against re-tokenizing get_value_source().
		$token_view_mismatch = array();
		foreach ( $inputs as $input => $expected ) {
			$processor = WP_HTML_Style_Attribute_Processor::create( $input );
			while ( $processor->next_declaration() ) {
				$view  = array_map( static fn( $t ) => array( $t['type'], $t['value'] ), $processor->get_value_tokens() );
				$raw   = array();
				$again = WP_CSS_Token_Processor::create( (string) $processor->get_value_source() );
				while ( $again->next_token() ) {
					$raw[] = array( $again->get_token_type(), $again->get_token_value() );
				}
				if ( $view !== $raw ) {
					$token_view_mismatch[] = array( $input, $processor->get_property_name(), $view, $raw );
				}
			}
		}
		$out .= 'token view vs re-tokenized raw value mismatches: ' . count( $token_view_mismatch ) . "\n";
		foreach ( $token_view_mismatch as $row ) {
			$out .= '  ' . json_encode( $row ) . "\n";
		}
		fwrite( STDOUT, "\n" . $out );
		$this->assertTrue( true );
	}

	/**
	 * Normalizes a declaration list to name => [normalized value, important] for comparison.
	 */
	private static function declarations( string $css ): array {
		$processor = WP_HTML_Style_Attribute_Processor::create( $css );
		$decls     = array();
		while ( $processor->next_declaration() ) {
			$value = (string) $processor->get_value_source();
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
