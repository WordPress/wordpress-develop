<?php

class Sample_Four_Subdir {
	/**
	 * Constructor.
	 */
	public function __construct() {}

}

// Define global for use in tests.
global $sample_four_subdir;
$sample_four_subdir = new Sample_Four_Subdir();
