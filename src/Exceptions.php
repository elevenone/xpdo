<?php 

namespace aphp\XPDO;

class BaseException extends \RuntimeException {
	public static function createException( /* ... */ ) {
		$args = func_get_args();
		$text = $args[0];
		unset($args[0]);
		return new static(sprintf($text, ...$args)); // PHP 5.6+
	}
}

class Utils_Exception extends BaseException {
	public static function tableFields($table) {
		return self::createException('tableFields error, table = %s', $table);
	}
	public static function jsonEncodeException($value) {
		return self::createException('jsonEncodeException, value = %s', print_r($value, true));
	}
	public static function jsonDecodeException($value) {
		return self::createException('jsonDecodeException, value = %s', print_r($value, true));
	}
}

class DateTime_Exception extends BaseException {
	public static function invalidTimestamp($timestamp) {
		return self::createException('invalid timestamp, must be int %s', print_r($timestamp, true));
	}
}

class Database_Exception extends BaseException {
	public static function pdoIsNull() {
		return self::createException('Database->_pdo = null');
	}
}

class Statement_Exception extends BaseException {
	public static function bindInvalidType($value, $query) {
		return self::createException('bindInvalidType: value = %s, query = "%s"', var_export($value, true), $query);
	}
	public static function bindNamedBlobAsFilenameException($name, $query, $filename) {
		return self::createException('bindNamedBlobAsFilename: name = %s, query = "%s", filename = %s', $name, $query, $filename);
	}	
}

class Model_Exception extends BaseException {
	public static function keyFieldIsNull($className) {
		return self::createException('keyFieldIsNull: className = %s', $className);
	}
	public static function emptyUpdateFields($className, $method) {
		return self::createException('update fields count = 0; className = %s; method = %s; existing key field cannot be overridden', $className, $method);
	}
}

class Relation_Exception extends BaseException {
	public static function invalidSyntax($relation) {
		return self::createException('invalid relation syntax: "%s", example: "%s"', $relation, 'this->%field% [*-**|**] %namespace\class%->%field%');
	}
	public static function invalidSyntax2($relation) {
		return self::createException('invalid relation syntax (manyToMany): "%s", example: "%s"', print_r($relation, true), 'this->%field% *-** %namespace\class%->%field% , %namespace\class%->%field% ** %namespace\class%->%field%');
	}
	public static function toManyRelationIsReadonly($className, $relation) {
		return self::createException('"%s->relation()->%s" toMany relation is readonly, please use additional api to edit objects"', $className, $relation);
	}
	public static function undefined($relation) {
		return self::createException('"%s" undefined relation', $relation);
	}
	public static function nullField($field) {
		return self::createException('Field value "%s" is null', $field);
	}
}