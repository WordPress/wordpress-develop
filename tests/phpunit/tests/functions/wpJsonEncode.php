<?php

/**
 * Tests for how wp_json_encode() encodes JsonSerializable objects when
 * json_encode() fails and the data is sanitized by _wp_json_sanity_check().
 *
 * @group functions
 *
 * @covers ::wp_json_encode
 * @covers ::_wp_json_sanity_check
 */
class Tests_Functions_WpJsonEncode extends WP_UnitTestCase {

	/**
	 * A string that is not valid UTF-8, so that json_encode() fails and
	 * wp_json_encode() falls back to sanitizing the data.
	 *
	 * @var string
	 */
	const INVALID_UTF8 = "caf\xE9";

	/**
	 * Tests that an object uses the value returned by jsonSerialize() when a
	 * string elsewhere in the data is not valid UTF-8.
	 *
	 * @ticket 66358
	 */
	public function test_uses_json_serialize_when_invalid_utf8_is_elsewhere_in_the_data() {
		$data = array(
			'object' => $this->get_json_serializable( array( 'kind' => 'serialized' ) ),
			'text'   => self::INVALID_UTF8,
		);

		$this->assertFalse( json_encode( $data ), 'Expected the data to take the fallback path.' );
		$this->assertSame(
			json_encode(
				array(
					'object' => array( 'kind' => 'serialized' ),
					'text'   => _wp_json_convert_string( self::INVALID_UTF8 ),
				)
			),
			wp_json_encode( $data )
		);
	}

	/**
	 * Tests that a string which is not valid UTF-8 in the value returned by
	 * jsonSerialize() is converted.
	 *
	 * @ticket 66358
	 */
	public function test_converts_invalid_utf8_returned_by_json_serialize() {
		$object = $this->get_json_serializable( array( 'label' => self::INVALID_UTF8 ) );

		$this->assertFalse( json_encode( $object ), 'Expected the object to take the fallback path.' );
		$this->assertSame(
			json_encode( array( 'label' => _wp_json_convert_string( self::INVALID_UTF8 ) ) ),
			wp_json_encode( $object )
		);
	}

	/**
	 * Tests that an object is rebuilt from its public properties, as before, when
	 * the value returned by jsonSerialize() cannot be encoded.
	 *
	 * @ticket 66358
	 *
	 * @dataProvider data_values_that_cannot_be_encoded
	 *
	 * @param mixed $serialized The value for jsonSerialize() to return.
	 */
	public function test_rebuilds_object_from_public_properties_when_serialized_value_cannot_be_encoded( $serialized ) {
		$data = array(
			'object' => $this->get_json_serializable( $serialized ),
			'text'   => self::INVALID_UTF8,
		);

		$this->assertSame(
			json_encode(
				array(
					'object' => array( 'public_property' => 'public value' ),
					'text'   => _wp_json_convert_string( self::INVALID_UTF8 ),
				)
			),
			wp_json_encode( $data )
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_values_that_cannot_be_encoded() {
		return array(
			'INF'                 => array( INF ),
			'NAN in nested array' => array( array( 'list' => array( 1, NAN ) ) ),
		);
	}

	/**
	 * Tests that only the object whose serialized value cannot be encoded is rebuilt
	 * from its public properties.
	 *
	 * @ticket 66358
	 */
	public function test_rebuilds_only_the_object_whose_serialized_value_cannot_be_encoded() {
		$data = array(
			'encodable'     => $this->get_json_serializable( array( 'kind' => 'serialized' ) ),
			'not encodable' => $this->get_json_serializable( INF ),
			'nested'        => $this->get_json_serializable(
				array( 'child' => $this->get_json_serializable( array( 'value' => NAN ) ) )
			),
			'text'          => self::INVALID_UTF8,
		);

		$this->assertSame(
			json_encode(
				array(
					'encodable'     => array( 'kind' => 'serialized' ),
					'not encodable' => array( 'public_property' => 'public value' ),
					'nested'        => array( 'child' => array( 'public_property' => 'public value' ) ),
					'text'          => _wp_json_convert_string( self::INVALID_UTF8 ),
				)
			),
			wp_json_encode( $data )
		);
	}

	/**
	 * Tests that an object is rebuilt from its public properties when its serialized
	 * value holds a resource.
	 *
	 * @ticket 66358
	 */
	public function test_rebuilds_object_whose_serialized_value_holds_a_resource() {
		$resource = fopen( 'php://memory', 'r' );
		$json     = wp_json_encode(
			array(
				'object' => $this->get_json_serializable( array( 'resource' => $resource ) ),
				'text'   => self::INVALID_UTF8,
			)
		);
		fclose( $resource );

		$this->assertSame(
			json_encode(
				array(
					'object' => array( 'public_property' => 'public value' ),
					'text'   => _wp_json_convert_string( self::INVALID_UTF8 ),
				)
			),
			$json
		);
	}

	/**
	 * Tests that every object is rebuilt from its public properties when the data is
	 * deeper than the depth limit after using the values returned by jsonSerialize().
	 *
	 * @ticket 66358
	 */
	public function test_rebuilds_every_object_when_the_serialized_data_exceeds_the_depth() {
		$data = array(
			$this->get_json_serializable( array( 'shallow' ) ),
			$this->get_json_serializable( array( array( array( 'deep' ) ) ) ),
			self::INVALID_UTF8,
		);

		$this->assertSame(
			json_encode(
				array(
					array( 'public_property' => 'public value' ),
					array( 'public_property' => 'public value' ),
					_wp_json_convert_string( self::INVALID_UTF8 ),
				)
			),
			wp_json_encode( $data, 0, 2 )
		);
	}

	/**
	 * Tests that jsonSerialize() is not used for an object nested inside an object
	 * which is rebuilt from its public properties.
	 *
	 * @ticket 66358
	 */
	public function test_does_not_use_json_serialize_inside_object_rebuilt_from_public_properties() {
		$object = new class() implements JsonSerializable {
			public $child;

			#[\ReturnTypeWillChange]
			public function jsonSerialize() {
				return INF;
			}
		};

		$object->child = $this->get_json_serializable( array( 'level' => 'child' ) );

		$this->assertSame(
			json_encode(
				array(
					'object' => array(
						'child' => array( 'public_property' => 'public value' ),
					),
					'text'   => _wp_json_convert_string( self::INVALID_UTF8 ),
				)
			),
			wp_json_encode(
				array(
					'object' => $object,
					'text'   => self::INVALID_UTF8,
				)
			)
		);
	}

	/**
	 * Tests that an object is rebuilt from its public properties when its
	 * jsonSerialize() throws during the fallback, which did not call it before.
	 *
	 * The string which is not valid UTF-8 comes first, so json_encode() fails
	 * before it calls jsonSerialize().
	 *
	 * @ticket 66358
	 *
	 * @dataProvider data_throwables
	 *
	 * @param string $throwable The class of the throwable for jsonSerialize() to throw.
	 */
	public function test_rebuilds_object_from_public_properties_when_json_serialize_throws( $throwable ) {
		$data = array(
			'text'   => self::INVALID_UTF8,
			'object' => $this->get_throwing_json_serializable( $throwable ),
		);

		$this->assertFalse( json_encode( $data ), 'Expected json_encode() to fail before calling jsonSerialize().' );
		$this->assertSame(
			json_encode(
				array(
					'text'   => _wp_json_convert_string( self::INVALID_UTF8 ),
					'object' => array( 'public_property' => 'public value' ),
				)
			),
			wp_json_encode( $data )
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_throwables() {
		return array(
			'exception' => array( RuntimeException::class ),
			'error'     => array( Error::class ),
		);
	}

	/**
	 * Tests that the fallback calls jsonSerialize() once for each object it reaches,
	 * and uses the value returned by that call.
	 *
	 * json_encode() calls jsonSerialize() for each object it reaches before it fails,
	 * and does not expose the value, so the fallback calls it again.
	 *
	 * @ticket 66358
	 */
	public function test_fallback_calls_json_serialize_once_for_each_object_it_reaches() {
		$object = $this->get_counting_json_serializable();
		$json   = wp_json_encode(
			array(
				'text'   => self::INVALID_UTF8,
				'object' => $object,
			)
		);

		$this->assertSame( 1, $object->calls, 'Expected json_encode() to fail before reaching the object, and the fallback to call jsonSerialize() once.' );
		$this->assertStringContainsString( '"object":{"call":1}', $json );

		$object = $this->get_counting_json_serializable();
		$json   = wp_json_encode(
			array(
				'object' => $object,
				'text'   => self::INVALID_UTF8,
			)
		);

		$this->assertSame( 2, $object->calls, 'Expected json_encode() and the fallback to call jsonSerialize() once each.' );
		$this->assertStringContainsString( '"object":{"call":2}', $json );
	}

	/**
	 * Tests that an object is rebuilt from its public properties when its
	 * jsonSerialize() succeeds for json_encode() but throws when the fallback
	 * calls it again.
	 *
	 * @ticket 66358
	 */
	public function test_rebuilds_object_from_public_properties_when_json_serialize_throws_on_its_second_call() {
		$object = $this->get_counting_json_serializable( 2 );
		$json   = wp_json_encode(
			array(
				'object' => $object,
				'text'   => self::INVALID_UTF8,
			)
		);

		$this->assertSame( 2, $object->calls, 'Expected jsonSerialize() to be called twice.' );
		$this->assertSame(
			json_encode(
				array(
					'object' => array(
						'calls'           => 2,
						'public_property' => 'public value',
					),
					'text'   => _wp_json_convert_string( self::INVALID_UTF8 ),
				)
			),
			$json
		);
	}

	/**
	 * Tests that, when json_encode() can encode the data with its strings converted
	 * to UTF-8, wp_json_encode() returns the same JSON for the data that is not valid
	 * UTF-8, with the same flags and depth. When it cannot, in these cases because of
	 * the depth or a cycle, the objects are rebuilt from their public properties.
	 *
	 * @ticket 66358
	 *
	 * @dataProvider data_matches_json_encode_of_the_converted_data
	 *
	 * @param string $build Name of the method that builds the data from a string.
	 * @param int    $flags Options to pass to both encoders.
	 */
	public function test_matches_json_encode_of_the_converted_data( $build, $flags ) {
		$converted = _wp_json_convert_string( self::INVALID_UTF8 );

		foreach ( array( 1, 2, 3, 512 ) as $depth ) {
			$expected = json_encode( $this->$build( $converted ), $flags, $depth );
			if ( false === $expected ) {
				$expected = $this->get_rebuilt_json( $this->$build( self::INVALID_UTF8 ), $flags, $depth );
			}

			$this->assertSame(
				$expected,
				wp_json_encode( $this->$build( self::INVALID_UTF8 ), $flags, $depth ),
				"Unexpected result at depth $depth."
			);
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_matches_json_encode_of_the_converted_data() {
		return array(
			'serializes to an array'                  => array( 'build_object_serializing_to_an_array', 0 ),
			'serializes to an array, with flags'      => array( 'build_object_serializing_to_an_array', JSON_FORCE_OBJECT | JSON_PRETTY_PRINT ),
			'serializes to a nested array'            => array( 'build_object_serializing_to_a_nested_array', 0 ),
			'serializes to a scalar'                  => array( 'build_object_serializing_to_a_scalar', 0 ),
			'serializes to another serializable'      => array( 'build_object_serializing_to_another_serializable', 0 ),
			'nested serializable objects'             => array( 'build_nested_serializable_objects', 0 ),
			'serializes to itself'                    => array( 'build_object_serializing_to_itself', 0 ),
			'objects serializing to each other'       => array( 'build_objects_serializing_to_each_other', 0 ),
			'objects serializing to arrays of others' => array( 'build_objects_serializing_to_arrays_of_each_other', 0 ),
		);
	}

	/**
	 * Tests that false is returned, with an error reported by json_last_error(), for
	 * objects which serialize to each other and also reference each other in their
	 * public properties.
	 *
	 * @ticket 66358
	 */
	public function test_returns_false_for_objects_serializing_to_each_other_through_public_properties() {
		$first  = $this->get_object_serializing_to_its_public_peer();
		$second = $this->get_object_serializing_to_its_public_peer();

		$first->peer  = $second;
		$second->peer = $first;

		$data = array(
			'object' => $first,
			'text'   => self::INVALID_UTF8,
		);

		$this->assertFalse( json_encode( $data ), 'Expected the data to take the fallback path.' );
		$this->assertFalse( wp_json_encode( $data ) );
		$this->assertNotSame( JSON_ERROR_NONE, json_last_error(), 'Expected json_last_error() to report an error.' );
	}

	/**
	 * Tests that, when the retry cannot encode the data and rebuilding the objects
	 * then exceeds the depth, json_last_error() reports the error of the retry, not
	 * the error of the first call to json_encode().
	 *
	 * Here the first call fails on the string which is not valid UTF-8. The value the
	 * object serializes to passes the depth check of _wp_json_sanity_check(), which
	 * allows one more level than json_encode(), so the retry fails on the depth. The
	 * public properties of the object are deeper still, so rebuilding it exceeds the
	 * depth check too.
	 *
	 * @ticket 66358
	 */
	public function test_json_last_error_reports_the_retry_error_when_rebuilding_exceeds_the_depth() {
		$object = new class() implements JsonSerializable {
			public $deep = array( array( array( 1 ) ) );

			#[\ReturnTypeWillChange]
			public function jsonSerialize() {
				return array( array( 1 ) );
			}
		};

		$data = array(
			'text'   => self::INVALID_UTF8,
			'object' => $object,
		);

		$this->assertFalse( json_encode( $data, 0, 2 ), 'Expected the first call to fail.' );
		$this->assertSame( JSON_ERROR_UTF8, json_last_error(), 'Expected the first call to fail on the string.' );

		$this->assertFalse( wp_json_encode( $data, 0, 2 ) );
		$this->assertSame( JSON_ERROR_DEPTH, json_last_error() );
	}

	/**
	 * Tests that an object which implements both JsonSerializable and IteratorAggregate,
	 * and serializes to itself, is encoded from its public properties, as json_encode()
	 * does, rather than from its iterator.
	 *
	 * @ticket 66358
	 */
	public function test_traversable_object_serializing_to_itself_is_encoded_from_its_public_properties() {
		$object = new class() implements JsonSerializable, IteratorAggregate {
			public $label = 'public value';

			#[\ReturnTypeWillChange]
			public function jsonSerialize() {
				return $this;
			}

			public function getIterator(): Traversable {
				return new ArrayIterator( array( 'iterated' => true ) );
			}
		};

		$this->assertSame(
			json_encode(
				array(
					'object' => $object,
					'text'   => _wp_json_convert_string( self::INVALID_UTF8 ),
				)
			),
			wp_json_encode(
				array(
					'object' => $object,
					'text'   => self::INVALID_UTF8,
				)
			)
		);
	}

	/**
	 * Tests that _wp_json_sanity_check() still rebuilds every object from its public
	 * properties when it is called without the `$use_json_serialize` argument.
	 *
	 * @ticket 66358
	 */
	public function test_sanity_check_rebuilds_objects_from_public_properties_by_default() {
		$this->assertSame(
			'{"public_property":"public value"}',
			json_encode( _wp_json_sanity_check( $this->get_json_serializable( array( 'kind' => 'serialized' ) ), 512 ) )
		);
	}

	/**
	 * Tests that a secret encodes to its mask, and not its value, when the data
	 * takes the fallback path.
	 *
	 * @ticket 66358
	 *
	 * @covers WP_Secret::jsonSerialize
	 */
	public function test_secret_encodes_to_its_mask() {
		$secret = new WP_Secret( 'json_encode_test', 'plaintext-value', 'fingerprint' );

		$json = wp_json_encode(
			array(
				'secret' => $secret,
				'text'   => self::INVALID_UTF8,
			)
		);

		$this->assertSame(
			json_encode(
				array(
					'secret' => '[secret:json_encode_test]',
					'text'   => _wp_json_convert_string( self::INVALID_UTF8 ),
				)
			),
			$json
		);
		$this->assertStringNotContainsString( 'plaintext-value', $json );
	}

	/**
	 * Tests that speculation rules encode to the same rules as with json_encode()
	 * when the data takes the fallback path.
	 *
	 * @ticket 66358
	 *
	 * @covers WP_Speculation_Rules::jsonSerialize
	 */
	public function test_speculation_rules_encode_to_their_rules() {
		$rules = new WP_Speculation_Rules();
		$added = $rules->add_rule(
			'prefetch',
			'json-encode-test',
			array(
				'source' => 'document',
				'where'  => array( 'href_matches' => '/*' ),
			)
		);

		$this->assertTrue( $added, 'Expected the rule to be added.' );
		$this->assertSame(
			json_encode(
				array(
					'rules' => $rules,
					'text'  => _wp_json_convert_string( self::INVALID_UTF8 ),
				)
			),
			wp_json_encode(
				array(
					'rules' => $rules,
					'text'  => self::INVALID_UTF8,
				)
			)
		);
	}

	/**
	 * Tests that a dangerous HTML token encodes to its marker when the data takes
	 * the fallback path.
	 *
	 * @ticket 66358
	 *
	 * @covers WP_Interactivity_Dangerous_HTML::jsonSerialize
	 */
	public function test_dangerous_html_token_encodes_to_its_marker() {
		$json = wp_json_encode(
			array(
				'token' => wp_interactivity_as_dangerous_html( '<b>json-encode-test</b>' ),
				'text'  => self::INVALID_UTF8,
			)
		);

		$this->assertSame(
			json_encode(
				array(
					'token' => array( 'wpServerHtml' => true ),
					'text'  => _wp_json_convert_string( self::INVALID_UTF8 ),
				)
			),
			$json
		);
		$this->assertStringNotContainsString( 'json-encode-test', $json );
	}

	/**
	 * Tests that a dangerous HTML token encodes to its marker inside an object which
	 * is rebuilt from its public properties.
	 *
	 * @ticket 66358
	 *
	 * @covers WP_Interactivity_Dangerous_HTML::getIterator
	 */
	public function test_dangerous_html_token_encodes_to_its_marker_when_objects_are_rebuilt() {
		$token = wp_interactivity_as_dangerous_html( '<b>json-encode-test</b>' );

		$rebuilt = new class() implements JsonSerializable {
			public $token;

			#[\ReturnTypeWillChange]
			public function jsonSerialize() {
				return NAN;
			}
		};

		$rebuilt->token = $token;

		$json = wp_json_encode(
			array(
				'token'   => $token,
				'rebuilt' => $rebuilt,
				'text'    => self::INVALID_UTF8,
			)
		);

		$this->assertSame(
			json_encode(
				array(
					'token'   => array( 'wpServerHtml' => true ),
					'rebuilt' => array( 'token' => array( 'wpServerHtml' => true ) ),
					'text'    => _wp_json_convert_string( self::INVALID_UTF8 ),
				)
			),
			$json
		);
		$this->assertStringNotContainsString( 'json-encode-test', $json );
	}

	/**
	 * Returns the JSON for the data with every object rebuilt from its public
	 * properties, which is how wp_json_encode() sanitized objects before.
	 *
	 * @param mixed $data  The data to encode.
	 * @param int   $flags Options to pass to json_encode().
	 * @param int   $depth Maximum depth.
	 * @return string|false The JSON, or false if it cannot be encoded.
	 */
	private function get_rebuilt_json( $data, $flags, $depth ) {
		try {
			return json_encode( _wp_json_sanity_check( $data, $depth ), $flags, $depth );
		} catch ( Exception $e ) {
			return false;
		}
	}

	private function build_object_serializing_to_an_array( $text ) {
		return array(
			'object' => $this->get_json_serializable( array( 1, 2 ) ),
			'text'   => $text,
		);
	}

	private function build_object_serializing_to_a_nested_array( $text ) {
		return array(
			'object' => $this->get_json_serializable( array( array( $text ) ) ),
		);
	}

	private function build_object_serializing_to_a_scalar( $text ) {
		return array( $this->get_json_serializable( $text ) );
	}

	private function build_object_serializing_to_another_serializable( $text ) {
		return array(
			'object' => $this->get_json_serializable( $this->get_json_serializable( array( 'inner' => $text ) ) ),
		);
	}

	private function build_nested_serializable_objects( $text ) {
		return $this->get_json_serializable(
			array(
				'child' => $this->get_json_serializable(
					array( 'grandchild' => $this->get_json_serializable( array( $text ) ) )
				),
			)
		);
	}

	private function build_object_serializing_to_itself( $text ) {
		return array(
			'object' => $this->get_object_serializing_to_itself( $text, $this->get_json_serializable( array( 1 ) ) ),
		);
	}

	private function build_objects_serializing_to_each_other( $text ) {
		$first  = $this->get_object_serializing_to_its_private_peer( false );
		$second = $this->get_object_serializing_to_its_private_peer( false );

		$first->set_peer( $second );
		$second->set_peer( $first );

		return array(
			'object' => $first,
			'text'   => $text,
		);
	}

	private function build_objects_serializing_to_arrays_of_each_other( $text ) {
		$first  = $this->get_object_serializing_to_its_private_peer( true );
		$second = $this->get_object_serializing_to_its_private_peer( true );

		$first->set_peer( $second );
		$second->set_peer( $first );

		return array(
			'object' => $first,
			'text'   => $text,
		);
	}

	/**
	 * Returns an object with a public property whose jsonSerialize() returns the given value.
	 *
	 * @param mixed $serialized The value for jsonSerialize() to return.
	 * @return JsonSerializable The object.
	 */
	private function get_json_serializable( $serialized ) {
		return new class( $serialized ) implements JsonSerializable {
			public $public_property = 'public value';
			private $serialized;

			public function __construct( $serialized ) {
				$this->serialized = $serialized;
			}

			#[\ReturnTypeWillChange]
			public function jsonSerialize() {
				return $this->serialized;
			}
		};
	}

	/**
	 * Returns an object whose jsonSerialize() returns the object itself.
	 *
	 * @param string $label The value of the public `label` property.
	 * @param mixed  $child The value of the public `child` property.
	 * @return JsonSerializable The object.
	 */
	private function get_object_serializing_to_itself( $label, $child ) {
		return new class( $label, $child ) implements JsonSerializable {
			public $label;
			public $child;
			private $private_property = 'private value';

			public function __construct( $label, $child ) {
				$this->label = $label;
				$this->child = $child;
			}

			#[\ReturnTypeWillChange]
			public function jsonSerialize() {
				return $this;
			}
		};
	}

	/**
	 * Returns an object with a public property whose jsonSerialize() throws.
	 *
	 * @param string $throwable Optional. The class of the throwable to throw. Default RuntimeException.
	 * @return JsonSerializable The object.
	 */
	private function get_throwing_json_serializable( $throwable = RuntimeException::class ) {
		return new class( $throwable ) implements JsonSerializable {
			public $public_property = 'public value';
			private $throwable;

			public function __construct( $throwable ) {
				$this->throwable = $throwable;
			}

			#[\ReturnTypeWillChange]
			public function jsonSerialize() {
				throw new $this->throwable( 'This object cannot be serialized.' );
			}
		};
	}

	/**
	 * Returns an object which counts the calls to its jsonSerialize(), and returns the
	 * number of the call, or throws on the given call.
	 *
	 * @param int $throw_on_call Optional. The call to throw on. Default 0, never.
	 * @return JsonSerializable The object.
	 */
	private function get_counting_json_serializable( $throw_on_call = 0 ) {
		return new class( $throw_on_call ) implements JsonSerializable {
			public $calls           = 0;
			public $public_property = 'public value';
			private $throw_on_call;

			public function __construct( $throw_on_call ) {
				$this->throw_on_call = $throw_on_call;
			}

			#[\ReturnTypeWillChange]
			public function jsonSerialize() {
				++$this->calls;

				if ( $this->calls === $this->throw_on_call ) {
					throw new RuntimeException( 'This object cannot be serialized again.' );
				}

				return array( 'call' => $this->calls );
			}
		};
	}

	/**
	 * Returns an object whose jsonSerialize() returns the object in its public `peer` property.
	 *
	 * @return JsonSerializable The object.
	 */
	private function get_object_serializing_to_its_public_peer() {
		return new class() implements JsonSerializable {
			public $peer;

			#[\ReturnTypeWillChange]
			public function jsonSerialize() {
				return $this->peer;
			}
		};
	}

	/**
	 * Returns an object whose jsonSerialize() returns the object in its private `peer`
	 * property, or an array holding it.
	 *
	 * @param bool $in_array Whether to return the peer in an array.
	 * @return JsonSerializable The object.
	 */
	private function get_object_serializing_to_its_private_peer( $in_array ) {
		return new class( $in_array ) implements JsonSerializable {
			public $label = 'peer';
			private $peer;
			private $in_array;

			public function __construct( $in_array ) {
				$this->in_array = $in_array;
			}

			public function set_peer( $peer ) {
				$this->peer = $peer;
			}

			#[\ReturnTypeWillChange]
			public function jsonSerialize() {
				return $this->in_array ? array( $this->peer ) : $this->peer;
			}
		};
	}
}
