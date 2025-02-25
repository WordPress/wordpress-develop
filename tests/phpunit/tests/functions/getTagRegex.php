<?php
class Tests_functions_getTagRegex extends WP_UnitTestCase {

	/**
	 * @ticket 59791
	 *
	 * @dataProvider data_get_tag_regex
	 */
	public function test_get_tag_regex_empty( $tag, $expected ) {
		$this->assertEquals( $expected, get_tag_regex( $tag ) );
	}

	/**
	 * @ticket 59791
	 */
	public function data_get_tag_regex() {

		return array(
			array( '', '' ),
			array( 'a', '<a[^<]*(?:>[\s\S]*<\/a>|\s*\/>)' ),
			array( 'video', '<video[^<]*(?:>[\s\S]*<\/video>|\s*\/>)' ),
			array( '<a>', '<a[^<]*(?:>[\s\S]*<\/a>|\s*\/>)' ),
			array( ' a video', '<avideo[^<]*(?:>[\s\S]*<\/avideo>|\s*\/>)' ),
			array( 'img', '<img[^<]*(?:>[\s\S]*<\/img>|\s*\/>)' ),
			array( 'br', '<br[^<]*(?:>[\s\S]*<\/br>|\s*\/>)' ),
			array( 'script', '<script[^<]*(?:>[\s\S]*<\/script>|\s*\/>)' ),
			array( 'div', '<div[^<]*(?:>[\s\S]*<\/div>|\s*\/>)' ),
			array( 'span', '<span[^<]*(?:>[\s\S]*<\/span>|\s*\/>)' ),
			array( 'table', '<table[^<]*(?:>[\s\S]*<\/table>|\s*\/>)' ),
			array( 'tr', '<tr[^<]*(?:>[\s\S]*<\/tr>|\s*\/>)' ),
			array( 'td', '<td[^<]*(?:>[\s\S]*<\/td>|\s*\/>)' ),
			array( 'th', '<th[^<]*(?:>[\s\S]*<\/th>|\s*\/>)' ),
			array( 'form', '<form[^<]*(?:>[\s\S]*<\/form>|\s*\/>)' ),
			array( 'input', '<input[^<]*(?:>[\s\S]*<\/input>|\s*\/>)' ),
			array( 'button', '<button[^<]*(?:>[\s\S]*<\/button>|\s*\/>)' ),
			array( 'label', '<label[^<]*(?:>[\s\S]*<\/label>|\s*\/>)' ),
			array( 'select', '<select[^<]*(?:>[\s\S]*<\/select>|\s*\/>)' ),
			array( 'option', '<option[^<]*(?:>[\s\S]*<\/option>|\s*\/>)' ),
			array( 'textarea', '<textarea[^<]*(?:>[\s\S]*<\/textarea>|\s*\/>)' ),
			array( 'ul', '<ul[^<]*(?:>[\s\S]*<\/ul>|\s*\/>)' ),
			array( 'ol', '<ol[^<]*(?:>[\s\S]*<\/ol>|\s*\/>)' ),
			array( 'li', '<li[^<]*(?:>[\s\S]*<\/li>|\s*\/>)' ),
			array( 'header', '<header[^<]*(?:>[\s\S]*<\/header>|\s*\/>)' ),
			array( 'footer', '<footer[^<]*(?:>[\s\S]*<\/footer>|\s*\/>)' ),
			array( 'section', '<section[^<]*(?:>[\s\S]*<\/section>|\s*\/>)' ),
			array( 'article', '<article[^<]*(?:>[\s\S]*<\/article>|\s*\/>)' ),
			array( 'nav', '<nav[^<]*(?:>[\s\S]*<\/nav>|\s*\/>)' ),
			array( 'aside', '<aside[^<]*(?:>[\s\S]*<\/aside>|\s*\/>)' ),
			array( 'd1v', '<d1v[^<]*(?:>[\s\S]*<\/d1v>|\s*\/>)' ),
			array( '_custom', '<_custom[^<]*(?:>[\s\S]*<\/_custom>|\s*\/>)' ),
		);
	}
}
