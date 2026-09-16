<?php
/**
 * REST API: WP_REST_Resolvable_Route class
 *
 * @package WordPress
 * @subpackage REST_API
 * @since X.X.0
 */

/**
 * Just-in-time resolvable route for REST API routes.
 *
 * @since X.X.0
 */
class WP_REST_Resolvable_Route implements ArrayAccess, IteratorAggregate, Countable {
	protected $namespace;
	protected $route;

	/**
	 * The callable used to resolve the route.
	 *
	 * @since X.X.0
	 * @var callable
	 */
	protected $callable;

	/**
	 * The resolved route definition.
	 *
	 * @since X.X.0
	 * @var array|null
	 */
	protected $resolved = null;

	/**
	 * Constructor.
	 *
	 * @since X.X.0
	 *
	 * @param callable $closure The callable used to resolve the route. Returns a single route definition.
	 */
	public function __construct( string $namespace, string $route, callable $closure ) {
		$this->namespace = $namespace;
		$this->route = $route;
		$this->callable = $closure;
	}

	/**
	 * Invokes the callable to resolve, if needed.
	 *
	 * Routes can only be resolved once, the first time they're used. Any
	 * subsequent calls will return the same resolved definition, which may
	 * be modified by reference if needed.
	 *
	 * @since X.X.0
	 *
	 * @return array The resolved route definition.
	 */
	public function __invoke() {
		if ( ! $this->resolved ) {
			$this->resolved = call_user_func( $this->callable );

			// Normalize the result.
			$this->resolved = normalize_rest_endpoint_options( $this->namespace, $this->route, $this->resolved );
		}
		return $this->resolved;
	}

	/**
	 * Checks a single array key exists in the resolved route definition.
	 *
	 * @since X.X.0
	 *
	 * @param string $key The key to check.
	 * @return bool True if the key exists, false otherwise.
	 */
	#[ReturnTypeWillChange]
	public function offsetExists( $k ) {
		$this->__invoke();
		return isset( $this->resolved[ $k ] );
	}

	/**
	 * Gets a single array key from the resolved route definition.
	 *
	 * @since X.X.0
	 *
	 * @param string $key The key to retrieve.
	 * @return mixed The value of the key, or null if not set. Returns by reference, so it can be modified if needed.
	 */
	#[ReturnTypeWillChange]
	public function &offsetGet( $k ) {
		$this->__invoke();
		return $this->resolved[ $k ];
	}

	/**
	 * Sets a single array key in the resolved route definition.
	 *
	 * @since X.X.0
	 *
	 * @param string $key The key to set.
	 * @param mixed $value The value to set.
	 */
	#[ReturnTypeWillChange]
	public function offsetSet( $k, $v ) {
		$this->__invoke();
		$this->resolved[ $k ] = $v;
	}

	/**
	 * Unsets a single array key in the resolved route definition.
	 *
	 * @since X.X.0
	 *
	 * @param string $key The key to unset.
	 */
	#[ReturnTypeWillChange]
	public function offsetUnset( $k ) {
		$this->__invoke();
		unset( $this->resolved[ $k ] );
	}

	/**
	 * Gets an iterator for the resolved route definition.
	 *
	 * @since X.X.0
	 *
	 * @return Traversable An iterator for the resolved route definition.
	 */
	public function getIterator(): Traversable {
		$this->__invoke();
		return new ArrayIterator( $this->resolved );
	}

	/**
	 * Counts the number of elements in the resolved route definition.
	 *
	 * @since X.X.0
	 *
	 * @return int The number of elements in the resolved route definition.
	 */
	public function count(): int {
		$this->__invoke();
		return count( $this->resolved );
	}
}
