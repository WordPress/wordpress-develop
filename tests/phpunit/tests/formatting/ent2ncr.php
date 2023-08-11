<?php

/**
 * @group formatting
 *
 * @covers ::ent2ncr
 */
class Tests_Formatting_Ent2ncr extends WP_UnitTestCase {
	/**
	 * @dataProvider data_entities
	 */
	public function test_converts_named_entities_to_numeric_character_references( $entity, $ncr ) {
		$entity = '&' . $entity . ';';
		$ncr    = '&#' . $ncr . ';';
		$this->assertSame( $ncr, ent2ncr( $entity ), $entity );
	}

	/**
	 * Get test data from files, one test per line.
	 * Comments start with "###".
	 */
	public function data_entities() {
		$entities      = file( DIR_TESTDATA . '/formatting/entities.txt' );
		$data_provided = array();
		foreach ( $entities as $line ) {
			// Comment.
			$commentpos = strpos( $line, '###' );
			if ( false !== $commentpos ) {
				$line = trim( substr( $line, 0, $commentpos ) );
				if ( ! $line ) {
					continue;
				}
			}
			$data_provided[] = array_map( 'trim', explode( '|', $line ) );
		}
		return $data_provided;
	}
}

