<?php
/**
 * Unit tests covering the functionality of the public functions of the
 * Interactivity API.
 *
 * @package WordPress
 * @subpackage Interactivity API
 *
 * @since 6.5.0
 *
 * @group interactivity-api
 */
class Tests_Interactivity_API_Functions extends WP_UnitTestCase {
	/**
	 * Set up.
	 */
	public function set_up() {
		parent::set_up();

		$interactive_block = array(
			'render_callback' => function ( $attributes, $content ) {
				return '
						<div
							data-wp-interactive=\'{ "namespace": "myPlugin" }\'
							data-wp-context=\'{ "block": ' . $attributes['block'] . ' }\'
						>
							<input
								class="interactive/block-' . $attributes['block'] . '"
								data-wp-bind--value="context.block"
							>' .
						$content .
					'</div>';
			},
			'supports'        => array(
				'interactivity' => true,
			),
		);

		register_block_type( 'test/interactive-block', $interactive_block );
		register_block_type( 'test/interactive-block-2', $interactive_block );

		register_block_type(
			'test/non-interactive-block',
			array(
				'render_callback' => function ( $attributes, $content ) {
					$directive = isset( $attributes['hasDirective'] ) ? ' data-wp-bind--value="context.block"' : '';
					return '
						<div>
							<input class="non-interactive/block-' . $attributes['block'] . '"' . $directive . '>' .
							$content .
						'</div>';
				},
			)
		);
	}

	/**
	 * Tear down.
	 */
	public function tear_down() {
		unregister_block_type( 'test/interactive-block' );
		unregister_block_type( 'test/interactive-block-2' );
		unregister_block_type( 'test/non-interactive-block' );
		parent::tear_down();
	}

	/**
	 * Tests processing of a single interactive block.
	 *
	 * @ticket 60356
	 *
	 * @covers ::wp_interactivity_process_directives_of_interactive_blocks
	 */
	public function test_processs_directives_of_single_interactive_block() {
		$post_content    = '<!-- wp:test/interactive-block { "block": 1 } /-->';
		$rendered_blocks = do_blocks( $post_content );
		$p               = new WP_HTML_Tag_Processor( $rendered_blocks );
		$p->next_tag( array( 'class_name' => 'interactive/block-1' ) );
		$this->assertEquals( '1', $p->get_attribute( 'value' ) );
	}

	/**
	 * Tests processing of multiple interactive blocks in parallel along with a
	 * non-interactive block.
	 *
	 * @ticket 60356
	 *
	 * @covers ::wp_interactivity_process_directives_of_interactive_blocks
	 */
	public function test_processs_directives_of_multiple_interactive_blocks_in_paralell() {
		$post_content    = '
			<!-- wp:test/interactive-block { "block": 1 } /-->
			<!-- wp:test/interactive-block-2 { "block": 2 } /-->
			<!-- wp:test/non-interactive-block { "block": 3, "hasDirective": true } /-->
			<!-- wp:test/interactive-block { "block": 4 } /-->
		';
		$rendered_blocks = do_blocks( $post_content );
		$p               = new WP_HTML_Tag_Processor( $rendered_blocks );
		$p->next_tag( array( 'class_name' => 'interactive/block-1' ) );
		$this->assertEquals( '1', $p->get_attribute( 'value' ) );
		$p->next_tag( array( 'class_name' => 'interactive/block-2' ) );
		$this->assertEquals( '2', $p->get_attribute( 'value' ) );
		$p->next_tag( array( 'class_name' => 'non-interactive/block-3' ) );
		$this->assertNull( $p->get_attribute( 'value' ) );
		$p->next_tag( array( 'class_name' => 'interactive/block-4' ) );
		$this->assertEquals( '4', $p->get_attribute( 'value' ) );
	}

	/**
	 * Tests processing of an interactive block inside a non-interactive block.
	 *
	 * @ticket 60356
	 *
	 * @covers ::wp_interactivity_process_directives_of_interactive_blocks
	 */
	public function test_processs_directives_of_interactive_block_inside_non_interactive_block() {
		$post_content    = '
			<!-- wp:test/non-interactive-block { "block": 1 } -->
				<!-- wp:test/interactive-block { "block": 2 } /-->
			<!-- /wp:test/non-interactive-block -->
		';
		$rendered_blocks = do_blocks( $post_content );
		$p               = new WP_HTML_Tag_Processor( $rendered_blocks );
		$p->next_tag( array( 'class_name' => 'interactive/block-2' ) );
		$this->assertEquals( '2', $p->get_attribute( 'value' ) );
	}

	/**
	 * Tests processing of multiple interactive blocks nested inside a
	 * non-interactive block.
	 *
	 * @ticket 60356
	 *
	 * @covers ::wp_interactivity_process_directives_of_interactive_blocks
	 */
	public function test_processs_directives_of_multple_interactive_blocks_inside_non_interactive_block() {
		$post_content    = '
			<!-- wp:test/non-interactive-block { "block": 1 } -->
				<!-- wp:test/interactive-block { "block": 2 } /-->
				<!-- wp:test/interactive-block { "block": 3 } /-->
			<!-- /wp:test/non-interactive-block -->
		';
		$rendered_blocks = do_blocks( $post_content );
		$p               = new WP_HTML_Tag_Processor( $rendered_blocks );
		$p->next_tag( array( 'class_name' => 'interactive/block-2' ) );
		$this->assertEquals( '2', $p->get_attribute( 'value' ) );
		$p->next_tag( array( 'class_name' => 'interactive/block-3' ) );
		$this->assertEquals( '3', $p->get_attribute( 'value' ) );
	}

	/**
	 * Tests processing of a single interactive block directive nested inside
	 * multiple non-interactive blocks.
	 *
	 * @ticket 60356
	 *
	 * @covers ::wp_interactivity_process_directives_of_interactive_blocks
	 */
	public function test_processs_directives_of_interactive_block_inside_multple_non_interactive_block() {
		$post_content    = '
			<!-- wp:test/non-interactive-block { "block": 1 } -->
				<!-- wp:test/interactive-block { "block": 2 } /-->
			<!-- /wp:test/non-interactive-block -->
			<!-- wp:test/non-interactive-block { "block": 3 } -->
				<!-- wp:test/interactive-block-2 { "block": 4 } /-->
			<!-- /wp:test/non-interactive-block -->
		';
		$rendered_blocks = do_blocks( $post_content );
		$p               = new WP_HTML_Tag_Processor( $rendered_blocks );
		$p->next_tag( array( 'class_name' => 'interactive/block-2' ) );
		$this->assertEquals( '2', $p->get_attribute( 'value' ) );
		$p->next_tag( array( 'class_name' => 'interactive/block-4' ) );
		$this->assertEquals( '4', $p->get_attribute( 'value' ) );
	}

	/**
	 * Tests processing of directives for an interactive block containing a
	 * non-interactive block without directives.
	 *
	 * @ticket 60356
	 *
	 * @covers ::wp_interactivity_process_directives_of_interactive_blocks
	 */
	public function test_processs_directives_of_interactive_block_containing_non_interactive_block_without_directives() {
		$post_content    = '
			<!-- wp:test/interactive-block { "block": 1 } -->
				<!-- wp:test/non-interactive-block { "block": 2 } /-->
			<!-- /wp:test/interactive-block -->
		';
		$rendered_blocks = do_blocks( $post_content );
		$p               = new WP_HTML_Tag_Processor( $rendered_blocks );
		$p->next_tag( array( 'class_name' => 'interactive/block-1' ) );
		$this->assertEquals( '1', $p->get_attribute( 'value' ) );
		$p->next_tag( array( 'class_name' => 'non-interactive/block-2' ) );
		$this->assertNull( $p->get_attribute( 'value' ) );
	}

	/**
	 * Tests processing of directives for an interactive block containing a
	 * non-interactive block with directives.
	 *
	 * @ticket 60356
	 *
	 * @covers ::wp_interactivity_process_directives_of_interactive_blocks
	 */
	public function test_processs_directives_of_interactive_block_containing_non_interactive_block_with_directives() {
		$post_content    = '
			<!-- wp:test/interactive-block { "block": 1 } -->
				<!-- wp:test/non-interactive-block { "block": 2, "hasDirective": true } /-->
			<!-- /wp:test/interactive-block -->
		';
		$rendered_blocks = do_blocks( $post_content );
		$p               = new WP_HTML_Tag_Processor( $rendered_blocks );
		$p->next_tag( array( 'class_name' => 'interactive/block-1' ) );
		$this->assertEquals( '1', $p->get_attribute( 'value' ) );
		$p->next_tag( array( 'class_name' => 'non-interactive/block-2' ) );
		$this->assertEquals( '1', $p->get_attribute( 'value' ) );
	}

	/**
	 * Tests processing of directives for an interactive block containing nested
	 * interactive and non-interactive blocks, checking proper propagation of
	 * context.
	 *
	 * @ticket 60356
	 *
	 * @covers ::wp_interactivity_process_directives_of_interactive_blocks
	 */
	public function test_processs_directives_of_interactive_block_containing_nested_interactive_and_non_interactive_blocks() {
		$post_content    = '
			<!-- wp:test/interactive-block { "block": 1 } -->
				<!-- wp:test/interactive-block-2 { "block": 2 } -->
					<!-- wp:test/non-interactive-block { "block": 3, "hasDirective": true } /-->
				<!-- /wp:test/interactive-block-2 -->
				<!-- wp:test/non-interactive-block { "block": 4, "hasDirective": true } /-->
			<!-- /wp:test/interactive-block -->
		';
		$rendered_blocks = do_blocks( $post_content );
		$p               = new WP_HTML_Tag_Processor( $rendered_blocks );
		$p->next_tag( array( 'class_name' => 'interactive/block-1' ) );
		$this->assertEquals( '1', $p->get_attribute( 'value' ) );
		$p->next_tag( array( 'class_name' => 'interactive/block-2' ) );
		$this->assertEquals( '2', $p->get_attribute( 'value' ) );
		$p->next_tag( array( 'class_name' => 'non-interactive/block-3' ) );
		$this->assertEquals( '2', $p->get_attribute( 'value' ) );
		$p->next_tag( array( 'class_name' => 'non-interactive/block-4' ) );
		$this->assertEquals( '1', $p->get_attribute( 'value' ) );
	}

	/**
	 * Counter for the number of times the test directive processor is called.
	 *
	 * @var int
	 */
	private $data_wp_test_processor_count = 0;

	/**
	 * Test directive processor callback.
	 *
	 * Increments the $data_wp_test_processor_count every time a tag that is not a
	 * tag closer is processed.
	 *
	 * @param WP_HTML_Tag_Processor $p Instance of the processor handling the current HTML tag.
	 */
	public function data_wp_test_processor( $p ) {
		if ( ! $p->is_tag_closer() ) {
			$this->data_wp_test_processor_count = $this->data_wp_test_processor_count + 1;
		}
	}

	/**
	 * Tests that directives are only processed once for the root interactive
	 * blocks.
	 *
	 * This ensures that nested blocks do not trigger additional processing of the
	 * same directives, leading to incorrect behavior or performance issues.
	 *
	 * @ticket 60356
	 *
	 * @covers ::wp_interactivity_process_directives_of_interactive_blocks
	 */
	public function test_process_directives_only_process_the_root_interactive_blocks() {
		$class                = new ReflectionClass( 'WP_Interactivity_API' );
		$directive_processors = $class->getProperty( 'directive_processors' );
		$directive_processors->setAccessible( true );
		$old_directive_processors = $directive_processors->getValue();
		$directive_processors->setValue( null, array( 'data-wp-test' => array( $this, 'data_wp_test_processor' ) ) );
		$html                               = '<div data-wp-test></div>';
		$this->data_wp_test_processor_count = 0;
		wp_interactivity_process_directives( $html );
		$this->assertEquals( 1, $this->data_wp_test_processor_count );

		register_block_type(
			'test/custom-directive-block',
			array(
				'render_callback' => function ( $attributes, $content ) {
					return '<div class="test" data-wp-test>' . $content . '</div>';
				},
				'supports'        => array(
					'interactivity' => true,
				),
			)
		);
		$post_content                       = '
			<!-- wp:test/custom-directive-block -->
				<!-- wp:test/custom-directive-block /-->
			<!-- /wp:test/custom-directive-block -->
		';
		$this->data_wp_test_processor_count = 0;
		do_blocks( $post_content );
		$this->assertEquals( 2, $this->data_wp_test_processor_count );
		unregister_block_type( 'test/custom-directive-block' );
		$directive_processors->setValue( null, $old_directive_processors );
	}

	/**
	 * Tests that data_wp_context function correctly converts different array
	 * structures to a JSON string.
	 *
	 * @ticket 60356
	 *
	 * @covers ::data_wp_context
	 */
	public function test_data_wp_context_with_different_arrays() {
		$this->assertEquals( 'data-wp-context=\'{}\'', data_wp_context( array() ) );
		$this->assertEquals(
			'data-wp-context=\'{"a":1,"b":"2","c":true}\'',
			data_wp_context(
				array(
					'a' => 1,
					'b' => '2',
					'c' => true,
				)
			)
		);
		$this->assertEquals(
			'data-wp-context=\'{"a":[1,2]}\'',
			data_wp_context( array( 'a' => array( 1, 2 ) ) )
		);
		$this->assertEquals(
			'data-wp-context=\'[1,2]\'',
			data_wp_context( array( 1, 2 ) )
		);
	}

	/**
	 * Tests that data_wp_context function correctly converts different array
	 * structures to a JSON string and adds a namespace.
	 *
	 * @ticket 60356
	 *
	 * @covers ::data_wp_context
	 */
	public function test_data_wp_context_with_different_arrays_and_a_namespace() {
		$this->assertEquals( 'data-wp-context=\'myPlugin::{}\'', data_wp_context( array(), 'myPlugin' ) );
		$this->assertEquals(
			'data-wp-context=\'myPlugin::{"a":1,"b":"2","c":true}\'',
			data_wp_context(
				array(
					'a' => 1,
					'b' => '2',
					'c' => true,
				),
				'myPlugin'
			)
		);
		$this->assertEquals(
			'data-wp-context=\'myPlugin::{"a":[1,2]}\'',
			data_wp_context( array( 'a' => array( 1, 2 ) ), 'myPlugin' )
		);
		$this->assertEquals(
			'data-wp-context=\'myPlugin::[1,2]\'',
			data_wp_context( array( 1, 2 ), 'myPlugin' )
		);
	}

	/**
	 * Tests that data_wp_context function correctly applies the JSON encoding
	 * flags. This ensures that characters like `<`, `>`, `'`, or `&` are
	 * properly escaped in the JSON-encoded string to prevent potential XSS
	 * attacks.
	 *
	 * @ticket 60356
	 *
	 * @covers ::data_wp_context
	 */
	public function test_data_wp_context_with_json_flags() {
		$this->assertEquals( 'data-wp-context=\'{"tag":"\u003Cfoo\u003E"}\'', data_wp_context( array( 'tag' => '<foo>' ) ) );
		$this->assertEquals( 'data-wp-context=\'{"apos":"\u0027bar\u0027"}\'', data_wp_context( array( 'apos' => "'bar'" ) ) );
		$this->assertEquals( 'data-wp-context=\'{"quot":"\u0022baz\u0022"}\'', data_wp_context( array( 'quot' => '"baz"' ) ) );
		$this->assertEquals( 'data-wp-context=\'{"amp":"T\u0026T"}\'', data_wp_context( array( 'amp' => 'T&T' ) ) );
	}
}
