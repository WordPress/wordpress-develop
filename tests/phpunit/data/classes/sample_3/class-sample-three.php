<?php

class Sample_Three {
	/**
	 * Constructor.
	 */
	public function __construct() {}

}

// Define global for use in tests.
global $sample_three;
$sample_three = new Sample_Three();
