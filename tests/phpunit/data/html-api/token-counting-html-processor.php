<?php

class Token_Counting_HTML_Processor extends WP_HTML_Processor {

	/**
	 * List of tokens that have already been seen.
	 *
	 * @var array<string, int>
	 */
	public $token_seen_count = array();

	/**
	 * Gets next token.
	 *
	 * @return bool Whether next token was matched.
	 */
	public function next_token(): bool {
		$result = parent::next_token();

		if ( $this->get_token_type() === '#tag' ) {
			$token_name = ( $this->is_tag_closer() ? '-' : '+' ) . $this->get_tag();
		} else {
			$token_name = $this->get_token_name();
		}

		if ( ! isset( $this->token_seen_count[ $token_name ] ) ) {
			$this->token_seen_count[ $token_name ] = 1;
		} else {
			++$this->token_seen_count[ $token_name ];
		}

		return $result;
	}

}
