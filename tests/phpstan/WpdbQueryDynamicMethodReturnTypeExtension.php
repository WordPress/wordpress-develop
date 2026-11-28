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
use PHPStan\Type\Constant\ConstantBooleanType;
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
 * Types the results of `wpdb::get_var()`, `get_row()`, `get_col()`, and
 * `get_results()` from the columns their query selects, and the result of
 * `query()` from the statement its query begins with.
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
 * string or null. A drop-in replacing it could return other types. It also
 * assumes that the query is run as written, which a callback on the `query`
 * filter, which `wpdb::query()` applies to every query, could change.
 *
 * A `*` over a core table is expanded to the columns of the core schema only
 * with the `wpdbTablesMatchSchema` parameter, since a plugin may have added
 * columns to the table on a site. It is true for core, which is analyzed
 * against its own schema.
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
	 * @param bool               $tables_match_schema  Whether the core tables have only the columns of the core schema,
	 *                                                 so that a `*` over one of them can be expanded. True for core
	 *                                                 itself, but not where plugins may have added columns.
	 */
	public function __construct( TypeStringResolver $type_string_resolver, bool $tables_match_schema ) {
		$this->type_string_resolver = $type_string_resolver;
		$this->parser               = new WpdbSelectParser( new WpdbSchema(), $tables_match_schema );
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
	 * Types the result of a query method call from its query.
	 *
	 * @link https://developer.wordpress.org/reference/classes/wpdb/query/
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

		if ( 'query' === strtolower( $methodReflection->getName() ) ) {
			$is_ddl = self::is_ddl_query( $query );
			if ( null === $is_ddl ) {
				return null;
			}

			// The declared type is narrowed to the true or the number of rows, either of which can be false instead.
			$declared = ParametersAcceptorSelector::selectFromArgs( $scope, $args, $methodReflection->getVariants() )->getReturnType();
			$narrowed = TypeCombinator::remove( $declared, $is_ddl ? new IntegerType() : new ConstantBooleanType( true ) );
			return $narrowed->equals( $declared ) ? null : $narrowed;
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
				/*
				 * For an `$output` of more than one value, the declared conditional return type joins the row arrays of
				 * `ARRAY_A` and `ARRAY_N` into one `array<mixed>`, which could then only be refined as one or the other.
				 */
				if ( isset( $args[1] ) && 1 !== count( $scope->getType( $args[1]->value )->getConstantStrings() ) ) {
					return null;
				}

				$declared = ParametersAcceptorSelector::selectFromArgs( $scope, $args, $methodReflection->getVariants() )->getReturnType();
				$refined  = $this->refine_rows( $declared, $columns, $result['complete'] );
				return $refined->equals( $declared ) ? null : $refined;
		}
	}

	/**
	 * Determines whether `query()` returns true for a query, rather than the number of rows, from the statement it
	 * begins with.
	 *
	 * For `CREATE`, `ALTER`, `TRUNCATE`, and `DROP`, `query()` returns the result of `mysqli_query()`, which is
	 * true for these statements, other than for the few which give a result set, like `ALTER TABLE ... ANALYZE
	 * PARTITION`, for which it is a `mysqli_result`. That is left out, as the declared type leaves it out.
	 * For any other statement, it returns the number of rows affected or selected. On an error, it returns
	 * false before either. It tells the statements apart as this does, by the keyword at the start of the query.
	 *
	 * @param string $query Query, as reconstructed by `WpdbQueryVisitor`.
	 * @return bool|null Whether the query is one of those statements, or null if the start of the query is unknown.
	 */
	private static function is_ddl_query( string $query ): ?bool {
		$ddl_keywords = array( 'create', 'alter', 'truncate', 'drop' );

		// The text before the first placeholder, which is all that is known of the start of the query.
		$position = strpos( $query, '{' );
		$known    = (string) preg_replace( '/^\s*/', '', false === $position ? $query : substr( $query, 0, $position ) );

		if ( preg_match( '/^(?:' . implode( '|', $ddl_keywords ) . ')\s/i', $known ) ) {
			return true;
		}

		// Unknown text could begin the query, or complete one of the keywords, as in `DROP{?}`.
		if ( false !== $position ) {
			if ( '' === $known ) {
				return null;
			}
			foreach ( $ddl_keywords as $keyword ) {
				if ( 0 === stripos( $keyword, $known ) ) {
					return null;
				}
			}
		}

		return false;
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
		$variants = self::get_row_variants( $columns );

		$types   = array();
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
				if ( ! $complete ) {
					return null;
				}
				$indexes = array_keys( $variants[0] );
				// Or an index past the last column, for which the value is null.
				$types[] = new NullType();
			}
		}

		foreach ( $indexes as $index ) {
			foreach ( $variants as $variant ) {
				if ( ! isset( $variant[ $index ] ) ) {
					// Past the known columns, or an index with no column, for which the value is null.
					if ( ! $complete ) {
						return null;
					}
					$types[] = new NullType();
					continue;
				}
				$type     = $variant[ $index ];
				$non_null = TypeCombinator::removeNull( $type );
				$types[]  = TypeCombinator::intersect( $non_null, new StringType(), new AccessoryNonEmptyStringType() );
				if ( ! $type->isNull()->no() || ! $non_null->isSuperTypeOf( new ConstantStringType( '' ) )->no() ) {
					$types[] = new NullType();
				}
			}
		}

		return TypeCombinator::union( ...$types );
	}

	/**
	 * Gets the values of a row as a list, as `ARRAY_N` and the column index of `get_var()` and `get_col()` see them.
	 *
	 * A column only present on some sites, like the multisite columns of the users table, shifts the positions of
	 * those after it. So there are two variants of the row, one with those columns and one without. They are taken to
	 * be all present or all absent together, as the multisite columns are.
	 *
	 * The values are those of the row object's properties, so there is one for each column name: a later column with
	 * the name of an earlier one replaces its value, in its position.
	 *
	 * @param list<array{ name: string|null, type: Type, optional: bool }> $columns Columns.
	 * @return non-empty-list<list<Type>> Types of the values, in each variant of the row.
	 */
	private static function get_row_variants( array $columns ): array {
		$with    = self::get_row_values( $columns );
		$without = self::get_row_values( array_values( array_filter( $columns, static fn ( array $column ): bool => ! $column['optional'] ) ) );
		return count( $with ) === count( $without ) ? array( $with ) : array( $with, $without );
	}

	/**
	 * Gets the types of the values of a row, as `array_values( get_object_vars( $row ) )` gives them.
	 *
	 * A column whose name is unknown is taken to have a name of its own.
	 *
	 * @param list<array{ name: string|null, type: Type, optional: bool }> $columns Columns present in the row.
	 * @return list<Type> Types of the values.
	 */
	private static function get_row_values( array $columns ): array {
		$values    = array();
		$positions = array();
		foreach ( $columns as $column ) {
			$name = $column['name'];
			if ( null !== $name && isset( $positions[ $name ] ) ) {
				$values[ $positions[ $name ] ] = $column['type'];
				continue;
			}
			if ( null !== $name ) {
				$positions[ $name ] = count( $values );
			}
			$values[] = $column['type'];
		}
		// Replacing a value at its position keeps the keys sequential.
		return array_values( $values );
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
			$list_rows = array();
			foreach ( self::get_row_variants( $columns ) as $variant ) {
				$builder = ConstantArrayTypeBuilder::createEmpty();
				foreach ( $variant as $index => $type ) {
					$builder->setOffsetValueType( new ConstantIntegerType( $index ), $type );
				}
				$list_rows[] = $builder->getArray();
			}
			$list_row = TypeCombinator::union( ...$list_rows );
		}

		/*
		 * `OBJECT_K` keys the rows by the first value of the row object, with null as ''. That is the value of the
		 * last column with the first column's name, and on some sites, of another column if the first is not there.
		 */
		$keys = array();
		foreach ( self::get_row_variants( $columns ) as $variant ) {
			if ( ! isset( $variant[0] ) ) {
				$keys = array();
				break;
			}
			$key = $variant[0];
			if ( ! $key->isNull()->no() ) {
				$key = TypeCombinator::union( TypeCombinator::removeNull( $key ), new ConstantStringType( '' ) );
			}
			$keys[] = $key->toArrayKey();
		}
		$key = array() !== $keys ? TypeCombinator::union( ...$keys ) : null;

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
