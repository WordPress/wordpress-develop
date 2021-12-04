<?php

class Sample_One {
	/**
	 * Constructor.
	 */
	public function __construct() {}

}

// Define global for use in tests.
global $sample_one;
$sample_one = new Sample_One();
