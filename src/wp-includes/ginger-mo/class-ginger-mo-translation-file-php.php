<?php
/**
 * Class Ginger_MO_Translation_File_PHP.
 *
 * @package WordPress
 */

/**
 * Class Ginger_MO_Translation_File_PHP.
 */
class Ginger_MO_Translation_File_PHP extends Ginger_MO_Translation_File {
	/**
	 * Parses the file.
	 *
	 * @return void
	 */
	protected function parse_file() {
		$this->parsed = true;

		$result = include $this->file;
		if ( ! $result || ! is_array( $result ) ) {
			$this->error = true;
			return;
		}

		if ( isset( $result['messages'] ) && is_array( $result['messages'] ) ) {
			foreach ( $result['messages'] as $singular => $translations ) {
				if ( is_array( $translations ) ) {
					$this->entries[ $singular ] = implode( "\0", $translations );
				} elseif ( is_string( $translations ) ) {
					$this->entries[ $singular ] = $translations;
				}
			}
			unset( $result['messages'] );
		}

		$this->headers = array_change_key_case( $result );
	}

	/**
	 * Exports translation contents as a string.
	 *
	 * @return string Translation file contents.
	 */
	public function export(): string {
		$data = array_merge( $this->headers, array( 'messages' => $this->entries ) );

		return '<?php' . PHP_EOL . 'return ' . $this->var_export( $data ) . ';' . PHP_EOL;
	}

	/**
	 * Determines if the given array is a list.
	 *
	 * An array is considered a list if its keys consist of consecutive numbers from 0 to count($array)-1.
	 *
	 * Polyfill for array_is_list() in PHP 8.1.
	 *
	 * @see https://github.com/symfony/polyfill-php81/tree/main
	 *
	 * @SuppressWarnings(PHPMD.UnusedLocalVariable)
	 *
	 * @codeCoverageIgnore
	 *
	 * @param array<mixed> $arr The array being evaluated.
	 * @return bool True if array is a list, false otherwise.
	 */
	private function array_is_list( array $arr ): bool {
		if ( function_exists( 'array_is_list' ) ) {
			return array_is_list( $arr );
		}

		if ( ( array() === $arr ) || ( array_values( $arr ) === $arr ) ) {
			return true;
		}

		$next_key = -1;

		foreach ( $arr as $k => $v ) {
			if ( ++$next_key !== $k ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Outputs or returns a parsable string representation of a variable.
	 *
	 * Like {@see var_export()} but "minified", using short array syntax
	 * and no newlines.
	 *
	 * @param mixed $value The variable you want to export.
	 * @return string The variable representation.
	 */
	private function var_export( $value ): string {
		if ( ! is_array( $value ) ) {
			return var_export( $value, true );
		}

		$entries = array();

		$is_list = $this->array_is_list( $value );

		foreach ( $value as $key => $val ) {
			$entries[] = $is_list ? $this->var_export( $val ) : var_export( $key, true ) . '=>' . $this->var_export( $val );
		}

		return '[' . implode( ',', $entries ) . ']';
	}
}
