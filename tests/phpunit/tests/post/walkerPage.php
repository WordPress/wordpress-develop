<?php
/**
 * @group post
 * @group walker
 */
class Tests_Post_Walker_Page extends WP_UnitTestCase {

	/**
	 * @var \Walker_Page The instance of the walker.
	 */
	public $walker;

	/**
	 * Setup.
	 */
	public function set_up() {
		parent::set_up();

		/** Walker_Page class */
		require_once ABSPATH . 'wp-includes/class-walker-page.php';
		$this->walker = new Walker_Page();
	}

	/**
	 * @ticket 47720
	 *
	 * @dataProvider data_start_el_with_empty_attributes
	 */
	public function test_start_el_with_empty_attributes( $value, $expected ) {
		$output = '';
		$page   = $this->factory->post->create_and_get( array( 'post_type' => 'page' ) );
		$link   = get_permalink( $page );

		add_filter(
			'page_menu_link_attributes',
			function( $atts ) use ( $value ) {
				$atts['data-test'] = $value;
				return $atts;
			}
		);

		$this->walker->start_el( $output, $page, 0 );

		if ( '' !== $expected ) {
			$expected = sprintf( ' data-test="%s"', $expected );
		}

		$this->assertSame( "<li class=\"page_item page-item-{$page->ID}\"><a href=\"{$link}\"{$expected}>{$page->post_title}</a>", $output );
	}

	public function data_start_el_with_empty_attributes() {
		return array(
			array(
				'',
				'',
			),
			array(
				0,
				'0',
			),
			array(
				0.0,
				'0',
			),
			array(
				'0',
				'0',
			),
			array(
				null,
				'',
			),
			array(
				false,
				'',
			),
			array(
				true,
				'1',
			),
			array(
				array(),
				'',
			),
		);
	}
}
