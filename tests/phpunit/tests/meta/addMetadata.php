<?php

/**
 * @group meta
 * @covers ::add_metadata
 */
class Tests_Meta_AddMetadata extends WP_UnitTestCase {

	/**
	 * @ticket 39706
	 *
	 * @dataProvider data_actions_should_receive_unique
	 *
	 * @param bool $unique Whether the specified meta key should be unique for the object.
	 */
	public function test_actions_should_receive_unique( $unique ) {
		$add_action   = new MockAction();
		$added_action = new MockAction();

		add_action( 'add_post_meta', array( $add_action, 'action' ), 10, 4 );
		add_action( 'added_post_meta', array( $added_action, 'action' ), 10, 5 );

		add_metadata( 'post', 123, 'test_key', 'value', $unique );

		$add_args   = $add_action->get_args();
		$added_args = $added_action->get_args();

		$this->assertSame( $unique, $add_args[0][3], 'The add_post_meta action should receive the value of $unique.' );
		$this->assertSame( $unique, $added_args[0][4], 'The added_post_meta action should receive the value of $unique.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_actions_should_receive_unique() {
		return array(
			'unique meta'     => array( true ),
			'non-unique meta' => array( false ),
		);
	}
}
