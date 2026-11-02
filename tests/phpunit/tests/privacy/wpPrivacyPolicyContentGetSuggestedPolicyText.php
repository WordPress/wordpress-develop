<?php

/**
 * Tests for WP_Privacy_Policy_Content::get_suggested_policy_text().
 *
 * @group privacy
 *
 * @covers WP_Privacy_Policy_Content::get_suggested_policy_text
 */
class Tests_Privacy_wpPrivacyPolicyContentGetSuggestedPolicyText extends WP_UnitTestCase {

	/**
	 * Original suggested policy content registered by plugins.
	 *
	 * @var array[]
	 */
	private $orig_policy_content;

	/**
	 * ID of the privacy policy page.
	 *
	 * @var int
	 */
	private $policy_page_id;

	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once ABSPATH . 'wp-admin/includes/class-wp-privacy-policy-content.php';
	}

	public function set_up() {
		parent::set_up();

		$this->orig_policy_content = $this->policy_content_property()->getValue();
		$this->policy_content_property()->setValue( null, array() );

		$this->policy_page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		update_option( 'wp_page_for_privacy_policy', $this->policy_page_id );
	}

	public function tear_down() {
		$this->policy_content_property()->setValue( null, $this->orig_policy_content );

		parent::tear_down();
	}

	/**
	 * Gets the accessible property that holds the suggested policy content.
	 *
	 * @return ReflectionProperty Accessible property.
	 */
	private function policy_content_property() {
		$property = new ReflectionProperty( 'WP_Privacy_Policy_Content', 'policy_content' );
		if ( PHP_VERSION_ID < 80100 ) {
			$property->setAccessible( true );
		}

		return $property;
	}

	/**
	 * Sets the suggested policy content currently registered by plugins.
	 *
	 * @param array[] $content Suggested policy content.
	 */
	private function set_registered_content( array $content ) {
		$this->policy_content_property()->setValue( null, $content );
	}

	/**
	 * Sets the suggested policy content previously stored for the policy page.
	 *
	 * @param array[] $content Stored policy content.
	 */
	private function set_stored_content( array $content ) {
		foreach ( $content as $data ) {
			add_post_meta( $this->policy_page_id, '_wp_suggested_privacy_policy_content', $data );
		}
	}

	/**
	 * Gets the suggested policy content stored for the policy page.
	 *
	 * @return array[] Stored policy content.
	 */
	private function get_stored_content() {
		return get_post_meta( $this->policy_page_id, '_wp_suggested_privacy_policy_content' );
	}

	/**
	 * Asserts that an entry has a timestamp from the time of the call, then removes it.
	 *
	 * @param array  $entry  Policy content entry.
	 * @param string $key    Key of the timestamp.
	 * @param int    $before Time before the call.
	 * @param int    $after  Time after the call.
	 * @return array The entry without the timestamp.
	 */
	private function assert_and_remove_timestamp( array $entry, $key, $before, $after ) {
		$this->assertArrayHasKey( $key, $entry, "The entry should have the {$key} timestamp." );
		$this->assertGreaterThanOrEqual( $before, $entry[ $key ], "The {$key} timestamp should be the time of the call." );
		$this->assertLessThanOrEqual( $after, $entry[ $key ], "The {$key} timestamp should be the time of the call." );

		unset( $entry[ $key ] );

		return $entry;
	}

	/**
	 * Tests that content from a newly activated plugin is added and stored.
	 *
	 * @ticket 65819
	 */
	public function test_adds_content_from_activated_plugin() {
		$this->set_registered_content(
			array(
				array(
					'plugin_name' => 'My Plugin',
					'policy_text' => '<p>My policy.</p>',
				),
			)
		);

		$before = time();
		$result = WP_Privacy_Policy_Content::get_suggested_policy_text();
		$after  = time();

		$this->assertCount( 1, $result, 'One entry should be returned.' );
		$this->assertSame(
			array(
				'plugin_name' => 'My Plugin',
				'policy_text' => '<p>My policy.</p>',
			),
			$this->assert_and_remove_timestamp( $result[0], 'added', $before, $after )
		);
		$this->assertSame( $result, $this->get_stored_content(), 'The entries should be stored for the policy page.' );
	}

	/**
	 * Tests that unchanged content is returned as stored, without updating the stored content.
	 *
	 * @ticket 65819
	 */
	public function test_keeps_unchanged_content() {
		$stored = array(
			'plugin_name' => 'My Plugin',
			'policy_text' => '<p>My policy.</p>',
			'added'       => 1234567890,
		);

		$this->set_registered_content(
			array(
				array(
					'plugin_name' => 'My Plugin',
					'policy_text' => '<p>My policy.</p>',
				),
			)
		);
		$this->set_stored_content( array( $stored ) );

		$deleted = new MockAction();
		add_action( 'delete_post_meta', array( $deleted, 'action' ) );

		$this->assertSame( array( $stored ), WP_Privacy_Policy_Content::get_suggested_policy_text(), 'The stored entry should be returned.' );
		$this->assertSame( 0, $deleted->get_call_count(), 'The stored content should not be updated.' );
	}

	/**
	 * Tests that the plugin name is updated when the same text is suggested under a new name.
	 *
	 * @ticket 65819
	 */
	public function test_updates_renamed_plugin() {
		$this->set_registered_content(
			array(
				array(
					'plugin_name' => 'My Renamed Plugin',
					'policy_text' => '<p>My policy.</p>',
				),
			)
		);
		$this->set_stored_content(
			array(
				array(
					'plugin_name' => 'My Plugin',
					'policy_text' => '<p>My policy.</p>',
					'added'       => 1234567890,
				),
			)
		);

		$expected = array(
			array(
				'plugin_name' => 'My Renamed Plugin',
				'policy_text' => '<p>My policy.</p>',
				'added'       => 1234567890,
			),
		);

		$this->assertSame( $expected, WP_Privacy_Policy_Content::get_suggested_policy_text(), 'The new plugin name should be used.' );
		$this->assertSame( $expected, $this->get_stored_content(), 'The new plugin name should be stored.' );
	}

	/**
	 * Tests that content from a re-activated plugin is marked as added again.
	 *
	 * @ticket 65819
	 */
	public function test_marks_reactivated_plugin_as_added() {
		$this->set_registered_content(
			array(
				array(
					'plugin_name' => 'My Plugin',
					'policy_text' => '<p>My policy.</p>',
				),
			)
		);
		$this->set_stored_content(
			array(
				array(
					'plugin_name' => 'My Plugin',
					'policy_text' => '<p>My policy.</p>',
					'removed'     => 1234567890,
				),
			)
		);

		$before = time();
		$result = WP_Privacy_Policy_Content::get_suggested_policy_text();
		$after  = time();

		$this->assertCount( 1, $result, 'One entry should be returned.' );
		$this->assertArrayNotHasKey( 'removed', $result[0], 'The entry should no longer be marked as removed.' );
		$this->assertSame(
			array(
				'plugin_name' => 'My Plugin',
				'policy_text' => '<p>My policy.</p>',
			),
			$this->assert_and_remove_timestamp( $result[0], 'added', $before, $after )
		);
		$this->assertSame( $result, $this->get_stored_content(), 'The re-activated entry should be stored.' );
	}

	/**
	 * Tests that changed text from the same plugin is marked as updated.
	 *
	 * @ticket 65819
	 */
	public function test_marks_changed_text_as_updated() {
		$this->set_registered_content(
			array(
				array(
					'plugin_name' => 'My Plugin',
					'policy_text' => '<p>My new policy.</p>',
				),
			)
		);
		$this->set_stored_content(
			array(
				array(
					'plugin_name' => 'My Plugin',
					'policy_text' => '<p>My policy.</p>',
					'added'       => 1234567890,
				),
			)
		);

		$before = time();
		$result = WP_Privacy_Policy_Content::get_suggested_policy_text();
		$after  = time();

		$this->assertCount( 1, $result, 'One entry should be returned.' );
		$this->assertSame(
			array(
				'plugin_name' => 'My Plugin',
				'policy_text' => '<p>My new policy.</p>',
			),
			$this->assert_and_remove_timestamp( $result[0], 'updated', $before, $after ),
			'The new text should replace the stored entry, without its added timestamp.'
		);
		$this->assertSame( $result, $this->get_stored_content(), 'The updated entry should be stored.' );
	}

	/**
	 * Tests that content from a deactivated plugin is marked as removed.
	 *
	 * @ticket 65819
	 */
	public function test_marks_deactivated_plugin_as_removed() {
		$this->set_stored_content(
			array(
				array(
					'plugin_name' => 'My Plugin',
					'policy_text' => '<p>My policy.</p>',
					'added'       => 1234567890,
				),
			)
		);

		$before = time();
		$result = WP_Privacy_Policy_Content::get_suggested_policy_text();
		$after  = time();

		$this->assertCount( 1, $result, 'One entry should be returned.' );
		$this->assertSame(
			array(
				'plugin_name' => 'My Plugin',
				'policy_text' => '<p>My policy.</p>',
			),
			$this->assert_and_remove_timestamp( $result[0], 'removed', $before, $after ),
			'The entry should be marked as removed, without its added timestamp.'
		);
		$this->assertSame( $result, $this->get_stored_content(), 'The removed entry should be stored.' );
	}

	/**
	 * Tests that entries without a plugin name or text are left out.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_incomplete_entries
	 *
	 * @param bool  $is_registered Whether the entry is registered by a plugin, rather than stored.
	 * @param array $entry         Incomplete entry.
	 */
	public function test_leaves_out_incomplete_entries( $is_registered, $entry ) {
		if ( $is_registered ) {
			$this->set_registered_content( array( $entry ) );
		} else {
			$this->set_stored_content( array( $entry ) );
		}

		$this->assertSame( array(), WP_Privacy_Policy_Content::get_suggested_policy_text(), 'No entry should be returned.' );
		$this->assertSame( array(), $this->get_stored_content(), 'No entry should be stored.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_incomplete_entries() {
		$no_name = array(
			'plugin_name' => '',
			'policy_text' => '<p>My policy.</p>',
		);
		$no_text = array(
			'plugin_name' => 'My Plugin',
			'policy_text' => '',
		);

		return array(
			'registered, no plugin name' => array( true, $no_name ),
			'registered, no text'        => array( true, $no_text ),
			'stored, no plugin name'     => array( false, $no_name ),
			'stored, no text'            => array( false, $no_text ),
		);
	}

	/**
	 * Tests the order of kept, added and removed entries.
	 *
	 * @ticket 65819
	 */
	public function test_orders_kept_then_added_then_removed_entries() {
		$this->set_registered_content(
			array(
				array(
					'plugin_name' => 'New Plugin',
					'policy_text' => '<p>New policy.</p>',
				),
				array(
					'plugin_name' => 'Kept Plugin',
					'policy_text' => '<p>Kept policy.</p>',
				),
			)
		);
		$this->set_stored_content(
			array(
				array(
					'plugin_name' => 'Old Plugin',
					'policy_text' => '<p>Old policy.</p>',
					'added'       => 1234567890,
				),
				array(
					'plugin_name' => 'Kept Plugin',
					'policy_text' => '<p>Kept policy.</p>',
					'added'       => 1234567890,
				),
			)
		);

		$result = WP_Privacy_Policy_Content::get_suggested_policy_text();

		$this->assertSame( array( 'Kept Plugin', 'New Plugin', 'Old Plugin' ), wp_list_pluck( $result, 'plugin_name' ), 'The entries should be in order.' );
		$this->assertArrayHasKey( 'added', $result[1], 'The new plugin should be marked as added.' );
		$this->assertArrayHasKey( 'removed', $result[2], 'The old plugin should be marked as removed.' );
		$this->assertSame( $result, $this->get_stored_content(), 'The entries should be stored.' );
	}

	/**
	 * Tests that nothing is stored without a privacy policy page.
	 *
	 * @ticket 65819
	 */
	public function test_does_not_store_without_policy_page() {
		delete_option( 'wp_page_for_privacy_policy' );

		$this->set_registered_content(
			array(
				array(
					'plugin_name' => 'My Plugin',
					'policy_text' => '<p>My policy.</p>',
				),
			)
		);

		$added = new MockAction();
		add_action( 'add_post_meta', array( $added, 'action' ) );

		$result = WP_Privacy_Policy_Content::get_suggested_policy_text();

		$this->assertSame( array( 'My Plugin' ), wp_list_pluck( $result, 'plugin_name' ), 'The registered content should be returned.' );
		$this->assertSame( 0, $added->get_call_count(), 'Nothing should be stored.' );
	}
}
