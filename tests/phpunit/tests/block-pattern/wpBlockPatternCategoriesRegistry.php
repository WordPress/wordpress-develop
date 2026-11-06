<?php
/**
 * Unit tests for WP_Block_Pattern_Categories_Registry.
 *
 * @package WordPress
 * @subpackage Blocks
 * @since 6.9.0
 *
 * @group block-patterns
 * @covers WP_Block_Pattern_Categories_Registry
 */

class Tests_Block_Pattern_WPBlockPatternCategoriesRegistry extends WP_UnitTestCase {

	/**
	 * Registry used by the tests, so the categories of core are not changed.
	 *
	 * @var WP_Block_Pattern_Categories_Registry
	 */
	private $registry;

	public function set_up() {
		parent::set_up();

		$this->registry = new WP_Block_Pattern_Categories_Registry();
	}

	/**
	 * @ticket 63957
	 */
	public function test_is_registered_with_null_category_name() {
		$registry = WP_Block_Pattern_Categories_Registry::get_instance();
		$this->assertFalse( $registry->is_registered( null ) );
	}

	/**
	 * Tests that a category is registered with its name and properties.
	 *
	 * @ticket 66504
	 */
	public function test_register_should_add_the_category_with_its_name_and_properties() {
		$this->assertTrue( $this->registry->register( 'banners', array( 'label' => 'Banners' ) ) );

		$this->assertSame(
			array(
				'name'  => 'banners',
				'label' => 'Banners',
			),
			$this->registry->get_registered( 'banners' )
		);
	}

	/**
	 * Tests that registering a category again replaces the previous one.
	 *
	 * @ticket 66504
	 */
	public function test_register_should_replace_a_category_with_the_same_name() {
		$this->registry->register( 'banners', array( 'label' => 'Banners' ) );
		$this->registry->register( 'banners', array( 'label' => 'New banners' ) );

		$this->assertCount( 1, $this->registry->get_all_registered(), 'There should be one category.' );
		$this->assertSame( 'New banners', $this->registry->get_registered( 'banners' )['label'], 'The label of the second registration should be used.' );
	}

	/**
	 * Tests that a category can be registered without properties.
	 *
	 * @ticket 66504
	 */
	public function test_register_should_accept_empty_properties() {
		$this->assertTrue( $this->registry->register( 'plain', array() ) );

		$this->assertSame( array( 'name' => 'plain' ), $this->registry->get_registered( 'plain' ) );
	}

	/**
	 * Tests that a name that is not a string is not accepted.
	 *
	 * @ticket 66504
	 *
	 * @dataProvider data_names_that_are_not_strings
	 *
	 * @param mixed $name Name that is not a string.
	 */
	public function test_register_should_not_accept_a_name_that_is_not_a_string( $name ) {
		$this->setExpectedIncorrectUsage( 'WP_Block_Pattern_Categories_Registry::register' );

		$this->assertFalse( $this->registry->register( $name, array( 'label' => 'Label' ) ) );
		$this->assertSame( array(), $this->registry->get_all_registered(), 'No category should be registered.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_names_that_are_not_strings() {
		return array(
			'null'    => array( null ),
			'integer' => array( 5 ),
			'array'   => array( array( 'banners' ) ),
			'boolean' => array( true ),
		);
	}

	/**
	 * Tests that a category is removed.
	 *
	 * @ticket 66504
	 */
	public function test_unregister_should_remove_the_category() {
		$this->registry->register( 'banners', array( 'label' => 'Banners' ) );
		$this->registry->register( 'text', array( 'label' => 'Text' ) );

		$this->assertTrue( $this->registry->unregister( 'banners' ) );

		$this->assertFalse( $this->registry->is_registered( 'banners' ), 'The category should be removed.' );
		$this->assertTrue( $this->registry->is_registered( 'text' ), 'The other category should be kept.' );
	}

	/**
	 * Tests that a category that is not registered cannot be removed.
	 *
	 * @ticket 66504
	 */
	public function test_unregister_should_fail_for_a_category_that_is_not_registered() {
		$this->setExpectedIncorrectUsage( 'WP_Block_Pattern_Categories_Registry::unregister' );

		$this->assertFalse( $this->registry->unregister( 'missing' ) );
	}

	/**
	 * Tests that a category that was registered outside of the `init` action is also removed from the list of those.
	 *
	 * @ticket 66504
	 */
	public function test_unregister_should_remove_the_category_registered_outside_init() {
		add_action(
			'wp_tests_late_registration',
			function () {
				$this->registry->register( 'late', array( 'label' => 'Late' ) );
			}
		);
		do_action( 'wp_tests_late_registration' );

		$this->assertCount( 1, $this->registry->get_all_registered( true ), 'The category should be registered outside init.' );

		$this->registry->unregister( 'late' );

		$this->assertSame( array(), $this->registry->get_all_registered( true ), 'The category should be removed from the list.' );
	}

	/**
	 * Tests that a category can be registered again after it is removed.
	 *
	 * @ticket 66504
	 */
	public function test_register_should_add_a_category_again_after_it_is_removed() {
		$this->registry->register( 'banners', array( 'label' => 'Banners' ) );
		$this->registry->unregister( 'banners' );

		$this->assertTrue( $this->registry->register( 'banners', array( 'label' => 'Again' ) ) );
		$this->assertSame( 'Again', $this->registry->get_registered( 'banners' )['label'] );
	}

	/**
	 * Tests that null is returned for a category that is not registered.
	 *
	 * @ticket 66504
	 */
	public function test_get_registered_should_return_null_for_a_category_that_is_not_registered() {
		$this->registry->register( 'banners', array( 'label' => 'Banners' ) );

		$this->assertNull( $this->registry->get_registered( 'missing' ) );
	}

	/**
	 * Tests that the requested category is returned among several.
	 *
	 * @ticket 66504
	 */
	public function test_get_registered_should_return_the_requested_category() {
		$this->registry->register( 'banners', array( 'label' => 'Banners' ) );
		$this->registry->register( 'text', array( 'label' => 'Text' ) );

		$this->assertSame( 'Text', $this->registry->get_registered( 'text' )['label'] );
	}

	/**
	 * Tests that no category is returned when none is registered.
	 *
	 * @ticket 66504
	 */
	public function test_get_all_registered_should_return_an_empty_array_when_none_is_registered() {
		$this->assertSame( array(), $this->registry->get_all_registered() );
		$this->assertSame( array(), $this->registry->get_all_registered( true ) );
	}

	/**
	 * Tests that the categories are returned as a list, in the order they are registered.
	 *
	 * @ticket 66504
	 */
	public function test_get_all_registered_should_return_a_list_in_the_order_of_registration() {
		$this->registry->register( 'text', array( 'label' => 'Text' ) );
		$this->registry->register( 'banners', array( 'label' => 'Banners' ) );
		$this->registry->register( 'featured', array( 'label' => 'Featured' ) );

		$all = $this->registry->get_all_registered();

		$this->assertSame( array( 0, 1, 2 ), array_keys( $all ), 'The result should be a list without the names as keys.' );
		$this->assertSame( array( 'text', 'banners', 'featured' ), wp_list_pluck( $all, 'name' ), 'The categories should be in the order of registration.' );
	}

	/**
	 * Tests that only the categories registered outside of the `init` action are returned when it is requested.
	 *
	 * @ticket 66504
	 */
	public function test_get_all_registered_should_return_only_the_categories_registered_outside_init() {
		// The callbacks of core for init are not needed, and would run again.
		remove_all_actions( 'init' );

		add_action(
			'init',
			function () {
				$this->registry->register( 'on-init', array( 'label' => 'On init' ) );
			}
		);
		add_action(
			'wp_tests_first_late_action',
			function () {
				$this->registry->register( 'on-first-late-action', array( 'label' => 'On the first late action' ) );
			}
		);
		add_action(
			'wp_tests_second_late_action',
			function () {
				$this->registry->register( 'on-second-late-action', array( 'label' => 'On the second late action' ) );
			}
		);

		do_action( 'init' );
		do_action( 'wp_tests_first_late_action' );
		do_action( 'wp_tests_second_late_action' );
		$this->registry->register( 'no-action', array( 'label' => 'No action' ) );

		$this->assertSame(
			array( 'on-init', 'on-first-late-action', 'on-second-late-action', 'no-action' ),
			wp_list_pluck( $this->registry->get_all_registered(), 'name' ),
			'All the categories should be returned by default.'
		);
		$this->assertSame(
			array( 'on-first-late-action', 'on-second-late-action' ),
			wp_list_pluck( $this->registry->get_all_registered( true ), 'name' ),
			'Only the categories registered inside an action other than init should be returned.'
		);
	}

	/**
	 * Tests that the registered categories are checked.
	 *
	 * @ticket 66504
	 */
	public function test_is_registered_should_tell_whether_the_category_is_registered() {
		$this->registry->register( 'banners', array( 'label' => 'Banners' ) );

		$this->assertTrue( $this->registry->is_registered( 'banners' ), 'The registered category should be found.' );
		$this->assertFalse( $this->registry->is_registered( 'missing' ), 'A category that is not registered should not be found.' );
		$this->assertFalse( $this->registry->is_registered( 'BANNERS' ), 'The name should be case sensitive.' );
	}

	/**
	 * Tests that the instance is always the same.
	 *
	 * @ticket 66504
	 */
	public function test_get_instance_should_always_return_the_same_instance() {
		$instance = WP_Block_Pattern_Categories_Registry::get_instance();

		$this->assertInstanceOf( WP_Block_Pattern_Categories_Registry::class, $instance );
		$this->assertSame( $instance, WP_Block_Pattern_Categories_Registry::get_instance() );
	}
}
