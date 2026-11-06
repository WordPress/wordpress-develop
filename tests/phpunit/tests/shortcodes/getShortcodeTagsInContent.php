<?php

/**
 * Tests for the get_shortcode_tags_in_content() function.
 *
 * @group shortcode
 *
 * @covers ::get_shortcode_tags_in_content
 */
class Tests_Shortcodes_GetShortcodeTagsInContent extends WP_UnitTestCase {

	/**
	 * Registered shortcodes, to restore after each test.
	 *
	 * @var array
	 */
	private $original_shortcode_tags;

	public function set_up() {
		parent::set_up();

		global $shortcode_tags;
		$this->original_shortcode_tags = $shortcode_tags;

		remove_all_shortcodes();

		add_shortcode( 'tags-alpha', '__return_empty_string' );
		add_shortcode( 'tags-beta', '__return_empty_string' );
		add_shortcode( 'tags-gamma', '__return_empty_string' );
	}

	public function tear_down() {
		global $shortcode_tags;
		$shortcode_tags = $this->original_shortcode_tags;

		parent::tear_down();
	}

	/**
	 * Tests that the registered shortcodes found in the content are returned, in the order they appear.
	 *
	 * @ticket 66500
	 *
	 * @dataProvider data_content
	 *
	 * @param string   $content  Content to check.
	 * @param string[] $expected Expected tags.
	 */
	public function test_should_return_the_registered_tags_found_in_the_content( $content, $expected ) {
		$this->assertSame( $expected, get_shortcode_tags_in_content( $content ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_content() {
		return array(
			'one self closing shortcode'                   => array( '[tags-alpha /]', array( 'tags-alpha' ) ),
			'one shortcode without a slash'                => array( '[tags-alpha]', array( 'tags-alpha' ) ),
			'one enclosing shortcode'                      => array( '[tags-alpha]text[/tags-alpha]', array( 'tags-alpha' ) ),
			'shortcode with attributes'                    => array( '[tags-alpha a="1" b=2 /]', array( 'tags-alpha' ) ),
			'shortcode in the middle of text'              => array( 'Before [tags-beta] after', array( 'tags-beta' ) ),
			'tags in the order they appear'                => array( '[tags-gamma] [tags-alpha] [tags-beta]', array( 'tags-gamma', 'tags-alpha', 'tags-beta' ) ),
			'repeated tags are repeated'                   => array( '[tags-alpha] [tags-alpha]', array( 'tags-alpha', 'tags-alpha' ) ),
			'unregistered shortcodes are ignored'          => array( '[tags-alpha] [tags-unknown] [tags-beta /]', array( 'tags-alpha', 'tags-beta' ) ),
			'only unregistered shortcodes'                 => array( '[tags-unknown] [tags-other /]', array() ),
			'a tag that only starts like a registered tag' => array( '[tags-alphabet] [tags-alpha-b]', array() ),
			'a space after the bracket'                    => array( '[ tags-alpha]', array() ),
			'brackets that are not shortcodes'             => array( 'An [array] of [1, 2] items', array() ),
			'content without brackets'                     => array( 'No shortcodes here', array() ),
			'empty string'                                 => array( '', array() ),
		);
	}

	/**
	 * Tests that the shortcodes inside the content of another shortcode are returned too.
	 *
	 * @ticket 66500
	 *
	 * @dataProvider data_nested_content
	 *
	 * @param string   $content  Content to check.
	 * @param string[] $expected Expected tags.
	 */
	public function test_should_return_the_tags_nested_in_the_content_of_a_shortcode( $content, $expected ) {
		$this->assertSame( $expected, get_shortcode_tags_in_content( $content ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_nested_content() {
		return array(
			'one level'                           => array(
				'[tags-alpha]x [tags-beta /] y[/tags-alpha]',
				array( 'tags-alpha', 'tags-beta' ),
			),
			'two levels'                          => array(
				'[tags-alpha][tags-beta][tags-gamma /][/tags-beta][/tags-alpha]',
				array( 'tags-alpha', 'tags-beta', 'tags-gamma' ),
			),
			'nested tags before the next sibling' => array(
				'[tags-alpha][tags-beta /][/tags-alpha] [tags-gamma /]',
				array( 'tags-alpha', 'tags-beta', 'tags-gamma' ),
			),
			'the same tag nested in itself'       => array(
				'[tags-alpha][tags-alpha /][/tags-alpha]',
				array( 'tags-alpha', 'tags-alpha' ),
			),
			'an unregistered shortcode around a registered one' => array(
				'[tags-unknown][tags-beta /][/tags-unknown]',
				array( 'tags-beta' ),
			),
			'an unregistered shortcode inside a registered one' => array(
				'[tags-alpha][tags-unknown][tags-beta /][/tags-alpha]',
				array( 'tags-alpha', 'tags-beta' ),
			),
			'a shortcode in the attributes is not returned' => array(
				'[tags-alpha text="[tags-beta]" /]',
				array( 'tags-alpha' ),
			),
		);
	}

	/**
	 * Tests that only the shortcodes that are registered when the function is called are returned.
	 *
	 * @ticket 66500
	 */
	public function test_should_use_the_shortcodes_registered_at_the_time_of_the_call() {
		$content = '[tags-alpha] [tags-late]';

		$this->assertSame( array( 'tags-alpha' ), get_shortcode_tags_in_content( $content ), 'The shortcode that is not registered yet should be ignored.' );

		add_shortcode( 'tags-late', '__return_empty_string' );

		$this->assertSame( array( 'tags-alpha', 'tags-late' ), get_shortcode_tags_in_content( $content ), 'The shortcode that was registered should be returned.' );

		remove_shortcode( 'tags-alpha' );

		$this->assertSame( array( 'tags-late' ), get_shortcode_tags_in_content( $content ), 'The shortcode that was removed should be ignored.' );
	}

	/**
	 * Tests that no tags are returned when there are no registered shortcodes.
	 *
	 * @ticket 66500
	 */
	public function test_should_return_an_empty_array_when_no_shortcodes_are_registered() {
		remove_all_shortcodes();

		$this->assertSame( array(), get_shortcode_tags_in_content( '[tags-alpha] [gallery]' ) );
	}
}
