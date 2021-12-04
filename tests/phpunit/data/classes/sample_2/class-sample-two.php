<?php

class Sample_Two {
	/**
	 * Constructor.
	 */
	public function __construct() {}

}

// Define global for use in tests.
global $sample_two;
$sample_two = new Sample_Two();
