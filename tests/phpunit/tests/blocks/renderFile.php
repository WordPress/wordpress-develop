<?php
/**
 * Tests for the server-side rendering of the File block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 *
 * @covers ::render_block_core_file
 */
class Tests_Blocks_RenderFile extends WP_UnitTestCase {

	/**
	 * ID of the script module of the block.
	 */
	const MODULE_ID = '@wordpress/block-library/file/view';

	/**
	 * Whether the script module of the block was in the queue before the test.
	 *
	 * @var bool
	 */
	private $module_was_queued;

	public function set_up() {
		parent::set_up();

		$this->module_was_queued = in_array( self::MODULE_ID, wp_script_modules()->get_queue(), true );
	}

	public function tear_down() {
		if ( ! $this->module_was_queued ) {
			wp_dequeue_script_module( self::MODULE_ID );
		}

		parent::tear_down();
	}

	/**
	 * Returns the markup of the block.
	 *
	 * @param string|null $label The aria-label of the object, or null for none.
	 * @return string The markup.
	 */
	private function get_content( $label = 'Embed of report.pdf.' ) {
		$aria_label = null === $label ? '' : ' aria-label="' . $label . '"';

		return '<div class="wp-block-file"><object class="wp-block-file__embed" data="https://example.org/report.pdf" type="application/pdf" style="width:100%;height:600px"' . $aria_label . '></object><a id="wp-block-file--media-1" href="https://example.org/report.pdf">report</a><a href="https://example.org/report.pdf" class="wp-block-file__button wp-element-button" download aria-describedby="wp-block-file--media-1">Download</a></div>';
	}

	/**
	 * Returns the attributes of the first element with the tag.
	 *
	 * @param string $markup The markup.
	 * @param string $tag    The tag name.
	 * @return array|null The attributes, or null when there is no element.
	 */
	private function get_element( $markup, $tag ) {
		$processor = new WP_HTML_Tag_Processor( $markup );

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
	 * Tests that the content is returned as it is unless the preview is displayed.
	 *
	 * @ticket 66597
	 *
	 * @dataProvider data_without_preview
	 *
	 * @param array $attributes The attributes of the block.
	 */
	public function test_should_return_the_content_unless_the_preview_is_displayed( $attributes ) {
		$content = $this->get_content();

		$this->assertSame( $content, render_block_core_file( $attributes, $content ) );

		if ( ! $this->module_was_queued ) {
			$this->assertNotContains( self::MODULE_ID, wp_script_modules()->get_queue(), 'The script module should not be enqueued.' );
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_without_preview() {
		return array(
			'no attributes' => array( array() ),
			'false'         => array( array( 'displayPreview' => false ) ),
			'null'          => array( array( 'displayPreview' => null ) ),
			'zero'          => array( array( 'displayPreview' => 0 ) ),
			'empty string'  => array( array( 'displayPreview' => '' ) ),
		);
	}

	/**
	 * Tests the interactivity directive of the block.
	 *
	 * @ticket 66597
	 */
	public function test_should_make_the_block_interactive() {
		$output = render_block_core_file( array( 'displayPreview' => true ), $this->get_content() );

		$this->assertSame( 'core/file', $this->get_element( $output, 'div' )['data-wp-interactive'] );
		$this->assertArrayNotHasKey( 'data-wp-interactive', $this->get_element( $output, 'object' ) );
		$this->assertArrayNotHasKey( 'data-wp-interactive', $this->get_element( $output, 'a' ) );
	}

	/**
	 * Tests the object of the preview.
	 *
	 * @ticket 66597
	 */
	public function test_should_bind_and_hide_the_object() {
		$object = $this->get_element( render_block_core_file( array( 'displayPreview' => true ), $this->get_content() ), 'object' );

		$this->assertSame( '!state.hasPdfPreview', $object['data-wp-bind--hidden'] );
		$this->assertArrayHasKey( 'hidden', $object );
		$this->assertSame( 'application/pdf', $object['type'], 'The other attributes of the object should stay.' );
		$this->assertSame( 'https://example.org/report.pdf', $object['data'], 'The other attributes of the object should stay.' );
	}

	/**
	 * Tests that the links are not changed.
	 *
	 * @ticket 66597
	 */
	public function test_should_not_change_the_links() {
		$output = render_block_core_file( array( 'displayPreview' => true ), $this->get_content() );
		$link   = $this->get_element( $output, 'a' );

		$this->assertSame( 'wp-block-file--media-1', $link['id'] );
		$this->assertStringContainsString( '<a id="wp-block-file--media-1" href="https://example.org/report.pdf">report</a>', $output );
		$this->assertStringContainsString( 'class="wp-block-file__button wp-element-button" download aria-describedby="wp-block-file--media-1">Download</a>', $output );
	}

	/**
	 * Tests the label of the object.
	 *
	 * @ticket 66597
	 *
	 * @dataProvider data_labels
	 *
	 * @param string|null $label    The aria-label of the object in the content.
	 * @param string      $expected The expected aria-label.
	 */
	public function test_should_label_the_object( $label, $expected ) {
		$object = $this->get_element( render_block_core_file( array( 'displayPreview' => true ), $this->get_content( $label ) ), 'object' );

		$this->assertSame( $expected, $object['aria-label'] );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_labels() {
		return array(
			'file name'                   => array( 'report.pdf', 'Embed of report.pdf.' ),
			'file name with a space'      => array( 'Annual report.pdf', 'Embed of Annual report.pdf.' ),
			'file name with an ampersand' => array( 'Q&amp;A.pdf', 'Embed of Q&A.pdf.' ),
			'default label'               => array( 'PDF embed', 'PDF embed' ),
			'empty label'                 => array( '', 'PDF embed' ),
			'no label'                    => array( null, 'PDF embed' ),
		);
	}

	/**
	 * Tests the label when the aria-label attribute of the object has no value.
	 *
	 * @ticket 66597
	 */
	public function test_should_use_the_default_label_when_the_aria_label_has_no_value() {
		$content = '<div><object data="https://example.org/report.pdf" aria-label></object></div>';
		$object  = $this->get_element( render_block_core_file( array( 'displayPreview' => true ), $content ), 'object' );

		$this->assertSame( 'PDF embed', $object['aria-label'] );
	}

	/**
	 * Tests that a file name is escaped in the label.
	 *
	 * @ticket 66597
	 */
	public function test_should_escape_the_file_name_in_the_label() {
		$output = render_block_core_file( array( 'displayPreview' => true ), $this->get_content( '&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;' ) );

		$this->assertStringNotContainsString( '<script', $output );
		$this->assertSame( 'Embed of "><script>alert(1)</script>.', $this->get_element( $output, 'object' )['aria-label'] );
	}

	/**
	 * Tests the content without an object.
	 *
	 * @ticket 66597
	 */
	public function test_should_return_the_content_without_an_object_as_it_is() {
		$content = '<div class="wp-block-file"><a id="wp-block-file--media-1" href="https://example.org/report.pdf">report</a></div>';

		$this->assertSame( $content, render_block_core_file( array( 'displayPreview' => true ), $content ) );
	}

	/**
	 * Tests the script module.
	 *
	 * @ticket 66597
	 *
	 * @dataProvider data_contents
	 *
	 * @param string $content The content of the block.
	 */
	public function test_should_enqueue_the_script_module_for_the_preview( $content ) {
		render_block_core_file( array( 'displayPreview' => true ), $content );

		$this->assertContains( self::MODULE_ID, wp_script_modules()->get_queue() );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_contents() {
		return array(
			'with an object'    => array( '<div><object></object></div>' ),
			'without an object' => array( '<div><a href="#">File</a></div>' ),
			'empty content'     => array( '' ),
		);
	}
}
