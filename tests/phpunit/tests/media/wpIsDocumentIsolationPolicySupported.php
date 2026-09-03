<?php

/**
 * Tests for wp_is_document_isolation_policy_supported().
 *
 * @group media
 * @covers ::wp_is_document_isolation_policy_supported
 */
class Tests_Media_wpIsDocumentIsolationPolicySupported extends WP_UnitTestCase {

	/**
	 * Original HTTP_USER_AGENT value.
	 */
	private ?string $original_user_agent;

	public function set_up() {
		parent::set_up();
		$this->original_user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
	}

	public function tear_down() {
		if ( null === $this->original_user_agent ) {
			unset( $_SERVER['HTTP_USER_AGENT'] );
		} else {
			$_SERVER['HTTP_USER_AGENT'] = $this->original_user_agent;
		}
		parent::tear_down();
	}

	/**
	 * @ticket 65661
	 *
	 * @dataProvider data_user_agents
	 *
	 * @param string|null $user_agent The User-Agent header, or null for none.
	 * @param bool        $expected   Whether Document-Isolation-Policy is supported.
	 */
	public function test_user_agent_support( ?string $user_agent, bool $expected ) {
		if ( null === $user_agent ) {
			unset( $_SERVER['HTTP_USER_AGENT'] );
		} else {
			$_SERVER['HTTP_USER_AGENT'] = $user_agent;
		}

		$this->assertSame( $expected, wp_is_document_isolation_policy_supported() );
	}

	/**
	 * @return array[]
	 */
	public function data_user_agents() {
		return array(
			'no user agent' => array( null, false ),
			'Firefox'       => array( 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:127.0) Gecko/20100101 Firefox/127.0', false ),
			'Safari'        => array( 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_5) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Safari/605.1.15', false ),
			'Chrome 136'    => array( 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', false ),
			'Chrome 137'    => array( 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', true ),
			'Edge 140'      => array( 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', true ),
		);
	}
}
