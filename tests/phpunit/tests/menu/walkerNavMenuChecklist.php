<?php

/**
 * Tests for the Walker_Nav_Menu_Checklist class.
 *
 * @group menu
 * @group walker
 *
 * @coversDefaultClass Walker_Nav_Menu_Checklist
 */
class Tests_Menu_Walker_Nav_Menu_Checklist extends WP_UnitTestCase {

	/**
	 * Original value of the $_nav_menu_placeholder global.
	 *
	 * @var mixed
	 */
	private $original_nav_menu_placeholder;

	/**
	 * Original value of the $nav_menu_selected_id global.
	 *
	 * @var mixed
	 */
	private $original_nav_menu_selected_id;

	/**
	 * Original value of the $one_theme_location_no_menus global.
	 *
	 * @var mixed
	 */
	private $original_one_theme_location_no_menus;

	public function set_up() {
		parent::set_up();

		require_once ABSPATH . 'wp-admin/includes/nav-menu.php';

		$this->original_nav_menu_placeholder        = $GLOBALS['_nav_menu_placeholder'] ?? null;
		$this->original_nav_menu_selected_id        = $GLOBALS['nav_menu_selected_id'] ?? null;
		$this->original_one_theme_location_no_menus = $GLOBALS['one_theme_location_no_menus'] ?? null;

		$GLOBALS['_nav_menu_placeholder']       = 0;
		$GLOBALS['nav_menu_selected_id']        = 1;
		$GLOBALS['one_theme_location_no_menus'] = false;
	}

	public function tear_down() {
		$GLOBALS['_nav_menu_placeholder']       = $this->original_nav_menu_placeholder;
		$GLOBALS['nav_menu_selected_id']        = $this->original_nav_menu_selected_id;
		$GLOBALS['one_theme_location_no_menus'] = $this->original_one_theme_location_no_menus;

		parent::tear_down();
	}

	/**
	 * Creates a menu item object for testing.
	 *
	 * @param array $fields Optional. Fields to override.
	 * @return WP_Post Menu item object.
	 */
	private function get_menu_item( array $fields = array() ) {
		return new WP_Post(
			(object) array_merge(
				array(
					'ID'               => 42,
					'post_title'       => 'Sample Page',
					'post_type'        => 'page',
					'post_status'      => 'publish',
					'object_id'        => 42,
					'object'           => 'page',
					'menu_item_parent' => 0,
					'type'             => 'post_type',
					'title'            => 'Sample Page',
					'url'              => 'https://example.org/sample-page/',
					'target'           => '_blank',
					'attr_title'       => 'Sample title attribute',
					'classes'          => array( 'class-one', 'class-two' ),
					'xfn'              => 'friend colleague',
				),
				$fields
			)
		);
	}

	/**
	 * Tests that __construct() sets custom database fields only when provided.
	 *
	 * @ticket 65819
	 *
	 * @covers ::__construct
	 */
	public function test_construct_sets_db_fields() {
		$default_walker = new Walker_Nav_Menu_Checklist();

		$this->assertSame(
			array(
				'parent' => 'menu_item_parent',
				'id'     => 'db_id',
			),
			$default_walker->db_fields,
			'Default db_fields should be inherited from Walker_Nav_Menu.'
		);

		$custom_fields = array(
			'parent' => 'post_parent',
			'id'     => 'ID',
		);
		$custom_walker = new Walker_Nav_Menu_Checklist( $custom_fields );

		$this->assertSame(
			$custom_fields,
			$custom_walker->db_fields,
			'Custom db_fields should override the default fields.'
		);
	}

	/**
	 * Tests the markup that opens and closes a nested list level.
	 *
	 * @ticket 65819
	 *
	 * @covers ::start_lvl
	 * @covers ::end_lvl
	 *
	 * @dataProvider data_depths
	 *
	 * @param int    $depth  Depth of the level.
	 * @param string $indent Expected indentation.
	 */
	public function test_start_lvl_and_end_lvl( $depth, $indent ) {
		$walker = new Walker_Nav_Menu_Checklist();

		$start_lvl = 'before';
		$walker->start_lvl( $start_lvl, $depth );

		$end_lvl = 'before';
		$walker->end_lvl( $end_lvl, $depth );

		$this->assertSame(
			"before\n{$indent}<ul class='children'>\n",
			$start_lvl,
			'start_lvl() should append an indented opening list tag.'
		);
		$this->assertSame(
			"before\n{$indent}</ul>",
			$end_lvl,
			'end_lvl() should append an indented closing list tag.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_depths() {
		return array(
			'top level'    => array( 0, '' ),
			'first level'  => array( 1, "\t" ),
			'second level' => array( 2, "\t\t" ),
		);
	}

	/**
	 * Tests that start_el() outputs the checkbox and hidden inputs for a non-nav_menu_item object.
	 *
	 * @ticket 65819
	 *
	 * @covers ::start_el
	 */
	public function test_start_el_outputs_checkbox_and_hidden_inputs() {
		$walker = new Walker_Nav_Menu_Checklist();
		$output = '';

		$walker->start_el( $output, $this->get_menu_item() );

		$this->assertSame( -1, $GLOBALS['_nav_menu_placeholder'], 'The placeholder should be decremented to -1.' );

		$processor = new WP_HTML_Tag_Processor( $output );

		$this->assertTrue( $processor->next_tag( 'LI' ), 'The output should start with a list item.' );
		$this->assertTrue( $processor->next_tag( 'LABEL' ), 'The list item should contain a label.' );
		$this->assertSame( 'menu-item-title', $processor->get_attribute( 'class' ) );

		$inputs = array();
		while ( $processor->next_tag( 'INPUT' ) ) {
			$inputs[ $processor->get_attribute( 'name' ) ] = array(
				'type'     => $processor->get_attribute( 'type' ),
				'class'    => $processor->get_attribute( 'class' ),
				'value'    => $processor->get_attribute( 'value' ),
				'disabled' => $processor->get_attribute( 'disabled' ),
			);
		}

		$this->assertSame(
			array(
				'menu-item[-1][menu-item-object-id]'  => array(
					'type'     => 'checkbox',
					'class'    => 'menu-item-checkbox',
					'value'    => '42',
					'disabled' => null,
				),
				'menu-item[-1][menu-item-db-id]'      => array(
					'type'     => 'hidden',
					'class'    => 'menu-item-db-id',
					'value'    => '0',
					'disabled' => null,
				),
				'menu-item[-1][menu-item-object]'     => array(
					'type'     => 'hidden',
					'class'    => 'menu-item-object',
					'value'    => 'page',
					'disabled' => null,
				),
				'menu-item[-1][menu-item-parent-id]'  => array(
					'type'     => 'hidden',
					'class'    => 'menu-item-parent-id',
					'value'    => '0',
					'disabled' => null,
				),
				'menu-item[-1][menu-item-type]'       => array(
					'type'     => 'hidden',
					'class'    => 'menu-item-type',
					'value'    => 'post_type',
					'disabled' => null,
				),
				'menu-item[-1][menu-item-title]'      => array(
					'type'     => 'hidden',
					'class'    => 'menu-item-title',
					'value'    => 'Sample Page',
					'disabled' => null,
				),
				'menu-item[-1][menu-item-url]'        => array(
					'type'     => 'hidden',
					'class'    => 'menu-item-url',
					'value'    => 'https://example.org/sample-page/',
					'disabled' => null,
				),
				'menu-item[-1][menu-item-target]'     => array(
					'type'     => 'hidden',
					'class'    => 'menu-item-target',
					'value'    => '_blank',
					'disabled' => null,
				),
				'menu-item[-1][menu-item-attr-title]' => array(
					'type'     => 'hidden',
					'class'    => 'menu-item-attr-title',
					'value'    => 'Sample title attribute',
					'disabled' => null,
				),
				'menu-item[-1][menu-item-classes]'    => array(
					'type'     => 'hidden',
					'class'    => 'menu-item-classes',
					'value'    => 'class-one class-two',
					'disabled' => null,
				),
				'menu-item[-1][menu-item-xfn]'        => array(
					'type'     => 'hidden',
					'class'    => 'menu-item-xfn',
					'value'    => 'friend colleague',
					'disabled' => null,
				),
			),
			$inputs
		);
	}

	/**
	 * Tests that start_el() uses object_id and ID when the item post_type is nav_menu_item.
	 *
	 * @ticket 65819
	 *
	 * @covers ::start_el
	 */
	public function test_start_el_uses_object_id_and_db_id_for_nav_menu_item() {
		$walker = new Walker_Nav_Menu_Checklist();
		$output = '';

		$item = $this->get_menu_item(
			array(
				'ID'        => 99,
				'post_type' => 'nav_menu_item',
				'object_id' => 15,
			)
		);

		$walker->start_el( $output, $item );

		$processor = new WP_HTML_Tag_Processor( $output );

		$this->assertTrue( $processor->next_tag( 'INPUT' ) );
		$this->assertSame( 'menu-item[15][menu-item-object-id]', $processor->get_attribute( 'name' ) );
		$this->assertSame( '15', $processor->get_attribute( 'value' ) );

		$this->assertTrue( $processor->next_tag( 'INPUT' ) );
		$this->assertSame( 'menu-item[15][menu-item-db-id]', $processor->get_attribute( 'name' ) );
		$this->assertSame( '99', $processor->get_attribute( 'value' ) );
	}

	/**
	 * Tests that start_el() adds the add-to-top class and disables the checkbox when no menu is selected.
	 *
	 * @ticket 65819
	 *
	 * @covers ::start_el
	 */
	public function test_start_el_adds_front_or_home_class_and_disabled_attribute() {
		$GLOBALS['nav_menu_selected_id'] = 0;

		$walker = new Walker_Nav_Menu_Checklist();
		$output = '';

		$item                = $this->get_menu_item();
		$item->front_or_home = true;

		$walker->start_el( $output, $item );

		$processor = new WP_HTML_Tag_Processor( $output );

		$this->assertTrue( $processor->next_tag( 'INPUT' ) );
		$this->assertSame( 'menu-item-checkbox add-to-top', $processor->get_attribute( 'class' ) );
		$this->assertSame( 'disabled', $processor->get_attribute( 'disabled' ) );
	}

	/**
	 * Tests the label text resolution and post states in start_el().
	 *
	 * @ticket 65819
	 *
	 * @covers ::start_el
	 */
	public function test_start_el_label_priority_and_page_post_states() {
		$walker = new Walker_Nav_Menu_Checklist();

		// 1. Explicit label property takes precedence and skips _post_states().
		$page_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'draft',
				'post_title'  => 'Draft Page',
			)
		);

		$labeled_item                   = get_post( $page_id );
		$labeled_item->label            = 'Custom Label';
		$labeled_item->object_id        = $page_id;
		$labeled_item->object           = 'page';
		$labeled_item->menu_item_parent = 0;
		$labeled_item->type             = 'post_type';
		$labeled_item->title            = 'Draft Page';
		$labeled_item->url              = 'https://example.org/';
		$labeled_item->target           = '';
		$labeled_item->attr_title       = '';
		$labeled_item->classes          = array();
		$labeled_item->xfn              = '';

		$labeled_output = '';
		$walker->start_el( $labeled_output, $labeled_item );

		$this->assertStringContainsString( 'Custom Label</label>', $labeled_output );
		$this->assertStringNotContainsString( 'post-state', $labeled_output );

		// 2. Without label on a page, the_title filter and _post_states() are applied.
		unset( $labeled_item->label );
		$unlabeled_output = '';
		$walker->start_el( $unlabeled_output, $labeled_item );

		$this->assertStringContainsString( "Draft Page &mdash; <span class='post-state'>Draft</span></label>", $unlabeled_output );

		// 3. Object without post_type or label falls back to the title property.
		$taxonomy_item = (object) array(
			'ID'               => 0,
			'object_id'        => 7,
			'object'           => 'category',
			'menu_item_parent' => 0,
			'type'             => 'taxonomy',
			'title'            => 'Category <Title>',
			'url'              => 'https://example.org/category/news/',
			'target'           => '',
			'attr_title'       => '',
			'classes'          => array(),
			'xfn'              => '',
		);

		$taxonomy_output = '';
		$walker->start_el( $taxonomy_output, $taxonomy_item );

		$this->assertStringContainsString( 'Category &lt;Title&gt;</label>', $taxonomy_output );
	}
}
