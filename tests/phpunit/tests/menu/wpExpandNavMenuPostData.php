<?php

/**
 * @group menu
 * @ticket 36590
 */
class Tests_Menu_WpExpandNavMenuPostData extends WP_UnitTestCase {
	public function test_unnested_data_should_expand() {
		require_once ABSPATH . 'wp-admin/includes/nav-menu.php';

		if ( empty( $_POST ) ) {
			$_POST = array();
		}

		$data                   = array();
		$data[0]                = new StdClass();
		$data[0]->name          = 'yesorno';
		$data[0]->value         = 'yes';
		$_POST['nav-menu-data'] = addslashes( json_encode( $data ) );

		_wp_expand_nav_menu_post_data();

		$expected = array(
			'nav-menu-data' => $_POST['nav-menu-data'],
			'yesorno'       => 'yes',
		);

		$this->assertSame( $expected, $_POST );
	}

	public function test_multidimensional_nested_array_should_expand() {
		require_once ABSPATH . 'wp-admin/includes/nav-menu.php';

		if ( empty( $_POST ) ) {
			$_POST = array();
		}

		$data                   = array();
		$data[0]                = new StdClass();
		$data[0]->name          = 'would[1][do][the][trick]';
		$data[0]->value         = 'yes';
		$_POST['nav-menu-data'] = addslashes( json_encode( $data ) );

		_wp_expand_nav_menu_post_data();

		$expected = array(
			'nav-menu-data' => $_POST['nav-menu-data'],
			'would'         => array(
				1 => array(
					'do' => array(
						'the' => array(
							'trick' => 'yes',
						),
					),
				),
			),
		);
		$this->assertSame( $expected, $_POST );
	}

	public function test_multidimensional_nested_array_should_expand_and_merge() {
		require_once ABSPATH . 'wp-admin/includes/nav-menu.php';

		if ( empty( $_POST ) ) {
			$_POST = array();
		}

		$data                   = array();
		$data[0]                = new StdClass();
		$data[0]->name          = 'would[1][do][the][trick]';
		$data[0]->value         = 'yes';
		$data[1]                = new StdClass();
		$data[1]->name          = 'would[2][do][the][trick]';
		$data[1]->value         = 'yes';
		$data[2]                = new StdClass();
		$data[2]->name          = 'would[2][do][the][job]';
		$data[2]->value         = 'yes';
		$_POST['nav-menu-data'] = addslashes( json_encode( $data ) );

		_wp_expand_nav_menu_post_data();

		$expected = array(
			'nav-menu-data' => $_POST['nav-menu-data'],
			'would'         => array(
				1 => array(
					'do' => array(
						'the' => array(
							'trick' => 'yes',
						),
					),
				),
				2 => array(
					'do' => array(
						'the' => array(
							'trick' => 'yes',
							'job'   => 'yes',
						),
					),
				),
			),
		);

		$this->assertSame( $expected, $_POST );
	}

	/**
	 * @ticket 41502
	 * @dataProvider data_form_fields
	 *
	 * @param array $fields   Ordered name/value pairs from serializeArray().
	 * @param array $expected Expected expanded fields.
	 * @param array $initial  Fields already parsed into $_POST.
	 */
	public function test_form_fields_should_expand( $fields, $expected, $initial = array() ) {
		require_once ABSPATH . 'wp-admin/includes/nav-menu.php';

		$data = array();
		foreach ( $fields as $field ) {
			$data[] = array(
				'name'  => $field[0],
				'value' => $field[1],
			);
		}
		$post_data = array(
			'nav-menu-data' => wp_slash( wp_json_encode( $data ) ),
			'_wpnonce'      => 'menu-nonce',
			'unrelated'     => 'keep',
		);
		$_POST     = array_merge( $post_data, $initial );

		_wp_expand_nav_menu_post_data();

		$this->assertSame( array_merge( $post_data, $expected ), $_POST );
	}

	/**
	 * @return array
	 */
	public static function data_form_fields() {
		return array(
			'append fields'                => array(
				array( array( 'field[]', 'A' ), array( 'field[]', 'B' ) ),
				array( 'field' => array( 'A', 'B' ) ),
			),
			'nested append fields'         => array(
				array( array( 'my[field][]', 'A' ), array( 'my[field][]', 'B' ) ),
				array( 'my' => array( 'field' => array( 'A', 'B' ) ) ),
			),
			'hidden input then checkboxes' => array(
				array( array( 'my[field]', '' ), array( 'my[field][]', 'A' ), array( 'my[field][]', 'B' ) ),
				array( 'my' => array( 'field' => array( 'A', 'B' ) ) ),
			),
			'checkboxes then hidden input' => array(
				array( array( 'my[field][]', 'A' ), array( 'my[field][]', 'B' ), array( 'my[field]', '' ) ),
				array( 'my' => array( 'field' => '' ) ),
			),
			'explicit index then append'   => array(
				array( array( 'field[2]', 'A' ), array( 'field[]', 'B' ) ),
				array(
					'field' => array(
						2 => 'A',
						3 => 'B',
					),
				),
			),
			'append then explicit index'   => array(
				array( array( 'field[]', 'A' ), array( 'field[0]', 'B' ) ),
				array( 'field' => array( 'B' ) ),
			),
			'string index then append'     => array(
				array( array( 'field[01]', 'A' ), array( 'field[]', 'B' ) ),
				array(
					'field' => array(
						'01' => 'A',
						0    => 'B',
					),
				),
			),
			'empty intermediate segments'  => array(
				array( array( 'rows[][name]', 'A' ), array( 'rows[][value]', 'B' ) ),
				array( 'rows' => array( array( 'name' => 'A' ), array( 'value' => 'B' ) ) ),
			),
			'multiple empty segments'      => array(
				array( array( 'rows[][]', 'A' ), array( 'rows[][]', 'B' ) ),
				array( 'rows' => array( array( 'A' ), array( 'B' ) ) ),
			),
			'already parsed fields'        => array(
				array( array( 'field[]', 'A' ), array( 'field[]', 'B' ) ),
				array( 'field' => array( 'A', 'B' ) ),
				array( 'field' => array( 'A', 'B' ) ),
			),
			'partially parsed fields'      => array(
				array( array( 'field[]', 'A' ), array( 'field[]', 'B' ) ),
				array( 'field' => array( 'A', 'B' ) ),
				array( 'field' => array( 'A' ) ),
			),
			'repeated scalar fields'       => array(
				array( array( 'field', 'A' ), array( 'field', 'B' ) ),
				array( 'field' => 'B' ),
			),
			'quotes and backslashes'       => array(
				array( array( 'field[]', "A'B\\C" ) ),
				array( 'field' => array( "A\\'B\\\\C" ) ),
			),
		);
	}

	/**
	 * @ticket 41502
	 */
	public function test_complete_json_should_replace_a_truncated_post_prefix() {
		require_once ABSPATH . 'wp-admin/includes/nav-menu.php';

		$data = array(
			array(
				'name'  => 'field[]',
				'value' => 'A',
			),
			array(
				'name'  => 'field[]',
				'value' => 'B',
			),
		);
		// Put a reset after the point where PHP would stop parsing ordinary inputs.
		$input_count = max( 1000, (int) ini_get( 'max_input_vars' ) );
		$expected    = array( 'field' => array( 'C' ) );
		$initial     = array( 'field' => array( 'A', 'B' ) );
		for ( $i = 0; $i < $input_count; $i++ ) {
			$name              = 'padding-' . $i;
			$data[]            = array(
				'name'  => $name,
				'value' => 'value',
			);
			$expected[ $name ] = 'value';
			if ( $i < $input_count - 4 ) {
				$initial[ $name ] = 'value';
			}
		}
		$data[] = array(
			'name'  => 'field',
			'value' => '',
		);
		$data[] = array(
			'name'  => 'field[]',
			'value' => 'C',
		);

		$post_data = array(
			'nav-menu-data' => wp_slash( wp_json_encode( $data ) ),
			'_wpnonce'      => 'menu-nonce',
		);
		$_POST     = array_merge( $post_data, $initial );

		_wp_expand_nav_menu_post_data();

		$this->assertSame( array_merge( $post_data, $expected ), $_POST );
	}
}
