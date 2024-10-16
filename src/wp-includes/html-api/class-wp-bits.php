<?php

function wp_replace_bits( $content ) {
	$processor = new class ( $content ) extends WP_HTML_Tag_Processor {
		private $deferred_updates = array();

		public function replace_token( $new_content ) {
			$this->set_bookmark( 'here' );
			$here = $this->bookmarks['here'];

			$this->deferred_updates[] = new WP_HTML_Text_Replacement(
				$here->start,
				$here->length,
				$new_content
			);
		}

		public function flush_updates() {
			foreach ( $this->deferred_updates as $update ) {
				$this->lexical_updates[] = $update;
			}
		}
	};

	while ( $processor->next_token() ) {
		switch ( $processor->get_token_type() ) {
			case '#funky-comment':
				$processor->replace_token( '<b>bl<em>ar</em>g</b>' );
				break;

			case '#tag':
				foreach ( $processor->get_attribute_names_with_prefix( '' ) ?? [] as $name ) {
					$value = $processor->get_attribute( $name );
					if ( is_string( $value ) ) {
						$new_value = preg_replace_callback(
							'~<//wp:([^>]+)>~',
							static function ( $bit ) {
								return 'blarg';
							},
							$value
						);

						if ( $new_value !== $value ) {
							$processor->set_attribute( $name, $new_value );
						}
					}
				}
				break;

			case '#comment':
				if ( WP_HTML_Tag_Processor::COMMENT_AS_HTML_COMMENT !== $processor->get_comment_type() ) {
					break;
				}

				$text = $processor->get_modifiable_text();
				if ( 1 === preg_match( '~^<//wp:([^>]+)>$~', $text ) ) {
					$processor->replace_token( '<b>Bla<em>rg</em>!</b>' );
					break;
				}

				$new_value = preg_replace_callback(
					'~<//wp:([^>]+)>~',
					static function ( $bit ) {
						return 'blarg';
					},
					$text
				);

				$processor->replace_token( "<!--{$new_value}-->" );
				break;
		}
	}
	$processor->flush_updates();
	$content = $processor->get_updated_html();

	return $content;
}
