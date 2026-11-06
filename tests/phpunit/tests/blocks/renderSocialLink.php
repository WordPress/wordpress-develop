<?php
/**
 * Tests for the server-side rendering of the Social Link block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 */
class Tests_Blocks_RenderSocialLink extends WP_UnitTestCase {

	/**
	 * Original block to render of the block supports.
	 *
	 * @var array|null
	 */
	private $original_block_to_render;

	public function set_up() {
		parent::set_up();

		$this->original_block_to_render     = WP_Block_Supports::$block_to_render;
		WP_Block_Supports::$block_to_render = null;
	}

	public function tear_down() {
		WP_Block_Supports::$block_to_render = $this->original_block_to_render;

		parent::tear_down();
	}

	/**
	 * Renders the block.
	 *
	 * @param array $attributes Attributes of the block.
	 * @param array $context    Context of the block.
	 * @return string The rendered block.
	 */
	private function render( $attributes, $context = array() ) {
		$block = new WP_Block(
			array(
				'blockName'    => 'core/social-link',
				'attrs'        => $attributes,
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);

		$block->context = $context;

		return render_block_core_social_link( $attributes, '', $block );
	}

	/**
	 * Gets the attributes of the first tag of a type of the output.
	 *
	 * @param string $output Rendered block.
	 * @param string $tag    Name of the tag.
	 * @return array|null The attributes, keyed by name, or null when there is no such tag.
	 */
	private function get_attributes_of( $output, $tag ) {
		$processor = new WP_HTML_Tag_Processor( $output );

		if ( ! $processor->next_tag( $tag ) ) {
			return null;
		}

		$attributes = array();
		foreach ( $processor->get_attribute_names_with_prefix( '' ) as $name ) {
			$attributes[ $name ] = $processor->get_attribute( $name );
		}

		return $attributes;
	}

	/**
	 * Tests that nothing is rendered without a URL that is valid.
	 *
	 * @ticket 66529
	 *
	 * @covers ::render_block_core_social_link
	 *
	 * @dataProvider data_attributes_without_a_valid_url
	 *
	 * @param array $attributes Attributes of the block.
	 */
	public function test_should_render_nothing_without_a_valid_url( $attributes ) {
		$this->assertSame( '', $this->render( $attributes ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_attributes_without_a_valid_url() {
		return array(
			'no url'       => array( array( 'service' => 'facebook' ) ),
			'an empty url' => array(
				array(
					'service' => 'facebook',
					'url'     => '',
				),
			),
			'a null url'   => array(
				array(
					'service' => 'facebook',
					'url'     => null,
				),
			),
			'false'        => array(
				array(
					'service' => 'facebook',
					'url'     => false,
				),
			),
			'zero'         => array(
				array(
					'service' => 'facebook',
					'url'     => '0',
				),
			),
			'an array'     => array(
				array(
					'service' => 'facebook',
					'url'     => array( 'https://example.com' ),
				),
			),
			'an integer'   => array(
				array(
					'service' => 'facebook',
					'url'     => 5,
				),
			),
		);
	}

	/**
	 * Tests the link of the item.
	 *
	 * @ticket 66529
	 *
	 * @covers ::render_block_core_social_link
	 */
	public function test_should_render_a_list_item_with_a_link_to_the_url() {
		$output = $this->render(
			array(
				'service' => 'facebook',
				'url'     => 'https://example.com/me',
			)
		);

		$item = $this->get_attributes_of( $output, 'li' );
		$link = $this->get_attributes_of( $output, 'a' );

		$this->assertSame( 'wp-social-link wp-social-link-facebook', $item['class'] );
		$this->assertSame( 'https://example.com/me', $link['href'] );
		$this->assertSame( 'wp-block-social-link-anchor', $link['class'] );
		$this->assertArrayNotHasKey( 'target', $link, 'The link should not open in a new tab.' );
		$this->assertArrayNotHasKey( 'rel', $link, 'The link should not have a rel.' );
		$this->assertStringContainsString( block_core_social_link_get_icon( 'facebook' ), $output, 'The icon of the service should be in the link.' );
		$this->assertStringEndsWith( '</span></a></li>', $output );
	}

	/**
	 * Tests the URL of the link.
	 *
	 * @ticket 66529
	 *
	 * @covers ::render_block_core_social_link
	 *
	 * @dataProvider data_urls
	 *
	 * @param string $url      URL of the block.
	 * @param string $expected Expected URL of the link.
	 */
	public function test_should_use_the_url_with_a_scheme( $url, $expected ) {
		$link = $this->get_attributes_of(
			$this->render(
				array(
					'service' => 'facebook',
					'url'     => $url,
				)
			),
			'a'
		);

		$this->assertSame( $expected, $link['href'] );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_urls() {
		return array(
			'an https URL'                   => array( 'https://example.com/me', 'https://example.com/me' ),
			'an http URL'                    => array( 'http://example.com/me', 'http://example.com/me' ),
			'a URL without a scheme'         => array( 'example.com/me', 'https://example.com/me' ),
			'a URL with www'                 => array( 'www.example.com', 'https://www.example.com' ),
			'a URL without a protocol'       => array( '//example.com/me', '//example.com/me' ),
			'a fragment'                     => array( '#section', '#section' ),
			'a protocol that is not allowed' => array( 'javascript:alert( 1 )', '' ),
			'a tel link'                     => array( 'tel:+123456789', 'tel:+123456789' ),
			'a URL with a query'             => array( 'https://example.com/?a=1&b=2', 'https://example.com/?a=1&b=2' ),
		);
	}

	/**
	 * Tests that an email gets the mailto scheme.
	 *
	 * @ticket 66529
	 *
	 * @covers ::render_block_core_social_link
	 */
	public function test_should_add_the_mailto_scheme_to_an_email() {
		$link = $this->get_attributes_of(
			$this->render(
				array(
					'service' => 'mail',
					'url'     => 'someone@example.com',
				)
			),
			'a'
		);

		$this->assertStringStartsWith( 'mailto:', $link['href'] );
		$this->assertStringNotContainsString( 'https://', $link['href'] );
		$this->assertSame( 'someone@example.com', html_entity_decode( substr( $link['href'], strlen( 'mailto:' ) ) ), 'The address should follow the scheme.' );
	}

	/**
	 * Tests the text of the label.
	 *
	 * @ticket 66529
	 *
	 * @covers ::render_block_core_social_link
	 *
	 * @dataProvider data_labels
	 *
	 * @param array  $attributes Attributes of the block.
	 * @param string $expected   Expected text of the label.
	 */
	public function test_should_use_the_label_or_the_name_of_the_service( $attributes, $expected ) {
		$output = $this->render( array_merge( array( 'url' => 'https://example.com' ), $attributes ) );

		$this->assertStringContainsString( '">' . $expected . '</span></a>', $output );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_labels() {
		return array(
			'the name of the service'        => array( array( 'service' => 'facebook' ), 'Facebook' ),
			'a label'                        => array(
				array(
					'service' => 'facebook',
					'label'   => 'My page',
				),
				'My page',
			),
			'a label with spaces'            => array(
				array(
					'service' => 'facebook',
					'label'   => '  My page  ',
				),
				'My page',
			),
			'a label that is only spaces'    => array(
				array(
					'service' => 'facebook',
					'label'   => '   ',
				),
				'Facebook',
			),
			'an empty label'                 => array(
				array(
					'service' => 'facebook',
					'label'   => '',
				),
				'Facebook',
			),
			'a label that is not a string'   => array(
				array(
					'service' => 'facebook',
					'label'   => 5,
				),
				'Facebook',
			),
			'an escaped label'               => array(
				array(
					'service' => 'facebook',
					'label'   => 'Tom & <b>Jerry</b>',
				),
				'Tom &amp; &lt;b&gt;Jerry&lt;/b&gt;',
			),
			'an unknown service'             => array( array( 'service' => 'not-a-service' ), block_core_social_link_get_name( 'share' ) ),
			'no service'                     => array( array(), block_core_social_link_get_name( 'share' ) ),
			'a service that is not a string' => array( array( 'service' => 5 ), block_core_social_link_get_name( 'share' ) ),
		);
	}

	/**
	 * Tests the classes and the icon of an unknown service.
	 *
	 * @ticket 66529
	 *
	 * @covers ::render_block_core_social_link
	 */
	public function test_should_use_the_share_icon_and_the_icon_class_without_a_service() {
		$output = $this->render( array( 'url' => 'https://example.com' ) );
		$item   = $this->get_attributes_of( $output, 'li' );

		$this->assertSame( 'wp-social-link wp-social-link-Icon', $item['class'] );
		$this->assertStringContainsString( block_core_social_link_get_icon( 'share' ), $output );

		$item = $this->get_attributes_of(
			$this->render(
				array(
					'service' => 5,
					'url'     => 'https://example.com',
				)
			),
			'li'
		);

		$this->assertSame( 'wp-social-link wp-social-link-Icon', $item['class'], 'A service that is not a string should be handled as no service.' );
	}

	/**
	 * Tests the label that is visible when it is requested.
	 *
	 * @ticket 66529
	 *
	 * @covers ::render_block_core_social_link
	 *
	 * @dataProvider data_show_labels
	 *
	 * @param array  $context  Context of the block.
	 * @param string $expected Expected class of the label.
	 */
	public function test_should_show_the_label_only_when_it_is_requested( $context, $expected ) {
		$output = $this->render(
			array(
				'service' => 'facebook',
				'url'     => 'https://example.com',
			),
			$context
		);

		$this->assertStringContainsString( '<span class="' . $expected . '">Facebook</span>', $output );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_show_labels() {
		return array(
			'no value' => array( array(), 'wp-block-social-link-label screen-reader-text' ),
			'false'    => array( array( 'showLabels' => false ), 'wp-block-social-link-label screen-reader-text' ),
			'null'     => array( array( 'showLabels' => null ), 'wp-block-social-link-label screen-reader-text' ),
			'true'     => array( array( 'showLabels' => true ), 'wp-block-social-link-label' ),
		);
	}

	/**
	 * Tests the colors of the icons.
	 *
	 * @ticket 66529
	 *
	 * @covers ::render_block_core_social_link
	 */
	public function test_should_add_the_classes_and_the_styles_of_the_colors() {
		$output = $this->render(
			array(
				'service' => 'x',
				'url'     => 'https://example.com',
			),
			array(
				'iconColor'                => 'vivid-red',
				'iconColorValue'           => '#ff0000',
				'iconBackgroundColor'      => 'cyan',
				'iconBackgroundColorValue' => '#00ffff',
			)
		);

		$item = $this->get_attributes_of( $output, 'li' );

		$this->assertSame( 'wp-social-link wp-social-link-x has-vivid-red-color has-cyan-background-color', $item['class'] );
		$this->assertSame( 'color:#ff0000;background-color:#00ffff', $item['style'] );
	}

	/**
	 * Tests the attributes of a link that opens in a new tab.
	 *
	 * @ticket 66529
	 *
	 * @covers ::render_block_core_social_link
	 *
	 * @dataProvider data_new_tab_rels
	 *
	 * @param mixed  $rel      Rel of the block.
	 * @param string $expected Expected rel of the link.
	 */
	public function test_should_open_the_link_in_a_new_tab_with_a_safe_rel( $rel, $expected ) {
		$attributes = array(
			'service' => 'facebook',
			'url'     => 'https://example.com',
		);

		if ( null !== $rel ) {
			$attributes['rel'] = $rel;
		}

		$link = $this->get_attributes_of( $this->render( $attributes, array( 'openInNewTab' => true ) ), 'a' );

		$this->assertSame( '_blank', $link['target'] );
		$this->assertSame( $expected, $link['rel'] );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_new_tab_rels() {
		return array(
			'no rel'                     => array( null, 'noopener nofollow' ),
			'an empty rel'               => array( '', 'noopener nofollow' ),
			'a rel'                      => array( 'me', 'me noopener nofollow' ),
			'a rel that is not a string' => array( 5, 'noopener nofollow' ),
		);
	}

	/**
	 * Tests the rel of a link that does not open in a new tab.
	 *
	 * @ticket 66529
	 *
	 * @covers ::render_block_core_social_link
	 *
	 * @dataProvider data_rels
	 *
	 * @param mixed       $rel      Rel of the block.
	 * @param string|null $expected Expected rel of the link, or null for no rel.
	 */
	public function test_should_only_add_the_rel_when_it_is_set( $rel, $expected ) {
		$attributes = array(
			'service' => 'facebook',
			'url'     => 'https://example.com',
		);

		if ( null !== $rel ) {
			$attributes['rel'] = $rel;
		}

		$link = $this->get_attributes_of( $this->render( $attributes, array( 'openInNewTab' => false ) ), 'a' );

		$this->assertArrayNotHasKey( 'target', $link );

		if ( null === $expected ) {
			$this->assertArrayNotHasKey( 'rel', $link );
		} else {
			$this->assertSame( $expected, $link['rel'] );
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_rels() {
		return array(
			'no rel'                     => array( null, null ),
			'an empty rel'               => array( '', null ),
			'a rel'                      => array( 'me', 'me' ),
			'a rel with spaces'          => array( '  me  ', 'me' ),
			'a rel that is not a string' => array( 5, null ),
		);
	}

	/**
	 * Tests that the block is registered with the render callback.
	 *
	 * @ticket 66529
	 *
	 * @covers ::register_block_core_social_link
	 */
	public function test_should_register_the_block_with_the_render_callback() {
		$registry       = WP_Block_Type_Registry::get_instance();
		$was_registered = $registry->is_registered( 'core/social-link' );

		if ( $was_registered ) {
			unregister_block_type( 'core/social-link' );
		}

		register_block_core_social_link();
		$block_type = $registry->get_registered( 'core/social-link' );

		if ( ! $was_registered ) {
			unregister_block_type( 'core/social-link' );
		}

		$this->assertInstanceOf( WP_Block_Type::class, $block_type );
		$this->assertSame( 'render_block_core_social_link', $block_type->render_callback );
	}
}
