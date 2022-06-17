<?php
;

$config = new PhpCsFixer\Config();

return $config->setRules( [
	'yoda_style' => [ 'equal' => false, 'identical' => false, 'less_and_greater' => false ],
] );
