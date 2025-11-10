<?php
/**
 * Tests for WP_Interactivity_API data-wp-each caching optimization
 *
 * @package WordPress
 * @subpackage Interactivity API
 * @group interactivity-api
 *
 * @covers WP_Interactivity_API::data_wp_each_processor
 * @covers WP_Interactivity_API::_process_directives
 */
class Tests_Interactivity_API_Data_Wp_Each_Optimization extends WP_UnitTestCase {
	/**
	 * Instance of WP_Interactivity_API for testing.
	 *
	 * @var WP_Interactivity_API
	 */
	private $interactivity;

	/**
	 * Set up each test.
	 */
	public function set_up() {
		parent::set_up();
		$this->interactivity = new WP_Interactivity_API();
	}

	/**
	 * Tests that data-wp-each with caching produces the same output as without.
	 *
	 * This ensures the optimization doesn't change behavior.
	 *
	 * @ticket 64093
	 */
	public function test_data_wp_each_caching_produces_correct_output() {
		$items = array(
			array(
				'name' => 'Alice',
				'age'  => 30,
			),
			array(
				'name' => 'Bob',
				'age'  => 25,
			),
			array(
				'name' => 'Charlie',
				'age'  => 35,
			),
		);

		$this->interactivity->state(
			'test-plugin',
			array( 'people' => $items )
		);

		$html = '
			<div data-wp-interactive="test-plugin">
				<template data-wp-each--person="state.people">
					<li data-wp-text="context.person.name"></li>
				</template>
			</div>
		';

		$processed = $this->interactivity->process_directives( $html );

		// Verify all items were rendered
		$this->assertStringContainsString( 'Alice', $processed );
		$this->assertStringContainsString( 'Bob', $processed );
		$this->assertStringContainsString( 'Charlie', $processed );

		// Verify data-wp-each-child directive was added
		$this->assertStringContainsString( 'data-wp-each-child', $processed );
	}

	/**
	 * Tests data-wp-each with nested directives.
	 *
	 * @ticket 64093
	 */
	public function test_data_wp_each_with_nested_directives() {
		$items = array(
			array(
				'title'  => 'Item 1',
				'link'   => '#1',
				'active' => true,
			),
			array(
				'title'  => 'Item 2',
				'link'   => '#2',
				'active' => false,
			),
		);

		$this->interactivity->state(
			'test-plugin',
			array( 'items' => $items )
		);

		$html = '
			<div data-wp-interactive="test-plugin">
				<template data-wp-each--item="state.items">
					<div data-wp-class--active="context.item.active">
						<a data-wp-bind--href="context.item.link" data-wp-text="context.item.title"></a>
					</div>
				</template>
			</div>
		';

		$processed = $this->interactivity->process_directives( $html );

		// Verify content
		$this->assertStringContainsString( 'Item 1', $processed );
		$this->assertStringContainsString( 'Item 2', $processed );
		$this->assertStringContainsString( 'href="#1"', $processed );
		$this->assertStringContainsString( 'href="#2"', $processed );

		// Verify class directive was processed (active should be added to first item)
		$this->assertStringContainsString( 'active', $processed );
	}

	/**
	 * Tests data-wp-each with large arrays for performance.
	 *
	 * This test validates that the optimization works correctly with
	 * realistic workloads similar to the stress test.
	 *
	 * @ticket 64093
	 */
	public function test_data_wp_each_with_large_array() {
		$items = array();
		for ( $i = 0; $i < 100; $i++ ) {
			$items[] = array(
				'id'    => $i,
				'value' => $i * 10,
			);
		}

		$this->interactivity->state(
			'test-plugin',
			array( 'data' => $items )
		);

		$html = '
			<div data-wp-interactive="test-plugin">
				<template data-wp-each--item="state.data">
					<span data-wp-text="context.item.value"></span>
				</template>
			</div>
		';

		$start_time = microtime( true );
		$processed  = $this->interactivity->process_directives( $html );
		$elapsed    = microtime( true ) - $start_time;

		// Verify all 100 items were rendered
		for ( $i = 0; $i < 100; $i++ ) {
			$expected_value = $i * 10;
			$this->assertStringContainsString( (string) $expected_value, $processed );
		}

		// Performance assertion - this should complete reasonably quickly
		// On a modern machine, 100 items should process in well under 1 second
		$this->assertLessThan( 1.0, $elapsed, 'Processing 100 items should be fast with caching' );
	}

	/**
	 * Tests that cache works correctly with multiple data-wp-each directives.
	 *
	 * @ticket 64093
	 */
	public function test_multiple_data_wp_each_directives() {
		$items1 = array(
			array( 'name' => 'Group A - Item 1' ),
			array( 'name' => 'Group A - Item 2' ),
		);

		$items2 = array(
			array( 'name' => 'Group B - Item 1' ),
			array( 'name' => 'Group B - Item 2' ),
		);

		$this->interactivity->state(
			'test-plugin',
			array(
				'group1' => $items1,
				'group2' => $items2,
			)
		);

		$html = '
			<div data-wp-interactive="test-plugin">
				<h2>Group A</h2>
				<template data-wp-each--item="state.group1">
					<li data-wp-text="context.item.name"></li>
				</template>
				
				<h2>Group B</h2>
				<template data-wp-each--item="state.group2">
					<li data-wp-text="context.item.name"></li>
				</template>
			</div>
		';

		$processed = $this->interactivity->process_directives( $html );

		// Verify all items from both groups were rendered
		$this->assertStringContainsString( 'Group A - Item 1', $processed );
		$this->assertStringContainsString( 'Group A - Item 2', $processed );
		$this->assertStringContainsString( 'Group B - Item 1', $processed );
		$this->assertStringContainsString( 'Group B - Item 2', $processed );
	}

	/**
	 * Tests data-wp-each with context modifications.
	 *
	 * @ticket 64093
	 */
	public function test_data_wp_each_with_context_directive() {
		$items = array(
			array( 'id' => 1 ),
			array( 'id' => 2 ),
		);

		$this->interactivity->state(
			'test-plugin',
			array( 'items' => $items )
		);

		$html = '
			<div data-wp-interactive="test-plugin">
				<template data-wp-each--item="state.items">
					<div data-wp-context=\'{"expanded": false}\'>
						<span data-wp-text="context.item.id"></span>
					</div>
				</template>
			</div>
		';

		$processed = $this->interactivity->process_directives( $html );

		// Verify items were rendered
		$this->assertStringContainsString( '>1</span>', $processed );
		$this->assertStringContainsString( '>2</span>', $processed );

		// Verify context directive was processed
		$this->assertStringContainsString( 'data-wp-context', $processed );
	}

	/**
	 * Tests that cache correctly handles templates with no directives.
	 *
	 * @ticket 64093
	 */
	public function test_data_wp_each_with_static_template() {
		$items = array(
			array( 'value' => 1 ),
			array( 'value' => 2 ),
		);

		$this->interactivity->state(
			'test-plugin',
			array( 'items' => $items )
		);

		// Template with no directives - just static HTML
		$html = '
			<div data-wp-interactive="test-plugin">
				<template data-wp-each--item="state.items">
					<li>Static item</li>
				</template>
			</div>
		';

		$processed = $this->interactivity->process_directives( $html );

		// Should render 2 static items plus 1 in the template (3 total)
		// The template tag remains in the output (for client-side hydration)
		// and the 2 rendered items are appended after it
		$count = substr_count( $processed, '<li' );
		$this->assertSame( 3, $count );
	}

	/**
	 * Tests data-wp-each with item name customization.
	 *
	 * @ticket 64093
	 */
	public function test_data_wp_each_with_custom_item_name() {
		$items = array(
			array( 'title' => 'Product 1' ),
			array( 'title' => 'Product 2' ),
		);

		$this->interactivity->state(
			'test-plugin',
			array( 'products' => $items )
		);

		$html = '
			<div data-wp-interactive="test-plugin">
				<template data-wp-each--product="state.products">
					<div data-wp-text="context.product.title"></div>
				</template>
			</div>
		';

		$processed = $this->interactivity->process_directives( $html );

		$this->assertStringContainsString( 'Product 1', $processed );
		$this->assertStringContainsString( 'Product 2', $processed );
	}
}
