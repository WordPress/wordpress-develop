<?php

/**
 * Tests for the_privacy_policy_link().
 *
 * @group link
 * @group privacy
 *
 * @covers ::the_privacy_policy_link
 */
class Tests_Link_ThePrivacyPolicyLink extends WP_UnitTestCase {

	/**
	 * Privacy policy page ID.
	 *
	 * @var int
	 */
	private static $page_id = 0;

	/**
	 * Creates shared privacy policy page fixture.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$page_id = $factory->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => 'Privacy Policy',
				'post_status' => 'publish',
			)
		);
	}

	/**
	 * Sets the privacy policy page option before each test.
	 */
	public function set_up() {
		parent::set_up();

		update_option( 'wp_page_for_privacy_policy', self::$page_id );
	}

	/**
	 * Tests that the_privacy_policy_link() includes before and after markup.
	 *
	 * @ticket 65819
	 */
	public function test_includes_before_and_after_markup() {
		$before = '<span class="before">';
		$after  = '</span>';
		$output = get_echo( 'the_privacy_policy_link', array( $before, $after ) );

		$this->assertSame( get_the_privacy_policy_link( $before, $after ), $output );
		$this->assertStringContainsString( 'class="privacy-policy-link"', $output );
		$this->assertStringStartsWith( $before, $output );
		$this->assertStringEndsWith( $after, $output );
	}

	/**
	 * Tests that the_privacy_policy_link() outputs nothing when privacy policy page is not published.
	 *
	 * @ticket 65819
	 */
	public function test_outputs_empty_string_when_page_is_not_published() {
		$draft_page_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => 'Draft Privacy Policy',
				'post_status' => 'draft',
			)
		);

		update_option( 'wp_page_for_privacy_policy', $draft_page_id );

		$this->assertSame( '', get_echo( 'the_privacy_policy_link' ) );
	}
}
