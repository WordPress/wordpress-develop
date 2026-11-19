<?php
/**
 * Invariants checked by hooks on WordPress functions while the PHPUnit suite runs.
 *
 * The gameshark PHP extension loads these classes, and the specs which use them,
 * at the start of every PHP process: before PHPUnit, and before WordPress. They
 * must not depend on either.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

/**
 * A named property of WordPress which must hold whenever its hooks run.
 *
 * An invariant has any number of hooks. Each one runs a check just before
 * (`before()`) or just after (`after()`) a call to a function or method. The
 * hooks of one invariant share its state, and a `before()` and an `after()` hook
 * on the same target are paired: what the `before()` check returns for a call is
 * given to the `after()` check for that same call, as `$call->before()`.
 *
 * Example:
 *
 *     WP_Invariant::define( 'query-is-counted' )
 *         ->describe( 'A successful wpdb::query() adds to $wpdb->num_queries.' )
 *         ->before( 'wpdb::query', static function ( WP_Invariant_Call $call ) {
 *             return $call->self()->num_queries;
 *         } )
 *         ->after( 'wpdb::query', static function ( WP_Invariant_Call $call ) {
 *             // ...compare $call->self()->num_queries with $call->before().
 *         } );
 *
 * @see tests/invariants/README.md
 */
final class WP_Invariant {

	/**
	 * Full id, set when the spec file is loaded: the file's path under specs/, then the name.
	 *
	 * @var string
	 */
	private $id = '';

	/**
	 * Name given to define(), unique within its spec file.
	 *
	 * @var string
	 */
	private $name;

	/**
	 * What must hold, in a sentence.
	 *
	 * @var string
	 */
	private $description = '';

	/**
	 * Hooks, each with `when` (`pre` or `post`), `target` and `check`.
	 *
	 * @var array[]
	 */
	private $hooks = array();

	/**
	 * Builds the starting state, or null when the invariant keeps none.
	 *
	 * @var callable|null
	 */
	private $state_initializer = null;

	/**
	 * When the state is reset: `test` before each test, or `run` never.
	 *
	 * @var string
	 */
	private $state_scope = 'test';

	/**
	 * The state shared by this invariant's hooks.
	 *
	 * @var mixed
	 */
	private $state = null;

	/**
	 * Whether the state has been built since it was last reset.
	 *
	 * @var bool
	 */
	private $has_state = false;

	/**
	 * Whether calls made directly from test code are checked too.
	 *
	 * @var bool
	 */
	private $include_test_callers = false;

	/**
	 * For each paired target, the results of `before()` checks which wait for their `after()` check.
	 *
	 * Each entry is `array( depth, value )`, where depth is the depth of the call stack.
	 *
	 * @var array[]
	 */
	private $pending = array();

	/**
	 * Every loaded invariant, by id.
	 *
	 * @var WP_Invariant[]
	 */
	private static $invariants = array();

	/**
	 * Shared objects by name, each with its scope.
	 *
	 * @var array[]
	 */
	private static $shared = array();

	/**
	 * Directories which hold test code, and which hold the code under test.
	 *
	 * @var array
	 */
	private static $paths = null;

	/**
	 * Properties property() has read: `Class::name` => ReflectionProperty.
	 *
	 * @var ReflectionProperty[]
	 */
	private static $properties = array();

	/**
	 * Starts defining an invariant.
	 *
	 * @param string $name Name, unique within its spec file, such as `positive-id`.
	 * @return WP_Invariant
	 */
	public static function define( $name ) {
		if ( ! is_string( $name ) || ! preg_match( '/^[a-z0-9][a-z0-9-]*$/', $name ) ) {
			throw new InvalidArgumentException( 'An invariant name is lower-case letters, digits and hyphens, such as "positive-id".' );
		}

		$invariant       = new self();
		$invariant->name = $name;
		return $invariant;
	}

	/**
	 * Says what must hold. This is shown with every violation.
	 *
	 * @param string $description One sentence.
	 * @return WP_Invariant
	 */
	public function describe( $description ) {
		$this->description = $description;
		return $this;
	}

	/**
	 * Adds a check which runs just before each call to a function or method.
	 *
	 * The check receives a WP_Invariant_Call and, by reference, the invariant's
	 * state. When the invariant also has an `after()` hook on the same target, the
	 * check's return value is given to that hook as `$call->before()`.
	 *
	 * @param string   $target Function or method, such as `get_post` or `wpdb::prepare`.
	 * @param callable $check  `function ( WP_Invariant_Call $call, &$state )`.
	 * @return WP_Invariant
	 */
	public function before( $target, $check ) {
		return $this->add_hook( 'pre', $target, $check );
	}

	/**
	 * Adds a check which runs just after each call to a function or method returns.
	 *
	 * It does not run when the call throws. The check sees the call's arguments as
	 * they were when the call started, and its return value.
	 *
	 * @param string   $target Function or method, such as `get_post` or `wpdb::prepare`.
	 * @param callable $check  `function ( WP_Invariant_Call $call, &$state )`.
	 * @return WP_Invariant
	 */
	public function after( $target, $check ) {
		return $this->add_hook( 'post', $target, $check );
	}

	/**
	 * Adds the same before check to several functions or methods.
	 *
	 * @param string[] $targets Functions or methods.
	 * @param callable $check   `function ( WP_Invariant_Call $call, &$state )`.
	 * @return WP_Invariant
	 */
	public function before_each( array $targets, $check ) {
		foreach ( $targets as $target ) {
			$this->before( $target, $check );
		}
		return $this;
	}

	/**
	 * Adds the same after check to several functions or methods.
	 *
	 * @param string[] $targets Functions or methods.
	 * @param callable $check   `function ( WP_Invariant_Call $call, &$state )`.
	 * @return WP_Invariant
	 */
	public function after_each( array $targets, $check ) {
		foreach ( $targets as $target ) {
			$this->after( $target, $check );
		}
		return $this;
	}

	/**
	 * Gives the invariant's hooks a state to share.
	 *
	 * @param callable $initial Returns the starting state.
	 * @param string   $scope   `test` to start again before each test, or `run` to keep it for the whole run.
	 * @return WP_Invariant
	 */
	public function state( $initial, $scope = 'test' ) {
		self::check_scope( $scope );
		$this->state_initializer = $initial;
		$this->state_scope       = $scope;
		return $this;
	}

	/**
	 * Reports violations on calls made directly from test code too.
	 *
	 * By default a violation on a call whose caller is in test code is not
	 * reported, because tests deliberately give invalid input to public functions.
	 * The default answers whether WordPress itself ever breaks the invariant.
	 * Invariants about what a function returns hold whoever calls it, and use this.
	 *
	 * The check runs either way; only reporting depends on the caller, so that
	 * the stack is only looked at when there is something to report.
	 *
	 * @return WP_Invariant
	 */
	public function include_test_callers() {
		$this->include_test_callers = true;
		return $this;
	}

	/**
	 * Returns an object shared by any number of invariants, in any spec files.
	 *
	 * It is a plain object: give it whatever properties are needed. Its properties
	 * are removed before each test, or kept for the whole run.
	 *
	 * @param string $name  Name, such as `wpdb/prepared`.
	 * @param string $scope `test` to empty it before each test, or `run` to keep it.
	 * @return stdClass
	 */
	public static function shared( $name, $scope = 'test' ) {
		self::check_scope( $scope );
		if ( ! isset( self::$shared[ $name ] ) ) {
			self::$shared[ $name ] = array(
				'object' => new stdClass(),
				'scope'  => $scope,
			);
		} elseif ( self::$shared[ $name ]['scope'] !== $scope ) {
			throw new InvalidArgumentException( sprintf( 'The shared object "%s" is used with both the "test" and "run" scopes.', $name ) );
		}

		return self::$shared[ $name ]['object'];
	}

	/**
	 * Returns the strings in a value: the value itself, or the strings in its arrays, at any depth.
	 *
	 * Objects are left out, because reading their properties can run code, such
	 * as WP_Post::__get(), which would change what the suite does.
	 *
	 * @param mixed $value Any value.
	 * @param int   $limit Most strings to return.
	 * @return string[]
	 */
	public static function strings_in( $value, $limit = 200 ) {
		if ( is_string( $value ) ) {
			return array( $value );
		}

		$strings = array();
		if ( is_array( $value ) ) {
			array_walk_recursive(
				$value,
				static function ( $item ) use ( &$strings, $limit ) {
					if ( is_string( $item ) && count( $strings ) < $limit ) {
						$strings[] = $item;
					}
				}
			);
		}

		return $strings;
	}

	/**
	 * Returns a property of an object, even a private or protected one.
	 *
	 * It reads the property with reflection, which runs no code of the object's,
	 * not even __get().
	 *
	 * @param object $instance  Object.
	 * @param string $declaring The class which declares the property: a private property belongs to its class, not to subclasses.
	 * @param string $name      Property.
	 * @return mixed
	 */
	public static function property( $instance, $declaring, $name ) {
		$key = "{$declaring}::{$name}";
		if ( ! isset( self::$properties[ $key ] ) ) {
			self::$properties[ $key ] = new ReflectionProperty( $declaring, $name );
		}

		return self::$properties[ $key ]->getValue( $instance );
	}

	/**
	 * Returns the methods declared in the PHP files of a directory whose names match a pattern.
	 *
	 * The files are read as tokens, never included, so this works before WordPress
	 * loads, as spec files do.
	 *
	 * @param string $directory Directory, searched recursively.
	 * @param string $pattern   Regular expression for method names, such as `/_permissions_check$/`.
	 * @return string[] Each as `Class::method`, sorted.
	 */
	public static function methods_in( $directory, $pattern ) {
		$methods = array();
		foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS ) ) as $file ) {
			if ( 'php' !== $file->getExtension() ) {
				continue;
			}

			$tokens = token_get_all( file_get_contents( $file->getPathname() ) );
			$class  = null;
			$depth  = 0;
			$body   = null;
			foreach ( $tokens as $index => $token ) {
				if ( '{' === $token || ( is_array( $token ) && in_array( $token[0], array( T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES ), true ) ) ) {
					++$depth;
					continue;
				}
				if ( '}' === $token ) {
					--$depth;
					if ( $depth === $body ) {
						$class = null;
						$body  = null;
					}
					continue;
				}
				if ( ! is_array( $token ) ) {
					continue;
				}

				$next = self::next_token( $tokens, $index );
				if ( in_array( $token[0], array( T_CLASS, T_TRAIT, T_INTERFACE ), true ) && null === $class && is_array( $next ) && T_STRING === $next[0] ) {
					// Not `Foo::class`, nor `new class`, which are followed by other tokens.
					$class = $next[1];
					$body  = $depth;
				} elseif ( T_FUNCTION === $token[0] && null !== $class && $depth === $body + 1 && is_array( $next ) && T_STRING === $next[0] && preg_match( $pattern, $next[1] ) ) {
					$methods[] = "{$class}::{$next[1]}";
				}
			}
		}

		sort( $methods );
		return $methods;
	}

	/**
	 * Returns every named function and method declared in the PHP files of some directories.
	 *
	 * The files are read as tokens, never included, so this works before WordPress
	 * loads, as spec files do. Functions declared inside other blocks, such as
	 * `if ( ! function_exists() )`, are included. Closures, abstract methods,
	 * and the methods of interfaces and traits are not: they are never the target
	 * of a call, which for a trait method is the class which uses it.
	 *
	 * @param string[] $directories Directories, searched recursively.
	 * @param string[] $exclude     Directories and files inside them to leave out.
	 * @return array[] Each with `target` (`name` or `Class::name`), `class`, `function`,
	 *                 `file`, `line`, `static`, `visibility` (`public`, `protected` or
	 *                 `private`), `magic`, `private` (named with `_`, or documented
	 *                 `@access private`, on itself or its class) and `deprecated`.
	 */
	public static function functions_in( array $directories, array $exclude = array() ) {
		$exclude = array_map(
			static function ( $path ) {
				return rtrim( $path, '/' );
			},
			$exclude
		);

		$files = array();
		foreach ( $directories as $directory ) {
			if ( ! is_dir( $directory ) ) {
				continue;
			}
			foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS ) ) as $file ) {
				$path = $file->getPathname();
				if ( 'php' !== $file->getExtension() ) {
					continue;
				}
				foreach ( $exclude as $excluded ) {
					if ( $path === $excluded || 0 === strpos( $path, $excluded . '/' ) ) {
						continue 2;
					}
				}
				$files[] = $path;
			}
		}
		sort( $files );

		$functions = array();
		foreach ( $files as $file ) {
			foreach ( self::functions_in_file( $file ) as $function ) {
				$functions[] = $function;
			}
		}

		return $functions;
	}

	/**
	 * Returns the named functions and methods declared in one PHP file.
	 *
	 * @see WP_Invariant::functions_in()
	 *
	 * @param string $file PHP file.
	 * @return array[]
	 */
	private static function functions_in_file( $file ) {
		$tokens    = token_get_all( file_get_contents( $file ) );
		$functions = array();
		$depth     = 0;
		$class     = null;
		$body      = null;
		$doc       = '';
		$modifiers = array();

		foreach ( $tokens as $index => $token ) {
			// A doc comment and modifiers belong to the declaration which follows them.
			if ( '{' === $token || ';' === $token || '}' === $token ) {
				$doc       = '';
				$modifiers = array();
			}

			if ( '{' === $token || ( is_array( $token ) && in_array( $token[0], array( T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES ), true ) ) ) {
				++$depth;
				continue;
			}
			if ( '}' === $token ) {
				--$depth;
				if ( $depth === $body ) {
					$class = null;
					$body  = null;
				}
				continue;
			}
			if ( ! is_array( $token ) ) {
				continue;
			}

			switch ( $token[0] ) {
				case T_DOC_COMMENT:
					$doc = $token[1];
					continue 2;

				case T_PUBLIC:
				case T_PROTECTED:
				case T_PRIVATE:
				case T_STATIC:
				case T_ABSTRACT:
					$modifiers[ $token[0] ] = true;
					continue 2;
			}

			$next = self::next_token( $tokens, $index );
			if ( in_array( $token[0], array( T_CLASS, T_TRAIT, T_INTERFACE, T_ENUM ), true ) && null === $class && is_array( $next ) && T_STRING === $next[0] ) {
				// Not `Foo::class`, nor `new class`, which are followed by other tokens.
				$class = array(
					'name'    => $next[1],
					'methods' => T_CLASS === $token[0] || T_ENUM === $token[0],
					'private' => false !== stripos( $doc, '@access private' ),
				);
				$body  = $depth;
				continue;
			}
			if ( T_FUNCTION !== $token[0] ) {
				continue;
			}

			// A function which returns by reference has `&` before its name, a token of its own since PHP 8.1.
			$by_reference = '&' === $next || ( is_array( $next ) && T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG === $next[0] );
			$name_index   = $by_reference ? self::next_token_index( $tokens, self::next_token_index( $tokens, $index ) ) : self::next_token_index( $tokens, $index );
			$name         = null === $name_index ? null : $tokens[ $name_index ];
			if ( ! is_array( $name ) || T_STRING !== $name[0] ) {
				continue;
			}

			$method = null !== $class && $depth === $body + 1;
			if ( $method && ( ! $class['methods'] || isset( $modifiers[ T_ABSTRACT ] ) ) ) {
				continue;
			}

			$visibility = 'public';
			if ( $method && isset( $modifiers[ T_PROTECTED ] ) ) {
				$visibility = 'protected';
			} elseif ( $method && isset( $modifiers[ T_PRIVATE ] ) ) {
				$visibility = 'private';
			}

			$functions[] = array(
				'target'     => $method ? "{$class['name']}::{$name[1]}" : $name[1],
				'class'      => $method ? $class['name'] : null,
				'function'   => $name[1],
				'file'       => $file,
				'line'       => $token[2],
				'static'     => $method && isset( $modifiers[ T_STATIC ] ),
				'visibility' => $visibility,
				'magic'      => $method && 0 === strpos( $name[1], '__' ),
				'private'    => ( ! $method && '_' === $name[1][0] ) || false !== stripos( $doc, '@access private' ) || ( $method && $class['private'] ),
				'deprecated' => false !== stripos( $doc, '@deprecated' ),
			);
		}

		return $functions;
	}

	/**
	 * Returns an absolute path with its . and .. segments resolved, as the file system would resolve them without symbolic links.
	 *
	 * A relative path is read from the working directory.
	 *
	 * @param string $path A non-empty path.
	 * @return string
	 */
	public static function resolve_path( $path ) {
		if ( '/' !== $path[0] ) {
			$path = getcwd() . '/' . $path;
		}

		$segments = array();
		foreach ( explode( '/', $path ) as $segment ) {
			if ( '..' === $segment ) {
				array_pop( $segments );
			} elseif ( '' !== $segment && '.' !== $segment ) {
				$segments[] = $segment;
			}
		}

		return '/' . implode( '/', $segments );
	}

	/**
	 * Whether a resolved path is one of some directories, or inside one.
	 *
	 * @param string   $path        A path from resolve_path(), or a real path.
	 * @param string[] $directories Directories, each ending in a slash.
	 * @return bool
	 */
	public static function path_inside( $path, array $directories ) {
		foreach ( $directories as $directory ) {
			if ( 0 === strpos( rtrim( $path, '/' ) . '/', $directory ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether a path is inside some directories, as it resolves or through symbolic links.
	 *
	 * A path which does not exist yet is looked at through its nearest directory which does.
	 *
	 * @param string   $path        A non-empty path.
	 * @param string[] $directories Directories, as they resolve and as their real paths, each ending in a slash.
	 * @return bool
	 */
	public static function path_resolves_inside( $path, array $directories ) {
		$resolved = self::resolve_path( $path );
		if ( self::path_inside( $resolved, $directories ) ) {
			return true;
		}

		$existing = $resolved;
		$rest     = '';
		while ( '/' !== $existing && ! file_exists( $existing ) ) {
			$rest     = '/' . basename( $existing ) . $rest;
			$existing = dirname( $existing );
		}
		$real = realpath( $existing );

		return false !== $real && self::path_inside( rtrim( $real, '/' ) . $rest, $directories );
	}

	/**
	 * Returns a directory as it resolves and as its real path, each ending in a slash.
	 *
	 * @param string $directory A non-empty path.
	 * @return string[]
	 */
	public static function directory_forms( $directory ) {
		$forms = array( rtrim( self::resolve_path( $directory ), '/' ) . '/' );
		$real  = realpath( $directory );
		if ( false !== $real ) {
			$forms[] = rtrim( $real, '/' ) . '/';
		}

		return array_values( array_unique( $forms ) );
	}

	/**
	 * Returns a mark for the innermost running call to a function, which running() can check later.
	 *
	 * A hook can open a scope for a call this way without an after hook, which
	 * would not run when the call throws, as AJAX handlers do through wp_die() in
	 * tests.
	 *
	 * @param string $name Function or method, such as `do_action` or `WP_REST_Server::respond_to_request`.
	 * @return int|null Position of its frame, counted from the outermost, or null when it is not running.
	 */
	public static function frame_mark( $name ) {
		$frames = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS );
		$count  = count( $frames );
		foreach ( $frames as $index => $frame ) {
			if ( self::frame_name( $frame ) === $name ) {
				return $count - $index;
			}
		}

		return null;
	}

	/**
	 * Whether the call frame_mark() marked is still running.
	 *
	 * The call is still running when a frame of the same function is at the same
	 * position from the outermost frame. A later call to the same function at
	 * exactly the same depth, after the first one threw, looks the same.
	 *
	 * @param int    $mark From frame_mark().
	 * @param string $name The same function.
	 * @return bool
	 */
	public static function running( $mark, $name ) {
		$frames = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS );
		$index  = count( $frames ) - $mark;

		return $index >= 0 && isset( $frames[ $index ] ) && self::frame_name( $frames[ $index ] ) === $name;
	}

	/**
	 * Returns the names of the functions and methods running, innermost first.
	 *
	 * @return string[] Such as `wp_generate_password` or `WP_Session_Tokens::create`.
	 */
	public static function running_functions() {
		return array_map( array( __CLASS__, 'frame_name' ), debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS ) );
	}

	/**
	 * Returns the function or method of a frame, as `name` or `Class::name`.
	 *
	 * @param array $frame A frame from debug_backtrace().
	 * @return string
	 */
	private static function frame_name( array $frame ) {
		return isset( $frame['class'] ) ? $frame['class'] . '::' . $frame['function'] : $frame['function'];
	}

	/**
	 * Returns a key which changes whenever a PHP file in some directories is added, removed or changed.
	 *
	 * It is computed once per process for each set of directories.
	 *
	 * @param string[] $directories Directories, searched recursively.
	 * @return string
	 */
	public static function source_key( array $directories ) {
		static $keys = array();

		$id = implode( "\0", $directories );
		if ( ! isset( $keys[ $id ] ) ) {
			$parts = $directories;
			foreach ( $directories as $directory ) {
				if ( ! is_dir( $directory ) ) {
					continue;
				}
				foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS ) ) as $file ) {
					if ( 'php' === $file->getExtension() ) {
						$parts[] = $file->getPathname() . ':' . $file->getMTime();
					}
				}
			}
			$keys[ $id ] = md5( implode( "\n", $parts ) );
		}

		return $keys[ $id ];
	}

	/**
	 * Returns a value computed from the PHP files in some directories, from a cache in the temporary directory when they have not changed.
	 *
	 * Processes which PHPUnit starts for tests in separate processes reuse it.
	 *
	 * @param string   $name        Name of the cache, such as `switch-to-blog-callers`.
	 * @param string[] $directories Directories the value is computed from.
	 * @param callable $compute     Computes the value, which serialize() can store.
	 * @return mixed
	 */
	public static function cached( $name, array $directories, $compute ) {
		$key   = self::source_key( $directories );
		$cache = sys_get_temp_dir() . "/wp-invariants-{$name}-" . md5( implode( "\0", $directories ) ) . '.cache';

		$cached = is_readable( $cache ) ? unserialize( file_get_contents( $cache ), array( 'allowed_classes' => false ) ) : null; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- A cache this class wrote.
		if ( is_array( $cached ) && isset( $cached['key'] ) && $cached['key'] === $key && array_key_exists( 'value', $cached ) ) {
			return $cached['value'];
		}

		$value  = $compute();
		$cached = array(
			'key'   => $key,
			'value' => $value,
		);
		file_put_contents( $cache, serialize( $cached ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- A cache for this class.

		return $value;
	}

	/**
	 * Returns the named functions and methods, in the PHP files of some directories, whose own code calls a function.
	 *
	 * A call inside a closure belongs to the closure, not to the function around it.
	 * The files are read as tokens, never included.
	 *
	 * @param string[] $directories Directories, searched recursively.
	 * @param string   $callee      Name of the function called, such as `switch_to_blog`.
	 * @return string[] Targets, such as `wpmu_delete_blog` or `WP_Site_Query::get_sites`.
	 */
	public static function functions_calling( array $directories, $callee ) {
		$callers = array();
		foreach ( $directories as $directory ) {
			if ( ! is_dir( $directory ) ) {
				continue;
			}
			foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS ) ) as $file ) {
				if ( 'php' !== $file->getExtension() ) {
					continue;
				}
				$code = file_get_contents( $file->getPathname() );
				if ( false === stripos( $code, $callee ) ) {
					continue;
				}
				foreach ( self::functions_calling_in( $code, $callee ) as $caller ) {
					$callers[ $caller ] = true;
				}
			}
		}

		$callers = array_keys( $callers );
		sort( $callers );
		return $callers;
	}

	/**
	 * Returns the named functions and methods in some PHP code whose own code calls a function.
	 *
	 * @see WP_Invariant::functions_calling()
	 *
	 * @param string $code   PHP code.
	 * @param string $callee Name of the function called.
	 * @return string[]
	 */
	private static function functions_calling_in( $code, $callee ) {
		$tokens  = token_get_all( $code );
		$depth   = 0;
		$class   = null;
		$body    = null;
		$scopes  = array(); // Each open function: its name, or null for a closure, and the depth of its body.
		$pending = false;   // The name of a function whose body has not opened yet, or null for a closure.
		$callers = array();

		foreach ( $tokens as $index => $token ) {
			if ( '{' === $token || ( is_array( $token ) && in_array( $token[0], array( T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES ), true ) ) ) {
				++$depth;
				if ( false !== $pending ) {
					$scopes[] = array( $pending, $depth );
					$pending  = false;
				}
				continue;
			}
			if ( '}' === $token ) {
				if ( array() !== $scopes && end( $scopes )[1] === $depth ) {
					array_pop( $scopes );
				}
				--$depth;
				if ( $depth === $body ) {
					$class = null;
					$body  = null;
				}
				continue;
			}
			if ( ';' === $token && false !== $pending ) {
				// An abstract or interface method has no body.
				$pending = false;
				continue;
			}
			if ( ! is_array( $token ) ) {
				continue;
			}

			$next = self::next_token( $tokens, $index );
			if ( in_array( $token[0], array( T_CLASS, T_TRAIT, T_INTERFACE, T_ENUM ), true ) && null === $class && is_array( $next ) && T_STRING === $next[0] ) {
				$class = $next[1];
				$body  = $depth;
				continue;
			}

			if ( T_FUNCTION === $token[0] || T_FN === $token[0] ) {
				$by_reference = '&' === $next || ( is_array( $next ) && T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG === $next[0] );
				$name_index   = $by_reference ? self::next_token_index( $tokens, self::next_token_index( $tokens, $index ) ) : self::next_token_index( $tokens, $index );
				$name         = null === $name_index ? null : $tokens[ $name_index ];
				if ( T_FUNCTION === $token[0] && is_array( $name ) && T_STRING === $name[0] ) {
					$pending = null !== $class && $depth === $body + 1 ? "{$class}::{$name[1]}" : $name[1];
				} elseif ( T_FUNCTION === $token[0] ) {
					$pending = null;
				}
				continue;
			}

			if ( T_STRING !== $token[0] || 0 !== strcasecmp( $token[1], $callee ) || '(' !== $next || array() === $scopes ) {
				continue;
			}

			// Not a method, a declaration or a constant of that name.
			$previous = null;
			for ( $back = $index - 1; $back >= 0; $back-- ) {
				if ( ! is_array( $tokens[ $back ] ) || ! in_array( $tokens[ $back ][0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
					$previous = $tokens[ $back ];
					break;
				}
			}
			if ( is_array( $previous ) && in_array( $previous[0], array( T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW ), true ) ) {
				continue;
			}

			$scope = end( $scopes );
			if ( null !== $scope[0] ) {
				$callers[ $scope[0] ] = true;
			}
		}

		return array_keys( $callers );
	}

	/**
	 * Whether a filter or action has callbacks.
	 *
	 * It reads $wp_filter directly, and runs no hook.
	 *
	 * @param string $hook Filter or action.
	 * @return bool
	 */
	public static function has_callbacks( $hook ) {
		return isset( $GLOBALS['wp_filter'][ $hook ] ) && $GLOBALS['wp_filter'][ $hook ]->has_filters();
	}

	/**
	 * Sets which directories hold test code, and which hold the code under test.
	 *
	 * @param array $paths {
	 *     @type string[] $test Directories of test code. Calls from them are not checked by default.
	 *     @type string[] $core Directories of the code under test. Only these frames are kept in stacks.
	 * }
	 */
	public static function configure_paths( array $paths ) {
		self::$paths = array(
			'test' => array_map( array( __CLASS__, 'directory' ), $paths['test'] ),
			'core' => array_map( array( __CLASS__, 'directory' ), $paths['core'] ),
		);
	}

	/**
	 * Loads every spec file in a directory, and returns the specs for the gameshark extension.
	 *
	 * Each file returns a list of invariants. The id of each is the file's path
	 * under the directory, without `.php`, then its name: `wpdb/prepared-structure`.
	 *
	 * The environment variable WP_INVARIANTS_SKIP leaves files out: a comma-separated
	 * list of their paths under the directory, without `.php`, such as `pcre,xml`.
	 *
	 * @param string $directory Directory of spec files.
	 * @return array[] One gameshark spec for each hook: `id`, `target`, `when` and `hook`.
	 */
	public static function load_specs( $directory ) {
		$directory = rtrim( $directory, '/' );
		$files     = array();
		foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS ) ) as $file ) {
			if ( 'php' === $file->getExtension() ) {
				$files[] = $file->getPathname();
			}
		}
		sort( $files );

		$skip  = array_filter( array_map( 'trim', explode( ',', (string) getenv( 'WP_INVARIANTS_SKIP' ) ) ) );
		$specs = array();
		foreach ( $files as $file ) {
			$prefix = substr( $file, strlen( $directory ) + 1, -4 );
			if ( in_array( $prefix, $skip, true ) ) {
				continue;
			}

			$invariants = self::require_spec_file( $file );
			if ( ! is_array( $invariants ) ) {
				throw new UnexpectedValueException( sprintf( '%s must return a list of invariants.', $file ) );
			}

			foreach ( $invariants as $invariant ) {
				if ( ! $invariant instanceof self ) {
					throw new UnexpectedValueException( sprintf( '%s must return only invariants made with WP_Invariant::define().', $file ) );
				}

				$invariant->id = $prefix . '/' . $invariant->name;
				if ( isset( self::$invariants[ $invariant->id ] ) ) {
					throw new UnexpectedValueException( sprintf( 'Two invariants in %s are named "%s".', $file, $invariant->name ) );
				}
				if ( '' === $invariant->description ) {
					throw new UnexpectedValueException( sprintf( 'The invariant %s needs a description: ->describe( \'...\' ).', $invariant->id ) );
				}
				if ( array() === $invariant->hooks ) {
					throw new UnexpectedValueException( sprintf( 'The invariant %s has no hooks: add ->before() or ->after().', $invariant->id ) );
				}

				self::$invariants[ $invariant->id ] = $invariant;
				foreach ( $invariant->hooks as $index => $hook ) {
					$specs[] = array(
						'id'     => $invariant->id . '#' . $index,
						'target' => $hook['target'],
						'when'   => $hook['when'],
						'hook'   => $invariant->gameshark_hook( $index ),
					);
				}
			}
		}

		return $specs;
	}

	/**
	 * Starts a test: resets test-scoped state and shared objects, and attributes violations to the test.
	 *
	 * @param string $test Name of the test, as PHPUnit gives it.
	 */
	public static function start_test( $test ) {
		WP_Invariant_Violations::start_test( $test );
		WP_Invariant_Request::reset();

		foreach ( self::$invariants as $invariant ) {
			// A call which is still running when a test starts is not paired across tests.
			$invariant->pending = array();
			if ( 'test' === $invariant->state_scope ) {
				$invariant->state     = null;
				$invariant->has_state = false;
			}
		}

		foreach ( self::$shared as $shared ) {
			if ( 'test' === $shared['scope'] ) {
				foreach ( array_keys( get_object_vars( $shared['object'] ) ) as $property ) {
					unset( $shared['object']->$property );
				}
			}
		}
	}

	/**
	 * Returns every loaded invariant, by id.
	 *
	 * @return WP_Invariant[]
	 */
	public static function all() {
		return self::$invariants;
	}

	/**
	 * Returns the invariant's full id, such as `wpdb/prepared-structure`.
	 *
	 * @return string
	 */
	public function id() {
		return $this->id;
	}

	/**
	 * Returns what must hold.
	 *
	 * @return string
	 */
	public function description() {
		return $this->description;
	}

	/**
	 * Returns the invariant's hooks, each with `when` and `target`.
	 *
	 * @return array[]
	 */
	public function hooks() {
		return array_map(
			static function ( $hook ) {
				return array(
					'when'   => $hook['when'],
					'target' => $hook['target'],
				);
			},
			$this->hooks
		);
	}

	/**
	 * Whether a violation on a call is left out of the report, because test code made the call.
	 *
	 * @param WP_Invariant_Call $call The call.
	 * @return bool
	 */
	public function ignores( WP_Invariant_Call $call ) {
		return ! $this->include_test_callers && self::is_test_file( $call->caller()['file'] );
	}

	/**
	 * Returns the invariant's current state, for the self-tests.
	 *
	 * @return mixed
	 */
	public function current_state() {
		return $this->state;
	}

	/**
	 * Whether a file holds test code.
	 *
	 * @param string|null $file Path of a file.
	 * @return bool
	 */
	public static function is_test_file( $file ) {
		return self::path_in( $file, self::paths()['test'] );
	}

	/**
	 * Whether a file holds code under test.
	 *
	 * @param string|null $file Path of a file.
	 * @return bool
	 */
	public static function is_core_file( $file ) {
		return self::path_in( $file, self::paths()['core'] );
	}

	/**
	 * Returns a path relative to the repository, for reports.
	 *
	 * @param string $file Path of a file.
	 * @return string
	 */
	public static function relative_path( $file ) {
		$root = self::directory( dirname( __DIR__, 3 ) );
		return 0 === strpos( $file, $root ) ? substr( $file, strlen( $root ) ) : $file;
	}

	/**
	 * Runs one of the invariant's hooks for a call. The gameshark extension calls this through gameshark_hook().
	 *
	 * Nothing it throws reaches the code under test: a check which fails with an
	 * exception is recorded as a hook error. Set the environment variable
	 * WP_INVARIANTS_THROW to rethrow instead, for debugging.
	 *
	 * @internal
	 *
	 * @param int   $index  Which hook.
	 * @param array $params What the extension passed to the hook.
	 */
	public function run( $index, array $params ) {
		$hook = $this->hooks[ $index ];
		$call = null;

		try {
			$call   = new WP_Invariant_Call( $this, $hook['when'], $hook['target'], $params );
			$paired = $hook['paired'];

			if ( $paired ) {
				// The hooks for one call run at the same depth; deeper entries were left by calls which threw.
				$depth = count( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS ) );
				$key   = $hook['key'];
				if ( 'post' === $hook['when'] ) {
					while ( ! empty( $this->pending[ $key ] ) && end( $this->pending[ $key ] )[0] > $depth ) {
						array_pop( $this->pending[ $key ] );
					}
					if ( ! empty( $this->pending[ $key ] ) && end( $this->pending[ $key ] )[0] === $depth ) {
						$call->set_before( array_pop( $this->pending[ $key ] )[1] );
					}
				}
			}

			if ( ! $this->has_state && null !== $this->state_initializer ) {
				$this->state     = call_user_func( $this->state_initializer );
				$this->has_state = true;
			}

			$check  = $hook['check'];
			$result = $check( $call, $this->state );

			if ( $paired && 'pre' === $hook['when'] ) {
				$this->pending[ $key ][] = array( $depth, $result );
			}
		} catch ( Throwable $error ) {
			if ( getenv( 'WP_INVARIANTS_THROW' ) ) {
				throw $error;
			}

			WP_Invariant_Violations::record_hook_error( $this, $hook, $call, $error );
		}
	}

	/**
	 * Returns the callable given to the gameshark extension for one hook.
	 *
	 * @param int $index Which hook.
	 * @return Closure
	 */
	private function gameshark_hook( $index ) {
		$invariant = $this;
		return static function ( ...$params ) use ( $invariant, $index ) {
			$invariant->run( $index, $params );
		};
	}

	/**
	 * Adds a hook.
	 *
	 * @param string   $when   `pre` or `post`.
	 * @param string   $target Function or method.
	 * @param callable $check  The check.
	 * @return WP_Invariant
	 */
	private function add_hook( $when, $target, $check ) {
		if ( ! is_string( $target ) || ! preg_match( '/^\\\\?[A-Za-z_][A-Za-z0-9_\\\\]*(::[A-Za-z_][A-Za-z0-9_]*)?$/', $target ) ) {
			throw new InvalidArgumentException( sprintf( 'The invariant "%s" has a target which is not a function or Class::method name.', $this->name ) );
		}
		if ( ! is_callable( $check ) ) {
			throw new InvalidArgumentException( sprintf( 'The invariant "%s" has a check for %s which is not callable.', $this->name, $target ) );
		}

		$target        = ltrim( $target, '\\' );
		$key           = strtolower( $target );
		$this->hooks[] = array(
			'when'   => $when,
			'target' => $target,
			'check'  => $check,
			'key'    => $key,
			'paired' => false,
		);

		// A before and an after hook on the same target are paired; work that out once, not on every call.
		$whens = array();
		foreach ( $this->hooks as $hook ) {
			if ( $hook['key'] === $key ) {
				$whens[ $hook['when'] ] = true;
			}
		}
		if ( isset( $whens['pre'], $whens['post'] ) ) {
			foreach ( $this->hooks as $index => $hook ) {
				if ( $hook['key'] === $key ) {
					$this->hooks[ $index ]['paired'] = true;
				}
			}
		}

		return $this;
	}

	/**
	 * Requires a spec file in its own scope.
	 *
	 * @param string $file Path of the spec file.
	 * @return mixed What the file returns.
	 */
	private static function require_spec_file( $file ) {
		return require $file;
	}

	/**
	 * Rejects a scope other than `test` or `run`.
	 *
	 * @param string $scope Scope.
	 */
	private static function check_scope( $scope ) {
		if ( 'test' !== $scope && 'run' !== $scope ) {
			throw new InvalidArgumentException( 'A scope is "test" or "run".' );
		}
	}

	/**
	 * Returns the configured directories, defaulting to the WordPress test suite.
	 *
	 * @return array
	 */
	private static function paths() {
		if ( null === self::$paths ) {
			$root = dirname( __DIR__, 3 );
			self::configure_paths(
				array(
					'test' => array( $root . '/tests' ),
					'core' => array( $root . '/src', $root . '/build' ),
				)
			);
		}

		return self::$paths;
	}

	/**
	 * Whether a file is in one of the directories.
	 *
	 * @param string|null $file        Path of a file.
	 * @param string[]    $directories Directories, each ending in a slash.
	 * @return bool
	 */
	private static function path_in( $file, array $directories ) {
		if ( ! is_string( $file ) ) {
			return false;
		}

		foreach ( $directories as $directory ) {
			if ( 0 === strpos( $file, $directory ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Returns the next token which is not whitespace or a comment.
	 *
	 * @param array $tokens Tokens from token_get_all().
	 * @param int   $index  Position to look after.
	 * @return array|string|null
	 */
	private static function next_token( array $tokens, $index ) {
		$next = self::next_token_index( $tokens, $index );
		return null === $next ? null : $tokens[ $next ];
	}

	/**
	 * Returns where the next token is which is not whitespace or a comment.
	 *
	 * @param array    $tokens Tokens from token_get_all().
	 * @param int|null $index  Position to look after.
	 * @return int|null
	 */
	private static function next_token_index( array $tokens, $index ) {
		if ( null === $index ) {
			return null;
		}

		$count = count( $tokens );
		for ( $i = $index + 1; $i < $count; $i++ ) {
			if ( ! is_array( $tokens[ $i ] ) || ! in_array( $tokens[ $i ][0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
				return $i;
			}
		}

		return null;
	}

	/**
	 * Normalizes a directory to its real path, ending in a slash.
	 *
	 * @param string $directory Directory.
	 * @return string
	 */
	private static function directory( $directory ) {
		$real = realpath( $directory );
		return rtrim( false === $real ? $directory : $real, '/' ) . '/';
	}
}
