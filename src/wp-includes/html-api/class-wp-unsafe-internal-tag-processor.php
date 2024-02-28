<?php

class WP_Unsafe_Internal_Tag_Processor extends WP_HTML_Tag_Processor {
	public function unsafe_get_raw_modifiable_text() {
		$text = $this->unsafe_get_modifiable_text_extents();

		return null !== $text
			? substr( $this->html, $text->start, $text->length )
			: '';
	}

	public function unsafe_get_raw_token() {
		$token = $this->unsafe_get_token_extents();

		return null !== $token
			? substr( $this->html, $token->start, $token->length )
			: '';
	}
}
