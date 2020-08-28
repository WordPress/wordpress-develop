<?php
/**
 * Trait that contains any new/deprecated/removed functionality in PHPUnit 8
 */

use PHPUnit\Framework\Error\Deprecated;
use PHPUnit\Framework\Error\Notice;
use PHPUnit\Framework\Error\Warning;
use PHPUnit\Framework\Error\Error;

trait WP_PHPUnit8_Compat {

	// Note: class_exists() checks are due to PHPUnit6 support.

	// New
	public function _expectDeprecation() {
		$this->expectException( class_exists( Deprecated::class ) ? Deprecated::class : 'PHPUnit_Framework_Error_Deprecated' );
	}

	// New
	public function _expectDeprecationMessage( $message ) {
		$this->expectExceptionMessage( $message );
	}

	// New
	public function _expectDeprecationMessageMatches( $regularExpression ) {
		$this->expectExceptionMessageRegExp( $regularExpression );
	}

	// New
	public function _expectNotice() {
		$this->expectException( class_exists( Notice::class ) ? Notice::class : 'PHPUnit_Framework_Error_Notice' );
	}

	// New
	public function _expectNoticeMessage( $message ) {
		$this->expectExceptionMessage( $message );
	}

	// New
	public function _expectNoticeMessageMatches( $regularExpression ) {
		$this->expectExceptionMessageRegExp( $regularExpression );
	}

	// New
	public function _expectWarning() {
		$this->expectException( class_exists( Warning::class ) ? Warning::class : 'PHPUnit_Framework_Error_Warning' );
	}

	// New
	public function _expectWarningMessage( $message ) {
		$this->expectExceptionMessage( $message );
	}

	// New
	public function _expectWarningMessageMatches( $regularExpression ) {
		$this->expectExceptionMessageRegExp( $regularExpression );
	}

	// New
	public function _expectError() {
		$this->expectException( class_exists( Error::class ) ? Error::class : 'PHPUnit_Framework_Error' );
	}

	// New
	public function _expectErrorMessage( $message ) {
		$this->expectExceptionMessage( $message );
	}

	// New
	public function _expectErrorMessageMatches( $regularExpression ) {
		$this->expectExceptionMessageRegExp( $regularExpression );
	}

}
