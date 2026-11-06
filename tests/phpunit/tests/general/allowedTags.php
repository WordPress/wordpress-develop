<?php

/**
 * Tests for the `allowed_tags()` function.
 *
 * @group general
 * @group formatting
 *
 * @covers ::allowed_tags
 */
class Tests_General_AllowedTags extends WP_UnitTestCase {

	/**
	 * Original allowed tags.
	 *
	 * @var array
	 */
	private $original_allowedtags;

	public function set_up() {
		parent::set_up();
		global $allowedtags;
		$this->original_allowedtags = $allowedtags;
	}

	public function tear_down() {
		global $allowedtags;
		$allowedtags = $this->original_allowedtags;
		parent::tear_down();
	}

	/**
	 * Tests that allowed_tags() returns encoded string of default allowed tags.
	 *
	 * @ticket 65819
	 */
	public function test_allowed_tags_default() {
		$expected = '&lt;a href=&quot;&quot; title=&quot;&quot;&gt; &lt;abbr title=&quot;&quot;&gt; &lt;acronym title=&quot;&quot;&gt; &lt;b&gt; &lt;blockquote cite=&quot;&quot;&gt; &lt;cite&gt; &lt;code&gt; &lt;del datetime=&quot;&quot;&gt; &lt;em&gt; &lt;i&gt; &lt;q cite=&quot;&quot;&gt; &lt;s&gt; &lt;strike&gt; &lt;strong&gt; ';
		$this->assertSame( $expected, allowed_tags() );
	}

	/**
	 * Tests that allowed_tags() formats custom tags and attributes correctly.
	 *
	 * @ticket 65819
	 */
	public function test_allowed_tags_custom() {
		global $allowedtags;

		$allowedtags = array(
			'em'     => array(),
			'strong' => array( 'class' => true ),
			'span'   => array(
				'class' => true,
				'id'    => true,
			),
		);

		$expected = '&lt;em&gt; &lt;strong class=&quot;&quot;&gt; &lt;span class=&quot;&quot; id=&quot;&quot;&gt; ';
		$this->assertSame( $expected, allowed_tags() );
	}

	/**
	 * Tests that allowed_tags() returns an empty string when allowedtags is empty.
	 *
	 * @ticket 65819
	 */
	public function test_allowed_tags_empty() {
		global $allowedtags;

		$allowedtags = array();

		$this->assertSame( '', allowed_tags() );
	}
}
