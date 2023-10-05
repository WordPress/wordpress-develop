<?php
/**
 * Base Ginger_MO_Translation_File class.
 *
 * @package WordPress
 */

/**
 * Class Ginger_MO_Translation_File.
 */
abstract class Ginger_MO_Translation_File {
	/**
	 * List of headers.
	 *
	 * @var array<string, string>
	 */
	protected $headers = array();

	/**
	 * Whether file has been parsed.
	 *
	 * @var bool
	 */
	protected $parsed = false;

	/**
	 * Error information.
	 *
	 * @var bool|string
	 */
	protected $error = false;

	/**
	 * File name.
	 *
	 * @var string
	 */
	protected $file = '';

	/**
	 * Translation entries.
	 *
	 * @var array<string, string>
	 */
	protected $entries = array();

	/**
	 * Plural forms function.
	 *
	 * @var callable|null Plural forms.
	 */
	protected $plural_forms = null;

	/**
	 * Constructor.
	 *
	 * @param string $file    File to load.
	 */
	protected function __construct( string $file ) {
		$this->file = $file;
	}

	/**
	 * Creates a new Ginger_MO_Translation_File instance for a given file.
	 *
	 * @param string      $file     File name.
	 * @param string|null $filetype Optional. File type. Default inferred from file name.
	 * @return false|Ginger_MO_Translation_File
	 *
	 * @phpstan-param 'mo'|'php'|null $filetype
	 */
	public static function create( string $file, string $filetype = null ) {
		if ( ! is_readable( $file ) ) {
			return false;
		}

		if ( null === $filetype ) {
			$pos = strrpos( $file, '.' );
			if ( false !== $pos ) {
				$filetype = substr( $file, $pos + 1 );
			}
		}

		switch ( $filetype ) {
			case 'mo':
				return new Ginger_MO_Translation_File_MO( $file );
			case 'php':
				return new Ginger_MO_Translation_File_PHP( $file );
			default:
				return false;
		}
	}

	/**
	 * Returns all headers.
	 *
	 * @return array<string, string> Headers.
	 */
	public function headers() {
		if ( ! $this->parsed ) {
			$this->parse_file();
		}
		return $this->headers;
	}

	/**
	 * Returns all entries.
	 *
	 * @return array<string, string> Entries.
	 * @phstan-return array<string, non-empty-array<string>> Entries.
	 */
	public function entries() {
		if ( ! $this->parsed ) {
			$this->parse_file();
		}

		return $this->entries;
	}

	/**
	 * Returns the current error information.
	 *
	 * @phpstan-impure
	 *
	 * @return bool|string Error
	 */
	public function error() {
		return $this->error;
	}

	/**
	 * Returns the file name.
	 *
	 * @return string File name.
	 */
	public function get_file(): string {
		return $this->file;
	}

	/**
	 * Translates a given string.
	 *
	 * @param string $text String to translate.
	 * @return false|string Translation(s) on success, false otherwise.
	 */
	public function translate( string $text ) {
		if ( ! $this->parsed ) {
			$this->parse_file();
		}

		return $this->entries[ $text ] ?? false;
	}

	/**
	 * Returns the plural form for a count.
	 *
	 * @param int $number Count.
	 * @return int Plural form.
	 */
	public function get_plural_form( int $number ): int {
		if ( ! $this->parsed ) {
			$this->parse_file();
		}

		// In case a plural form is specified as a header, but no function included, build one.
		if ( null === $this->plural_forms && isset( $this->headers['plural-forms'] ) ) {
			$this->plural_forms = $this->make_plural_form_function( $this->headers['plural-forms'] );
		}

		if ( is_callable( $this->plural_forms ) ) {
			/**
			 * Plural form.
			 *
			 * @phpstan-var int $result Plural form.
			 */
			$result = call_user_func( $this->plural_forms, $number );
			return $result;
		}

		// Default plural form matches English, only "One" is considered singular.
		return ( 1 === $number ? 0 : 1 );
	}

	/**
	 * Makes a function, which will return the right translation index, according to the
	 * plural forms header
	 *
	 * @param string $expression Plural form expression.
	 * @return callable(int $num): int Plural forms function.
	 */
	public function make_plural_form_function( string $expression ) {
		try {
			$handler = new Plural_Forms( rtrim( $expression, ';' ) );
			return array( $handler, 'get' );
		} catch ( Exception $e ) {
			// Fall back to default plural-form function.
			return $this->make_plural_form_function( 'n != 1' );
		}
	}

	/**
	 * Creates a new Ginger_MO_Translation_File instance for a given file.
	 *
	 * @param string $file     Source file name.
	 * @param string $filetype Desired target file type.
	 * @return string|false Transformed translation file contents on success, false otherwise.
	 *
	 * @phpstan-param 'mo'|'php' $filetype
	 */
	public static function transform( string $file, string $filetype ) {
		$source = self::create( $file );

		if ( false === $source ) {
			return false;
		}

		switch ( $filetype ) {
			case 'mo':
				$destination = new Ginger_MO_Translation_File_MO( '' );
				break;
			case 'php':
				$destination = new Ginger_MO_Translation_File_PHP( '' );
				break;
			default:
				return false;
		}

		$success = $destination->import( $source );

		if ( ! $success ) {
			return false;
		}

		return $destination->export();
	}

	/**
	 * Imports translations from another file.
	 *
	 * @param Ginger_MO_Translation_File $source Source file.
	 * @return bool True on success, false otherwise.
	 */
	protected function import( Ginger_MO_Translation_File $source ): bool {
		if ( false !== $source->error() ) {
			return false;
		}

		$this->headers = $source->headers();
		$this->entries = $source->entries();
		$this->error   = $source->error();

		return false === $this->error;
	}

	/**
	 * Parses the file.
	 *
	 * @return void
	 */
	abstract protected function parse_file();


	/**
	 * Exports translation contents as a string.
	 *
	 * @return string Translation file contents.
	 */
	abstract public function export(): string;
}
