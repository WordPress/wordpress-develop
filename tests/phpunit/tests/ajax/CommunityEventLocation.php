<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing Ajax Community_Event_location functionality.
 *
 * @package    WordPress
 * @subpackage UnitTests
 * @since      5.7.0
 * @group      ajaxo
 */
class Tests_Community_Event_location extends WP_Ajax_UnitTestCase
{
	private $userId;

	/**
	 * Runs before each Test
	 */
	public function setUp()
	{
		parent::setUp();
		$wpUser = get_user_by('email', WP_TESTS_EMAIL);
		update_user_meta($wpUser->ID, 'community-events-location', 'DummyData');

		$wpUser = get_user_by('email', WP_TESTS_EMAIL);
		wp_set_current_user($wpUser->ID);
		$this->userId = $wpUser->ID;
	}

	/**
	 *    Tests the new Clear Community Events Location Ajax endpoint without nonce
	 */
	public function test_clear_community_events_location_without_nonce()
	{
		// Before should be set to 'DummyData'
		$content = get_user_meta($this->userId, 'community-events-location', true);
		$this->assertNotTrue($this->isNullOrEmptyString($content));
		$this->handleAjaxCall();

		// After Failed Ajax Call should be still 'DummyData';
		$content = $this->getCommunityEventsContent();
		$this->assertNotTrue($this->isNullOrEmptyString($content));
	}

	/**
	 *    Tests the new Clear Community Events Location Ajax endpoint with wrong nonce
	 */
	public function test_clear_community_events_location_with_wrong_nonce()
	{
		// Before should be set to 'DummyData'
		$content = get_user_meta($this->userId, 'community-events-location', true);
		$this->assertNotTrue($this->isNullOrEmptyString($content));
		$_POST['_wpnonce'] = wp_create_nonce('community_events_but_wrong_nonce');
		$this->handleAjaxCall();

		// After Failed Ajax Call should be still 'DummyData';
		$content = $this->getCommunityEventsContent();
		$this->assertNotTrue($this->isNullOrEmptyString($content));
	}

	/**
	 *    Tests the new Clear Community Events Location Ajax endpoint without being logged in
	 */
	public function test_clear_community_events_location_logged_out()
	{
		$this->logout();

		// Before should be set to 'DummyData'
		$content = get_user_meta($this->userId, 'community-events-location', true);
		$this->assertNotTrue($this->isNullOrEmptyString($content));
		$_POST['_wpnonce'] = wp_create_nonce('community_events');
		$this->handleAjaxCall();

		// After Failed Ajax Call should be still 'DummyData';
		$content = $this->getCommunityEventsContent();
		$this->assertNotTrue($this->isNullOrEmptyString($content));
	}

	/**
	 *    Tests the new Clear Community Events Location Ajax endpoint in the correct way
	 */
	public function test_clear_community_events_location()
	{
		// Before should be set to 'DummyData'
		$content = $this->getCommunityEventsContent();
		$this->assertNotTrue($this->isNullOrEmptyString($content));
		$_POST['_wpnonce'] = wp_create_nonce('community_events');
		$this->handleAjaxCall();

		// After Ajax Call should be set to null
		$content = $this->getCommunityEventsContent();
		$this->assertTrue($this->isNullOrEmptyString($content));
	}

	/**
	 * @return mixed
	 */
	private function getCommunityEventsContent()
	{
		return get_user_meta($this->userId, 'community-events-location', true);
	}

	private function handleAjaxCall()
	{
		try {
			$this->_handleAjax('clear-community-events');
		} catch (WPDieException $e) {
			unset($e);
		}
	}

	private function isNullOrEmptyString($string)
	{
		return (!isset($string) || trim($string) === '');
	}
}
