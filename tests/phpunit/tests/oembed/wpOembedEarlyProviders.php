<?php

/**
 * Tests for the WP_oEmbed::_add_provider_early() and WP_oEmbed::_remove_provider_early() methods.
 *
 * @group oembed
 *
 * @covers WP_oEmbed::_add_provider_early
 * @covers WP_oEmbed::_remove_provider_early
 */
class Tests_Oembed_WpOembedEarlyProviders extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();

		require_once ABSPATH . WPINC . '/class-wp-oembed.php';

		WP_oEmbed::$early_providers = array();
	}

	public function tear_down() {
		WP_oEmbed::$early_providers = array();

		parent::tear_down();
	}

	/**
	 * Tests that a provider to add is stored with the endpoint and the regular expression flag.
	 *
	 * @ticket 66508
	 */
	public function test_add_provider_early_should_store_the_provider() {
		WP_oEmbed::_add_provider_early( '#https?://early\.example\.org/.*#i', 'https://early.example.org/oembed', true );

		$this->assertSame(
			array( '#https?://early\.example\.org/.*#i' => array( 'https://early.example.org/oembed', true ) ),
			WP_oEmbed::$early_providers['add']
		);
		$this->assertArrayNotHasKey( 'remove', WP_oEmbed::$early_providers, 'No provider should be marked to be removed.' );
	}

	/**
	 * Tests that the regular expression flag is false by default.
	 *
	 * @ticket 66508
	 */
	public function test_add_provider_early_should_not_use_a_regular_expression_by_default() {
		WP_oEmbed::_add_provider_early( 'https://early.example.org/*', 'https://early.example.org/oembed' );

		$this->assertSame( array( 'https://early.example.org/oembed', false ), WP_oEmbed::$early_providers['add']['https://early.example.org/*'] );
	}

	/**
	 * Tests that several providers are stored, and that adding the same format again replaces the provider.
	 *
	 * @ticket 66508
	 */
	public function test_add_provider_early_should_store_several_providers_and_replace_the_same_format() {
		WP_oEmbed::_add_provider_early( 'https://first.example.org/*', 'https://first.example.org/oembed' );
		WP_oEmbed::_add_provider_early( 'https://second.example.org/*', 'https://second.example.org/oembed' );
		WP_oEmbed::_add_provider_early( 'https://first.example.org/*', 'https://replaced.example.org/oembed', true );

		$this->assertSame(
			array(
				'https://first.example.org/*'  => array( 'https://replaced.example.org/oembed', true ),
				'https://second.example.org/*' => array( 'https://second.example.org/oembed', false ),
			),
			WP_oEmbed::$early_providers['add']
		);
	}

	/**
	 * Tests that the formats to remove are stored in the order they are removed.
	 *
	 * @ticket 66508
	 */
	public function test_remove_provider_early_should_store_the_formats_in_order() {
		WP_oEmbed::_remove_provider_early( 'https://first.example.org/*' );
		WP_oEmbed::_remove_provider_early( 'https://second.example.org/*' );

		$this->assertSame(
			array( 'https://first.example.org/*', 'https://second.example.org/*' ),
			WP_oEmbed::$early_providers['remove']
		);
		$this->assertArrayNotHasKey( 'add', WP_oEmbed::$early_providers, 'No provider should be marked to be added.' );
	}

	/**
	 * Tests that a provider that was added early is added to the next object.
	 *
	 * @ticket 66508
	 */
	public function test_the_next_object_should_use_the_providers_added_early() {
		WP_oEmbed::_add_provider_early( '#https?://early\.example\.org/.*#i', 'https://early.example.org/oembed', true );

		$oembed = new WP_oEmbed();

		$this->assertSame(
			array( 'https://early.example.org/oembed', true ),
			$oembed->providers['#https?://early\.example\.org/.*#i']
		);
	}

	/**
	 * Tests that a provider of core that was removed early is not in the next object.
	 *
	 * @ticket 66508
	 */
	public function test_the_next_object_should_not_have_the_providers_removed_early() {
		$format = '#https?://youtu\.be/.*#i';

		$this->assertArrayHasKey( $format, ( new WP_oEmbed() )->providers, 'The provider should exist before it is removed.' );

		WP_oEmbed::_remove_provider_early( $format );

		$providers = ( new WP_oEmbed() )->providers;

		$this->assertArrayNotHasKey( $format, $providers, 'The provider should be removed.' );
		$this->assertArrayHasKey( '#https?://(www\.)?flickr\.com/.*#i', $providers, 'The other providers should be kept.' );
	}

	/**
	 * Tests that the providers are added before the others are removed.
	 *
	 * @ticket 66508
	 */
	public function test_the_next_object_should_remove_a_provider_that_was_added_early() {
		WP_oEmbed::_add_provider_early( 'https://early.example.org/*', 'https://early.example.org/oembed' );
		WP_oEmbed::_remove_provider_early( 'https://early.example.org/*' );

		$this->assertArrayNotHasKey( 'https://early.example.org/*', ( new WP_oEmbed() )->providers );
	}

	/**
	 * Tests that the early providers are used by one object only.
	 *
	 * @ticket 66508
	 */
	public function test_the_early_providers_should_be_cleared_after_they_are_used() {
		WP_oEmbed::_add_provider_early( 'https://early.example.org/*', 'https://early.example.org/oembed' );
		WP_oEmbed::_remove_provider_early( '#https?://youtu\.be/.*#i' );

		new WP_oEmbed();

		$this->assertSame( array(), WP_oEmbed::$early_providers, 'The list should be cleared.' );

		$second = new WP_oEmbed();

		$this->assertArrayNotHasKey( 'https://early.example.org/*', $second->providers, 'The provider added early should not be added again.' );
		$this->assertArrayHasKey( '#https?://youtu\.be/.*#i', $second->providers, 'The provider removed early should not be removed again.' );
	}
}
