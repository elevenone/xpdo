<?php 
namespace aphp\XPDO;
use aphp\Foundation\AException;

class XPDOException extends AException {
	// Utils_Exception
	static function tableFieldsError($table) { // OK
		return self::create(sprintf('table = %s', $table));
	}
	static function jsonEncodeException($value) { // OK
		return self::create(print_r($value, true));
	}
	static function jsonDecodeException($value) { // OK
		return self::create(print_r($value, true));
	}
	// DateTime_Exception
	static function invalidTimestamp($timestamp) { // OK
		return self::create(sprintf('timestamp, must be int %s', print_r($timestamp, true)));
	}
	// Database_Exception
	static function pdoIsNull() { // OK
		return self::create('Database->_pdo = null');
	}
	// Statement_Exception
	static function bindInvalidType($value, $query) { // OK
		return self::create(sprintf('value = %s, query = "%s"', var_export($value, true), $query));
	}
	static function bindNamedBlobAsFilenameException($name, $query, $filename) { // OK
		return self::create(sprintf('name = %s, query = "%s", filename = %s', $name, $query, $filename));
	}
	// Model_Exception
	static function keyFieldIsNull($className) { // OK
		return self::create(sprintf('className = %s', $className)); 
	}
	static function emptyUpdateFields($className, $method) { // OK
		return self::create(sprintf('update fields count = 0; className = %s; method = %s; existing key field cannot be overridden', $className, $method));
	}
	// Relation_Exception
	static function invalidSyntaxRelation($relation) { // OK
		return self::create(sprintf('relation syntax: "%s", example: "%s"', $relation, 'this->%field% [*-**|**] %namespace\class%->%field%'));
	}
	static function invalidSyntax2Relation($relation) { // OK
		return self::create(sprintf('relation syntax (manyToMany): "%s", example: "%s"', print_r($relation, true), 'this->%field% *-** %namespace\class%->%field% , %namespace\class%->%field% ** %namespace\class%->%field%'));
	}
	static function toManyRelationIsReadonly($className, $relation) { // OK
		return self::create(sprintf('"%s->relation()->%s" toMany relation is readonly, please use additional api to edit objects"', $className, $relation));
	}
	static function undefinedRelation($relation) { // OK
		return self::create(sprintf('"%s" undefined relation', $relation));
	}
	static function nullField($field) { // OK
		return self::create(sprintf('Field value "%s" is null', $field));
	}
}
