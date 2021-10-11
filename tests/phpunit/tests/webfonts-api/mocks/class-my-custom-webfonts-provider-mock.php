<?php

require_once ABSPATH . WPINC . '/webfonts-api/providers/class-wp-webfonts-provider.php';

class My_Custom_Webfonts_Provider_Mock extends WP_Webfonts_Provider {
	protected $id = 'my-custom-provider';

	protected $preconnect_urls = array(
		array(
			'href' => 'https://fonts.my-custom-api.com',
		),
	);

	public function get_css() {
		return '';
	}
}
