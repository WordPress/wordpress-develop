<?php
/**
 * @group secrets
 */
class Tests_Secrets_WPSecretVersion extends WP_UnitTestCase {

	public function test_current_and_previous_are_distinct_strings(): void {
		$this->assertIsString( WP_Secret_Version::CURRENT );
		$this->assertIsString( WP_Secret_Version::PREVIOUS );
		$this->assertNotSame( WP_Secret_Version::CURRENT, WP_Secret_Version::PREVIOUS );
	}

	public function test_class_is_final(): void {
		$reflection = new ReflectionClass( WP_Secret_Version::class );

		$this->assertTrue( $reflection->isFinal() );
	}

	public function test_class_declares_no_constructor(): void {
		$reflection = new ReflectionClass( WP_Secret_Version::class );

		$this->assertNull( $reflection->getConstructor() );
	}

	public function test_class_declares_exactly_two_constants(): void {
		$reflection = new ReflectionClass( WP_Secret_Version::class );

		$this->assertCount( 2, $reflection->getConstants() );
	}
}
