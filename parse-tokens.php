<?php

require_once( __DIR__ . '/src/wp-load.php' );

$html = <<<HTML
<!DOCTYPE html>
<title>Just another <img> post</title>
<script>
<!--
console.log( '<script>This is javascript</script>' );
-->
</script>
<style>
body {
	font-size: 14580px;
}
</style>
<![what[ is this?]]>
<title>This is a <img> post</title>
<?php echo __( "Just a test" ); ?>
<div>Not </> all <![CDATA[content]]> is HTML</div>
<p>This <!-- actual comment --> is like <!------>, <!--->, and <!--> and <!-- improperly closed --!>.</p>
<div>An abridged CDATA is <![CDATA[5 >3]]></div>
<textarea>
Writing <html> is <strong>fun</strong> and <em>you</em> can do it.
</textarea>
There is a </%funky_comment> syntax.</body>
HTML;

//$html = '<ul><li><div class=start>I </3 when <img> outflow <br class=end> inflow</div></li></ul>';
//$html = file_get_contents( '~/Downloads/single-page.html' );

if ( isset( $argv[1] ) ) {
	$html = file_get_contents( 'php://stdin' );
}

$p = new WP_HTML_Tag_Processor( $html );

echo "\e[32m{$html}\e[m\n\n";

$text_content = '';
$pre_depth    = 0;
while ( $p->next_token() ) {
	$prefix = $p->is_tag_closer() ? '/' : '';
	$suffix = $p->has_self_closing_flag() ? '/' : '';
	$text = str_replace( "\n", '␤', $p->get_modifiable_text() ?? '' );
	$node_text = html_entity_decode( $p->get_modifiable_text(), ENT_HTML5 | ENT_QUOTES );
	echo "\e[35m{$p->get_token_type()}\e[90m \e[24G\e[36m{$prefix}\e[33m{$p->get_token_name()}\e[35m{$suffix}\e[90m \e[42G\"\e[34m{$text}\e[90m\"\e[m\n";

	if ( 'PRE' === $p->get_token_name() ) {
		$pre_depth += $p->is_tag_closer() ? -1 : 1;
	}

	if ( is_line_breaker( $p->get_token_name() ) && ! $p->is_tag_closer() ) {
		$text_content .= "\n";
	}

	switch ( $p->get_token_name() ) {
		case '#text':
			$text_content .= $pre_depth > 0 ? $node_text : preg_replace( '~[ \r\t\f\n]+~', ' ', $node_text );
	}
}

echo "\n" . $text_content;

function is_line_breaker( $tag_name ) {
	switch ( $tag_name ) {
		case 'BLOCKQUOTE':
		case 'BR':
		case 'DD':
		case 'DIV':
		case 'DL':
		case 'DT':
		case 'H1':
		case 'H2':
		case 'H3':
		case 'H4':
		case 'H5':
		case 'H6':
		case 'HR':
		case 'LI':
		case 'OL':
		case 'P':
		case 'UL':
			return true;
	}

	return false;
}
