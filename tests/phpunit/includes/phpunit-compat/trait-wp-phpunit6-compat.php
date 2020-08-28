<?php
/**
 * Trait that contains any new/deprecated/removed functionality in PHPUnit 6
 */
trait WP_PHPUnit6_Compat {

	// Removed
	public function _setExpectedException( $exception, $message = '', $code = null ) {
		$this->expectException( $exception );
		if ( '' !== $message ) {
			$this->expectExceptionMessage( $message );
		}
		if ( null !== $code ) {
			$this->expectExceptionCode( $code );
		}
	}
}
