<?php

/**
 * @ticket 65526
 *
 * @group functions
 *
 * @covers ::wp_extract_urls
 */
class Tests_Functions_wpExtractUrls extends WP_UnitTestCase {

	/**
	 * @ticket 9064
	 */
	public function test_wp_extract_urls() {
		$original_urls = array(
			'http://woo.com/1,2,3,4,5,6/-1-2-3-4-/woo.html',
			'http://this.com',
			'http://127.0.0.1',
			'http://www111.urwyeoweytwutreyytqytwetowteuiiu.com/?346236346326&2134362574863.437',
			'http://wordpress-core/1,2,3,4,5,6/-1-2-3-4-/woo.html',
			'http://wordpress-core.com:8080/',
			'http://www.website.com:5000',
			'http://wordpress-core/?346236346326&2134362574863.437',
			'http://افغانستا.icom.museum',
			'http://الجزائر.icom.museum',
			'http://österreich.icom.museum',
			'http://বাংলাদেশ.icom.museum',
			'http://беларусь.icom.museum',
			'http://belgië.icom.museum',
			'http://българия.icom.museum',
			'http://تشادر.icom.museum',
			'http://中国.icom.museum',
			// 'http://القمر.icom.museum',         // Comoros http://القمر.icom.museum
			// 'http://κυπρος.icom.museum',        // Cyprus  http://κυπρος.icom.museum
			'http://českárepublika.icom.museum',
			// 'http://مصر.icom.museum',           // Egypt   http://مصر.icom.museum
			'http://ελλάδα.icom.museum',
			'http://magyarország.icom.museum',
			'http://ísland.icom.museum',
			'http://भारत.icom.museum',
			'http://ايران.icom.museum',
			'http://éire.icom.museum',
			'http://איקו״ם.ישראל.museum',
			'http://日本.icom.museum',
			'http://الأردن.icom.museum',
			'http://қазақстан.icom.museum',
			'http://한국.icom.museum',
			'http://кыргызстан.icom.museum',
			'http://ລາວ.icom.museum',
			'http://لبنان.icom.museum',
			'http://македонија.icom.museum',
			// 'http://موريتانيا.icom.museum',     // Mauritania http://موريتانيا.icom.museum
			'http://méxico.icom.museum',
			'http://монголулс.icom.museum',
			// 'http://المغرب.icom.museum',        // Morocco    http://المغرب.icom.museum
			'http://नेपाल.icom.museum',
			// 'http://عمان.icom.museum',          // Oman       http://عمان.icom.museum
			'http://قطر.icom.museum',
			'http://românia.icom.museum',
			'http://россия.иком.museum',
			'http://србијаицрнагора.иком.museum',
			'http://இலங்கை.icom.museum',
			'http://españa.icom.museum',
			'http://ไทย.icom.museum',
			'http://تونس.icom.museum',
			'http://türkiye.icom.museum',
			'http://украина.icom.museum',
			'http://việtnam.icom.museum',
			'ftp://127.0.0.1/',
			'http://www.woo.com/video?v=exvUH2qKLTU',
			'http://taco.com?burrito=enchilada#guac',
			'http://example.org/?post_type=post&p=4',
			'http://example.org/?post_type=post&p=5',
			'http://example.org/?post_type=post&p=6',
			'http://typo-in-query.org/?foo=bar&ampbaz=missing_semicolon',
		);

		$blob = '
			http://woo.com/1,2,3,4,5,6/-1-2-3-4-/woo.html

			http://this.com

			http://127.0.0.1

			http://www111.urwyeoweytwutreyytqytwetowteuiiu.com/?346236346326&amp;2134362574863.437

			http://wordpress-core/1,2,3,4,5,6/-1-2-3-4-/woo.html

			http://wordpress-core.com:8080/

			http://www.website.com:5000

			http://wordpress-core/?346236346326&amp;2134362574863.437

			http://افغانستا.icom.museum
			http://الجزائر.icom.museum
			http://österreich.icom.museum
			http://বাংলাদেশ.icom.museum
			http://беларусь.icom.museum
			http://belgië.icom.museum
			http://българия.icom.museum
			http://تشادر.icom.museum
			http://中国.icom.museum
			http://českárepublika.icom.museum
			http://ελλάδα.icom.museum
			http://magyarország.icom.museum
			http://ísland.icom.museum
			http://भारत.icom.museum
			http://ايران.icom.museum
			http://éire.icom.museum
			http://איקו״ם.ישראל.museum
			http://日本.icom.museum
			http://الأردن.icom.museum
			http://қазақстан.icom.museum
			http://한국.icom.museum
			http://кыргызстан.icom.museum
			http://ລາວ.icom.museum
			http://لبنان.icom.museum
			http://македонија.icom.museum
			http://méxico.icom.museum
			http://монголулс.icom.museum
			http://नेपाल.icom.museum
			http://قطر.icom.museum
			http://românia.icom.museum
			http://россия.иком.museum
			http://србијаицрнагора.иком.museum
			http://இலங்கை.icom.museum
			http://españa.icom.museum
			http://ไทย.icom.museum
			http://تونس.icom.museum
			http://türkiye.icom.museum
			http://украина.icom.museum
			http://việtnam.icom.museum
			ftp://127.0.0.1/
			http://www.woo.com/video?v=exvUH2qKLTU

			http://taco.com?burrito=enchilada#guac

			http://example.org/?post_type=post&amp;p=4
			http://example.org/?post_type=post&#038;p=5
			http://example.org/?post_type=post&p=6

			http://typo-in-query.org/?foo=bar&ampbaz=missing_semicolon
		';

		$urls = wp_extract_urls( $blob );
		$this->assertNotEmpty( $urls );
		$this->assertIsArray( $urls );
		$this->assertCount( count( $original_urls ), $urls );
		$this->assertSame( $original_urls, $urls );

		$exploded = array_values( array_filter( array_map( 'trim', explode( "\n", $blob ) ) ) );
		// wp_extract_urls() calls html_entity_decode().
		$decoded = array_map( 'html_entity_decode', $exploded );

		$this->assertSame( $decoded, $urls );
		$this->assertSame( $original_urls, $decoded );

		$blob = 'Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor
			incididunt ut labore http://woo.com/1,2,3,4,5,6/-1-2-3-4-/woo.html et dolore magna aliqua.
			Ut http://this.com enim ad minim veniam, quis nostrud exercitation 16.06. to 18.06.2014 ullamco http://127.0.0.1
			laboris nisi ut aliquip ex http://www111.urwyeoweytwutreyytqytwetowteuiiu.com/?346236346326&amp;2134362574863.437 ea
			commodo consequat. http://wordpress-core/1,2,3,4,5,6/-1-2-3-4-/woo.html Duis aute irure dolor in reprehenderit in voluptate
			velit esse http://wordpress-core.com:8080/ cillum dolore eu fugiat nulla <A href="http://www.website.com:5000">http://www.website.com:5000</B> pariatur. Excepteur sint occaecat cupidatat non proident,
			sunt in culpa qui officia deserunt mollit http://wordpress-core/?346236346326&amp;2134362574863.437 anim id est laborum.';

		$urls = wp_extract_urls( $blob );
		$this->assertNotEmpty( $urls );
		$this->assertIsArray( $urls );
		$this->assertCount( 8, $urls );
		$this->assertSame( array_slice( $original_urls, 0, 8 ), $urls );

		$blob = 'Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor
			incididunt ut labore <a href="http://woo.com/1,2,3,4,5,6/-1-2-3-4-/woo.html">343462^</a> et dolore magna aliqua.
			Ut <a href="http://this.com">&amp;3640i6p1yi499</a> enim ad minim veniam, quis nostrud exercitation 16.06. to 18.06.2014 ullamco <a href="http://127.0.0.1">localhost</a>
			laboris nisi ut aliquip ex <a href="http://www111.urwyeoweytwutreyytqytwetowteuiiu.com/?346236346326&amp;2134362574863.437">343462^</a> ea
			commodo consequat. <a href="http://wordpress-core/1,2,3,4,5,6/-1-2-3-4-/woo.html">343462^</a> Duis aute irure dolor in reprehenderit in voluptate
			velit esse <a href="http://wordpress-core.com:8080/">-3-4--321-64-4@#!$^$!@^@^</a> cillum dolore eu <A href="http://www.website.com:5000">http://www.website.com:5000</B> fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident,
			sunt in culpa qui officia deserunt mollit <a href="http://wordpress-core/?346236346326&amp;2134362574863.437">)(*&^%$</a> anim id est laborum.';

		$urls = wp_extract_urls( $blob );
		$this->assertNotEmpty( $urls );
		$this->assertIsArray( $urls );
		$this->assertCount( 8, $urls );
		$this->assertSame( array_slice( $original_urls, 0, 8 ), $urls );
	}

	/**
	 * Tests for backward compatibility of `wp_extract_urls` to remove unused semicolons.
	 *
	 * @ticket 30580
	 *
	 * @covers ::wp_extract_urls
	 */
	public function test_wp_extract_urls_remove_semicolon() {
		$expected = array(
			'http://typo.com',
			'http://example.org/?post_type=post&p=8',
		);
		$actual   = wp_extract_urls(
			'
				http://typo.com;
				http://example.org/?post_type=;p;o;s;t;&amp;p=8;
			'
		);

		$this->assertSame( $expected, $actual );
	}
}
