<?php

/**
 * @group xmlrpc
 *
 * @covers wp_xmlrpc_server::add_enclosure_if_new
 */
class Tests_XMLRPC_add_enclosure_if_new extends WP_XMLRPC_UnitTestCase {

	/**
	 * @ticket 23219
	 */
	function test_add_enclosure_if_new() {
		// Sample enclosure data.
		$enclosure = array(
			'url'    => 'http://example.com/sound.mp3',
			'length' => 12345,
			'type'   => 'audio/mpeg',
		);

		// Second sample enclosure data array.
		$new_enclosure = array(
			'url'    => 'http://example.com/sound2.mp3',
			'length' => 12345,
			'type'   => 'audio/mpeg',
		);

		// Create a test user.
		$editor_id = $this->make_user_by_role( 'editor' );

		// Add a dummy post.
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Post Enclosure Test',
				'post_content' => 'Fake content',
				'post_author'  => $editor_id,
				'post_status'  => 'publish',
			)
		);

		// Add the enclosure as it is added in "do_enclose()".
		$enclosure_string = "{$enclosure['url']}\n{$enclosure['length']}\n{$enclosure['type']}\n";
		add_post_meta( $post_id, 'enclosure', $enclosure_string );

		// Verify that the correct data is there.
		$this->assertSame( $enclosure_string, get_post_meta( $post_id, 'enclosure', true ) );

		// Attempt to add the enclosure a second time.
		$this->myxmlrpcserver->add_enclosure_if_new( $post_id, $enclosure );

		// Verify that there is only a single value in the array and that a duplicate is not present.
		$this->assertSame( 1, count( get_post_meta( $post_id, 'enclosure' ) ) );

		// For good measure, check that the expected value is in the array.
		$this->assertTrue( in_array( $enclosure_string, get_post_meta( $post_id, 'enclosure' ), true ) );

		// Attempt to add a brand new enclosure via XML-RPC.
		$this->myxmlrpcserver->add_enclosure_if_new( $post_id, $new_enclosure );

		// Having added the new enclosure, 2 values are expected in the array.
		$this->assertSame( 2, count( get_post_meta( $post_id, 'enclosure' ) ) );

		// Check that the new enclosure is in the enclosure meta.
		$new_enclosure_string = "{$new_enclosure['url']}\n{$new_enclosure['length']}\n{$new_enclosure['type']}\n";
		$this->assertTrue( in_array( $new_enclosure_string, get_post_meta( $post_id, 'enclosure' ), true ) );

		// Check that the old enclosure is in the enclosure meta.
		$this->assertTrue( in_array( $enclosure_string, get_post_meta( $post_id, 'enclosure' ), true ) );
	}
}
