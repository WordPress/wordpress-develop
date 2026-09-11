<?php
/**
 * Tests for _reset_privacy_policy_page_for_post() and the self-healing
 * check in WP_Privacy_Policy_Content::notice().
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 7.1.0
 *
 * @group privacy
 *
 * @covers ::_reset_privacy_policy_page_for_post
 */
class Tests_Privacy_WpPrivacyResetPolicyPageForPost extends WP_UnitTestCase {
	/**
	 * ID of the page set as the Privacy Policy page.
	 */
	private int $policy_page_id;

	public function set_up(): void {
		parent::set_up();

		$page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		assert( is_int( $page_id ) );
		$this->policy_page_id = $page_id;
		update_option( 'wp_page_for_privacy_policy', $this->policy_page_id );
	}

	public function tear_down(): void {
		delete_option( 'wp_page_for_privacy_policy' );
		parent::tear_down();
	}

	/**
	 * Tests that trashing the Privacy Policy page does NOT reset the option,
	 * so that restoring from trash preserves the assignment.
	 *
	 * @ticket 56694
	 */
	public function test_trashing_privacy_policy_page_does_not_reset_option(): void {
		wp_trash_post( $this->policy_page_id );

		$this->assertSame(
			$this->policy_page_id,
			(int) get_option( 'wp_page_for_privacy_policy' ),
			'Trashing the Privacy Policy page should not reset wp_page_for_privacy_policy.'
		);
	}

	/**
	 * Tests that permanently deleting the Privacy Policy page resets the option to 0.
	 *
	 * @ticket 56694
	 */
	public function test_deleting_privacy_policy_page_resets_option(): void {
		wp_delete_post( $this->policy_page_id, true );

		$this->assertSame( 0, (int) get_option( 'wp_page_for_privacy_policy' ) );
	}

	/**
	 * Tests that trashing a different page does not change the option.
	 *
	 * @ticket 56694
	 */
	public function test_trashing_a_different_page_does_not_reset_option(): void {
		$other_page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$this->assertIsInt( $other_page_id );
		wp_trash_post( $other_page_id );

		$this->assertSame(
			$this->policy_page_id,
			(int) get_option( 'wp_page_for_privacy_policy' ),
			'Trashing an unrelated page should not reset wp_page_for_privacy_policy.'
		);
	}

	/**
	 * Tests that deleting a non-page post type does not change the option.
	 *
	 * @ticket 56694
	 */
	public function test_deleting_non_page_post_type_does_not_reset_option(): void {
		$post_id = self::factory()->post->create( array( 'post_type' => 'post' ) );
		$this->assertIsInt( $post_id );
		wp_delete_post( $post_id, true );

		$this->assertSame(
			$this->policy_page_id,
			(int) get_option( 'wp_page_for_privacy_policy' ),
			'Deleting a non-page post should not reset wp_page_for_privacy_policy.'
		);
	}

	/**
	 * Tests that WP_Privacy_Policy_Content::notice() resets the option to 0
	 * when the stored ID points to a page that no longer exists.
	 *
	 * @ticket 56694
	 *
	 * @covers WP_Privacy_Policy_Content::notice
	 */
	public function test_notice_self_heals_when_policy_page_does_not_exist(): void {
		update_option( 'wp_page_for_privacy_policy', 99999 );

		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->assertIsInt( $user_id );
		wp_set_current_user( $user_id );
		if ( is_multisite() ) {
			grant_super_admin( $user_id );
		}
		set_current_screen( 'post' );

		$post = self::factory()->post->create_and_get( array( 'post_type' => 'page' ) );
		$this->assertInstanceOf( WP_Post::class, $post );
		WP_Privacy_Policy_Content::notice( $post );

		$this->assertSame(
			0,
			(int) get_option( 'wp_page_for_privacy_policy' ),
			'notice() should reset the option to 0 when the stored page does not exist.'
		);
	}

	/**
	 * Tests that _reset_privacy_policy_page_for_post() does not call
	 * update_option() when wp_page_for_privacy_policy is already 0.
	 *
	 * @ticket 56694
	 */
	public function test_no_update_option_when_policy_page_already_zero(): void {
		update_option( 'wp_page_for_privacy_policy', 0 );

		$call_count = 0;
		add_filter(
			'pre_update_option_wp_page_for_privacy_policy',
			static function ( $value ) use ( &$call_count ) {
				++$call_count;
				return $value;
			}
		);

		$other_page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$this->assertIsInt( $other_page_id );
		wp_delete_post( $other_page_id, true );

		$this->assertSame(
			0,
			$call_count,
			'update_option() should not be called when wp_page_for_privacy_policy is already 0.'
		);
	}

	/**
	 * Tests that untrashing the Privacy Policy page preserves the option,
	 * confirming the trash/restore cycle keeps the assignment intact.
	 *
	 * @ticket 56694
	 */
	public function test_untrashing_privacy_policy_page_preserves_option(): void {
		wp_trash_post( $this->policy_page_id );
		wp_untrash_post( $this->policy_page_id );

		$this->assertSame(
			$this->policy_page_id,
			(int) get_option( 'wp_page_for_privacy_policy' ),
			'Untrashing the Privacy Policy page should preserve wp_page_for_privacy_policy.'
		);
	}
}
