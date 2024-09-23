<?php

/**
 * Test avatar related functions
 *
 * @group avatar
 *
 * @covers ::get_avatar
 */
class Functions_GetAvatar_Test extends WP_UnitTestCase {
	public function test_get_avatar() {
		$img = get_avatar( 1 );
		$this->assertSame( preg_match( "|^<img alt='[^']*' src='[^']*' srcset='[^']*' class='[^']*' height='[^']*' width='[^']*' loading='lazy' decoding='async'/>$|", $img ), 1 );
	}

	public function test_get_avatar_size() {
		$size = '100';
		$img  = get_avatar( 1, $size );
		$this->assertSame( preg_match( "|^<img .*height='$size'.*width='$size'|", $img ), 1 );
	}

	public function test_get_avatar_alt() {
		$alt = 'Mr Hyde';
		$img = get_avatar( 1, 96, '', $alt );
		$this->assertSame( preg_match( "|^<img alt='$alt'|", $img ), 1 );
	}

	public function test_get_avatar_class() {
		$class = 'first';
		$img   = get_avatar( 1, 96, '', '', array( 'class' => $class ) );
		$this->assertSame( preg_match( "|^<img .*class='[^']*{$class}[^']*'|", $img ), 1 );
	}

	public function test_get_avatar_default_class() {
		$img = get_avatar( 1, 96, '', '', array( 'force_default' => true ) );
		$this->assertSame( preg_match( "|^<img .*class='[^']*avatar-default[^']*'|", $img ), 1 );
	}

	public function test_get_avatar_force_display() {
		$old = get_option( 'show_avatars' );
		update_option( 'show_avatars', false );

		$this->assertFalse( get_avatar( 1 ) );

		$this->assertNotEmpty( get_avatar( 1, 96, '', '', array( 'force_display' => true ) ) );

		update_option( 'show_avatars', $old );
	}


	protected $fake_img;
	/**
	 * @ticket 21195
	 */
	public function test_pre_get_avatar_filter() {
		$this->fake_img = 'YOU TOO?!';

		add_filter( 'pre_get_avatar', array( $this, 'pre_get_avatar_filter' ), 10, 1 );
		$img = get_avatar( 1 );
		remove_filter( 'pre_get_avatar', array( $this, 'pre_get_avatar_filter' ), 10 );

		$this->assertSame( $img, $this->fake_img );
	}
	public function pre_get_avatar_filter( $img ) {
		return $this->fake_img;
	}

	/**
	 * @ticket 21195
	 */
	public function test_get_avatar_filter() {
		$this->fake_url = 'YA RLY';

		add_filter( 'get_avatar', array( $this, 'get_avatar_filter' ), 10, 1 );
		$img = get_avatar( 1 );
		remove_filter( 'get_avatar', array( $this, 'get_avatar_filter' ), 10 );

		$this->assertSame( $img, $this->fake_url );
	}
	public function get_avatar_filter( $img ) {
		return $this->fake_url;
	}
}
