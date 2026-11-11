<?php
/**
 * Tests for the get_search_form() function.
 *
 * @since 7.1.0
 *
 * @group general
 * @group template
 *
 * @covers ::get_search_form
 */
class Tests_General_GetSearchForm extends WP_UnitTestCase {

	/**
	 * Removes any theme support added during a test.
	 */
	public function tear_down(): void {
		remove_theme_support( 'html5' );
		remove_theme_support( 'search-element' );
		parent::tear_down();
	}

	/**
	 * Enables the html5 'search-form' theme support so the html5 markup is used.
	 */
	private function enable_html5_search_form(): void {
		add_theme_support( 'html5', array( 'search-form' ) );
	}

	/**
	 * The html5 form should keep role="search" on the <form> by default.
	 *
	 * @ticket 65288
	 */
	public function test_html5_default_uses_form_role_search(): void {
		$this->enable_html5_search_form();

		$form = get_search_form( array( 'echo' => false ) );

		$this->assertStringContainsString( '<form role="search"', $form, 'The default html5 form should keep role="search" on the form.' );
		$this->assertStringNotContainsString( '<search', $form, 'The default html5 form should not be wrapped in a <search> element.' );
	}

	/**
	 * Opting in should wrap the html5 form in a <search> element and drop role="search".
	 *
	 * @ticket 65288
	 */
	public function test_wrap_in_search_wraps_form_and_drops_role(): void {
		$this->enable_html5_search_form();

		$form = get_search_form(
			array(
				'echo'           => false,
				'wrap_in_search' => true,
			)
		);

		$this->assertStringContainsString( '<search>', $form, 'The opted-in form should open a <search> element.' );
		$this->assertStringContainsString( '</search>', $form, 'The opted-in form should close the <search> element.' );
		$this->assertStringContainsString( '<form method="get" class="search-form"', $form, 'The inner form markup should be preserved.' );
		$this->assertStringNotContainsString( 'role="search"', $form, 'role="search" should be dropped to avoid a nested duplicate landmark.' );
	}

	/**
	 * A custom aria_label should name the <search> landmark, not the inner form.
	 *
	 * @ticket 65288
	 */
	public function test_wrap_in_search_applies_aria_label_to_search_element(): void {
		$this->enable_html5_search_form();

		$form = get_search_form(
			array(
				'echo'           => false,
				'wrap_in_search' => true,
				'aria_label'     => 'Search products',
			)
		);

		$this->assertStringContainsString( '<search aria-label="Search products">', $form, 'The aria-label should be applied to the <search> element.' );
		$this->assertStringContainsString( '<form method="get"', $form, 'The inner form should not carry the aria-label.' );
		$this->assertStringNotContainsString( '<form method="get" aria-label', $form, 'The aria-label should not appear on the inner form.' );
	}

	/**
	 * Without a custom aria_label, the <search> element should have no attributes.
	 *
	 * @ticket 65288
	 */
	public function test_wrap_in_search_without_aria_label_has_no_attributes(): void {
		$this->enable_html5_search_form();

		$form = get_search_form(
			array(
				'echo'           => false,
				'wrap_in_search' => true,
			)
		);

		$this->assertStringContainsString( '<search>', $form, 'The <search> element should have no attributes when no aria-label is set.' );
		$this->assertStringNotContainsString( '<search >', $form, 'The <search> element should not contain a stray space.' );
	}

	/**
	 * The default html5 form should still apply a custom aria_label to the form.
	 *
	 * @ticket 65288
	 */
	public function test_html5_default_applies_aria_label_to_form(): void {
		$this->enable_html5_search_form();

		$form = get_search_form(
			array(
				'echo'       => false,
				'aria_label' => 'Search the site',
			)
		);

		$this->assertStringContainsString( '<form role="search" aria-label="Search the site"', $form, 'The aria-label should be applied to the form by default.' );
		$this->assertStringNotContainsString( '<search', $form, 'The default form should not be wrapped in a <search> element.' );
	}

	/**
	 * The wrap_in_search argument should not affect the xhtml format.
	 *
	 * The <search> element does not exist in XHTML 1.x, so themes without html5
	 * 'search-form' support should continue to receive the unchanged xhtml markup.
	 *
	 * @ticket 65288
	 */
	public function test_wrap_in_search_is_ignored_for_xhtml_format(): void {
		// No html5 theme support added: the format defaults to xhtml.
		$form = get_search_form(
			array(
				'echo'           => false,
				'wrap_in_search' => true,
			)
		);

		$this->assertStringNotContainsString( '<search', $form, 'The xhtml format should not use the <search> element.' );
		$this->assertStringContainsString( '<form role="search"', $form, 'The xhtml format should keep role="search" on the form.' );
		$this->assertStringContainsString( 'id="searchform"', $form, 'The xhtml markup should be unchanged.' );
	}

	/**
	 * The wrapping should be enableable globally via the search_form_args filter.
	 *
	 * @ticket 65288
	 */
	public function test_wrap_in_search_can_be_enabled_via_filter(): void {
		$this->enable_html5_search_form();

		add_filter(
			'search_form_args',
			static function ( array $args ) {
				$args['wrap_in_search'] = true;
				return $args;
			}
		);

		$form = get_search_form( array( 'echo' => false ) );

		$this->assertStringContainsString( '<search>', $form, 'The search_form_args filter should be able to enable wrapping.' );
		$this->assertStringNotContainsString( 'role="search"', $form, 'role="search" should be dropped when wrapping is enabled via filter.' );
	}

	/**
	 * Declaring 'search-element' theme support should wrap the form by default.
	 *
	 * @ticket 65288
	 */
	public function test_search_element_theme_support_enables_wrap_by_default(): void {
		$this->enable_html5_search_form();
		add_theme_support( 'search-element' );

		$form = get_search_form( array( 'echo' => false ) );

		$this->assertStringContainsString( '<search>', $form, 'Declaring search-element support should wrap the form by default.' );
		$this->assertStringNotContainsString( 'role="search"', $form, 'role="search" should be dropped when search-element support is declared.' );
	}

	/**
	 * An explicit wrap_in_search => false should override 'search-element' theme support.
	 *
	 * @ticket 65288
	 */
	public function test_wrap_in_search_false_overrides_theme_support(): void {
		$this->enable_html5_search_form();
		add_theme_support( 'search-element' );

		$form = get_search_form(
			array(
				'echo'           => false,
				'wrap_in_search' => false,
			)
		);

		$this->assertStringNotContainsString( '<search', $form, 'An explicit false should override search-element theme support.' );
		$this->assertStringContainsString( '<form role="search"', $form, 'The form should keep role="search" when wrapping is explicitly disabled.' );
	}
}
