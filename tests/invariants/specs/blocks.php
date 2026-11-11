<?php
/**
 * Invariants of serialized blocks.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

// Returns the name and attributes of each named block, depth first, with the attributes as JSON gives them back.
$named_blocks = static function ( array $blocks ) use ( &$named_blocks ) {
	$named = array();
	foreach ( $blocks as $block ) {
		if ( ! is_array( $block ) ) {
			continue;
		}
		if ( isset( $block['blockName'] ) ) {
			$name    = $block['blockName'];
			$attrs   = isset( $block['attrs'] ) ? json_decode( (string) json_encode( $block['attrs'] ), true ) : array(); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- As the parser decodes them.
			$named[] = array( false === strpos( $name, '/' ) ? "core/{$name}" : $name, is_array( $attrs ) ? $attrs : array() );
		}
		if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
			$named = array_merge( $named, $named_blocks( $block['innerBlocks'] ) );
		}
	}

	return $named;
};

// Whether every block, at any depth, has the innerContent serialize_block() walks, with one null for each inner block.
$well_formed = static function ( array $blocks ) use ( &$well_formed ) {
	foreach ( $blocks as $block ) {
		if ( ! is_array( $block ) || ! isset( $block['innerContent'] ) || ! is_array( $block['innerContent'] ) ) {
			return false;
		}
		$inner = isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ? $block['innerBlocks'] : array();
		if ( count( array_filter( $block['innerContent'], 'is_null' ) ) !== count( $inner ) || ! $well_formed( $inner ) ) {
			return false;
		}
	}

	return true;
};

return array(
	WP_Invariant::define( 'attributes-stay-in-comment' )
		->describe( 'What serialize_block_attributes() returns stays in the HTML comment of a block delimiter, as a browser reads it, and decodes back to the attributes.' )
		->include_test_callers()
		->after(
			'serialize_block_attributes',
			static function ( WP_Invariant_Call $call ) {
				$json = $call->return_value();
				if ( ! is_string( $json ) ) {
					return;
				}

				$attributes = $call->arg( 'block_attributes' );
				$comment    = " wp:x {$json} /";
				$nodes      = WP_Invariant_Browser::nodes( "<!--{$comment}-->" );
				if ( ! $call->expect( array( array( 'comment', $comment ) ) === $nodes, 'serialize_block_attributes() returned %s, which a browser does not read as one comment.', $json ) ) {
					return;
				}

				// Compared as JSON gives them back, so that numbers and empty arrays read the same.
				$expected = json_encode( $attributes ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Independent of wp_json_encode().
				if ( false === $expected ) {
					// Such as invalid UTF-8, which wp_json_encode() repairs first.
					return;
				}
				$call->expect( json_decode( $json, true ) === json_decode( $expected, true ), 'serialize_block_attributes() returned %s, which decodes to different attributes.', $json );
			}
		),

	WP_Invariant::define( 'serialized-blocks-parse-back' )
		->describe( 'Parsed again, what serialize_blocks() returns gives the same named blocks, in the same order and nesting, with the same attributes.' )
		->include_test_callers()
		->after(
			'serialize_blocks',
			static function ( WP_Invariant_Call $call ) use ( $named_blocks, $well_formed ) {
				$html   = $call->return_value();
				$blocks = $call->arg( 'blocks' );
				if ( ! is_string( $html ) || ! is_array( $blocks ) || ! $well_formed( $blocks ) ) {
					return;
				}

				// The parser itself, not parse_blocks(), which runs a filter.
				$parsed = ( new WP_Block_Parser() )->parse( $html );
				$call->expect( $named_blocks( $parsed ) === $named_blocks( $blocks ), 'serialize_blocks() returned %s, which parses to different blocks.', $html );
			}
		),
);
