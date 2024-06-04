<?php

require __DIR__ . "/class-wp-html-token.php";
require __DIR__ . "/class-wp-html-span.php";
require __DIR__ . "/class-wp-html-text-replacement.php";
require __DIR__ . "/class-wp-html-decoder.php";
require __DIR__ . "/class-wp-html-attribute-token.php";
require __DIR__ . "/class-wp-xml-decoder.php";
require __DIR__ . "/class-wp-xml-tag-processor.php";
require __DIR__ . "/class-wp-xml-processor.php";


$processor = new WP_XML_Processor(
    '<root><wp:post>The open source publishing  <content> platform of choice for millions of websites <image /> worldwide—from creators </content>and small businesses</wp:post></root>'
);
$processor->next_tag('wp:post');
var_dump($processor->get_inner_text());
$processor->next_tag();
var_dump($processor->get_tag());

// $wxr = file_get_contents(__DIR__ . '/test.wxr');
// $processor = new WP_XML_Processor( $wxr );
// while( $processor->next_tag() ) {
//     echo "\n " . dump_token($processor);
// }

function dump_token(WP_XML_Tag_Processor $p) {
    $result = $p->get_token_type() . ' ';
    switch($p->get_token_type()) {
        case '#tag':
            $result .= '(' . $p->get_token_name() . ')' . ' IN ' . implode( ' > ', $p->get_breadcrumbs() );
            break;
        case '#text':
            $result .= '(' . preg_replace('~\s+~', ' ', $p->get_modifiable_text()) . ')';
            break;
    }
    return $result;
}