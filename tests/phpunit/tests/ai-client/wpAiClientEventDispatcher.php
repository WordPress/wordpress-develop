<?php
/**
 * Tests for WP_AI_Client_Event_Dispatcher.
 *
 * @group ai-client
 * @covers WP_AI_Client_Event_Dispatcher
 */

require_once dirname( __DIR__, 2 ) . '/includes/wp-ai-client-mock-event.php';

class Tests_AI_Client_EventDispatcher extends WP_UnitTestCase {

	/**
	 * Test that dispatch fires the appropriate action hook.
	 *
	 * @ticket 64591
	 */
	public function test_dispatch_fires_action_hook() {
		$dispatcher = new WP_AI_Client_Event_Dispatcher();
		$event      = new WP_AI_Client_Mock_Event();

		$hook_fired  = false;
		$fired_event = null;

		add_action(
			'wp_ai_client_wp_ai_client_mock',
			function ( $e ) use ( &$hook_fired, &$fired_event ) {
				$hook_fired  = true;
				$fired_event = $e;
			}
		);

		$result = $dispatcher->dispatch( $event );

		$this->assertTrue( $hook_fired, 'The action hook should have been fired' );
		$this->assertSame( $event, $fired_event, 'The fired event should be the same as the dispatched event' );
		$this->assertSame( $event, $result, 'The dispatch method should return the same event' );
	}

	/**
	 * Test that dispatch returns event without listeners.
	 *
	 * @ticket 64591
	 */
	public function test_dispatch_returns_event_without_listeners() {
		$dispatcher        = new WP_AI_Client_Event_Dispatcher();
		$event             = new stdClass();
		$event->test_value = 'original';

		$result = $dispatcher->dispatch( $event );

		$this->assertSame( $event, $result, 'The dispatch method should return the same object' );
		$this->assertSame( 'original', $result->test_value, 'The event object should remain unchanged' );
	}
}
