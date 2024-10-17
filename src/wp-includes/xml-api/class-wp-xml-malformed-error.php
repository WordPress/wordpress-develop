<?php

class WP_XML_Malformed_Error extends ValueError {
	public $level_of_concern;

	public function __construct( int $level_of_concern, string $message ) {
		parent::__construct( $message );
		$this->level_of_concern = $level_of_concern;
	}

	public function get_level_of_concern(): int {
		switch ( $this->level_of_concern ) {
			case WP_XML_Processor::CONCERNED_ABOUT_EVERYTHING:
				return 'EVERYTHING';

			case WP_XML_Processor::CONCERNED_ABOUT_CONTENT_ERRORS:
				return 'CONTENT';

			case WP_XML_Processor::CONCERNED_ABOUT_UNAMBIGUOUS_SYNTAX_ERRORS:
				return 'BENIGN_SYNTAX';

			case WP_XML_Processor::CONCERNED_ABOUT_UNRESOLVABLE_SYNTAX_ERRORS:
				return 'INVALID_SYNTAX';

			case WP_XML_Processor::CONCERNED_ABOUT_UNRESOLVABLES:
				return 'UNRESOLVABLE';

			default:
				throw new ValueError( "Unknown level of concern: {$this->level_of_concern}" );
		}
	}
}
