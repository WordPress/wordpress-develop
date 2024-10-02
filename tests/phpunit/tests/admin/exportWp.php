<?php

/**
 * @group admin
 * @group export
 *
 * @covers ::export_wp
 *
 * Tests run in a separate process to prevent "headers already sent" error.
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class Tests_Admin_ExportWp extends WP_UnitTestCase {
	/**
	 * Post IDs for posts, pages, and attachments.
	 *
	 * The structure is shown for understanding how to
	 * lookup / reference the information within it.
	 *
	 * IDs will be created in this order.
	 *
	 * @var array {
	 *      @type array $data {
	 *          Data for each post, page, or attachment.
	 *
	 *          @type int $post_id        The ID for the post, page, or attachment.
	 *          @type int $post_author    The author's ID.
	 *          @type int $xml_item_index The XML item index for this post, page, or attachment.
	 *                                    This number is based upon all of the posts, pages, and attachments
	 *                                    in the self::$post_ids static property.
	 *      }
	 * }
	 */
	private static $post_ids = array(
		'post 1'                => array(),
		'attachment for post 1' => array(),
		'post 2'                => array(),
		'attachment for post 2' => array(),
		'page 1'                => array(),
		'attachment for page 1' => array(),
		'page 2'                => array(),
		'attachment for page 2' => array(),
	);

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		require_once ABSPATH . 'wp-admin/includes/export.php';
		$file = DIR_TESTDATA . '/images/test-image.jpg';

		$dataset = array(
			'post 1' => array(
				'post_title' => 'Test Post 1',
				'post_type'  => 'post',
			),
			'post 2' => array(
				'post_title' => 'Test Post 2',
				'post_type'  => 'post',
			),
			'page 1' => array(
				'post_title' => 'Test Page 1',
				'post_type'  => 'page',
			),
			'page 2' => array(
				'post_title' => 'Test Page 2',
				'post_type'  => 'page',
			),
		);

		$xml_item_index = -1;

		foreach ( $dataset as $post_key => $post_data ) {
			$attachment_key           = "attachment for $post_key";
			$post_data['post_author'] = $factory->user->create( array( 'role' => 'editor' ) );

			$post_id       = $factory->post->create( $post_data );
			$attachment_id = $factory->attachment->create_upload_object( $file, $post_id );
			set_post_thumbnail( $post_id, $attachment_id );

			self::$post_ids[ $post_key ]       = array(
				'post_id'        => $post_id,
				'post_author'    => $post_data['post_author'],
				'xml_item_index' => ++$xml_item_index,
			);
			self::$post_ids[ $attachment_key ] = array(
				'post_id'        => $attachment_id,
				'post_author'    => $post_data['post_author'],
				'xml_item_index' => ++$xml_item_index,
			);
		}
	}

	/**
	 * @dataProvider data_should_include_attachments
	 *
	 * @ticket 17379
	 *
	 * @param array $args            Arguments to pass to export_wp().
	 * @param array $expected {
	 *     The expected data.
	 *
	 *     @type array $items {
	 *         The expected XML items count assertion arguments.
	 *
	 *         @type int    $number_of_items The expected number of XML items.
	 *         @type string $message         The assertion failure message.
	 *     }
	 *     @type array $ids A list of self::$post_ids keys.
	 */
	public function test_should_include_attachments( array $args, array $expected ) {
		$this->populate_args_post_authors( $args, $expected['ids'] );

		$xml = $this->get_the_export( $args );

		$expected_number_of_items = $expected['items']['number_of_items'];
		$this->assertCount( $expected_number_of_items, $xml->channel->item, $expected['items']['message'] );

		// Test each XML item's post ID to valid the post, page, and attachment (when appropriate) were exported.
		foreach ( $expected['ids'] as $post_ids_key ) {
			$xml_item = $this->get_xml_item( $xml, $post_ids_key, $expected_number_of_items );

			$this->assertSame(
				$this->get_expected_id( $post_ids_key ),
				(int) $xml_item->post_id,
				"In the XML, the {$post_ids_key}'s ID should match the expected content"
			);
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_should_include_attachments() {
		return array(
			'for all content'           => array(
				'args'     => array(
					'content' => 'all',
				),
				'expected' => array(
					'items' => array(
						'number_of_items' => 8,
						'message'         => 'The number of items should be 8 = 2 pages, 2 posts and 4 attachments',
					),
					'ids'   => array(
						'post 1',
						'post 2',
						'page 1',
						'page 2',
						'attachment for page 1',
						'attachment for post 2',
						'attachment for page 1',
						'attachment for page 2',
					),
				),
			),
			'for all posts'             => array(
				'args'     => array(
					'content' => 'post',
				),
				'expected' => array(
					'items' => array(
						'number_of_items' => 4,
						'message'         => 'The number of items should be 4 = 2 posts and 2 attachments',
					),
					'ids'   => array(
						'post 1',
						'post 2',
						'attachment for post 1',
						'attachment for post 2',
					),
				),
			),
			'for all pages'             => array(
				'args'     => array(
					'content' => 'page',
				),
				'expected' => array(
					'items' => array(
						'number_of_items' => 4,
						'message'         => 'The number of items should be 4 = 2 pages and 2 attachments',
					),
					'ids'   => array(
						'page 1',
						'attachment for page 1',
						'page 2',
						'attachment for page 2',
					),
				),
			),
			'for specific author posts' => array(
				'args'     => array(
					'content' => 'post',
					'author'  => '', // The test will populate the author's ID.
				),
				'expected' => array(
					'items' => array(
						'number_of_items' => 2,
						'message'         => 'The number of items should be 2 = 1 post and 1 attachment',
					),
					'ids'   => array(
						'post 1',
						'attachment for post 1',
					),
				),
			),
			'for specific author pages' => array(
				'args'     => array(
					'content' => 'page',
					'author'  => '', // The test will populate the author's ID.
				),
				'expected' => array(
					'items' => array(
						'number_of_items' => 2,
						'message'         => 'The number of items should be 2 = 1 page and 1 attachment',
					),
					'ids'   => array(
						'page 2',
						'attachment for page 2',
					),
				),
			),
		);
	}

	/**
	 * Gets the export results.
	 *
	 * @since 6.5.0
	 *
	 * @param array $args Arguments to pass to export_wp().
	 * @return SimpleXMLElement|false Returns the XML object on success, otherwise false is returned.
	 */
	private function get_the_export( $args ) {
		ob_start();
		export_wp( $args );
		$results = ob_get_clean();

		return simplexml_load_string( $results );
	}

	/**
	 * Gets the expected ID.
	 *
	 * @since 6.5.0
	 *
	 * @param string $post_ids_key The key to lookup in the $post_ids static property.
	 * @return int Expected ID.
	 */
	private function get_expected_id( $post_ids_key ) {
		$post_info = self::$post_ids[ $post_ids_key ];

		return $post_info['post_id'];
	}

	/**
	 * Gets the XML item for the given post or attachment in the self::$post_ids.
	 *
	 * @since 6.5.0
	 *
	 * @param SimpleXMLElement $xml             XML object.
	 * @param string           $post_ids_key    The key to lookup in the $post_ids static property.
	 * @param int              $number_of_items The number of expected XML items.
	 * @return SimpleXMLElement The XML item.
	 */
	private function get_xml_item( $xml, $post_ids_key, $number_of_items ) {
		$post_info = self::$post_ids[ $post_ids_key ];

		if ( $post_info['xml_item_index'] < $number_of_items ) {
			$xml_item_index = $post_info['xml_item_index'];
		} elseif ( 2 === $number_of_items ) {
			$xml_item_index = 0 === $post_info['xml_item_index'] % 2 ? 0 : 1;
		} else {
			$xml_item_index = $post_info['xml_item_index'] - $number_of_items;
		}

		return $xml->channel->item[ $xml_item_index ]->children( 'wp', true );
	}

	/**
	 * Populates the post author in the given args.
	 *
	 * @since 6.5.0
	 *
	 * @param array $args Passed by reference. export_wp() arguments to process.
	 */
	private function populate_args_post_authors( array &$args, $expected_ids ) {
		if ( ! isset( $args['author'] ) ) {
			return;
		}
		$post_ids_key   = $expected_ids[0];
		$args['author'] = self::$post_ids[ $post_ids_key ]['post_author'];
	}
}
