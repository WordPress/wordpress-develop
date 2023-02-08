<?php

/**
 * @group post
 * @group media
 * @group upload
 *
 * @covers ::wp_get_attachment_link
 */
class Tests_Post_WpGetAttachmentLink extends WP_UnitTestCase {

	/**
	 * The ID of an attachment for testing.
	 *
	 * @var int $attachment
	 */
	private static $attachment;

	/**
	 * Creates an attachment for testing before any tests run.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		self::$attachment = self::factory()->attachment->create();
	}

	/**
	 * Tests that wp_get_attachment_link() applies the
	 * wp_get_attachment_link_attributes filter.
	 *
	 * @ticket 41574
	 *
	 * @dataProvider data_should_apply_attributes_filter
	 *
	 * @param array  $attributes Attributes to return from the callback.
	 * @param string $expected   The substring expected to be in the attachment link.
	 */
	public function test_should_apply_attributes_filter( $attributes, $expected ) {
		$expected = str_replace( 'ATTACHMENT_ID', self::$attachment, $expected );

		add_filter(
			'wp_get_attachment_link_attributes',
			static function( $attr ) use ( $attributes ) {
				return array_merge( $attr, $attributes );
			}
		);

		$this->assertStringContainsString(
			$expected,
			wp_get_attachment_link( self::$attachment )
		);
	}

	/**
	 * Data provider for test_should_apply_attributes_filter().
	 *
	 * @return array[]
	 */
	public function data_should_apply_attributes_filter() {
		return array(
			'no new attributes'                         => array(
				'attributes' => array(),
				'expected'   => "<a href='http://example.org/?attachment_id=ATTACHMENT_ID'>",
			),
			'one new attribute'                         => array(
				'attributes' => array(
					'class' => 'test-attribute-filter',
				),
				'expected'   => " class='test-attribute-filter'",
			),
			'two new attributes'                        => array(
				'attributes' => array(
					'class' => 'test-attribute-filter',
					'id'    => 'test-attribute-filter-1',
				),
				'expected'   => " class='test-attribute-filter' id='test-attribute-filter-1'",
			),
			'an existing attribute'                     => array(
				'attributes' => array(
					'href' => 'http://test-attribute-filter.org',
				),
				'expected'   => " href='http://test-attribute-filter.org'",
			),
			'an existing attribute and a new attribute' => array(
				'attributes' => array(
					'href'  => 'http://test-attribute-filter.org',
					'class' => 'test-attribute-filter',
				),
				'expected'   => " href='http://test-attribute-filter.org' class='test-attribute-filter'",
			),
			'an attribute name with unsafe characters'  => array(
				'attributes' => array(
					"> <script>alert('Howdy, admin!')</script> <a href=''></a" => '',
				),
				'expected'   => " &gt; &lt;script&gt;alert(&#039;Howdy, admin!&#039;)&lt;/script&gt; &lt;a href=&#039;&#039;&gt;&lt;/a=''",
			),
			'an attribute value with unsafe characters' => array(
				'attributes' => array(
					'class' => "'> <script>alert('Howdy, admin!')</script> <a href=''></a",
				),
				'expected'   => '&#039;&gt; &lt;script&gt;alert(&#039;Howdy, admin!&#039;)&lt;/script&gt; &lt;a href=&#039;&#039;&gt;&lt;/a',
			),
		);
	}
}
