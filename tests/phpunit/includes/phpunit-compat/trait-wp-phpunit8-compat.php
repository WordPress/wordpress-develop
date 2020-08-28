<?php
/**
 * Trait that contains any new/deprecated/removed functionality in PHPUnit 8
 */

use PHPUnit\Framework\Error\Deprecated;
use PHPUnit\Framework\Error\Notice;
use PHPUnit\Framework\Error\Warning;
use PHPUnit\Framework\Error\Error;

trait WP_PHPUnit8_Compat {

	// New
	function _expectDeprecation() {
		$this->expectException( Deprecated::class );
	}

	// New
	function _expectDeprecationMessage( $message ) {
		$this->expectExceptionMessage( $message );
	}

	// New
	function _expectDeprecationMessageMatches( $regularExpression ) {
		$this->expectExceptionMessageRegExp( $regularExpression );
	}

	// New
	function _expectNotice() {
		$this->expectException( Notice::class );
	}

	// New
	function _expectNoticeMessage( $message ) {
		$this->expectExceptionMessage( $message );
	}

	// New
	function _expectNoticeMessageMatches( $regularExpression ) {
		$this->expectExceptionMessageRegExp( $regularExpression );
	}

	// New
	function _expectWarning() {
		$this->expectException( Warning::class );
	}

	// New
	function _expectWarningMessage( $message ) {
		$this->expectExceptionMessage( $message );
	}

	// New
	function _expectWarningMessageMatches( $regularExpression ) {
		$this->expectExceptionMessageRegExp( $regularExpression );
	}

	// New
	function _expectError() {
		$this->expectException( Error::class );
	}

	// New
	function _expectErrorMessage( $message ) {
		$this->expectExceptionMessage( $message );
	}

	// New
	function _expectErrorMessageMatches( $regularExpression ) {
		$this->expectExceptionMessageRegExp( $regularExpression );
	}

}
