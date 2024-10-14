<?php

/**
 * @group rewrite
 */
class Tests_Rewrite_Tags extends WP_UnitTestCase {
	protected $rewritecode;
	protected $rewritereplace;
	protected $queryreplace;

	public function set_up() {
		global $wp_rewrite;
		parent::set_up();

		$wp_rewrite = new WP_Rewrite();
		$wp_rewrite->init();

		$this->rewritecode    = $wp_rewrite->rewritecode;
		$this->rewritereplace = $wp_rewrite->rewritereplace;
		$this->queryreplace   = $wp_rewrite->queryreplace;
	}

	/**
	 * @dataProvider data_add_rewrite_tag_invalid
	 *
	 * @param string $tag   Rewrite tag.
	 * @param string $regex Regex.
	 *
	 * @covers ::add_rewrite_tag
	 */
	public function test_add_rewrite_tag_invalid( $tag, $regex ) {
		global $wp_rewrite;

		add_rewrite_tag( $tag, $regex );
		$this->assertSameSets( $this->rewritecode, $wp_rewrite->rewritecode );
		$this->assertSameSets( $this->rewritereplace, $wp_rewrite->rewritereplace );
		$this->assertSameSets( $this->queryreplace, $wp_rewrite->queryreplace );
	}

	public function data_add_rewrite_tag_invalid() {
		return array(
			array( 'foo', 'bar' ),
			array( '%', 'bar' ),
			array( '%a', 'bar' ),
			array( 'a%', 'bar' ),
			array( '%%', 'bar' ),
			array( '', 'bar' ),
		);
	}

	/**
	 * @covers ::add_rewrite_tag
	 */
	public function test_add_rewrite_tag_empty_query() {
		global $wp_rewrite;

		$rewritecode   = $wp_rewrite->rewritecode;
		$rewritecode[] = '%foo%';
		add_rewrite_tag( '%foo%', 'bar' );

		$this->assertSameSets( $rewritecode, $wp_rewrite->rewritecode );
		$this->assertSameSets( array_merge( $this->rewritereplace, array( 'bar' ) ), $wp_rewrite->rewritereplace );
		$this->assertSameSets( array_merge( $this->queryreplace, array( 'foo=' ) ), $wp_rewrite->queryreplace );
	}

	/**
	 * @covers ::add_rewrite_tag
	 */
	public function test_add_rewrite_tag_custom_query() {
		global $wp_rewrite;

		$rewritecode   = $wp_rewrite->rewritecode;
		$rewritecode[] = '%foo%';
		add_rewrite_tag( '%foo%', 'bar', 'baz=' );

		$this->assertSameSets( $rewritecode, $wp_rewrite->rewritecode );
		$this->assertSameSets( array_merge( $this->rewritereplace, array( 'bar' ) ), $wp_rewrite->rewritereplace );
		$this->assertSameSets( array_merge( $this->queryreplace, array( 'baz=' ) ), $wp_rewrite->queryreplace );
	}

	/**
	 * @covers ::add_rewrite_tag
	 */
	public function test_add_rewrite_tag_updates_existing() {
		global $wp_rewrite;

		add_rewrite_tag( '%pagename%', 'foo', 'bar=' );
		$this->assertContains( '%pagename%', $wp_rewrite->rewritecode );
		$this->assertContains( 'foo', $wp_rewrite->rewritereplace );
		$this->assertNotContains( '([^/]+?)', $wp_rewrite->rewritereplace );
		$this->assertContains( 'bar=', $wp_rewrite->queryreplace );
		$this->assertNotContains( 'pagename=', $wp_rewrite->queryreplace );
	}

	/**
	 * @covers ::remove_rewrite_tag
	 */
	public function test_remove_rewrite_tag() {
		global $wp_rewrite;

		$rewritecode   = $wp_rewrite->rewritecode;
		$rewritecode[] = '%foo%';
		add_rewrite_tag( '%foo%', 'bar', 'baz=' );
		$this->assertSameSets( $rewritecode, $wp_rewrite->rewritecode );
		$this->assertSameSets( array_merge( $this->rewritereplace, array( 'bar' ) ), $wp_rewrite->rewritereplace );
		$this->assertSameSets( array_merge( $this->queryreplace, array( 'baz=' ) ), $wp_rewrite->queryreplace );

		remove_rewrite_tag( '%foo%' );
		$this->assertSameSets( $this->rewritecode, $wp_rewrite->rewritecode );
		$this->assertSameSets( $this->rewritereplace, $wp_rewrite->rewritereplace );
		$this->assertSameSets( $this->queryreplace, $wp_rewrite->queryreplace );
	}

	/**
	 * @covers ::remove_rewrite_tag
	 */
	public function test_remove_rewrite_tag_internal_tag() {
		global $wp_rewrite;

		$this->assertContains( '%post_id%', $wp_rewrite->rewritecode );
		$this->assertContains( '([0-9]+)', $wp_rewrite->rewritereplace );
		$this->assertContains( 'p=', $wp_rewrite->queryreplace );

		remove_rewrite_tag( '%post_id%' );

		$this->assertNotContains( '%post_id%', $wp_rewrite->rewritecode );
		$this->assertNotContains( '([0-9]+)', $wp_rewrite->rewritereplace );
		$this->assertNotContains( 'p=', $wp_rewrite->queryreplace );
	}

	/**
	 * @covers ::remove_rewrite_tag
	 */
	public function test_remove_rewrite_tag_only_removes_one_array_value() {
		global $wp_rewrite;

		$rewritecode      = $wp_rewrite->rewritecode;
		$rewritecode[]    = '%foo%';
		$rewritereplace   = $wp_rewrite->rewritereplace;
		$rewritereplace[] = '([0-9]{1,2})';
		add_rewrite_tag( '%foo%', '([0-9]{1,2})', 'post_type=foo&name=' );
		$this->assertSameSets( $rewritecode, $wp_rewrite->rewritecode );
		$this->assertSameSets( $rewritereplace, $wp_rewrite->rewritereplace );
		$this->assertSameSets( array_merge( $this->queryreplace, array( 'post_type=foo&name=' ) ), $wp_rewrite->queryreplace );

		remove_rewrite_tag( '%foo%' );
		$this->assertSameSets( $this->rewritecode, $wp_rewrite->rewritecode );
		$this->assertSameSets( $this->rewritereplace, $wp_rewrite->rewritereplace );
		$this->assertSameSets( $this->queryreplace, $wp_rewrite->queryreplace );

		$this->assertNotContains( '%foo%', $wp_rewrite->rewritecode );
		$this->assertContains( '([0-9]{1,2})', $wp_rewrite->rewritereplace );
		$this->assertNotContains( 'post_type=foo&name=', $wp_rewrite->queryreplace );
	}
}
