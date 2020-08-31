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
	public function _expectDeprecation() {
		$this->expectException( Deprecated::class );
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
		$this->expectException( Notice::class );
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
		$this->expectException( Warning::class );
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
		$this->expectException( Error::class );
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
