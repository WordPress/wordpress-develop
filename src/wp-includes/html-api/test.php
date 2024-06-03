<?php

require __DIR__ . "/class-wp-html-token.php";
require __DIR__ . "/class-wp-html-span.php";
require __DIR__ . "/class-wp-html-text-replacement.php";
require __DIR__ . "/class-wp-html-decoder.php";
require __DIR__ . "/class-wp-html-attribute-token.php";
require __DIR__ . "/class-wp-xml-tag-processor.php";

$processor = new WP_XML_Tag_Processor( '<root></root>   <?xml hey ?> <!-- comment --> <?xml another pi ?> <!-- more comments! -->' );
		
$processor->next_token();
$processor->next_token();
$processor->next_token();
var_dump(
    $processor->get_token_type()
);


// $wxr = file_get_contents(__DIR__ . '/test.wxr');
// $processor = new WP_XML_Tag_Processor( $wxr );
// while( $processor->next_token() ) {
//     echo "\n " . dump_token($processor);
// }

function dump_token(WP_XML_Tag_Processor $p) {
    $result = $p->get_token_type() . ' ';
    switch($p->get_token_type()) {
        case '#tag':
            $result .= '(' . $p->get_token_name() . ')';
            break;
        case '#text':
            $result .= '(' . preg_replace('~\s+~', ' ', $p->get_modifiable_text()) . ')';
            break;
    }
    return $result;
}