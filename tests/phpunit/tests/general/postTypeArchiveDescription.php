<?php
/**
 * @group general
 * @group template
 */
class Tests_General_PostTypeArchiveDescription extends WP_UnitTestCase {
	public function set_up() {
		parent::set_up();
		register_post_type(
			'cpt_with_desc',
			array(
				'public'      => true,
				'has_archive' => true,
				'description' => 'This is a test description.',
			)
		);
		register_post_type(
			'cpt_no_desc',
			array(
				'public'      => true,
				'has_archive' => true,
			)
		);
	}

	public function test_post_type_archive_description_echoes_by_default() {
		$this->go_to( get_post_type_archive_link( 'cpt_with_desc' ) );
		$this->assertTrue( is_post_type_archive() );
		$this->expectOutputString( 'This is a test description.' );
		post_type_archive_description();
	}

	public function test_post_type_archive_description_no_desc() {
		$this->go_to( get_post_type_archive_link( 'cpt_no_desc' ) );
		$this->assertSame( '', post_type_archive_description( '', false ) );
	}

	public function test_post_type_archive_description_with_passed_post_type() {
		$this->assertSame( 'This is a test description.', post_type_archive_description( 'cpt_with_desc', false ) );
	}

	public function test_post_type_archive_description_filter() {
		add_filter( 'post_type_archive_description', array( $this, 'filter_description' ), 10, 2 );
		$desc = post_type_archive_description( 'cpt_with_desc', false );
		remove_filter( 'post_type_archive_description', array( $this, 'filter_description' ) );

		$this->assertSame( 'Filtered desc: cpt_with_desc', $desc );
	}

	public function filter_description( $desc, $post_type ) {
		return "Filtered desc: {$post_type}";
	}
}
