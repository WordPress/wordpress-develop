<?php
/**
 * Unit Tests: JsonSerializable_Object
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 5.3.0
 */

class JsonSerializable_Object implements JsonSerializable {

	private $data;

	public function __construct( $data ) {
		$this->data = $data;
	}

	public function jsonSerialize() {
		return $this->data;
	}
}
