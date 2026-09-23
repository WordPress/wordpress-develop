<?php
/**
 * Tests for the wp_create_links function.
 *
 * @package WordPress
 */

/**
 * Tests for the wp_create_links function.
 *
 * @since 6.2.0
 *
 * @group functions.php
 *
 * @covers ::wp_create_links
 */
class Tests_Functions_WpCreateLinks extends WP_UnitTestCase {

	/**
	 * Tests that an empty array returns the same.
	 *
	 * @ticket 42066
	 */
	public function test_should_return_empty_array() {
		$actual = wp_create_links( array() );
		$this->assertIsArray( $actual, 'wp_create_links did not return an array' );
		$this->assertEmpty( $actual, 'wp_create_links did not return an empty array' );
	}

	/**
	 * Tests that the expected link markup is created.
	 *
	 * @ticket 42066
	 *
	 * @dataProvider data_should_return_link_markup
	 *
	 * @param array $expected  The expected array of link markup.
	 * @param array $link_data An array of data for the links.
	 */
	public function test_should_return_link_markup( $expected, $link_data ) {
		$actual = wp_create_links( $link_data );
		$this->assertSameSetsWithIndex( $expected, $actual );
	}

	/**
	 * Data provider for test_should_return_link_markup().
	 *
	 * @return array[]
	 */
	public function data_should_return_link_markup() {
		return array(
			'a link data array with only keys'            => array(
				'expected'  => array(
					'Home'    => '<a href="#">Home</a>',
					'About'   => '<a href="#">About</a>',
					'Contact' => '<a href="#">Contact</a>',
				),
				'link_data' => array(
					'Home'    => null,
					'About'   => null,
					'Contact' => null,
				),
			),
			'a link with an empty url'                    => array(
				'expected'  => array(
					'one' => '<a href="#">Link One</a>',
				),
				'link_data' => array(
					'one' => array(
						'url'   => '',
						'label' => 'Link One',
					),
				),
			),
			'a link with only /'                          => array(
				'expected'  => array(
					'Home' => '<a href="/">Home</a>',
				),
				'link_data' => array(
					'Home' => array(
						'url' => '/',
					),
				),
			),
			'a link with only #'                          => array(
				'expected'  => array(
					'home' => '<a href="#">Link One</a>',
				),
				'link_data' => array(
					'home' => array(
						'url'   => '#',
						'label' => 'Link One',
					),
				),
			),
			'non-string url'                              => array(
				'expected'  => array(
					'home' => '<a href="#">home</a>',
				),
				'link_data' => array(
					'home' => array(
						'url' => array( 'http://example.com' ),
					),
				),
			),
			'url with only spaces'                        => array(
				'expected'  => array(
					'home' => '<a href="#">home</a>',
				),
				'link_data' => array(
					'home' => array(
						'url' => '  ',
					),
				),
			),
			'a link with no label'                        => array(
				'expected'  => array(
					'<a href="#">Link 0</a>',
				),
				'link_data' => array(
					array(
						'url' => '#',
					),
				),
			),
			'a link with no label and a key'              => array(
				'expected'  => array(
					'home' => '<a href="#">home</a>',
				),
				'link_data' => array(
					'home' => array(
						'url' => '#',
					),
				),
			),
			'a link with an invalid class (object array)' => array(
				'expected'  => array(
					'home' => '<a href="#">home</a>',
				),
				'link_data' => array(
					'home' => array(
						'url'        => '#',
						'attributes' => array(
							'class' => array(
								(object) array( 'additional-class' ),
							),
						),
					),
				),
			),
			'an in-page anchor'                           => array(
				'expected'  => array(
					'<a name="contact"></a>',
				),
				'link_data' => array(
					array(
						'attributes' => array(
							'name' => 'contact',
						),
					),
				),
			),
			'an in-page anchor with a class'              => array(
				'expected'  => array(
					'<a class="anchor-link" name="contact"></a>',
				),
				'link_data' => array(
					array(
						'attributes' => array(
							'name'  => 'contact',
							'class' => 'anchor-link',
						),
					),
				),
			),
			'one link with no attributes'                 => array(
				'expected'  => array(
					'one' => '<a href="https://example.org">Link One</a>',
				),
				'link_data' => array(
					'one' => array(
						'url'   => 'https://example.org',
						'label' => 'Link One',
					),
				),
			),
			'two links with no attributes'                => array(
				'expected'  => array(
					'one' => '<a href="https://example.org">Link One</a>',
					'two' => '<a href="https://example.com">Link Two</a>',
				),
				'link_data' => array(
					'one' => array(
						'url'   => 'https://example.org',
						'label' => 'Link One',
					),
					'two' => array(
						'url'   => 'https://example.com',
						'label' => 'Link Two',
					),
				),
			),
			'links with numeric keys'                     => array(
				'expected'  => array(
					'<a href="https://example.org">Link One</a>',
					'<a href="https://example.com">Link Two</a>',
				),
				'link_data' => array(
					array(
						'url'   => 'https://example.org',
						'label' => 'Link One',
					),
					array(
						'url'   => 'https://example.com',
						'label' => 'Link Two',
					),
				),
			),
			'links with attribute/value pairs'            => array(
				'expected'  => array(
					'<a href="https://example.org" target="_blank">Link One</a>',
					'<a href="https://example.com" rel="me">Link Two</a>',
				),
				'link_data' => array(
					array(
						'url'        => 'https://example.org',
						'label'      => 'Link One',
						'attributes' => array(
							'target' => '_blank',
						),
					),
					array(
						'url'        => 'https://example.com',
						'label'      => 'Link Two',
						'attributes' => array(
							'rel' => 'me',
						),
					),
				),
			),
			'links with int and float attribute values'   => array(
				'expected'  => array(
					'<a href="https://example.org" data-count="1">Link One</a>',
					'<a href="https://example.com" data-count="1.05">Link Two</a>',
				),
				'link_data' => array(
					array(
						'url'        => 'https://example.org',
						'label'      => 'Link One',
						'attributes' => array(
							'data-count' => 1,
						),
					),
					array(
						'url'        => 'https://example.com',
						'label'      => 'Link Two',
						'attributes' => array(
							'data-count' => 1.05,
						),
					),
				),
			),
			'a link with a boolean attribute (true)'      => array(
				'expected'  => array(
					'<a href="https://example.org" hidden>Link One</a>',
				),
				'link_data' => array(
					array(
						'url'        => 'https://example.org',
						'label'      => 'Link One',
						'attributes' => array(
							'hidden' => true,
						),
					),
				),
			),
			'a link with a boolean attribute (false)'     => array(
				'expected'  => array(
					'<a href="https://example.org">Link One</a>',
				),
				'link_data' => array(
					array(
						'url'        => 'https://example.org',
						'label'      => 'Link One',
						'attributes' => array(
							'hidden' => false,
						),
					),
				),
			),
			'a link with an attribute/value pair and a boolean attribute' => array(
				'expected'  => array(
					'<a href="https://example.org" class="menu-link" hidden>Link One</a>',
				),
				'link_data' => array(
					array(
						'url'        => 'https://example.org',
						'label'      => 'Link One',
						'attributes' => array(
							'class'  => 'menu-link',
							'hidden' => true,
						),
					),
				),
			),
			'a link with an ARIA attribute'               => array(
				'expected'  => array(
					'<a href="https://example.org" aria-label="Visit Link One">Link One</a>',
				),
				'link_data' => array(
					array(
						'url'        => 'https://example.org',
						'label'      => 'Link One',
						'attributes' => array(
							'aria-label' => 'Visit Link One',
						),
					),
				),
			),
			'a link to the current page'                  => array(
				'expected'  => array(
					'<a href="https://example.org" aria-current="page" class="current">Link One</a>',
				),
				'link_data' => array(
					array(
						'url'     => 'https://example.org',
						'label'   => 'Link One',
						'current' => true,
					),
				),
			),
			'a link to the current page with a class'     => array(
				'expected'  => array(
					'<a href="https://example.org" aria-current="page" class="menu-link current">Link One</a>',
				),
				'link_data' => array(
					array(
						'url'        => 'https://example.org',
						'label'      => 'Link One',
						'current'    => true,
						'attributes' => array(
							'class' => 'menu-link',
						),
					),
				),
			),
			'a link to the current page with a class and a boolean attribute' => array(
				'expected'  => array(
					'<a href="https://example.org" aria-current="page" class="menu-link current" hidden>Link One</a>',
				),
				'link_data' => array(
					array(
						'url'        => 'https://example.org',
						'label'      => 'Link One',
						'current'    => true,
						'attributes' => array(
							'class'  => 'menu-link',
							'hidden' => true,
						),
					),
				),
			),
			'a link to the current page and another link, both keyed and with a class and a boolean attribute' => array(
				'expected'  => array(
					'one' => '<a href="https://example.org" class="menu-link" hidden>Link One</a>',
					'two' => '<a href="https://example.com" aria-current="page" class="menu-link current" hidden>Link Two</a>',
				),
				'link_data' => array(
					'one' => array(
						'url'        => 'https://example.org',
						'label'      => 'Link One',
						'attributes' => array(
							'class'  => 'menu-link',
							'hidden' => true,
						),
					),
					'two' => array(
						'url'        => 'https://example.com',
						'label'      => 'Link Two',
						'current'    => true,
						'attributes' => array(
							'class'  => 'menu-link',
							'hidden' => true,
						),
					),
				),
			),
			'a url and a href attribute'                  => array(
				'expected'  => array(
					'home' => '<a href="https://example.org">Home</a>',
				),
				'link_data' => array(
					'home' => array(
						'url'        => 'https://example.org',
						'label'      => 'Home',
						'attributes' => array(
							'href' => 'https://example.com',
						),
					),
				),
			),
			'an array value'                              => array(
				'expected'  => array(
					'home' => '<a href="http://example.com">Home</a>',
				),
				'link_data' => array(
					'home' => array(
						'url'        => 'http://example.com',
						'label'      => 'Home',
						'attributes' => array(
							'rel' => array( 'me', 'noreferrer' ),
						),
					),
				),
			),
			'an invalid attribute (int)'                  => array(
				'expected'  => array(
					'home' => '<a href="http://example.com">Home</a>',
				),
				'link_data' => array(
					'home' => array(
						'url'        => 'http://example.com',
						'label'      => 'Home',
						'attributes' => array(
							'value',
						),
					),
				),
			),
			'an invalid attribute (empty string)'         => array(
				'expected'  => array(
					'home' => '<a href="http://example.com">Home</a>',
				),
				'link_data' => array(
					'home' => array(
						'url'        => 'http://example.com',
						'label'      => 'Home',
						'attributes' => array(
							'' => 'value',
						),
					),
				),
			),
			'an invalid value (object)'                   => array(
				'expected'  => array(
					'home' => '<a href="http://example.com">Home</a>',
				),
				'link_data' => array(
					'home' => array(
						'url'        => 'http://example.com',
						'label'      => 'Home',
						'attributes' => array(
							'rel' => (object) array( 'me', 'noreferrer' ),
						),
					),
				),
			),
		);
	}

	/**
	 * Test the `wp_create_links_current_class` filter.
	 *
	 * @ticket 42066
	 *
	 * @dataProvider data_should_filter_current_class
	 *
	 * @param mixed $custom_class The custom class(es) to use.
	 * @param array $expected     An array of link markup.
	 * @param array $link_data    An array of link data.
	 */
	public function test_should_filter_current_class( $custom_class, $expected, $link_data ) {
		add_filter(
			'wp_create_links_current_class',
			static function() use ( $custom_class ) {
				return $custom_class;
			}
		);

		$this->assertSameSetsWithIndex( $expected, wp_create_links( $link_data ) );
	}

	/**
	 * Data provider for test_should_filter_current_class().
	 *
	 * @return array[]
	 */
	public function data_should_filter_current_class() {
		return array(
			'an invalid custom current class and no additional classes' => array(
				'custom_class' => array(
					'custom-current-class',
				),
				'expected'     => array(
					'home' => '<a href="#" aria-current="page" class="current">home</a>',
				),
				'link_data'    => array(
					'home' => array(
						'current' => true,
					),
				),
			),
			'a custom current class and no additional classes' => array(
				'custom_class' => 'custom-current-class',
				'expected'     => array(
					'home' => '<a href="#" aria-current="page" class="custom-current-class">home</a>',
				),
				'link_data'    => array(
					'home' => array(
						'current' => true,
					),
				),
			),
			'a custom current class and an additional class' => array(
				'custom_class' => 'custom-current-class',
				'expected'     => array(
					'home' => '<a href="#" aria-current="page" class="additional-class custom-current-class">home</a>',
				),
				'link_data'    => array(
					'home' => array(
						'current'    => true,
						'attributes' => array(
							'class' => 'additional-class',
						),
					),
				),
			),
			'a custom current class and two additional classes (string)' => array(
				'custom_class' => 'custom-current-class',
				'expected'     => array(
					'home' => '<a href="#" aria-current="page" class="additional-class-1 additional-class-2 custom-current-class">home</a>',
				),
				'link_data'    => array(
					'home' => array(
						'current'    => true,
						'attributes' => array(
							'class' => 'additional-class-1 additional-class-2',
						),
					),
				),
			),
			'a custom current class and two additional invalid classes (array)' => array(
				'custom_class' => 'custom-current-class',
				'expected'     => array(
					'home' => '<a href="#" aria-current="page" class="custom-current-class">home</a>',
				),
				'link_data'    => array(
					'home' => array(
						'current'    => true,
						'attributes' => array(
							'class' => array(
								'additional-class-1',
								'additional-class-2',
							),
						),
					),
				),
			),
			'two invalid custom classes (array) and two additional classes (array)' => array(
				'custom_class' => array(
					'custom-current-class-1',
					'custom-current-class-2',
				),
				'expected'     => array(
					'home' => '<a href="#" aria-current="page" class="current">home</a>',
				),
				'link_data'    => array(
					'home' => array(
						'current'    => true,
						'attributes' => array(
							'class' => array(
								'additional-class-1',
								'additional-class-2',
							),
						),
					),
				),
			),
			'two invalid custom classes (array) and an additional invalid class (object)' => array(
				'custom_class' => array(
					'custom-current-class-1',
					'custom-current-class-2',
				),
				'expected'     => array(
					'home' => '<a href="#" aria-current="page" class="current">home</a>',
				),
				'link_data'    => array(
					'home' => array(
						'current'    => true,
						'attributes' => array(
							'class' => array(
								(object) array( 'additional-class' ),
							),
						),
					),
				),
			),
		);
	}
}
