<?php
/**
 * Enum fixtures for the absint() tests.
 *
 * Enum syntax requires PHP 8.1, so these are kept out of the test class file,
 * which must remain parseable on the minimum supported PHP version.
 *
 * @see Tests_Functions_Absint
 */

enum Absint_Test_Pure_Enum {
	case Hearts;
}

enum Absint_Test_Backed_Enum: int {
	case Ace = 1;
}
