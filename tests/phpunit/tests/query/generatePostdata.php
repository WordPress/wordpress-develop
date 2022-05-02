<?php

/**
 * @group query
 * @covers ::generate_postdata
 */
class Tests_Query_GeneratePostdata extends WP_UnitTestCase {

	/**
	 * @ticket 42814
	 */
	public function test_setup_by_id() {
		$p    = self::factory()->post->create_and_get();
		$data = generate_postdata( $p->ID );
		$this->assertSame( $p->ID, $data['id'] );
	}

	/**
	 * @ticket 42814
	 */
	public function test_setup_by_fake_post() {
		$fake     = new stdClass;
		$fake->ID = 98765;
		$data     = generate_postdata( $fake->ID );

		// Fails because there's no post with this ID.
		$this->assertFalse( $data );
	}

	/**
	 * @ticket 42814
	 */
	public function test_setup_by_postish_object() {
		$p = self::factory()->post->create();

		$post     = new stdClass();
		$post->ID = $p;
		$data     = generate_postdata( $p );

		$this->assertSame( $p, $data['id'] );
	}

	/**
	 * @ticket 42814
	 */
	public function test_authordata() {
		$u    = self::factory()->user->create_and_get();
		$p    = self::factory()->post->create_and_get(
			array(
				'post_author' => $u->ID,
			)
		);
		$data = generate_postdata( $p );

		$this->assertNotEmpty( $data['authordata'] );
		$this->assertEquals( $u, $data['authordata'] );
	}

	/**
	 * @ticket 42814
	 */
	public function test_currentday() {
		$p    = self::factory()->post->create_and_get(
			array(
				'post_date' => '1980-09-09 06:30:00',
			)
		);
		$data = generate_postdata( $p );

		$this->assertSame( '09.09.80', $data['currentday'] );
	}

	public function test_currentmonth() {
		$p    = self::factory()->post->create_and_get(
			array(
				'post_date' => '1980-09-09 06:30:00',
			)
		);
		$data = generate_postdata( $p );

		$this->assertSame( '09', $data['currentmonth'] );
	}

	/**
	 * @ticket 42814
	 */
	public function test_single_page() {
		$post = self::factory()->post->create_and_get(
			array(
				'post_content' => 'Page 0',
			)
		);
		$data = generate_postdata( $post );

		$this->assertSame( 0, $data['multipage'] );
		$this->assertSame( 1, $data['numpages'] );
		$this->assertSame( array( 'Page 0' ), $data['pages'] );
	}

	/**
	 * @ticket 42814
	 */
	public function test_multi_page() {
		$post = self::factory()->post->create_and_get(
			array(
				'post_content' => 'Page 0<!--nextpage-->Page 1<!--nextpage-->Page 2<!--nextpage-->Page 3',
			)
		);
		$data = generate_postdata( $post );

		$this->assertSame( 1, $data['multipage'] );
		$this->assertSame( 4, $data['numpages'] );
		$this->assertSame( array( 'Page 0', 'Page 1', 'Page 2', 'Page 3' ), $data['pages'] );
	}

	/**
	 * @ticket 42814
	 */
	public function test_nextpage_at_start_of_content() {
		$post = self::factory()->post->create_and_get(
			array(
				'post_content' => '<!--nextpage-->Page 1<!--nextpage-->Page 2<!--nextpage-->Page 3',
			)
		);
		$data = generate_postdata( $post );

		$this->assertSame( 1, $data['multipage'] );
		$this->assertSame( 3, $data['numpages'] );
		$this->assertSame( array( 'Page 1', 'Page 2', 'Page 3' ), $data['pages'] );
	}

	/**
	 * @ticket 42814
	 */
	public function test_trim_nextpage_linebreaks() {
		$post = self::factory()->post->create_and_get(
			array(
				'post_content' => "Page 0\n<!--nextpage-->\nPage 1\nhas a line break\n<!--nextpage-->Page 2<!--nextpage-->\n\nPage 3",
			)
		);
		$data = generate_postdata( $post );

		$this->assertSame( array( 'Page 0', "Page 1\nhas a line break", 'Page 2', "\nPage 3" ), $data['pages'] );
	}
}
