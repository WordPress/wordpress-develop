<?php

require __DIR__ . "/class-wp-html-token.php";
require __DIR__ . "/class-wp-html-span.php";
require __DIR__ . "/class-wp-html-text-replacement.php";
require __DIR__ . "/class-wp-html-decoder.php";
require __DIR__ . "/class-wp-html-attribute-token.php";
require __DIR__ . "/class-wp-xml-tag-processor.php";


$processor = new WP_XML_Tag_Processor( '<div class="a">Test</div>' );

$processor->next_tag( array( 'tag_closers' => 'visit' ) );

//var_dump($processor);

//
