<?php
/**
 * @group taxonomy
 * @group walker
 */
class Tests_Category_Walker_Category extends WP_UnitTestCase {

	/**
	 * @var \Walker_Category The instance of the walker.
	 */
	public $walker;

	/**
	 * Setup.
	 */
	public function setUp() {
		parent::setUp();

		/** Walker_Category class */
		require_once ABSPATH . 'wp-includes/class-walker-category.php';
		$this->walker = new Walker_Category();
	}

	/**
	 * @ticket 47720
	 *
	 * @dataProvider data_start_el_with_empty_attributes
	 */
	public function test_start_el_with_empty_attributes( $value, $expected ) {
		$output   = '';
		$category = $this->factory->category->create_and_get();
		$link     = get_term_link( $category );

		$args = array(
			'use_desc_for_title' => 0,
			'style'              => 'list',
		);

		add_filter(
			'category_list_link_attributes',
			function( $atts ) use ( $value ) {
				$atts['data-test'] = $value;
				return $atts;
			}
		);

		$this->walker->start_el( $output, $category, 0, $args );

		if ( '' !== $expected ) {
			$expected = sprintf( ' data-test="%s"', $expected );
		}

		$this->assertSame( "<li class=\"cat-item cat-item-{$category->term_id}\"><a href=\"{$link}\"{$expected}>{$category->name}</a>", trim( $output ) );
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
