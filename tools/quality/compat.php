<?php

$compat_path = __DIR__ . '/../../src/wp-includes/compat.php';
require_once $compat_path;

$functions = get_defined_functions();
$tokens    = token_get_all( file_get_contents( $compat_path ) );
$last_i    = count( $tokens ) -  1;
$exit_code = 0;

foreach ( $tokens as $i => $token ) {
        // A function call looks like [ T_STRING function_name, '(' ]
        if ( is_string( $token ) || $i === $last_i || 'T_STRING' !== token_name( $token[0] ) || '(' !== $tokens[ $i + 1 ] ) {
                continue;
        }

        $name = $token[1];
        if ( ! in_array( $name, $functions['internal'], true ) && ! in_array( $name, $functions['user'], true ) ) {
                echo "Possible call to undefined function '{$name}' on line {$token[2]}\n";
				$exit_code = 1;
        }
}

exit( $exit_code );
