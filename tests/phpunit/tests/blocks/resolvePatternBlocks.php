<?php
/**
 * Tests for the resolve_pattern_blocks function.
 *
 * @package WordPress
 * @subpackage Blocks
 * @since 5.5.0
 *
 * @group blocks
 * @covers resolve_pattern_blocks
 */
class Tests_Blocks_ResolvePatternBlocks extends WP_UnitTestCase {

	public function test_resolve_pattern_blocks() {
		$block = array (
			0 =>
				array (
					'blockName' => 'core/query',
					'attrs' =>
						array (
							'query' =>
								array (
									'perPage' => 3,
									'pages' => 0,
									'offset' => 0,
									'postType' => 'post',
									'order' => 'desc',
									'orderBy' => 'date',
									'author' => '',
									'search' => '',
									'exclude' =>
										array (
										),
									'sticky' => '',
									'inherit' => false,
								),
						),
					'innerBlocks' =>
						array (
							0 =>
								array (
									'blockName' => 'core/post-template',
									'attrs' =>
										array (
										),
									'innerBlocks' =>
										array (
											0 =>
												array (
													'blockName' => 'core/post-title',
													'attrs' =>
														array (
															'isLink' => true,
														),
													'innerBlocks' =>
														array (
														),
													'innerHTML' => '',
													'innerContent' =>
														array (
														),
												),
											1 =>
												array (
													'blockName' => 'core/post-featured-image',
													'attrs' =>
														array (
															'isLink' => true,
															'align' => 'wide',
														),
													'innerBlocks' =>
														array (
														),
													'innerHTML' => '',
													'innerContent' =>
														array (
														),
												),
											2 =>
												array (
													'blockName' => 'core/post-excerpt',
													'attrs' =>
														array (
														),
													'innerBlocks' =>
														array (
														),
													'innerHTML' => '',
													'innerContent' =>
														array (
														),
												),
											3 =>
												array (
													'blockName' => 'core/separator',
													'attrs' =>
														array (
														),
													'innerBlocks' =>
														array (
														),
													'innerHTML' => '
					<hr class="wp-block-separator"/>
					',
													'innerContent' =>
														array (
															0 => '
					<hr class="wp-block-separator"/>
					',
														),
												),
											4 =>
												array (
													'blockName' => 'core/post-date',
													'attrs' =>
														array (
														),
													'innerBlocks' =>
														array (
														),
													'innerHTML' => '',
													'innerContent' =>
														array (
														),
												),
										),
									'innerHTML' => '





					',
									'innerContent' =>
										array (
											0 => '
					',
											1 => NULL,
											2 => '
					',
											3 => NULL,
											4 => '
					',
											5 => NULL,
											6 => '
					',
											7 => NULL,
											8 => '
					',
											9 => NULL,
											10 => '
					',
										),
								),
						),
					'innerHTML' => '
					<div class="wp-block-query">

					</div>
					',
					'innerContent' =>
						array (
							0 => '
					<div class="wp-block-query">
					',
							1 => NULL,
							2 => '
					</div>
					',
						),
				),
		);
	}
}
