<?php

class Sample_Seven_Subdir {
	/**
	 * Constructor.
	 */
	public function __construct() {}

}

// Define global for use in tests.
global $sample_seven_subdir;
$sample_seven_subdir = new Sample_Seven_Subdir();
