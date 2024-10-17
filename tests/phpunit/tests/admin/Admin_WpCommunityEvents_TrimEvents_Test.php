<?php

require_once __DIR__ . '/Admin_WpCommunityEvents_TestCase.php';

/**
 * @package WordPress
 * @subpackage UnitTests
 * @since 4.8.0
 *
 * @group admin
 * @group community-events
 *
 * @covers WP_Community_Events::trim_events
 */
class Admin_WpCommunityEvents_TrimEvents_Test extends Admin_WpCommunityEvents_TestCase {
	/**
	 * Test: `trim_events()` should immediately remove expired events.
	 *
	 * @since 5.5.2
	 */
	public function test_trim_expired_events() {
		$trim_events = new ReflectionMethod( $this->instance, 'trim_events' );
		$trim_events->setAccessible( true );

		$events = $this->get_valid_events();

		// This should be removed because it's already ended.
		$events[0]['start_unix_timestamp'] = strtotime( '1 hour ago' );
		$events[0]['end_unix_timestamp']   = strtotime( '2 seconds ago' );

		// This should remain because it hasn't ended yet.
		$events[1]['start_unix_timestamp'] = strtotime( '2 seconds ago' );
		$events[1]['end_unix_timestamp']   = strtotime( '+1 hour' );

		$actual = $trim_events->invoke( $this->instance, $events );

		$this->assertCount( 2, $actual );
		$this->assertSame( $actual[0]['title'], 'Part 3- Site Maintenance - Tools to Make It Easy' );
		$this->assertSame( $actual[1]['title'], 'WordCamp San Francisco' );
	}

	/**
	 * Test: get_events() should return the events with the WordCamp pinned in the prepared list.
	 *
	 * @since 4.9.7
	 * @since 5.5.2 Tests `trim_events()` directly instead of indirectly via `get_events()`.
	 */
	public function test_trim_events_pin_wordcamp() {
		$trim_events = new ReflectionMethod( $this->instance, 'trim_events' );
		$trim_events->setAccessible( true );

		$actual = $trim_events->invoke( $this->instance, $this->_events_with_unpinned_wordcamp() );

		/*
		 * San Diego was at index 3 in the mock API response, but pinning puts it at index 2,
		 * so that it remains in the list. The other events should remain unchanged.
		 */
		$this->assertCount( 3, $actual );
		$this->assertSame( $actual[0]['title'], 'Flexbox + CSS Grid: Magic for Responsive Layouts' );
		$this->assertSame( $actual[1]['title'], 'Part 3- Site Maintenance - Tools to Make It Easy' );
		$this->assertSame( $actual[2]['title'], 'WordCamp San Diego' );
	}

	/**
	 * Simulates a scenario where a WordCamp needs to be pinned higher than it's default position.
	 *
	 * @since 4.9.7
	 * @since 5.5.2 Accepts and returns only the events, rather than an entire HTTP response.
	 *
	 * @return array A list of mock events.
	 */
	public function _events_with_unpinned_wordcamp() {
		return array(
			array(
				'type'                 => 'meetup',
				'title'                => 'Flexbox + CSS Grid: Magic for Responsive Layouts',
				'url'                  => 'https://www.meetup.com/Eastbay-WordPress-Meetup/events/236031233/',
				'meetup'               => 'The East Bay WordPress Meetup Group',
				'meetup_url'           => 'https://www.meetup.com/Eastbay-WordPress-Meetup/',
				'start_unix_timestamp' => strtotime( 'next Monday 1pm' ),
				'end_unix_timestamp'   => strtotime( 'next Monday 2pm' ),

				'location'             => array(
					'location'  => 'Oakland, CA, USA',
					'country'   => 'us',
					'latitude'  => 37.808453,
					'longitude' => -122.26593,
				),
			),

			array(
				'type'                 => 'meetup',
				'title'                => 'Part 3- Site Maintenance - Tools to Make It Easy',
				'url'                  => 'https://www.meetup.com/Wordpress-Bay-Area-CA-Foothills/events/237706839/',
				'meetup'               => 'WordPress Bay Area Foothills Group',
				'meetup_url'           => 'https://www.meetup.com/Wordpress-Bay-Area-CA-Foothills/',
				'start_unix_timestamp' => strtotime( 'next Tuesday 1:30pm' ),
				'end_unix_timestamp'   => strtotime( 'next Tuesday 2:30pm' ),

				'location'             => array(
					'location'  => 'Milpitas, CA, USA',
					'country'   => 'us',
					'latitude'  => 37.432813,
					'longitude' => -121.907095,
				),
			),

			array(
				'type'                 => 'meetup',
				'title'                => 'WordPress Q&A',
				'url'                  => 'https://www.meetup.com/sanjosewp/events/245419844/',
				'meetup'               => 'The San Jose WordPress Meetup',
				'meetup_url'           => 'https://www.meetup.com/sanjosewp/',
				'start_unix_timestamp' => strtotime( 'next Wednesday 5:30pm' ),
				'end_unix_timestamp'   => strtotime( 'next Wednesday 6:30pm' ),

				'location'             => array(
					'location'  => 'Milpitas, CA, USA',
					'country'   => 'us',
					'latitude'  => 37.244194,
					'longitude' => -121.889313,
				),
			),

			array(
				'type'                 => 'wordcamp',
				'title'                => 'WordCamp San Diego',
				'url'                  => 'https://2018.sandiego.wordcamp.org',
				'meetup'               => null,
				'meetup_url'           => null,
				'start_unix_timestamp' => strtotime( 'next Thursday 9am' ),
				'end_unix_timestamp'   => strtotime( 'next Thursday 10am' ),

				'location'             => array(
					'location'  => 'San Diego, CA',
					'country'   => 'US',
					'latitude'  => 32.7220419,
					'longitude' => -117.1534513,
				),
			),
		);
	}

	/**
	 * Test: get_events() shouldn't stick an extra WordCamp when there's already one that naturally
	 * falls into the list.
	 *
	 * @since 4.9.7
	 * @since 5.5.2 Tests `trim_events()` directly instead of indirectly via `get_events()`.
	 */
	public function test_trim_events_dont_pin_multiple_wordcamps() {
		$trim_events = new ReflectionMethod( $this->instance, 'trim_events' );
		$trim_events->setAccessible( true );

		$actual = $trim_events->invoke( $this->instance, $this->_events_with_multiple_wordcamps() );

		/*
		 * The first meetup should be removed because it's expired, while the next 3 events are selected.
		 * WordCamp LA should not be stuck to the list, because San Diego already appears naturally.
		 */
		$this->assertCount( 3, $actual );
		$this->assertSame( $actual[0]['title'], 'WordCamp San Diego' );
		$this->assertSame( $actual[1]['title'], 'Part 3- Site Maintenance - Tools to Make It Easy' );
		$this->assertSame( $actual[2]['title'], 'WordPress Q&A' );
	}

	/**
	 * Simulates a valid HTTP response where a WordCamp needs to be pinned higher than it's default position.
	 * no need to pin extra camp b/c one already exists in response
	 *
	 * @since 4.9.7
	 * @since 5.5.2 Tests `trim_events()` directly instead of indirectly via `get_events()`.
	 *
	 * @return array A mock HTTP response.
	 */
	public function _events_with_multiple_wordcamps() {
		return array(
			array(
				'type'                 => 'meetup',
				'title'                => 'Flexbox + CSS Grid: Magic for Responsive Layouts',
				'url'                  => 'https://www.meetup.com/Eastbay-WordPress-Meetup/events/236031233/',
				'meetup'               => 'The East Bay WordPress Meetup Group',
				'meetup_url'           => 'https://www.meetup.com/Eastbay-WordPress-Meetup/',
				'start_unix_timestamp' => strtotime( '2 days ago' ) - HOUR_IN_SECONDS,
				'end_unix_timestamp'   => strtotime( '2 days ago' ),

				'location'             => array(
					'location'  => 'Oakland, CA, USA',
					'country'   => 'us',
					'latitude'  => 37.808453,
					'longitude' => -122.26593,
				),
			),

			array(
				'type'                 => 'wordcamp',
				'title'                => 'WordCamp San Diego',
				'url'                  => 'https://2018.sandiego.wordcamp.org',
				'meetup'               => null,
				'meetup_url'           => null,
				'start_unix_timestamp' => strtotime( 'next Tuesday 9am' ),
				'end_unix_timestamp'   => strtotime( 'next Tuesday 10am' ),

				'location'             => array(
					'location'  => 'San Diego, CA',
					'country'   => 'US',
					'latitude'  => 32.7220419,
					'longitude' => -117.1534513,
				),
			),

			array(
				'type'                 => 'meetup',
				'title'                => 'Part 3- Site Maintenance - Tools to Make It Easy',
				'url'                  => 'https://www.meetup.com/Wordpress-Bay-Area-CA-Foothills/events/237706839/',
				'meetup'               => 'WordPress Bay Area Foothills Group',
				'meetup_url'           => 'https://www.meetup.com/Wordpress-Bay-Area-CA-Foothills/',
				'start_unix_timestamp' => strtotime( 'next Wednesday 1:30pm' ),
				'end_unix_timestamp'   => strtotime( 'next Wednesday 2:30pm' ),

				'location'             => array(
					'location'  => 'Milpitas, CA, USA',
					'country'   => 'us',
					'latitude'  => 37.432813,
					'longitude' => -121.907095,
				),
			),

			array(
				'type'                 => 'meetup',
				'title'                => 'WordPress Q&A',
				'url'                  => 'https://www.meetup.com/sanjosewp/events/245419844/',
				'meetup'               => 'The San Jose WordPress Meetup',
				'meetup_url'           => 'https://www.meetup.com/sanjosewp/',
				'start_unix_timestamp' => strtotime( 'next Thursday 5:30pm' ),
				'end_unix_timestamp'   => strtotime( 'next Thursday 6:30pm' ),

				'location'             => array(
					'location'  => 'Milpitas, CA, USA',
					'country'   => 'us',
					'latitude'  => 37.244194,
					'longitude' => -121.889313,
				),
			),

			array(
				'type'                 => 'wordcamp',
				'title'                => 'WordCamp Los Angeles',
				'url'                  => 'https://2018.la.wordcamp.org',
				'meetup'               => null,
				'meetup_url'           => null,
				'start_unix_timestamp' => strtotime( 'next Friday 9am' ),
				'end_unix_timestamp'   => strtotime( 'next Friday 10am' ),

				'location'             => array(
					'location'  => 'Los Angeles, CA',
					'country'   => 'US',
					'latitude'  => 34.050888,
					'longitude' => -118.285426,
				),
			),
		);
	}
}
