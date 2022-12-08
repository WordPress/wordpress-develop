<?php

class Sample_Eight_Subdir {
	/**
	 * Constructor.
	 */
	public function __construct() {}

}

// Define global for use in tests.
global $sample_eight_subdir;
$sample_eight_subdir = new Sample_Eight_Subdir();
