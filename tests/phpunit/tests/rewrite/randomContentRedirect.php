<?php

/**
 * @group rewrite
 * @ticket 64498
 * @covers wp_random_content_redirect
 */
class Tests_Rewrite_RandomContentRedirect extends WP_UnitTestCase {
	protected $random_content_redirect_url;

	public function set_up() {
		parent::set_up();

		add_filter( 'enable_random_content_redirect', '__return_true' );
		add_filter( 'random_content_redirect_url', array( $this, 'filter_random_content_redirect_url' ) );

		$_GET['random'] = '';
	}

	public function test_hook_is_registered() {
		$this->assertSame( 10, has_action( 'template_redirect', 'wp_random_content_redirect' ) );
	}

	public function test_disabled_by_default() {
		remove_filter( 'enable_random_content_redirect', '__return_true' );

		self::factory()->post->create();

		wp_random_content_redirect();
		$this->assertNull( $this->random_content_redirect_url );
	}

	public function test_no_random_parameter_does_not_redirect() {
		unset( $_GET['random'] );

		self::factory()->post->create();

		wp_random_content_redirect();
		$this->assertNull( $this->random_content_redirect_url );
	}

	public function test_redirects_to_a_published_post() {
		$post_ids = self::factory()->post->create_many( 3 );

		wp_random_content_redirect();
		$this->assertContains( $this->random_content_redirect_url, array_map( 'get_permalink', $post_ids ) );
	}

	public function test_post_request_does_not_redirect() {
		$_SERVER['REQUEST_METHOD'] = 'POST';

		self::factory()->post->create();

		wp_random_content_redirect();
		$this->assertNull( $this->random_content_redirect_url );
	}

	public function test_head_request_redirects() {
		$_SERVER['REQUEST_METHOD'] = 'HEAD';

		$post_id = self::factory()->post->create();

		wp_random_content_redirect();
		$this->assertSame( get_permalink( $post_id ), $this->random_content_redirect_url );
	}

	public function test_excludes_password_protected_posts() {
		$public_id = self::factory()->post->create();
		self::factory()->post->create_many( 2, array( 'post_password' => 'secret' ) );

		wp_random_content_redirect();
		$this->assertSame( get_permalink( $public_id ), $this->random_content_redirect_url );
	}

	public function test_excludes_unpublished_posts() {
		$public_id = self::factory()->post->create();
		self::factory()->post->create( array( 'post_status' => 'draft' ) );
		self::factory()->post->create( array( 'post_status' => 'private' ) );

		wp_random_content_redirect();
		$this->assertSame( get_permalink( $public_id ), $this->random_content_redirect_url );
	}

	public function test_random_post_type_parameter() {
		self::factory()->post->create();
		$page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );

		$_GET['random_post_type'] = 'page';

		wp_random_content_redirect();
		$this->assertSame( get_permalink( $page_id ), $this->random_content_redirect_url );
	}

	public function test_invalid_post_type_does_not_redirect() {
		self::factory()->post->create();

		$_GET['random_post_type'] = 'nonexistent_type';

		wp_random_content_redirect();
		$this->assertNull( $this->random_content_redirect_url );
	}

	public function test_non_viewable_post_type_does_not_redirect() {
		register_post_type( 'wptests_hidden', array( 'public' => false ) );

		self::factory()->post->create();
		self::factory()->post->create( array( 'post_type' => 'wptests_hidden' ) );

		$_GET['random_post_type'] = 'wptests_hidden';

		wp_random_content_redirect();
		$this->assertNull( $this->random_content_redirect_url );
	}

	public function test_random_cat_id_parameter() {
		$cat_id    = self::factory()->category->create();
		$in_cat_id = self::factory()->post->create( array( 'post_category' => array( $cat_id ) ) );
		self::factory()->post->create_many( 3 );

		$_GET['random_cat_id'] = (string) $cat_id;

		wp_random_content_redirect();
		$this->assertSame( get_permalink( $in_cat_id ), $this->random_content_redirect_url );
	}

	public function test_nonexistent_category_does_not_redirect() {
		self::factory()->post->create();

		$_GET['random_cat_id'] = '99999';

		wp_random_content_redirect();
		$this->assertNull( $this->random_content_redirect_url );
	}

	/**
	 * @dataProvider data_invalid_category_ids
	 *
	 * @param string $cat_id Invalid category ID value.
	 */
	public function test_invalid_category_id_does_not_redirect( $cat_id ) {
		self::factory()->post->create();

		$_GET['random_cat_id'] = $cat_id;

		wp_random_content_redirect();
		$this->assertNull( $this->random_content_redirect_url );
	}

	public function data_invalid_category_ids() {
		return array(
			'zero'        => array( '0' ),
			'negative'    => array( '-5' ),
			'non-numeric' => array( 'foo' ),
		);
	}

	public function test_redirect_url_is_filterable() {
		self::factory()->post->create();

		add_filter(
			'random_content_redirect_url',
			static function () {
				return home_url( '/custom-target/' );
			},
			9
		);

		wp_random_content_redirect();
		$this->assertSame( home_url( '/custom-target/' ), $this->random_content_redirect_url );
	}

	public function test_no_posts_does_not_redirect() {
		wp_random_content_redirect();
		$this->assertNull( $this->random_content_redirect_url );
	}

	public function test_single_post_redirects_to_it() {
		$post_id = self::factory()->post->create();

		wp_random_content_redirect();
		$this->assertSame( get_permalink( $post_id ), $this->random_content_redirect_url );
	}

	public function filter_random_content_redirect_url( $url ) {
		$this->random_content_redirect_url = $url;
		return false;
	}
}
