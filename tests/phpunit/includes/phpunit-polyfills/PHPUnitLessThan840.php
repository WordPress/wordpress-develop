<?php

use PHPUnit\Framework\Error\Deprecated;
use PHPUnit\Framework\Error\Error;
use PHPUnit\Framework\Error\Notice;
use PHPUnit\Framework\Error\Warning;
use PHPUnit\Framework\TestCase;

/**
 * Polyfills select PHPUnit functionality introduced in PHPUnit 8.4.0 to older PHPUnit versions.
 *
 * When the minimum supported PHPUnit version of the WP testsuite goes
 * beyond PHPUnit 8.4.0, this polyfill trait can be removed.
 *
 * @since 5.6.0
 */
trait PHPUnitLessThan840 {

	public function expectDeprecation(): void {
		// PHPUnit >= 8.4.0: Use the PHPUnit native method.
		if ( is_callable( array( TestCase::class, 'expectDeprecation' ) ) ) {
			TestCase::expectDeprecation();
			return;
		}

		// PHPUnit < 8.4.0.
		$this->expectException( Deprecated::class );
	}

	public function expectDeprecationMessage( string $message ): void {
		// PHPUnit >= 8.4.0: Use the PHPUnit native method.
		if ( is_callable( array( TestCase::class, 'expectDeprecationMessage' ) ) ) {
			TestCase::expectDeprecationMessage( $message );
			return;
		}

		// PHPUnit < 8.4.0.
		$this->expectExceptionMessage( $message );
	}

	public function expectDeprecationMessageMatches( string $regularExpression ): void {
		// PHPUnit >= 8.4.0: Use the PHPUnit native method.
		if ( is_callable( array( TestCase::class, 'expectDeprecationMessageMatches' ) ) ) {
			TestCase::expectDeprecationMessageMatches( $regularExpression );
			return;
		}

		// PHPUnit < 8.4.0.
		$this->expectExceptionMessageRegExp( $regularExpression );
	}

	public function expectNotice(): void {
		// PHPUnit >= 8.4.0: Use the PHPUnit native method.
		if ( is_callable( array( TestCase::class, 'expectNotice' ) ) ) {
			TestCase::expectNotice();
			return;
		}

		// PHPUnit < 8.4.0.
		$this->expectException( Notice::class );
	}

	public function expectNoticeMessage( string $message ): void {
		// PHPUnit >= 8.4.0: Use the PHPUnit native method.
		if ( is_callable( array( TestCase::class, 'expectNoticeMessage' ) ) ) {
			TestCase::expectNoticeMessage( $message );
			return;
		}

		// PHPUnit < 8.4.0.
		$this->expectExceptionMessage( $message );
	}

	public function expectNoticeMessageMatches( string $regularExpression ): void {
		// PHPUnit >= 8.4.0: Use the PHPUnit native method.
		if ( is_callable( array( TestCase::class, 'expectNoticeMessageMatches' ) ) ) {
			TestCase::expectNoticeMessageMatches( $regularExpression );
			return;
		}

		// PHPUnit < 8.4.0.
		$this->expectExceptionMessageRegExp( $regularExpression );
	}

	public function expectWarning(): void {
		// PHPUnit >= 8.4.0: Use the PHPUnit native method.
		if ( is_callable( array( TestCase::class, 'expectWarning' ) ) ) {
			TestCase::expectWarning();
			return;
		}

		// PHPUnit < 8.4.0.
		$this->expectException( Warning::class );
	}

	public function expectWarningMessage( string $message ): void {
		// PHPUnit >= 8.4.0: Use the PHPUnit native method.
		if ( is_callable( array( TestCase::class, 'expectWarningMessage' ) ) ) {
			TestCase::expectWarningMessage( $message );
			return;
		}

		// PHPUnit < 8.4.0.
		$this->expectExceptionMessage( $message );
	}

	public function expectWarningMessageMatches( string $regularExpression ): void {
		// PHPUnit >= 8.4.0: Use the PHPUnit native method.
		if ( is_callable( array( TestCase::class, 'expectWarningMessageMatches' ) ) ) {
			TestCase::expectWarningMessageMatches( $regularExpression );
			return;
		}

		// PHPUnit < 8.4.0.
		$this->expectExceptionMessageRegExp( $regularExpression );
	}

	public function expectError(): void {
		// PHPUnit >= 8.4.0: Use the PHPUnit native method.
		if ( is_callable( array( TestCase::class, 'expectError' ) ) ) {
			TestCase::expectError();
			return;
		}

		// PHPUnit < 8.4.0.
		$this->expectException( Error::class );
	}

	public function expectErrorMessage( string $message ): void {
		// PHPUnit >= 8.4.0: Use the PHPUnit native method.
		if ( is_callable( array( TestCase::class, 'expectErrorMessage' ) ) ) {
			TestCase::expectErrorMessage( $message );
			return;
		}

		// PHPUnit < 8.4.0.
		$this->expectExceptionMessage( $message );
	}

	public function expectErrorMessageMatches( string $regularExpression ): void {
		// PHPUnit >= 8.4.0: Use the PHPUnit native method.
		if ( is_callable( array( TestCase::class, 'expectErrorMessageMatches' ) ) ) {
			TestCase::expectErrorMessageMatches( $regularExpression );
			return;
		}

		// PHPUnit < 8.4.0.
		$this->expectExceptionMessageRegExp( $regularExpression );
	}

}
