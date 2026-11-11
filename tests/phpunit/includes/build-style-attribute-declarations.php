<?php

/**
 * Lists the declarations of a style attribute value in a canonical form.
 *
 * Two values give the same list when they have the same declarations in the
 * same order: the same property names, priorities and value tokens. Whitespace
 * and comments are ignored, and blocks left open at the end are closed. Each
 * declaration is written `name:value;` or `name:value !important;`, with a space
 * only between value tokens that would otherwise read as one.
 *
 * For example, `COLOR: rgb(1, 2, 3); margin: 1px  2px` gives
 * `array( 'color:rgb(1,2,3);', 'margin:1px 2px;' )`.
 *
 * @since 7.3.0
 *
 * @param string $style Decoded style attribute value.
 * @return string[]|null Declarations, or null when the value has text that is
 *                       not a declaration, a `;`, whitespace or a comment.
 */
function build_style_attribute_declarations( string $style ): ?array {
	$is_ignored = static function ( WP_CSS_Token $token ): bool {
		return (
			WP_CSS_Token_Processor::TOKEN_WHITESPACE === $token->get_type() ||
			WP_CSS_Token_Processor::TOKEN_COMMENT === $token->get_type()
		);
	};

	$processor          = new WP_HTML_Style_Attribute_Processor( $style );
	$declarations       = array();
	$declaration_tokens = 0;

	while ( $processor->next_declaration() ) {
		$tokens = array();
		foreach ( $processor->get_value_tokens() as $token ) {
			if ( ! $is_ignored( $token ) ) {
				$tokens[] = $token;
			}
		}

		// The name, the colon, the value tokens other than `;`, and `!` and `important`.
		$declaration_tokens += 2 + ( $processor->is_important() ? 2 : 0 );
		foreach ( $tokens as $token ) {
			if ( WP_CSS_Token_Processor::TOKEN_SEMICOLON !== $token->get_type() ) {
				++$declaration_tokens;
			}
		}

		// The serializer separates tokens that would read as one with an empty comment.
		$serialized = WP_CSS_Token_Serializer::serialize( $tokens );
		$reader     = new WP_CSS_Token_Processor( $serialized );
		$value      = '';
		while ( $reader->next_token() ) {
			$token  = $reader->get_token();
			$value .= WP_CSS_Token_Processor::TOKEN_COMMENT === $token->get_type()
				? ' '
				: substr( $serialized, $token->get_start(), $token->get_length() );
		}

		$name           = WP_CSS_Token_Serializer::escape_ident( $processor->get_property_name() );
		$declarations[] = "{$name}:{$value}" . ( $processor->is_important() ? ' !important' : '' ) . ';';
	}

	// Any token not counted above, other than whitespace, a comment or a `;`, is not part of a declaration.
	$reader = new WP_CSS_Token_Processor( $style );
	while ( $reader->next_token() ) {
		$token = $reader->get_token();
		if ( ! $is_ignored( $token ) && WP_CSS_Token_Processor::TOKEN_SEMICOLON !== $token->get_type() ) {
			--$declaration_tokens;
		}
	}

	return 0 === $declaration_tokens ? $declarations : null;
}
