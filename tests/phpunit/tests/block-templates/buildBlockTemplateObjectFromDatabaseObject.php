<?php

require_once __DIR__ . '/base.php';

/**
 * @group block-templates
 * @covers ::_build_block_template_object_from_database_object
 */
class Tests_Block_Templates_BuildBlockTemplateFromDatabaseObject extends WP_Block_Templates_UnitTestCase {

	/**
	 * Tear down each test method.
	 *
	 * @since 6.6.0
	 */
	public function tear_down() {
		$registry = WP_Block_Type_Registry::get_instance();

		if ( $registry->is_registered( 'tests/my-block' ) ) {
			$registry->unregister( 'tests/my-block' );
		}

		if ( $registry->is_registered( 'tests/ignored' ) ) {
			$registry->unregister( 'tests/ignored' );
		}

		parent::tear_down();
	}

	/**
	 * @ticket 60759
	 */
	public function test_should_build_template_from_uncustomized_object() {
		$template = _build_block_template_object_from_database_object( self::$uncustomized_template_db_object );

		$this->assertNotWPError( $template );
		$this->assertSame( get_stylesheet() . '//my_template', $template->id );
		$this->assertSame( get_stylesheet(), $template->theme );
		$this->assertSame( 'my_template', $template->slug );
		$this->assertSame( 'publish', $template->status );
		$this->assertSame( 'custom', $template->source );
		$this->assertSame( 'My Template', $template->title );
		$this->assertSame( 'Description of my template', $template->description );
		$this->assertSame( 'wp_template', $template->type );
	}

	/**
	 * @ticket 60759
	 */
	public function test_should_build_template_from_customized_object() {
		self::$customized_template_db_object->ID = self::$template_post->ID;
		$template                                = _build_block_template_object_from_database_object( self::$customized_template_db_object );

		$this->assertNotWPError( $template );
		$this->assertSame( get_stylesheet() . '//my_template', $template->id );
		$this->assertSame( get_stylesheet(), $template->theme );
		$this->assertSame( 'my_template', $template->slug );
		$this->assertSame( 'publish', $template->status );
		$this->assertSame( 'custom', $template->source );
		$this->assertSame( 'My Customized Template', $template->title );
		$this->assertSame( 'Description of my template', $template->description );
		$this->assertSame( 'wp_template', $template->type );
	}
}
