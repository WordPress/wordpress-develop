<?php
/**
 * Test custom ability class that extends WP_Ability.
 *
 * This class overrides do_execute() and check_permissions() directly,
 * allowing registration without execute_callback or permission_callback.
 */
class Tests_Custom_Ability_Class extends WP_Ability {

	/**
	 * Custom execute implementation that multiplies instead of adds.
	 *
	 * @param mixed $input The input data.
	 * @return int The result of multiplying a and b.
	 */
	protected function do_execute( $input = null ) {
		return $input['a'] * $input['b'];
	}

	/**
	 * Custom permission check that always returns true.
	 *
	 * @param mixed $input The input data.
	 * @return bool Always true.
	 */
	public function check_permissions( $input = null ) {
		return true;
	}
}
