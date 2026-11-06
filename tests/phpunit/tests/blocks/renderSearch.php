<?php
/**
 * Tests for the server-side rendering of the Search block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 * @group interactivity-api
 */
class Tests_Blocks_RenderSearch extends WP_UnitTestCase {

	/**
	 * Original block to render of the block supports.
	 *
	 * @var array|null
	 */
	private $original_block_to_render;

	/**
	 * Original script modules.
	 *
	 * @var WP_Script_Modules|null
	 */
	private $original_script_modules;

	/**
	 * Original features of the theme.
	 *
	 * @var array
	 */
	private $original_theme_features;

	public function set_up() {
		global $wp_script_modules, $_wp_theme_features;

		parent::set_up();

		$this->original_block_to_render     = WP_Block_Supports::$block_to_render;
		$this->original_script_modules      = $wp_script_modules;
		$this->original_theme_features      = $_wp_theme_features;
		WP_Block_Supports::$block_to_render = null;
		$wp_script_modules                  = null;
	}

	public function tear_down() {
		global $wp_script_modules, $_wp_theme_features;

		WP_Block_Supports::$block_to_render = $this->original_block_to_render;
		$wp_script_modules                  = $this->original_script_modules;
		$_wp_theme_features                 = $this->original_theme_features;

		parent::tear_down();
	}

	/**
	 * Renders the block with the defaults of the block, and the attributes that are passed.
	 *
	 * @param array $attributes Attributes of the block.
	 * @return string The rendered block.
	 */
	private function render( $attributes = array() ) {
		return render_block_core_search(
			array_merge(
				array(
					'placeholder'    => '',
					'buttonPosition' => 'button-outside',
					'buttonUseIcon'  => false,
					'showLabel'      => true,
				),
				$attributes
			)
		);
	}

	/**
	 * Gets the elements of the HTML that are of a tag.
	 *
	 * @param string $html HTML.
	 * @param string $tag  Name of the tag, in lower case.
	 * @return array[] The attributes of each element, keyed by name.
	 */
	private function get_elements( $html, $tag ) {
		$processor = new WP_HTML_Tag_Processor( $html );
		$elements  = array();

		while ( $processor->next_tag() ) {
			if ( strtolower( $processor->get_tag() ) !== $tag ) {
				continue;
			}

			$attributes = array();
			foreach ( $processor->get_attribute_names_with_prefix( '' ) as $name ) {
				$attributes[ $name ] = $processor->get_attribute( $name );
			}

			$elements[] = $attributes;
		}

		return $elements;
	}

	/**
	 * Gets the classes of an element as a list.
	 *
	 * @param array $element Attributes of the element.
	 * @return string[] The classes.
	 */
	private function get_classes( $element ) {
		return isset( $element['class'] ) ? preg_split( '/\s+/', trim( $element['class'] ) ) : array();
	}

	/**
	 * Tests the form of the block.
	 *
	 * @ticket 66534
	 *
	 * @covers ::render_block_core_search
	 */
	public function test_should_render_a_search_form_that_goes_to_the_home_page() {
		$output = $this->render();
		$form   = $this->get_elements( $output, 'form' );

		$this->assertCount( 1, $form, 'There should be a form.' );
		$this->assertSame( 'search', $form[0]['role'] );
		$this->assertSame( 'get', $form[0]['method'] );
		$this->assertSame( home_url( '/' ), $form[0]['action'] );
		$this->assertStringStartsWith( '<form ', $output );
		$this->assertStringEndsWith( '</form>', $output );
	}

	/**
	 * Tests the input of the block.
	 *
	 * @ticket 66534
	 *
	 * @covers ::render_block_core_search
	 */
	public function test_should_render_the_search_input() {
		$inputs = $this->get_elements( $this->render( array( 'placeholder' => 'Find it' ) ), 'input' );
		$input  = $inputs[0];

		$this->assertSame( 'search', $input['type'] );
		$this->assertSame( 's', $input['name'] );
		$this->assertArrayHasKey( 'required', $input );
		$this->assertSame( 'Find it', $input['placeholder'] );
		$this->assertMatchesRegularExpression( '/^wp-block-search__input-\d+$/', $input['id'] );
		$this->assertContains( 'wp-block-search__input', $this->get_classes( $input ) );
		$this->assertSame( '', $input['value'], 'The input should be empty outside a search.' );
	}

	/**
	 * Tests that the input has the search that is being made.
	 *
	 * @ticket 66534
	 *
	 * @covers ::render_block_core_search
	 */
	public function test_should_fill_the_input_with_the_search_query() {
		$this->go_to( '/?s=hello+world' );

		$inputs = $this->get_elements( $this->render(), 'input' );

		$this->assertSame( 'hello world', $inputs[0]['value'] );
	}

	/**
	 * Tests that the search query is escaped.
	 *
	 * @ticket 66534
	 *
	 * @covers ::render_block_core_search
	 */
	public function test_should_escape_the_search_query() {
		$this->go_to( '/?s=' . rawurlencode( '"><script>alert(1)</script>' ) );

		$output = $this->render();

		$this->assertStringNotContainsString( '<script>', $output );
	}

	/**
	 * Tests that the search query is not escaped twice.
	 *
	 * @ticket 66534
	 *
	 * @covers ::render_block_core_search
	 */
	public function test_should_not_escape_the_search_query_twice() {
		$this->go_to( '/?s=' . rawurlencode( 'Tom & Jerry' ) );

		$inputs = $this->get_elements( $this->render(), 'input' );

		$this->assertSame( 'Tom & Jerry', $inputs[0]['value'] );
	}

	/**
	 * Tests that the URL of the form is escaped.
	 *
	 * @ticket 66534
	 *
	 * @covers ::render_block_core_search
	 */
	public function test_should_escape_the_url_of_the_form() {
		add_filter(
			'home_url',
			static function () {
				return 'https://example.org/?a=1&b=2';
			}
		);

		$this->assertStringContainsString( 'action="https://example.org/?a=1&#038;b=2"', $this->render() );
	}

	/**
	 * Tests that the label comes before the field.
	 *
	 * @ticket 66534
	 *
	 * @covers ::render_block_core_search
	 */
	public function test_should_render_the_label_before_the_field() {
		$output = $this->render();

		$this->assertLessThan( strpos( $output, 'wp-block-search__inside-wrapper' ), strpos( $output, '<label' ) );
	}

	/**
	 * Tests that the label and the input use the same ID.
	 *
	 * @ticket 66534
	 *
	 * @covers ::render_block_core_search
	 */
	public function test_should_link_the_label_to_the_input() {
		$output = $this->render();
		$label  = $this->get_elements( $output, 'label' );
		$inputs = $this->get_elements( $output, 'input' );

		$this->assertSame( $inputs[0]['id'], $label[0]['for'] );
	}

	/**
	 * Tests that the ID of each block is different.
	 *
	 * @ticket 66534
	 *
	 * @covers ::render_block_core_search
	 */
	public function test_should_use_a_different_id_for_each_block() {
		$first  = $this->get_elements( $this->render(), 'input' );
		$second = $this->get_elements( $this->render(), 'input' );

		$this->assertNotSame( $first[0]['id'], $second[0]['id'] );
	}

	/**
	 * Tests the label.
	 *
	 * @ticket 66534
	 *
	 * @covers ::render_block_core_search
	 *
	 * @dataProvider data_labels
	 *
	 * @param array  $attributes   Attributes of the block.
	 * @param string $text         Expected text of the label.
	 * @param bool   $screen_reader Whether the label is only for the screen readers.
	 */
	public function test_should_render_the_label( $attributes, $text, $screen_reader ) {
		$output = $this->render( $attributes );
		$labels = $this->get_elements( $output, 'label' );

		$this->assertStringContainsString( '>' . $text . '</label>', $output );
		$this->assertContains( 'wp-block-search__label', $this->get_classes( $labels[0] ) );

		if ( $screen_reader ) {
			$this->assertContains( 'screen-reader-text', $this->get_classes( $labels[0] ) );
		} else {
			$this->assertNotContains( 'screen-reader-text', $this->get_classes( $labels[0] ) );
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_labels() {
		return array(
			'the default label'           => array( array( 'showLabel' => true ), 'Search', false ),
			'a label'                     => array(
				array(
					'showLabel' => true,
					'label'     => 'Find posts',
				),
				'Find posts',
				false,
			),
			'a label that is hidden'      => array(
				array(
					'showLabel' => false,
					'label'     => 'Find posts',
				),
				'Find posts',
				true,
			),
			'no value to show the label'  => array( array( 'showLabel' => null ), 'Search', true ),
			'an empty label'              => array(
				array(
					'showLabel' => true,
					'label'     => '',
				),
				'Search',
				true,
			),
			'a label with allowed markup' => array(
				array(
					'showLabel' => true,
					'label'     => 'Find <strong>posts</strong>',
				),
				'Find <strong>posts</strong>',
				false,
			),
			'a label with a script'       => array(
				array(
					'showLabel' => true,
					'label'     => 'Find<script>alert( 1 );</script> posts',
				),
				'Find posts',
				false,
			),
		);
	}

	/**
	 * Tests the hidden inputs of the query.
	 *
	 * @ticket 66534
	 *
	 * @covers ::render_block_core_search
	 */
	public function test_should_add_the_query_parameters_as_hidden_inputs() {
		$inputs = $this->get_elements(
			$this->render(
				array(
					'query' => array(
						'post_type' => 'page',
						'a"b'       => 'c"d',
					),
				)
			),
			'input'
		);

		$hidden = array_values(
			array_filter(
				$inputs,
				static function ( $input ) {
					return isset( $input['type'] ) && 'hidden' === $input['type'];
				}
			)
		);

		$this->assertCount( 2, $hidden );
		$this->assertSame( 'post_type', $hidden[0]['name'] );
		$this->assertSame( 'page', $hidden[0]['value'] );
		$this->assertSame( 'a"b', $hidden[1]['name'], 'The name should be escaped.' );
		$this->assertSame( 'c"d', $hidden[1]['value'], 'The value should be escaped.' );
	}

	/**
	 * Tests that there are no hidden inputs without parameters.
	 *
	 * @ticket 66534
	 *
	 * @covers ::render_block_core_search
	 *
	 * @dataProvider data_queries_without_parameters
	 *
	 * @param mixed $query Value of the query.
	 */
	public function test_should_not_add_hidden_inputs_without_parameters( $query ) {
		$inputs = $this->get_elements( $this->render( array( 'query' => $query ) ), 'input' );

		$this->assertCount( 1, $inputs );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_queries_without_parameters() {
		return array(
			'an empty array' => array( array() ),
			'null'           => array( null ),
			'false'          => array( false ),
		);
	}

	/**
	 * Tests the button.
	 *
	 * @ticket 66534
	 *
	 * @covers ::render_block_core_search
	 */
	public function test_should_render_a_button_with_a_text() {
		$output  = $this->render( array( 'buttonText' => 'Go' ) );
		$buttons = $this->get_elements( $output, 'button' );

		$this->assertCount( 1, $buttons );
		$this->assertSame( 'submit', $buttons[0]['type'] );
		$this->assertSame( 'Go', $buttons[0]['aria-label'] );
		$this->assertContains( 'wp-block-search__button', $this->get_classes( $buttons[0] ) );
		$this->assertContains( 'wp-element-button', $this->get_classes( $buttons[0] ), 'The button should have the class of the element.' );
		$this->assertNotContains( 'has-icon', $this->get_classes( $buttons[0] ) );
		$this->assertStringContainsString( '>Go</button>', $output );
		$this->assertStringNotContainsString( '<svg', $output );
	}

	/**
	 * Tests the default text of the button.
	 *
	 * @ticket 66534
	 *
	 * @covers ::render_block_core_search
	 */
	public function test_should_use_the_default_text_for_the_button() {
		$output  = $this->render();
		$buttons = $this->get_elements( $output, 'button' );

		$this->assertStringContainsString( '>Search</button>', $output );
		$this->assertSame( 'Search', $buttons[0]['aria-label'] );
	}

	/**
	 * Tests the text of the button.
	 *
	 * @ticket 66534
	 *
	 * @covers ::render_block_core_search
	 */
	public function test_should_clean_the_text_of_the_button() {
		$output  = $this->render( array( 'buttonText' => 'Go <strong>now</strong><script>alert( 1 );</script>' ) );
		$buttons = $this->get_elements( $output, 'button' );

		$this->assertStringContainsString( '>Go <strong>now</strong></button>', $output, 'The text should have the allowed markup.' );
		$this->assertSame( 'Go now', $buttons[0]['aria-label'], 'The label should have no markup, and no script.' );
	}

	/**
	 * Tests the button with an icon.
	 *
	 * @ticket 66534
	 *
	 * @covers ::render_block_core_search
	 */
	public function test_should_render_the_button_with_an_icon() {
		$output  = $this->render(
			array(
				'buttonUseIcon' => true,
				'buttonText'    => 'Go',
			)
		);
		$buttons = $this->get_elements( $output, 'button' );

		$this->assertContains( 'has-icon', $this->get_classes( $buttons[0] ) );
		$this->assertStringContainsString( '<svg class="search-icon"', $output );
		$this->assertStringNotContainsString( '>Go<', $output, 'The text should not be shown.' );
		$this->assertSame( 'Go', $buttons[0]['aria-label'], 'The label of the button should be kept.' );
	}

	/**
	 * Tests that there is no button without one.
	 *
	 * @ticket 66534
	 *
	 * @covers ::render_block_core_search
	 */
	public function test_should_not_render_the_button_when_there_is_no_button() {
		$output = $this->render( array( 'buttonPosition' => 'no-button' ) );

		$this->assertSame( array(), $this->get_elements( $output, 'button' ) );
		$this->assertStringNotContainsString( '<button', $output );
	}

	/**
	 * Tests the classes of the form for the position of the button.
	 *
	 * @ticket 66534
	 *
	 * @covers ::render_block_core_search
	 *
	 * @dataProvider data_positions
	 *
	 * @param string $position Position of the button.
	 * @param string $class_name Expected class of the form.
	 */
	public function test_should_add_the_classes_of_the_position_to_the_form( $position, $class_name ) {
		$form = $this->get_elements( $this->render( array( 'buttonPosition' => $position ) ), 'form' );

		$this->assertContains( $class_name, $this->get_classes( $form[0] ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_positions() {
		return array(
			'inside'  => array( 'button-inside', 'wp-block-search__button-inside' ),
			'outside' => array( 'button-outside', 'wp-block-search__button-outside' ),
			'none'    => array( 'no-button', 'wp-block-search__no-button' ),
			'only'    => array( 'button-only', 'wp-block-search__button-only' ),
		);
	}

	/**
	 * Tests the directives of a button that expands the field.
	 *
	 * @ticket 66534
	 *
	 * @covers ::render_block_core_search
	 */
	public function test_should_add_the_directives_when_the_button_expands_the_field() {
		$output = $this->render( array( 'buttonPosition' => 'button-only' ) );
		$form   = $this->get_elements( $output, 'form' );
		$input  = $this->get_elements( $output, 'input' );
		$button = $this->get_elements( $output, 'button' );

		$this->assertSame( 'core/search', $form[0]['data-wp-interactive'] );
		$this->assertStringContainsString( 'data-wp-context', $output );
		$this->assertSame( '!context.isSearchInputVisible', $form[0]['data-wp-class--wp-block-search__searchfield-hidden'] );
		$this->assertSame( 'actions.handleSearchKeydown', $form[0]['data-wp-on--keydown'] );
		$this->assertSame( 'actions.handleSearchFocusout', $form[0]['data-wp-on--focusout'] );

		$this->assertSame( 'true', $input[0]['aria-hidden'] );
		$this->assertSame( '-1', $input[0]['tabindex'] );
		$this->assertSame( '!context.isSearchInputVisible', $input[0]['data-wp-bind--aria-hidden'] );
		$this->assertSame( 'state.tabindex', $input[0]['data-wp-bind--tabindex'] );

		$this->assertSame( 'button', $button[0]['type'] );
		$this->assertSame( 'Expand search field', $button[0]['aria-label'] );
		$this->assertSame( 'false', $button[0]['aria-expanded'] );
		$this->assertSame( 'actions.openSearchInput', $button[0]['data-wp-on--click'] );
		$this->assertSame( 'context.isSearchInputVisible', $button[0]['data-wp-bind--aria-expanded'] );
		$this->assertSame( 'state.ariaLabel', $button[0]['data-wp-bind--aria-label'] );
	}

	/**
	 * Tests that the script module is only enqueued when the field is expanded by the button.
	 *
	 * @ticket 66534
	 *
	 * @covers ::render_block_core_search
	 */
	public function test_should_only_enqueue_the_script_module_when_the_button_expands_the_field() {
		$this->render( array( 'buttonPosition' => 'button-outside' ) );
		$this->assertNotContains( '@wordpress/block-library/search/view', wp_script_modules()->get_queue(), 'The module should not be enqueued for a button outside.' );

		$this->render( array( 'buttonPosition' => 'button-only' ) );
		$this->assertContains( '@wordpress/block-library/search/view', wp_script_modules()->get_queue(), 'The module should be enqueued for a button only.' );
	}

	/**
	 * Tests that the other positions have no directives.
	 *
	 * @ticket 66534
	 *
	 * @covers ::render_block_core_search
	 *
	 * @dataProvider data_positions_without_directives
	 *
	 * @param string $position Position of the button.
	 */
	public function test_should_not_add_the_directives_for_the_other_positions( $position ) {
		$output = $this->render( array( 'buttonPosition' => $position ) );

		$this->assertStringNotContainsString( 'data-wp-', $output );
		$this->assertStringNotContainsString( 'aria-hidden', $output );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_positions_without_directives() {
		return array(
			'inside'  => array( 'button-inside' ),
			'outside' => array( 'button-outside' ),
			'none'    => array( 'no-button' ),
		);
	}

	/**
	 * Tests the classes of the colors.
	 *
	 * @ticket 66534
	 *
	 * @covers ::render_block_core_search
	 */
	public function test_should_add_the_color_classes_to_the_button_or_to_the_input() {
		$attributes = array( 'textColor' => 'vivid-red' );

		$with_button = $this->render( $attributes );
		$no_button   = $this->render( array_merge( $attributes, array( 'buttonPosition' => 'no-button' ) ) );

		$button = $this->get_elements( $with_button, 'button' );
		$input  = $this->get_elements( $with_button, 'input' );

		$this->assertContains( 'has-vivid-red-color', $this->get_classes( $button[0] ), 'The button should have the color.' );
		$this->assertNotContains( 'has-vivid-red-color', $this->get_classes( $input[0] ), 'The input should not have the color when there is a button.' );

		$input = $this->get_elements( $no_button, 'input' );

		$this->assertContains( 'has-vivid-red-color', $this->get_classes( $input[0] ), 'The input should have the color without a button.' );
	}

	/**
	 * Tests the classes of the typography.
	 *
	 * @ticket 66534
	 *
	 * @covers ::render_block_core_search
	 */
	public function test_should_add_the_typography_classes_to_the_label_the_input_and_the_button() {
		$output = $this->render( array( 'fontSize' => 'large' ) );

		foreach ( array( 'label', 'input', 'button' ) as $tag ) {
			$elements = $this->get_elements( $output, $tag );

			$this->assertContains( 'has-large-font-size', $this->get_classes( $elements[0] ), "The $tag should have the font size." );
		}
	}

	/**
	 * Tests that the label has the typography only when it is shown.
	 *
	 * @ticket 66534
	 *
	 * @covers ::render_block_core_search
	 */
	public function test_should_not_add_the_typography_classes_to_a_label_that_is_hidden() {
		$labels = $this->get_elements(
			$this->render(
				array(
					'fontSize'  => 'large',
					'showLabel' => false,
				)
			),
			'label'
		);

		$this->assertNotContains( 'has-large-font-size', $this->get_classes( $labels[0] ) );
	}

	/**
	 * Tests where the classes of the border color are added.
	 *
	 * @ticket 66534
	 *
	 * @covers ::render_block_core_search
	 */
	public function test_should_add_the_border_color_classes_to_the_wrapper_or_to_the_input_and_the_button() {
		$inside  = $this->render(
			array(
				'buttonPosition' => 'button-inside',
				'borderColor'    => 'cyan',
			)
		);
		$outside = $this->render( array( 'borderColor' => 'cyan' ) );

		$this->assertContains( 'has-cyan-border-color', $this->get_classes( $this->get_elements( $inside, 'div' )[0] ), 'The wrapper should have the class when the button is inside.' );
		$this->assertNotContains( 'has-cyan-border-color', $this->get_classes( $this->get_elements( $inside, 'input' )[0] ), 'The input should not have the class when the button is inside.' );
		$this->assertNotContains( 'has-cyan-border-color', $this->get_classes( $this->get_elements( $inside, 'button' )[0] ), 'The button should not have the class when the button is inside.' );

		$this->assertNotContains( 'has-cyan-border-color', $this->get_classes( $this->get_elements( $outside, 'div' )[0] ), 'The wrapper should not have the class.' );
		$this->assertContains( 'has-cyan-border-color', $this->get_classes( $this->get_elements( $outside, 'input' )[0] ), 'The input should have the class.' );
		$this->assertContains( 'has-cyan-border-color', $this->get_classes( $this->get_elements( $outside, 'button' )[0] ), 'The button should have the class.' );
	}

	/**
	 * Tests the styles of the elements.
	 *
	 * @ticket 66534
	 *
	 * @covers ::render_block_core_search
	 */
	public function test_should_add_the_inline_styles_to_the_elements() {
		$output = $this->render(
			array(
				'width'     => 50,
				'widthUnit' => '%',
				'style'     => array( 'color' => array( 'text' => '#ff0000' ) ),
			)
		);

		$wrapper = $this->get_elements( $output, 'div' );
		$button  = $this->get_elements( $output, 'button' );

		$this->assertSame( 'width: 50%', $wrapper[0]['style'] );
		$this->assertSame( 'color: #ff0000', $button[0]['style'] );
	}

	/**
	 * Tests the element of a search.
	 *
	 * @ticket 66534
	 *
	 * @covers ::render_block_core_search
	 *
	 * @dataProvider data_tag_names
	 *
	 * @param array $attributes Attributes of the block.
	 * @param bool  $html5      Whether the theme supports the search element.
	 * @param bool  $expected   Whether the search element is expected.
	 */
	public function test_should_use_the_search_element_when_it_is_requested_or_supported( $attributes, $html5, $expected ) {
		if ( $html5 ) {
			add_theme_support( 'html5', array( 'search-element' ) );
		}

		$output = $this->render( $attributes );

		if ( $expected ) {
			$this->assertStringStartsWith( '<search ', $output );
			$this->assertStringEndsWith( '</form></search>', $output );
			$forms = $this->get_elements( $output, 'form' );

			$this->assertCount( 1, $this->get_elements( $output, 'search' ), 'There should be a search element.' );
			$this->assertCount( 1, $forms, 'The form should be inside.' );
			$this->assertArrayNotHasKey( 'role', $forms[0], 'The form should not have a role inside the search element.' );
		} else {
			$this->assertStringStartsWith( '<form role="search"', $output );
			$this->assertStringNotContainsString( '<search', $output );
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_tag_names() {
		return array(
			'the default'                      => array( array(), false, false ),
			'a theme that supports it'         => array( array(), true, true ),
			'the tag of the block'             => array( array( 'tagName' => 'search' ), false, true ),
			'the tag of the block and support' => array( array( 'tagName' => 'search' ), true, true ),
			'a form that is requested'         => array( array( 'tagName' => 'form' ), true, false ),
			'a form without support'           => array( array( 'tagName' => 'form' ), false, false ),
		);
	}

	/**
	 * Tests that the block is registered with the render callback.
	 *
	 * @ticket 66534
	 *
	 * @covers ::register_block_core_search
	 */
	public function test_should_register_the_block_with_the_render_callback() {
		$registry       = WP_Block_Type_Registry::get_instance();
		$was_registered = $registry->is_registered( 'core/search' );

		if ( $was_registered ) {
			unregister_block_type( 'core/search' );
		}

		register_block_core_search();
		$block_type = $registry->get_registered( 'core/search' );

		if ( ! $was_registered ) {
			unregister_block_type( 'core/search' );
		}

		$this->assertInstanceOf( WP_Block_Type::class, $block_type );
		$this->assertSame( 'render_block_core_search', $block_type->render_callback );
	}
}
