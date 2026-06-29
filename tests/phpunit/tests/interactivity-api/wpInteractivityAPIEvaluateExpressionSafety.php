<?php
/**
 * Unit tests for WP_Interactivity_API::evaluate_expression_safety().
 *
 * @package WordPress
 * @subpackage Interactivity API
 * @since 6.9.0
 *
 * @group interactivity-api
 *
 * @coversDefaultClass WP_Interactivity_API
 */
class Tests_Interactivity_API_EvaluateExpressionSafety extends WP_UnitTestCase {
	/**
	 * Instance under test.
	 *
	 * @var WP_Interactivity_API
	 */
	protected $interactivity;

	/**
	 * Set up.
	 */
	public function set_up() {
		parent::set_up();
		$this->interactivity = new WP_Interactivity_API();
	}

	/**
	 * Invokes the private evaluate_expression_safety() helper.
	 *
	 * @param string $php_expr Post-regex-transform PHP expression.
	 * @return int 1 (VALID), 0 (UNSUPPORTED), -1 (INVALID).
	 */
	private function evaluate_expression_safety( string $php_expr ): int {
		$method = new ReflectionMethod( $this->interactivity, 'evaluate_expression_safety' );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}
		return $method->invoke( $this->interactivity, $php_expr );
	}

	/**
	 * @ticket 60356
	 */
	public function test_valid_read_only_expressions() {
		$this->assertSame( 1, $this->evaluate_expression_safety( '$__st[\'count\'] !== $__ctx[\'n\']' ) );
		$this->assertSame( 1, $this->evaluate_expression_safety( '$__st[\'count\'] > 0 ? $__st[\'name\'] : \'no\'' ) );
		$this->assertSame( 1, $this->evaluate_expression_safety( 'true && false' ) );
		$this->assertSame( 1, $this->evaluate_expression_safety( 'null' ) );
	}

	/**
	 * @ticket 60356
	 */
	public function test_unsupported_assignments_and_calls() {
		$this->assertSame( 0, $this->evaluate_expression_safety( '$__st[\'x\'] = 5' ) );
		$this->assertSame( 0, $this->evaluate_expression_safety( '$__st[\'count\'] += 1' ) );
		$this->assertSame( 0, $this->evaluate_expression_safety( '$__st[\'count\']++' ) );
		$this->assertSame( 0, $this->evaluate_expression_safety( 'doSomething()' ) );
		$this->assertSame( 0, $this->evaluate_expression_safety( '$__st[\'zero\'] ??= 1' ) );
		$this->assertSame( 0, $this->evaluate_expression_safety( 'customConstant' ) );
	}

	/**
	 * Representative dangerous and PHP-specific constructs should all be
	 * rejected as INVALID so they never reach eval().
	 *
	 * @ticket 60356
	 *
	 * @return array<string, array{0:string}>
	 */
	public function data_invalid_dangerous_or_php_specific_constructs(): array {
		return array(
			'object operator'             => array( '$__st->method()' ),
			'nullsafe object operator'    => array( '$__st?->method()' ),
			'fully qualified static call' => array( '\\Foo\\Bar::baz()' ),
			'qualified static call'       => array( 'Foo\\Bar::baz()' ),
			'relative static call'        => array( 'namespace\\Foo::baz()' ),
			'nested eval'                 => array( 'eval("bad")' ),
			'exit'                        => array( 'exit(1)' ),
			'include'                     => array( 'include "file.php"' ),
			'include once'                => array( 'include_once "file.php"' ),
			'require'                     => array( 'require "file.php"' ),
			'require once'                => array( 'require_once "file.php"' ),
			'new'                         => array( 'new stdClass()' ),
			'clone'                       => array( 'clone $__st' ),
			'function literal'            => array( 'function() { return 1; }' ),
			'arrow function literal'      => array( 'fn() => 1' ),
			'echo'                        => array( 'echo 1' ),
			'print'                       => array( 'print 1' ),
			'unset'                       => array( 'unset($__st["x"])' ),
			'throw'                       => array( 'throw new Exception()' ),
			'global'                      => array( 'global $foo' ),
			'static'                      => array( 'static $foo' ),
			'return'                      => array( 'return $__st["x"]' ),
			'yield'                       => array( 'yield 1' ),
			'yield from'                  => array( 'yield from array(1,2)' ),
			'attribute'                   => array( '#[Attr] 1' ),
			'spaceship'                   => array( '$__st["a"] <=> $__st["b"]' ),
			'php array syntax'            => array( 'array(1, 2, 3)' ),
			'php double arrow'            => array( 'array("k" => 1)' ),
			'logical and keyword'         => array( '$__ctx["x"] and $__ctx["y"]' ),
			'logical or keyword'          => array( '$__ctx["x"] or $__ctx["y"]' ),
			'logical xor keyword'         => array( '$__ctx["x"] xor $__ctx["y"]' ),
			'array cast'                  => array( '(array)$__ctx["n"]' ),
			'bool cast'                   => array( '(bool)$__ctx["n"]' ),
			'int cast'                    => array( '(int)$__ctx["n"]' ),
			'double cast'                 => array( '(float)$__ctx["n"]' ),
			'object cast'                 => array( '(object)$__ctx["n"]' ),
			'string cast'                 => array( '(string)$__ctx["n"]' ),
			'unset cast'                  => array( '(unset)$__ctx["n"]' ),
			'empty construct'             => array( 'empty($__ctx["x"])' ),
			'isset construct'             => array( 'isset($__ctx["x"])' ),
			'concat dot'                  => array( '$__st["a"] . $__st["b"]' ),
			'concat equal'                => array( '$__st["a"] .= $__st["b"]' ),
			'backticks'                   => array( '`id`' ),
			'magic constant'              => array( '__FILE__' ),
			'magic dir constant'          => array( '__DIR__' ),
			'magic line constant'         => array( '__LINE__' ),
			'match expression'            => array( 'match ($__st["count"]) { 1 => 1, default => 0 }' ),
			'list destructuring'          => array( 'list($__st["a"]) = array(1)' ),
			'php close tag'               => array( '1 ?>' ),
		);
	}

	/**
	 * @ticket 60356
	 *
	 * @dataProvider data_invalid_dangerous_or_php_specific_constructs
	 */
	public function test_invalid_dangerous_or_php_specific_constructs( string $php_expr ) {
		$this->assertSame( -1, $this->evaluate_expression_safety( $php_expr ) );
	}

	/**
	 * @ticket 60356
	 *
	 * Regression for the token_get_all() open-tag bug: without prepending
	 * `<?php ` a valid expression tokenizes as a single T_INLINE_HTML token.
	 */
	public function test_valid_expression_is_parsed_as_php_code_not_inline_html() {
		$this->assertSame( 1, $this->evaluate_expression_safety( '$__st[\'count\'] !== $__ctx[\'n\']' ) );
	}
}