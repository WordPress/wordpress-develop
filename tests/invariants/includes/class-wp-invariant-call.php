<?php
/**
 * The call an invariant's check is looking at.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

/**
 * One call to a hooked function or method, as one check sees it.
 *
 * The gameshark extension passes hooks their arguments in four different ways,
 * depending on whether the hook runs before or after the call, and whether the
 * target is an instance method. This turns all of them into one object.
 *
 * In an `after()` check, the arguments are as they were when the call started.
 * Objects are not copied, though: an object argument, or `self()`, shows any
 * changes the call made to it. To compare with how an object was before the
 * call, return what is needed from a paired `before()` check.
 */
final class WP_Invariant_Call {

	/**
	 * Parameters of each target, found by reflection: lower-case target => list of parameters.
	 *
	 * @var array[]
	 */
	private static $parameters = array();

	/**
	 * Whether each lower-case method target is static.
	 *
	 * @var bool[]
	 */
	private static $static_methods = array();

	/**
	 * The invariant whose check this is.
	 *
	 * @var WP_Invariant
	 */
	private $invariant;

	/**
	 * `pre` or `post`.
	 *
	 * @var string
	 */
	private $when;

	/**
	 * Function or method, as the invariant names it.
	 *
	 * @var string
	 */
	private $target;

	/**
	 * The object for an instance method, or null.
	 *
	 * @var object|null
	 */
	private $self = null;

	/**
	 * Arguments, in order.
	 *
	 * @var array
	 */
	private $args;

	/**
	 * What the call returned, in an after check.
	 *
	 * @var mixed
	 */
	private $return_value = null;

	/**
	 * What the paired before check returned.
	 *
	 * @var mixed
	 */
	private $before = null;

	/**
	 * Whether a paired before check ran for this call.
	 *
	 * @var bool
	 */
	private $has_before = false;

	/**
	 * Frames of the call stack, from the target's caller outwards, found when first needed.
	 *
	 * @var array[]|null
	 */
	private $frames = null;

	/**
	 * Reads what the gameshark extension passed to a hook.
	 *
	 * @param WP_Invariant $invariant The invariant whose check this is.
	 * @param string       $when      `pre` or `post`.
	 * @param string       $target    Function or method.
	 * @param array        $params    What the extension passed to the hook.
	 */
	public function __construct( WP_Invariant $invariant, $when, $target, array $params ) {
		$this->invariant = $invariant;
		$this->when      = $when;
		$this->target    = $target;

		$instance = false !== strpos( $target, '::' ) && ! self::is_static_method( $target );
		if ( $instance ) {
			$this->self = array_shift( $params );
		}

		if ( 'post' === $when ) {
			$this->return_value = $params[0];
			$this->args         = $params[1];
		} else {
			$this->args = $params;
		}
	}

	/**
	 * Returns the function or method, as the invariant names it, such as `wpdb::prepare`.
	 *
	 * @return string
	 */
	public function target() {
		return $this->target;
	}

	/**
	 * Returns `before` or `after`.
	 *
	 * @return string
	 */
	public function when() {
		return 'pre' === $this->when ? 'before' : 'after';
	}

	/**
	 * Returns the object an instance method was called on, or null.
	 *
	 * @return object|null
	 */
	public function self() {
		return $this->self;
	}

	/**
	 * Returns the arguments in order, as they were when the call started.
	 *
	 * @return array
	 */
	public function args() {
		return $this->args;
	}

	/**
	 * Returns an argument by its parameter name, or the parameter's default when it was not passed.
	 *
	 * A variadic parameter gives the list of arguments it collected.
	 *
	 * @param string $name Parameter name, without `$`.
	 * @return mixed
	 */
	public function arg( $name ) {
		foreach ( self::parameters( $this->target ) as $position => $parameter ) {
			if ( $parameter['name'] !== $name ) {
				continue;
			}

			if ( $parameter['variadic'] ) {
				return array_slice( $this->args, $position );
			}
			if ( array_key_exists( $position, $this->args ) ) {
				return $this->args[ $position ];
			}
			return $parameter['default'];
		}

		throw new InvalidArgumentException( sprintf( '%s() has no parameter named $%s.', $this->target, $name ) );
	}

	/**
	 * Returns what the call returned. Only after checks have one.
	 *
	 * @return mixed
	 */
	public function return_value() {
		if ( 'post' !== $this->when ) {
			throw new LogicException( 'A before check has no return value yet; use an after check.' );
		}

		return $this->return_value;
	}

	/**
	 * Returns what the paired before check returned for this same call.
	 *
	 * @return mixed
	 */
	public function before() {
		if ( ! $this->has_before ) {
			throw new LogicException( sprintf( 'This invariant has no before check on %s paired with this after check.', $this->target ) );
		}

		return $this->before;
	}

	/**
	 * Returns where the target was called from.
	 *
	 * @return array {
	 *     @type string|null $file Path of the calling file, or null when PHP called the target itself.
	 *     @type int|null    $line Line in that file.
	 * }
	 */
	public function caller() {
		$frames = $this->frames();
		return array(
			'file' => isset( $frames[0]['file'] ) ? $frames[0]['file'] : null,
			'line' => isset( $frames[0]['line'] ) ? $frames[0]['line'] : null,
		);
	}

	/**
	 * Returns the frames of the call stack in the code under test, innermost first.
	 *
	 * @param int $limit Most frames to return.
	 * @return string[] Each one as `function() at file:line`, the function holding that line, with the file relative to the repository.
	 */
	public function stack( $limit = 8 ) {
		$stack  = array();
		$frames = $this->frames();
		foreach ( $frames as $index => $frame ) {
			if ( ! isset( $frame['file'] ) || ! WP_Invariant::is_core_file( $frame['file'] ) ) {
				continue;
			}

			// A frame's file and line are where its function was called from, which is in the next frame's function.
			$function = isset( $frames[ $index + 1 ] ) ? self::frame_function( $frames[ $index + 1 ] ) . '()' : 'main';
			$stack[]  = sprintf( '%s at %s:%d', $function, WP_Invariant::relative_path( $frame['file'] ), $frame['line'] );
			if ( count( $stack ) >= $limit ) {
				break;
			}
		}

		return $stack;
	}

	/**
	 * Records a violation of the invariant when a condition is false.
	 *
	 * @param bool   $condition What must be true.
	 * @param string $format    Message, with `%s` and similar for the values.
	 * @param mixed  ...$values Values for the message. Values which are not scalars are shown as short PHP.
	 * @return bool The condition.
	 */
	public function expect( $condition, $format, ...$values ) {
		if ( ! $condition ) {
			WP_Invariant_Violations::record( $this->invariant, vsprintf( $format, array_map( array( __CLASS__, 'describe_value' ), $values ) ), $this );
		}

		return (bool) $condition;
	}

	/**
	 * Gives an after check what the paired before check returned.
	 *
	 * @internal
	 *
	 * @param mixed $value What the before check returned.
	 */
	public function set_before( $value ) {
		$this->before     = $value;
		$this->has_before = true;
	}

	/**
	 * Shows a value briefly in a message.
	 *
	 * @param mixed $value Any value.
	 * @return string|int|float
	 */
	public static function describe_value( $value ) {
		if ( is_string( $value ) ) {
			return strlen( $value ) > 200 ? substr( $value, 0, 200 ) . '…' : $value;
		}
		if ( is_int( $value ) || is_float( $value ) ) {
			return $value;
		}
		if ( is_object( $value ) ) {
			return get_class( $value ) . ( isset( $value->ID ) ? '#' . $value->ID : '' );
		}

		$text = var_export( $value, true );
		return strlen( $text ) > 200 ? substr( $text, 0, 200 ) . '…' : $text;
	}

	/**
	 * Returns the frames from the target's caller outwards.
	 *
	 * @return array[]
	 */
	private function frames() {
		if ( null !== $this->frames ) {
			return $this->frames;
		}

		$frames = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS );
		$parts  = explode( '::', $this->target );
		$method = array_pop( $parts );
		$class  = array_pop( $parts );

		// The target's own frame holds the file and line it was called from.
		foreach ( $frames as $index => $frame ) {
			if ( ! isset( $frame['function'] ) || 0 !== strcasecmp( $frame['function'], $method ) ) {
				continue;
			}
			if ( null !== $class && ( ! isset( $frame['class'] ) || ( 0 !== strcasecmp( $frame['class'], $class ) && ! is_subclass_of( $frame['class'], $class ) ) ) ) {
				continue;
			}

			$this->frames = array_slice( $frames, $index );
			return $this->frames;
		}

		$this->frames = array();
		return $this->frames;
	}

	/**
	 * Names the function a frame is in.
	 *
	 * @param array $frame A frame from debug_backtrace().
	 * @return string
	 */
	private static function frame_function( array $frame ) {
		return ( isset( $frame['class'] ) ? $frame['class'] . '::' : '' ) . $frame['function'];
	}

	/**
	 * Whether a method target is static, found by reflection the first time it is called.
	 *
	 * @param string $target `Class::method`.
	 * @return bool
	 */
	private static function is_static_method( $target ) {
		$key = strtolower( $target );
		if ( ! isset( self::$static_methods[ $key ] ) ) {
			list( $class, $method )       = explode( '::', $target );
			self::$static_methods[ $key ] = ( new ReflectionMethod( $class, $method ) )->isStatic();
		}

		return self::$static_methods[ $key ];
	}

	/**
	 * Returns a target's parameters, found by reflection the first time they are needed.
	 *
	 * @param string $target Function or method.
	 * @return array[] Each with `name`, `variadic` and `default`.
	 */
	private static function parameters( $target ) {
		$key = strtolower( $target );
		if ( isset( self::$parameters[ $key ] ) ) {
			return self::$parameters[ $key ];
		}

		if ( false !== strpos( $target, '::' ) ) {
			list( $class, $method ) = explode( '::', $target );
			$reflection             = new ReflectionMethod( $class, $method );
		} else {
			$reflection = new ReflectionFunction( $target );
		}
		$parameters = array();
		foreach ( $reflection->getParameters() as $parameter ) {
			$parameters[] = array(
				'name'     => $parameter->getName(),
				'variadic' => $parameter->isVariadic(),
				'default'  => $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null,
			);
		}

		self::$parameters[ $key ] = $parameters;
		return $parameters;
	}
}
