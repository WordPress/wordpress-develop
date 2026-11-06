<?php
/**
 * Tests for the helper functions of the Social Link block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 */
class Tests_Blocks_SocialLinkHelpers extends WP_UnitTestCase {

	/**
	 * Tests that the list of the services has the name and the icon of each one.
	 *
	 * @ticket 66529
	 *
	 * @covers ::block_core_social_link_services
	 */
	public function test_should_return_the_name_and_the_icon_of_each_service() {
		$services = block_core_social_link_services();

		$this->assertIsArray( $services );

		foreach ( array( 'facebook', 'x', 'mail', 'wordpress', 'youtube', 'share' ) as $slug ) {
			$this->assertArrayHasKey( $slug, $services, "The service '$slug' should be in the list." );
		}

		foreach ( $services as $slug => $service ) {
			$this->assertNotEmpty( $service['name'], "The service '$slug' should have a name." );
			$this->assertStringStartsWith( '<svg', $service['icon'], "The icon of the service '$slug' should be an SVG." );
		}
	}

	/**
	 * Tests that the data of a service is returned.
	 *
	 * @ticket 66529
	 *
	 * @covers ::block_core_social_link_services
	 */
	public function test_should_return_the_data_of_a_service() {
		$services = block_core_social_link_services();

		$this->assertSame( $services['facebook'], block_core_social_link_services( 'facebook' ) );
		$this->assertSame( 'Facebook', block_core_social_link_services( 'facebook' )['name'] );
	}

	/**
	 * Tests that a field of a service is returned.
	 *
	 * @ticket 66529
	 *
	 * @covers ::block_core_social_link_services
	 *
	 * @dataProvider data_fields
	 *
	 * @param string $field Field of the service.
	 */
	public function test_should_return_a_field_of_a_service( $field ) {
		$services = block_core_social_link_services();

		$this->assertSame( $services['facebook'][ $field ], block_core_social_link_services( 'facebook', $field ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_fields() {
		return array(
			'the name' => array( 'name' ),
			'the icon' => array( 'icon' ),
		);
	}

	/**
	 * Tests that the data of the service is returned for a field that is not valid.
	 *
	 * @ticket 66529
	 *
	 * @covers ::block_core_social_link_services
	 *
	 * @dataProvider data_fields_that_are_not_valid
	 *
	 * @param string $field Field of the service.
	 */
	public function test_should_return_the_data_of_the_service_for_a_field_that_is_not_valid( $field ) {
		$services = block_core_social_link_services();

		$this->assertSame( $services['facebook'], block_core_social_link_services( 'facebook', $field ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_fields_that_are_not_valid() {
		return array(
			'an empty field'      => array( '' ),
			'an unknown field'    => array( 'url' ),
			'a field in capitals' => array( 'NAME' ),
		);
	}

	/**
	 * Tests that the list of the services is returned for a service that is not known.
	 *
	 * @ticket 66529
	 *
	 * @covers ::block_core_social_link_services
	 *
	 * @dataProvider data_services_that_are_not_known
	 *
	 * @param string $service Service.
	 * @param string $field   Field of the service.
	 */
	public function test_should_return_the_list_for_a_service_that_is_not_known( $service, $field ) {
		$this->assertSame( block_core_social_link_services(), block_core_social_link_services( $service, $field ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_services_that_are_not_known() {
		return array(
			'no service'                     => array( '', '' ),
			'no service and a field'         => array( '', 'name' ),
			'an unknown service'             => array( 'not-a-service', '' ),
			'an unknown service and a field' => array( 'not-a-service', 'icon' ),
			'a service in capitals'          => array( 'Facebook', '' ),
			'zero'                           => array( '0', '' ),
		);
	}

	/**
	 * Tests that an empty array is returned when the arguments are not strings.
	 *
	 * @ticket 66529
	 *
	 * @covers ::block_core_social_link_services
	 *
	 * @dataProvider data_arguments_that_are_not_strings
	 *
	 * @param mixed $service Service.
	 * @param mixed $field   Field of the service.
	 */
	public function test_should_return_an_empty_array_when_the_arguments_are_not_strings( $service, $field ) {
		$this->assertSame( array(), block_core_social_link_services( $service, $field ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_arguments_that_are_not_strings() {
		return array(
			'an integer service' => array( 5, '' ),
			'a null service'     => array( null, '' ),
			'an array service'   => array( array( 'facebook' ), '' ),
			'an integer field'   => array( 'facebook', 5 ),
			'an array field'     => array( 'facebook', array( 'name' ) ),
			'a null field'       => array( 'facebook', null ),
		);
	}

	/**
	 * Tests that the list of the services is filtered.
	 *
	 * @ticket 66529
	 *
	 * @covers ::block_core_social_link_services
	 */
	public function test_should_filter_the_list_of_the_services() {
		add_filter(
			'block_core_social_link_get_services',
			static function ( $services ) {
				$services['wp_test_service'] = array(
					'name' => 'Test service',
					'icon' => '<svg></svg>',
				);

				$services['facebook']['name'] = 'Changed';

				return $services;
			}
		);

		$this->assertArrayHasKey( 'wp_test_service', block_core_social_link_services(), 'The service should be added to the list.' );
		$this->assertSame( 'Test service', block_core_social_link_services( 'wp_test_service', 'name' ), 'The name of the service that was added should be returned.' );
		$this->assertSame( 'Changed', block_core_social_link_services( 'facebook', 'name' ), 'The change of the service should be returned.' );
	}

	/**
	 * Tests the icon of a service.
	 *
	 * @ticket 66529
	 *
	 * @covers ::block_core_social_link_get_icon
	 */
	public function test_should_return_the_icon_of_a_service() {
		$this->assertSame( block_core_social_link_services( 'facebook', 'icon' ), block_core_social_link_get_icon( 'facebook' ) );
		$this->assertNotSame( block_core_social_link_get_icon( 'share' ), block_core_social_link_get_icon( 'facebook' ), 'The icon should not be the one of the share service.' );
	}

	/**
	 * Tests that the icon of the share service is used when the service is not known.
	 *
	 * @ticket 66529
	 *
	 * @covers ::block_core_social_link_get_icon
	 *
	 * @dataProvider data_values_that_are_not_services
	 *
	 * @param mixed $service Service.
	 */
	public function test_should_return_the_share_icon_for_a_service_that_is_not_known( $service ) {
		$this->assertSame( block_core_social_link_services( 'share', 'icon' ), block_core_social_link_get_icon( $service ) );
	}

	/**
	 * Tests the name of a service.
	 *
	 * @ticket 66529
	 *
	 * @covers ::block_core_social_link_get_name
	 */
	public function test_should_return_the_name_of_a_service() {
		$this->assertSame( 'Facebook', block_core_social_link_get_name( 'facebook' ) );
		$this->assertSame( block_core_social_link_services( 'youtube', 'name' ), block_core_social_link_get_name( 'youtube' ) );
	}

	/**
	 * Tests that the name of the share service is used when the service is not known.
	 *
	 * @ticket 66529
	 *
	 * @covers ::block_core_social_link_get_name
	 *
	 * @dataProvider data_values_that_are_not_services
	 *
	 * @param mixed $service Service.
	 */
	public function test_should_return_the_share_name_for_a_service_that_is_not_known( $service ) {
		$this->assertSame( block_core_social_link_services( 'share', 'name' ), block_core_social_link_get_name( $service ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_values_that_are_not_services() {
		return array(
			'an unknown service'    => array( 'not-a-service' ),
			'an empty string'       => array( '' ),
			'a service in capitals' => array( 'Facebook' ),
			'null'                  => array( null ),
			'an integer'            => array( 5 ),
			'an array'              => array( array( 'facebook' ) ),
		);
	}

	/**
	 * Tests the styles of the colors.
	 *
	 * @ticket 66529
	 *
	 * @covers ::block_core_social_link_get_color_styles
	 *
	 * @dataProvider data_color_styles
	 *
	 * @param array  $context  Context of the block.
	 * @param string $expected Expected styles.
	 */
	public function test_should_return_the_styles_of_the_colors( $context, $expected ) {
		$this->assertSame( $expected, block_core_social_link_get_color_styles( $context ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_color_styles() {
		return array(
			'no colors'          => array( array(), '' ),
			'other values'       => array( array( 'iconColor' => 'red' ), '' ),
			'an icon color'      => array( array( 'iconColorValue' => '#ff0000' ), 'color:#ff0000;' ),
			'a background color' => array( array( 'iconBackgroundColorValue' => '#00ff00' ), 'background-color:#00ff00;' ),
			'both colors'        => array(
				array(
					'iconBackgroundColorValue' => '#00ff00',
					'iconColorValue'           => '#ff0000',
				),
				'color:#ff0000;background-color:#00ff00;',
			),
		);
	}

	/**
	 * Tests the classes of the colors.
	 *
	 * @ticket 66529
	 *
	 * @covers ::block_core_social_link_get_color_classes
	 *
	 * @dataProvider data_color_classes
	 *
	 * @param array  $context  Context of the block.
	 * @param string $expected Expected classes.
	 */
	public function test_should_return_the_classes_of_the_colors( $context, $expected ) {
		$this->assertSame( $expected, block_core_social_link_get_color_classes( $context ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_color_classes() {
		return array(
			'no colors'          => array( array(), ' ' ),
			'other values'       => array( array( 'iconColorValue' => '#ff0000' ), ' ' ),
			'an icon color'      => array( array( 'iconColor' => 'vivid-red' ), ' has-vivid-red-color' ),
			'a background color' => array( array( 'iconBackgroundColor' => 'cyan' ), ' has-cyan-background-color' ),
			'both colors'        => array(
				array(
					'iconBackgroundColor' => 'cyan',
					'iconColor'           => 'vivid-red',
				),
				' has-vivid-red-color has-cyan-background-color',
			),
		);
	}
}
