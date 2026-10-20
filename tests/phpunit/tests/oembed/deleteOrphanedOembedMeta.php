<?php
/**
 * @group oembed
 */
class Tests_oEmbed_DeleteOrphanedOembedMeta extends WP_UnitTestCase {
	public function test_oembed_meta_deleted_when_embed_removed() {
		$url     = 'https://www.youtube.com/watch?v=Ok6JKHMAkH8';
		$post_id = self::factory()->post->create(
			array(
				'post_content' => $url . "\nSome text.",
			)
		);

		// Trigger oEmbed caching as core does
		global $wp_embed;
		$wp_embed->cache_oembed( $post_id );

		$key_suffix    = md5( $url . serialize( wp_embed_defaults( $url ) ) );
		$cachekey      = '_oembed_' . $key_suffix;
		$cachekey_time = '_oembed_time_' . $key_suffix;

		// Ensure the oEmbed meta is present after caching
		$this->assertNotEmpty( get_post_meta( $post_id, $cachekey, true ), 'oEmbed cache meta should exist after caching.' );
		$this->assertNotEmpty( get_post_meta( $post_id, $cachekey_time, true ), 'oEmbed time meta should exist after caching.' );

		// Remove the embed from content and update the post.
		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => 'Some text.',
			)
		);

		// Re-trigger oEmbed cache
		$wp_embed->cache_oembed( $post_id );

		// The oEmbed meta should be deleted after update.
		$this->assertEmpty( get_post_meta( $post_id, $cachekey, true ), 'oEmbed cache meta should be deleted when embed is removed.' );
		$this->assertEmpty( get_post_meta( $post_id, $cachekey_time, true ), 'oEmbed time meta should be deleted when embed is removed.' );
	}
}
