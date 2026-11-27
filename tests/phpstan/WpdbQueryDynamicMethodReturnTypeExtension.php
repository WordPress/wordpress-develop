<?php
/**
 * PHPStan dynamic return type extension that types the results of the `wpdb`
 * query methods from the columns their query selects.
 *
 * @package WordPress
 */

declare(strict_types=1);

namespace WordPress\PHPStan;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\PhpDoc\TypeStringResolver;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Type\Accessory\AccessoryArrayListType;
use PHPStan\Type\Accessory\AccessoryNonEmptyStringType;
use PHPStan\Type\ArrayType;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\IntegerType;
use PHPStan\Type\MixedType;
use PHPStan\Type\NullType;
use PHPStan\Type\ObjectShapeType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\TypeTraverser;

/**
 * Types the results of `wpdb::get_var()`, `get_row()`, `get_col()` and
 * `get_results()` from the columns their query selects.
 *
 * For example, given:
 *
 *     $post = $wpdb->get_row( $wpdb->prepare( "SELECT ID, post_title FROM $wpdb->posts WHERE ID = %d", $id ) );
 *
 * PHPStan will infer `(object{ ID: decimal-int-string, post_title: string }&stdClass)|null`
 * rather than `stdClass|null`, with no need for an inline `@var` tag.
 *
 * The query's text comes from `WpdbQueryVisitor`, or from the query's type
 * when that is a constant string. `WpdbSelectParser` reads the columns from it,
 * typed from the core schema by `WpdbSchema`.
 *
 * The method's declared return type is kept, and refined: each `stdClass` row
 * in it becomes an object shape, each row array an array shape, and each
 * value the column's type. So the cases in which a method returns null or an
 * empty array, which depend on its arguments rather than the query, are as
 * declared. Where the columns are not known well enough to refine a type, it
 * is left as declared.
 *
 * This assumes `wpdb` itself, with mysqli, which returns every value as a
 * string or null. A drop-in replacing it could return other types.
 */
class WpdbQueryDynamicMethodReturnTypeExtension implements DynamicMethodReturnTypeExtension {

	/**
	 * Resolver for the column types, which are PHPDoc type strings.
	 *
	 * @var TypeStringResolver
	 */
	private TypeStringResolver $type_string_resolver;

	/**
	 * Parser for the select list of a query.
	 *
	 * @var WpdbSelectParser
	 */
	private WpdbSelectParser $parser;

	/**
	 * Resolved column types, keyed by PHPDoc type string.
	 *
	 * @var array<string, Type>
	 */
	private array $types = array();

	/**
	 * Constructor.
	 *
	 * @param TypeStringResolver $type_string_resolver Resolver for PHPDoc type strings.
	 */
	public function __construct( TypeStringResolver $type_string_resolver ) {
		$this->type_string_resolver = $type_string_resolver;
		$this->parser               = new WpdbSelectParser( new WpdbSchema() );
	}

	/**
	 * Gets the class whose methods this extension types.
	 *
	 * @return string
	 */
	public function getClass(): string {
		return 'wpdb';
	}

	/**
	 * Determines whether this extension types the given method.
	 *
	 * @param MethodReflection $methodReflection Method being analyzed.
	 * @return bool
	 */
	public function isMethodSupported( MethodReflection $methodReflection ): bool {
		return in_array( strtolower( $methodReflection->getName() ), WpdbQueryVisitor::METHODS, true );
	}

	/**
	 * Types the result of a query method call from its query's columns.
	 *
	 * @link https://developer.wordpress.org/reference/classes/wpdb/get_var/
	 * @link https://developer.wordpress.org/reference/classes/wpdb/get_row/
	 * @link https://developer.wordpress.org/reference/classes/wpdb/get_col/
	 * @link https://developer.wordpress.org/reference/classes/wpdb/get_results/
	 *
	 * @param MethodReflection $methodReflection Method being analyzed.
	 * @param MethodCall       $methodCall       The method call node.
	 * @param Scope            $scope            Analysis scope.
	 * @return Type|null Type, or null to use the declared return type.
	 */
	public function getTypeFromMethodCall( MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope ): ?Type {
		$args = $methodCall->getArgs();
		if ( ! isset( $args[0] ) ) {
			return null;
		}

		$query     = null;
		$constants = $scope->getType( $args[0]->value )->getConstantStrings();
		if ( 1 === count( $constants ) ) {
			$query = $constants[0]->getValue();
		} else {
			$attribute = $methodCall->getAttribute( WpdbQueryVisitor::ATTRIBUTE );
			if ( is_string( $attribute ) ) {
				$query = $attribute;
			}
		}
		if ( null === $query ) {
			return null;
		}

		$result = $this->parser->parse( $query );
		if ( null === $result || array() === $result['columns'] ) {
			return null;
		}

		$columns = array();
		foreach ( $result['columns'] as $column ) {
			$columns[] = array(
				'name'     => $column['name'],
				'type'     => $this->resolve_type( $column['type'] ),
				'optional' => $column['optional'],
			);
		}

		switch ( strtolower( $methodReflection->getName() ) ) {
			case 'get_var':
				$value = $this->get_positional_value_type( $columns, $result['complete'], $args[1]->value ?? null, $scope );
				return null !== $value ? TypeCombinator::addNull( $value ) : null;

			case 'get_col':
				$value = $this->get_positional_value_type( $columns, $result['complete'], $args[1]->value ?? null, $scope );
				return null !== $value ? TypeCombinator::intersect( new ArrayType( new IntegerType(), $value ), new AccessoryArrayListType() ) : null;

			default:
				$declared = ParametersAcceptorSelector::selectFromArgs( $scope, $args, $methodReflection->getVariants() )->getReturnType();
				$refined  = $this->refine_rows( $declared, $columns, $result['complete'] );				return $refined->equals( $declared ) ? null : $refined;
		}
	}

	/**
	 * Gets the type `get_var()` or `get_col()` returns for the value in a row.
	 *
	 * Both return null for an empty string as well as for SQL NULL, since `get_col()` uses `get_var()`.
	 *
	 * @param list<array{ name: string|null, type: Type, optional: bool }> $columns  Columns.
	 * @param bool                                                         $complete Whether the columns are all known.
	 * @param \PhpParser\Node\Expr|null                                    $x        The column index argument, if any.
	 * @param Scope                                                        $scope    Analysis scope.
	 * @return Type|null Type, or null if unknown.
	 */
	private function get_positional_value_type( array $columns, bool $complete, ?\PhpParser\Node\Expr $x, Scope $scope ): ?Type {
		$positional = $this->get_positional_columns( $columns, $complete );

		$indexes = array( 0 );
		if ( null !== $x ) {
			$indexes = array();
			foreach ( $scope->getType( $x )->getConstantScalarValues() as $value ) {
				if ( ! is_int( $value ) ) {
					return null;
				}
				$indexes[] = $value;
			}
			if ( array() === $indexes ) {
				// Any column.
				if ( count( $positional ) !== count( $columns ) || ! $complete ) {
					return null;
				}
				$indexes = array_keys( $columns );
			}
		}

		$types = array();
		foreach ( $indexes as $index ) {
			if ( ! isset( $positional[ $index ] ) ) {
				// Past the known columns, or an index with no column, for which the value is null.
				if ( $complete && count( $positional ) === count( $columns ) ) {
					$types[] = new NullType();
					continue;
				}
				return null;
			}
			$type     = $positional[ $index ]['type'];
			$non_null = TypeCombinator::removeNull( $type );
			$types[]  = TypeCombinator::intersect( $non_null, new StringType(), new AccessoryNonEmptyStringType() );
			if ( ! $type->isNull()->no() || ! $non_null->isSuperTypeOf( new ConstantStringType( '' ) )->no() ) {
				$types[] = new NullType();
			}
		}

		return TypeCombinator::union( ...$types );
	}

	/**
	 * Gets the columns whose positions are known.
	 *
	 * A column only present on some sites shifts the positions of those after it, and the columns after an unknown
	 * one are unknown.
	 *
	 * @param list<array{ name: string|null, type: Type, optional: bool }> $columns  Columns.
	 * @param bool                                                         $complete Whether the columns are all known.
	 * @return list<array{ name: string|null, type: Type, optional: bool }> Columns, from the first.
	 */
	private function get_positional_columns( array $columns, bool $complete ): array {
		$positional = array();
		foreach ( $columns as $index => $column ) {
			if ( $column['optional'] ) {
				// Its own position is known, but whether it is there is not.
				$is_last = ( count( $columns ) - 1 === $index ) && $complete;
				if ( ! $is_last ) {
					break;
				}
			}
			$positional[] = $column;
		}
		return $positional;
	}

	/**
	 * Refines the rows in a declared return type of `get_row()` or `get_results()`.
	 *
	 * @param Type                                                         $declared Declared return type.
	 * @param list<array{ name: string|null, type: Type, optional: bool }> $columns  Columns.
	 * @param bool                                                         $complete Whether the columns are all known.
	 * @return Type
	 */
	private function refine_rows( Type $declared, array $columns, bool $complete ): Type {
		$properties = $complete ? $this->get_properties( $columns ) : null;

		$object_row = null;
		$assoc_row  = null;
		if ( null !== $properties ) {
			$shape    = array();
			$optional = array();
			$builder  = ConstantArrayTypeBuilder::createEmpty();
			foreach ( $properties as $name => $property ) {
				// A numeric name, like that of `SELECT 1`, is an integer key.
				$name           = (string) $name;
				$shape[ $name ] = $property['type'];
				if ( $property['optional'] ) {
					$optional[] = $name;
				}
				$builder->setOffsetValueType( ( new ConstantStringType( $name ) )->toArrayKey(), $property['type'], $property['optional'] );
			}
			$object_row = TypeCombinator::intersect( new ObjectShapeType( $shape, $optional ), new ObjectType( 'stdClass' ) );
			$assoc_row  = $builder->getArray();
		}

		$list_row = null;
		if ( $complete ) {
			$builder = ConstantArrayTypeBuilder::createEmpty();
			foreach ( $columns as $index => $column ) {
				$builder->setOffsetValueType( new ConstantIntegerType( $index ), $column['type'], $column['optional'] );
			}
			$list_row = $builder->getArray();
		}

		// `OBJECT_K` keys the rows by the first column's value, with null as ''.
		$key = null;
		if ( isset( $columns[0] ) && ! $columns[0]['optional'] ) {
			$key = $columns[0]['type'];
			if ( ! $key->isNull()->no() ) {
				$key = TypeCombinator::union( TypeCombinator::removeNull( $key ), new ConstantStringType( '' ) );
			}
			$key = $key->toArrayKey();
		}

		return self::replace_rows(
			$declared,
			array(
				'object' => $object_row,
				'assoc'  => $assoc_row,
				'list'   => $list_row,
				'key'    => $key,
			)
		);
	}

	/**
	 * Replaces the rows in a declared return type of `get_row()` or `get_results()`.
	 *
	 * An array is rebuilt rather than traversed, since traversing a `list<mixed>` would reach its `list` accessory
	 * apart from its value type, and the accessory alone also reads as an array of mixed.
	 *
	 * @param Type                                                                   $type Type.
	 * @param array{ object: Type|null, assoc: Type|null, list: Type|null, key: Type|null } $rows {
	 *     Types to replace with, each null to leave as is.
	 *
	 *     @type Type|null $object Row object, replacing `stdClass`.
	 *     @type Type|null $assoc  Associative row array, replacing an `array<mixed>`.
	 *     @type Type|null $list   List row array, replacing a `list<mixed>`.
	 *     @type Type|null $key    Key of the rows keyed by `OBJECT_K`, replacing the key of an `array<stdClass>`.
	 * }
	 * @return Type
	 */
	private static function replace_rows( Type $type, array $rows ): Type {
		return TypeTraverser::map(
			$type,
			static function ( Type $type, callable $traverse ) use ( $rows ): Type {
				if ( $type instanceof ObjectType && 'stdClass' === $type->getClassName() ) {
					return $rows['object'] ?? $type;
				}

				if ( ! $type->isArray()->yes() ) {
					return $traverse( $type );
				}

				$key     = $type->getIterableKeyType();
				$value   = $type->getIterableValueType();
				$is_list = $type->isList()->yes();

				// A row array.
				if ( $value instanceof MixedType ) {
					return ( $is_list ? $rows['list'] : $rows['assoc'] ) ?? $type;
				}

				// Rows keyed by `OBJECT_K`, which unlike the other results may have string keys.
				if ( $value instanceof ObjectType && 'stdClass' === $value->getClassName() && ! $key->isString()->no() ) {
					$key = $rows['key'] ?? $key;
				}

				$array = new ArrayType( $key, self::replace_rows( $value, $rows ) );
				return $is_list ? TypeCombinator::intersect( $array, new AccessoryArrayListType() ) : $array;
			}
		);
	}

	/**
	 * Gets the properties of a row object, which are the columns by name.
	 *
	 * A later column replaces an earlier one with the same name, as in the row object.
	 *
	 * @param list<array{ name: string|null, type: Type, optional: bool }> $columns Columns.
	 * @return array<string, array{ type: Type, optional: bool }>|null Properties, or null if a column's name is unknown.
	 */
	private function get_properties( array $columns ): ?array {
		$properties = array();
		foreach ( $columns as $column ) {
			if ( null === $column['name'] ) {
				return null;
			}

			$earlier = $properties[ $column['name'] ] ?? null;
			if ( null !== $earlier && $column['optional'] ) {
				// It only replaces the earlier one when present.
				$properties[ $column['name'] ] = array(
					'type'     => TypeCombinator::union( $earlier['type'], $column['type'] ),
					'optional' => $earlier['optional'],
				);
				continue;
			}

			$properties[ $column['name'] ] = array(
				'type'     => $column['type'],
				'optional' => $column['optional'],
			);
		}
		return $properties;
	}

	/**
	 * Resolves a PHPDoc type string, once.
	 *
	 * @param string $type_string PHPDoc type.
	 * @return Type
	 */
	private function resolve_type( string $type_string ): Type {
		if ( ! isset( $this->types[ $type_string ] ) ) {
			$this->types[ $type_string ] = $this->type_string_resolver->resolve( $type_string );
		}
		return $this->types[ $type_string ];
	}
}
