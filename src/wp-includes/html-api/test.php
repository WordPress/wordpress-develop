<?php

require __DIR__ . "/class-wp-html-token.php";
require __DIR__ . "/class-wp-html-span.php";
require __DIR__ . "/class-wp-html-text-replacement.php";
require __DIR__ . "/class-wp-html-decoder.php";
require __DIR__ . "/class-wp-html-attribute-token.php";
require __DIR__ . "/class-wp-xml-tag-processor.php";


// $processor = new WP_XML_Tag_Processor( 'This is the first text node <![CDATA[ and this is a second text node ]]>.' );
// var_dump($processor->next_token());
$processor = new WP_XML_Tag_Processor( '<?xml version="1.0" encoding="UTF-8" ?><?xml dsversion="1.0" encoding="UTF-8" ?>dadsa' );
var_dump(
    array(
        'next_token' => $processor->next_token(),
        'token type' => $processor->get_token_type(),
        'token name' => $processor->get_token_name(),
        'text' => $processor->get_modifiable_text(),
    )
);
var_dump(
    array(
        'next_token' => $processor->next_token(),
        'token type' => $processor->get_token_type(),
        'token name' => $processor->get_token_name(),
        'text' => $processor->get_modifiable_text(),
    )
);
var_dump(
    array(
        'next_token' => $processor->next_token(),
        'token type' => $processor->get_token_type(),
        'token name' => $processor->get_token_name(),
        'text' => $processor->get_modifiable_text(),
    )
);