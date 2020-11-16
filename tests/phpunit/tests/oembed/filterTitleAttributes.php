<?php

/**
 * @group oembed
 */
class Tests_Filter_oEmbed_Iframe_Title_Attribute extends WP_UnitTestCase {
	public function data_filter_oembed_iframe_title_attribute() {
		return array(
			array(
				'<p>Foo</p><iframe src=""></iframe><b>Bar</b>',
				array(
					'type' => 'rich',
				),
				'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
				'<p>Foo</p><iframe src=""></iframe><b>Bar</b>',
			),
			array(
				'<p>Foo</p><iframe src="" title="Hello World"></iframe><b>Bar</b>',
				array(
					'type' => 'rich',
				),
				'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
				'<p>Foo</p><iframe title="Hello World" src=""></iframe><b>Bar</b>',
			),
			array(
				'<p>Foo</p>',
				array(
					'type'  => 'rich',
					'title' => 'Hello World',
				),
				'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
				'<p>Foo</p>',
			),
			array(
				'<p title="Foo">Bar</p>',
				array(
					'type'  => 'rich',
					'title' => 'Hello World',
				),
				'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
				'<p title="Foo">Bar</p>',
			),
			array(
				'<p>Foo</p><iframe src=""></iframe><b>Bar</b>',
				array(
					'type'  => 'rich',
					'title' => 'Hello World',
				),
				'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
				'<p>Foo</p><iframe title="Hello World" src=""></iframe><b>Bar</b>',
			),
			array(
				'<iframe src="" title="Foo"></iframe>',
				array(
					'type'  => 'rich',
					'title' => 'Bar',
				),
				'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
				'<iframe title="Foo" src=""></iframe>',
			),
		);
	}

	/**
	 * @dataProvider data_filter_oembed_iframe_title_attribute
	 */
	public function test_oembed_iframe_title_attribute( $html, $oembed_data, $url, $expected ) {
		$actual = wp_filter_oembed_iframe_title_attribute( $html, (object) $oembed_data, $url );

		$this->assertSame( $expected, $actual );
	}

	public function test_filter_oembed_iframe_title_attribute() {
		add_filter( 'oembed_iframe_title_attribute', array( $this, '_filter_oembed_iframe_title_attribute' ) );

		$actual = wp_filter_oembed_iframe_title_attribute(
			'<iframe title="Foo" src=""></iframe>',
			(object) array(
				'type'  => 'rich',
				'title' => 'Bar',
			),
			'https://www.youtube.com/watch?v=dQw4w9WgXcQ'
		);

		remove_filter( 'oembed_iframe_title_attribute', array( $this, '_filter_oembed_iframe_title_attribute' ) );

		$this->assertSame( '<iframe title="Baz" src=""></iframe>', $actual );
	}

	public function test_filter_oembed_iframe_title_attribute_does_not_modify_other_tags() {
		add_filter( 'oembed_iframe_title_attribute', array( $this, '_filter_oembed_iframe_title_attribute' ) );

		$actual = wp_filter_oembed_iframe_title_attribute(
			'<p title="Bar">Baz</p><iframe title="Foo" src=""></iframe>',
			(object) array(
				'type'  => 'rich',
				'title' => 'Bar',
			),
			'https://www.youtube.com/watch?v=dQw4w9WgXcQ'
		);

		remove_filter( 'oembed_iframe_title_attribute', array( $this, '_filter_oembed_iframe_title_attribute' ) );

		$this->assertSame( '<p title="Bar">Baz</p><iframe title="Baz" src=""></iframe>', $actual );
	}

	public function _filter_oembed_iframe_title_attribute() {
		return 'Baz';
	}
}
