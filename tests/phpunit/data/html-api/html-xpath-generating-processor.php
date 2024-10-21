<?php

class HTML_XPath_Generating_Processor extends WP_HTML_Processor {

	/**
	 * List of tokens that have already been seen.
	 *
	 * @var array<string, int>
	 */
	public $token_seen_count = array();

	/**
	 * Previous depth.
	 *
	 * @var int
	 */
	private $previous_depth = 0;

	/**
	 * Open stack indices.
	 *
	 * @since n.e.x.t
	 * @var array<int, array{tag_name: string, index: int}>
	 */
	private $open_stack_indices = array();

	/**
	 * Gets XPath for the current open tag.
	 *
	 * @return string XPath.
	 */
	public function get_xpath(): string {
		$xpath = '';
		foreach ( $this->open_stack_indices as $level ) {
			$xpath .= sprintf( '/*[%d][self::%s]', $level['index'] + 1, $level['tag_name'] );
		}
		return $xpath;
	}

	/**
	 * Gets next token.
	 *
	 * @return bool Whether next token was matched.
	 */
	public function next_token(): bool {
		$result        = parent::next_token();
		$current_depth = $this->get_current_depth();
		$current_tag   = $this->get_tag();

		$current_depth--; // Because HTML starts at depth 1.

		if ( $this->get_token_type() === '#tag' ) {
			$token_name = ( $this->is_tag_closer() ? '-' : '+' ) . $current_tag;
		} else {
			$token_name = $this->get_token_name();
		}

		if ( ! isset( $this->token_seen_count[ $token_name ] ) ) {
			$this->token_seen_count[ $token_name ] = 1;
		} else {
			++$this->token_seen_count[ $token_name ];
		}

		if ( $this->get_token_type() === '#tag' && ! $this->is_tag_closer() ) {
			if ( $current_depth < $this->previous_depth ) {
				array_splice(
					$this->open_stack_indices,
					$current_depth + 1
				);
			}

			if ( ! isset( $this->open_stack_indices[ $current_depth ] ) ) {
				$this->open_stack_indices[ $current_depth ] = array(
					'tag_name' => $current_tag,
					'index'    => 0,
				);
			} else {
				$this->open_stack_indices[ $current_depth ]['tag_name'] = $current_tag;
				++$this->open_stack_indices[ $current_depth ]['index'];
			}

			$this->previous_depth = $current_depth;
		}

		return $result;
	}

}
