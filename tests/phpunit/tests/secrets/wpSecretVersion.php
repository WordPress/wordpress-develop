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
		$this->assertIsString( WP_Secret_Version::CURRENT );
		$this->assertIsString( WP_Secret_Version::PREVIOUS );
		$this->assertNotSame( WP_Secret_Version::CURRENT, WP_Secret_Version::PREVIOUS );
	}

	/**
	 * Ensure that WP_Secret_Version cannot be extended.
	 *
	 * @coversNothing
	 */
	public function test_class_is_final(): void {
		$reflection = new ReflectionClass( WP_Secret_Version::class );

		$this->assertTrue( $reflection->isFinal() );
	}

	/**
	 * Ensure that WP_Secret_Version is a pure constants holder with no constructor.
	 *
	 * @coversNothing
	 */
	public function test_class_declares_no_constructor(): void {
		$reflection = new ReflectionClass( WP_Secret_Version::class );

		$this->assertNull( $reflection->getConstructor() );
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

		$this->assertCount( 2, $reflection->getConstants() );
	}
}
