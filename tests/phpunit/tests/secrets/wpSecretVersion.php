<?php
/**
 * @ticket 66187
 *
 * @group secrets
 */
class Tests_Secrets_WPSecretVersion extends WP_UnitTestCase {

	/**
	 * Ensure that WP_Secret_Version::CURRENT and WP_Secret_Version::PREVIOUS are distinct strings.
	 *
	 * @coversNothing
	 */
	public function test_current_and_previous_are_distinct_strings(): void {
		$this->assertIsString( WP_Secret_Version::CURRENT, 'WP_Secret_Version::CURRENT should be a string.' );
		$this->assertIsString( WP_Secret_Version::PREVIOUS, 'WP_Secret_Version::PREVIOUS should be a string.' );
		$this->assertNotSame( WP_Secret_Version::CURRENT, WP_Secret_Version::PREVIOUS, 'CURRENT and PREVIOUS should hold different values.' );
	}

	/**
	 * Ensure that WP_Secret_Version cannot be extended.
	 *
	 * @coversNothing
	 */
	public function test_class_is_final(): void {
		$reflection = new ReflectionClass( WP_Secret_Version::class );

		$this->assertTrue( $reflection->isFinal(), 'WP_Secret_Version should be declared final.' );
	}

	/**
	 * Ensure that WP_Secret_Version is a pure constants holder with no constructor.
	 *
	 * @coversNothing
	 */
	public function test_class_declares_no_constructor(): void {
		$reflection = new ReflectionClass( WP_Secret_Version::class );

		$this->assertNull( $reflection->getConstructor(), 'WP_Secret_Version should not declare a constructor.' );
	}

	/**
	 * Ensure that WP_Secret_Version defines only the current and previous slots.
	 *
	 * Records hold exactly two slots and the cipher accepts only these two values, so a
	 * new constant would need matching support elsewhere before it could be used.
	 *
	 * @coversNothing
	 */
	public function test_class_declares_exactly_two_constants(): void {
		$reflection = new ReflectionClass( WP_Secret_Version::class );

		$this->assertCount( 2, $reflection->getConstants(), 'WP_Secret_Version should declare exactly two constants.' );
	}
}
