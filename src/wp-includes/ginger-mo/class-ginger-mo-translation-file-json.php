<?php
/**
 * Class Ginger_MO_Translation_File_JSON.
 *
 * @package WordPress
 */

/**
 * Class Ginger_MO_Translation_File_JSON.
 */
class Ginger_MO_Translation_File_JSON extends Ginger_MO_Translation_File {
	/**
	 * Parses the file.
	 *
	 * @SuppressWarnings(PHPMD.NPathComplexity)
	 *
	 * @return void
	 */
	protected function parse_file() {
		$this->parsed = true;

		$data = file_get_contents( $this->file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		if ( false === $data ) {
			$this->error = true;
			return;
		}

		$data = json_decode( $data, true );

		if ( false === $data || ! is_array( $data ) ) {
			$this->error = json_last_error_msg();
			return;
		}

		if ( ! isset( $data['domain'] ) || ! isset( $data['locale_data'][ $data['domain'] ] ) ) {
			$this->error = true;
			return;
		}

		if ( isset( $data['translation-revision-date'] ) ) {
			$this->headers['po-revision-date'] = $data['translation-revision-date'];
		}

		$entries = $data['locale_data'][ $data['domain'] ];

		foreach ( $entries as $key => $item ) {
			if ( '' === $key ) {
				$headers = array_change_key_case( $item );
				if ( isset( $headers['lang'] ) ) {
					$this->headers['language'] = $headers['lang'];
					unset( $headers['lang'] );
				}

				$this->headers = array_merge(
					$this->headers,
					$headers
				);
				continue;
			}

			if ( is_string( $item ) ) {
				$this->entries[ (string) $key ] = $item;
			} elseif ( is_array( $item ) ) {
				$this->entries[ (string) $key ] = implode( "\0", $item );
			}
		}

		unset( $this->headers['domain'] );
	}

	/**
	 * Exports translation contents as a string.
	 *
	 * @return string Translation file contents.
	 */
	public function export(): string {
		$headers = array_change_key_case( $this->headers );

		$domain = $headers['domain'] ?? 'messages';

		$data = array(
			'domain'      => $domain,
			'locale_data' => array(
				$domain => $this->entries,
			),
		);

		if ( isset( $headers['po-revision-date'] ) ) {
			$data['translation-revision-date'] = $headers['po-revision-date'];
		}

		if ( isset( $headers['x-generator'] ) ) {
			$data['generator'] = $headers['x-generator'];
		}

		$data['locale_data'][ $domain ][''] = array(
			'domain' => $domain,
		);

		if ( isset( $headers['plural-forms'] ) ) {
			$data['locale_data'][ $domain ]['']['plural-forms'] = $headers['plural-forms'];
		}

		if ( isset( $headers['language'] ) ) {
			$data['locale_data'][ $domain ]['']['lang'] = $headers['language'];
		}

		$json = json_encode( $data, JSON_PRETTY_PRINT );

		if ( false === $json ) {
			return '';
		}

		return $json;
	}
}
