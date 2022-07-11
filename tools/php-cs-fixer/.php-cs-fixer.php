<?php

$finder = \PhpCsFixer\Finder::create()
	->exclude( __DIR__ . '/../../src/blocks' )
	->in( __DIR__ . '/../../src' )
	->in( __DIR__ . '/../../tests' );


$config = new PhpCsFixer\Config();
return $config
	->setRules( [
		'yoda_style' => [ 'equal' => false, 'identical' => false, 'less_and_greater' => false ],
	] )
	->setFinder( $finder );
