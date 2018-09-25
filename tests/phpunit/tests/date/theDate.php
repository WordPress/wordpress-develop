<?php

/**
 * @group date
 * @group datetime
 */
class Tests_Date_TheDate extends WP_UnitTestCase {

	/** @var array $hooks_called Count of hooks called. */
	protected $hooks_called = array(
		'the_time'               => 0,
		'get_the_time'           => 0,
		'the_modified_time'      => 0,
		'get_the_modified_time'  => 0,
		'the_date'               => 0,
		'get_the_date'           => 0,
		'the_modified_date'      => 0,
		'get_the_modified_date'  => 0,
		'get_post_time'          => 0,
		'get_post_modified_time' => 0,
	);

	public function test_should_call_hooks() {
		add_filter( 'the_time', array( $this, 'count_hook' ) );
		add_filter( 'get_the_time', array( $this, 'count_hook' ) );
		add_filter( 'get_post_time', array( $this, 'count_hook' ) );

		add_filter( 'the_modified_time', array( $this, 'count_hook' ) );
		add_filter( 'get_the_modified_time', array( $this, 'count_hook' ) );
		add_filter( 'get_post_modified_time', array( $this, 'count_hook' ) );

		add_filter( 'the_date', array( $this, 'count_hook' ) );
		add_filter( 'get_the_date', array( $this, 'count_hook' ) );

		add_filter( 'the_modified_date', array( $this, 'count_hook' ) );
		add_filter( 'get_the_modified_date', array( $this, 'count_hook' ) );

		$post_id = self::factory()->post->create();
		global $post, $currentday, $previousday;
		$post        = get_post( $post_id );
		$currentday  = 1;
		$previousday = 0;

		ob_start();

		the_time();
		get_the_time();

		the_modified_time();
		get_the_modified_time();

		the_date();
		get_the_date();

		the_modified_date();
		get_the_modified_date();

		get_post_time();
		get_post_modified_time();

		ob_end_clean();

		$this->assertEquals( 1, $this->hooks_called['the_time'] );
		$this->assertEquals( 2, $this->hooks_called['get_the_time'] );

		$this->assertEquals( 1, $this->hooks_called['the_modified_time'] );
		$this->assertEquals( 2, $this->hooks_called['get_the_modified_time'] );

		$this->assertEquals( 1, $this->hooks_called['the_date'] );
		$this->assertEquals( 2, $this->hooks_called['get_the_date'] );

		$this->assertEquals( 1, $this->hooks_called['the_modified_date'] );
		$this->assertEquals( 2, $this->hooks_called['get_the_modified_date'] );

		$this->assertEquals( 5, $this->hooks_called['get_post_time'] );
		$this->assertEquals( 5, $this->hooks_called['get_post_modified_time'] );
	}

	public function count_hook( $input ) {
		$this->hooks_called[ current_filter() ] ++;

		return $input;
	}
}
