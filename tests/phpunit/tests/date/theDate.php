<?php

/**
 * @group date
 * @group datetime
 * @group post
 * @covers ::the_date
 * @covers ::the_weekday_date
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

		$this->assertSame( 1, $this->hooks_called['the_time'] );
		$this->assertSame( 2, $this->hooks_called['get_the_time'] );

		$this->assertSame( 1, $this->hooks_called['the_modified_time'] );
		$this->assertSame( 2, $this->hooks_called['get_the_modified_time'] );

		$this->assertSame( 1, $this->hooks_called['the_date'] );
		$this->assertSame( 2, $this->hooks_called['get_the_date'] );

		$this->assertSame( 1, $this->hooks_called['the_modified_date'] );
		$this->assertSame( 2, $this->hooks_called['get_the_modified_date'] );

		$this->assertSame( 5, $this->hooks_called['get_post_time'] );
		$this->assertSame( 5, $this->hooks_called['get_post_modified_time'] );
	}

	public function count_hook( $input ) {
		$this->hooks_called[ current_filter() ]++;

		return $input;
	}

	/**
	 * @ticket 33750
	 */
	public function test_the_date() {
		ob_start();
		the_date();
		$actual = ob_get_clean();
		$this->assertSame( '', $actual );

		$GLOBALS['post'] = self::factory()->post->create_and_get(
			array(
				'post_date' => '2015-09-16 08:00:00',
			)
		);

		ob_start();
		$GLOBALS['currentday']  = '18.09.15';
		$GLOBALS['previousday'] = '17.09.15';
		the_date();
		$this->assertSame( 'September 16, 2015', ob_get_clean() );

		ob_start();
		$GLOBALS['currentday']  = '18.09.15';
		$GLOBALS['previousday'] = '17.09.15';
		the_date( 'Y' );
		$this->assertSame( '2015', ob_get_clean() );

		ob_start();
		$GLOBALS['currentday']  = '18.09.15';
		$GLOBALS['previousday'] = '17.09.15';
		the_date( 'Y', 'before ', ' after' );
		$this->assertSame( 'before 2015 after', ob_get_clean() );

		ob_start();
		$GLOBALS['currentday']  = '18.09.15';
		$GLOBALS['previousday'] = '17.09.15';
		the_date( 'Y', 'before ', ' after', false );
		$this->assertSame( '', ob_get_clean() );
	}

	/**
	 * @ticket 47354
	 */
	public function test_the_weekday_date() {
		ob_start();
		the_weekday_date();
		$actual = ob_get_clean();
		$this->assertSame( '', $actual );

		$GLOBALS['post'] = self::factory()->post->create_and_get(
			array(
				'post_date' => '2015-09-16 08:00:00',
			)
		);

		ob_start();
		$GLOBALS['currentday']      = '18.09.15';
		$GLOBALS['previousweekday'] = '17.09.15';
		the_weekday_date();
		$this->assertSame( 'Wednesday', ob_get_clean() );

		ob_start();
		$GLOBALS['currentday']      = '18.09.15';
		$GLOBALS['previousweekday'] = '17.09.15';
		the_weekday_date( 'before ', ' after' );
		$this->assertSame( 'before Wednesday after', ob_get_clean() );
	}
}
