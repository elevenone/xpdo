<?php

namespace aphp\XPDO;

# ------------------------------------
# Header
# ------------------------------------

abstract class ModelH {
	// STATIC Override
	static function keyField() {
		return ModelConfig::$keyField;
	}
	static function jsonFields() {
		return [];
	}
	static function dateFields() {
		return [];
	}
	static function keyFieldAutoIncrement() {
		return true;
	}
	static function tableName() {
		$rf = new \ReflectionClass( get_called_class() );
		return \strtolower( $rf->getShortName() );
	}
	static function database() {
		return Database::getInstance();
	}
	static function relations() {
		return [];
	}
	// STATIC
	static $_fields = []; // [className] = [ field, field, field ]

	/*
	abstract static function newModel() { } // Model
	abstract static function lastId() { } // value OR null

	abstract  static function loadWithWhereQuery($SQLWhere, $params = [],  $fields = []) { } // Model or null
	abstract  static function loadAllWithWhereQuery($SQLWhere, $params = [],  $fields = []) { } // [ Model ] or (ModelConfig::$fetchAll_nullValue)
	abstract  static function loadAll($fields = []) { } // [ Model ] or (ModelConfig::$fetchAll_nullValue)

	abstract static function loadWithStatement(Statement $statement, $fields = []) { } // Model or null ,
	abstract static function loadAllWithStatement(Statement $statement, $fields = []) { }  // [ Model ] or (ModelConfig::$fetchAll_nullValue)

	abstract static function loadWithField( $field, $value, $fields = [],  $newModel = false ) { } // Model or null
	abstract static function loadWithId( $value, $fields = [],  $newModel = false ) { } // Model or null
	*/

	// PUBLIC
	public $_model_isLoadedWithDB = false;
	protected $_model_isDeleted = false;
	public $_model_loadedFields = null;
	protected $_model_relation = null;

	abstract public function save($fields = []);
	abstract public function delete();

	abstract public function relation(); // Relation
}

# ------------------------------------
# Model
# ------------------------------------

class Model extends ModelH {

	const LOADED_WITH_DB = true;

	// PRIVATE

	private static function statementWithWhereQuery($SQLWhere, $params,  $fields) { // Statement
		$db = static::database();
		$select = Utils::selectColumns($fields);
		$table = Utils::quoteColumns( static::tableName() );
		if ($SQLWhere === null) {
			$statement = $db->prepare( "SELECT $select FROM $table" );
		} else {
			$statement = $db->prepare( "SELECT $select FROM $table WHERE $SQLWhere" );
		}
		// conversion
		$statement->setJSONColumns( static::jsonFields() );
		$statement->setDateColumns( static::dateFields() );
		// --
		if (count($params) > 0) {
			if (strpos($statement->_query, '?') !== false) {
				$statement->bindValues($params);
			} else {
				$statement->bindNamedValues($params);
			}
		}
		return $statement;
	}

	private static function getTableFields($fields) {
		if (count($fields) == 0) {
			$class = get_called_class();
			if (!isset(self::$_fields[$class])) {
				$db = static::database();
				if ($db->isSQLite()) {
					self::$_fields[$class] = Utils::SQLite_tableColumns( $db->getPDO(), static::tableName() );
				}
				if ($db->isMYSQL()) {
					self::$_fields[$class] = Utils::MYSQL_tableColumns( $db->getPDO(), static::tableName() );
				}
			}
			return self::$_fields[$class];
		}
		return $fields;
	}

	// Constructor

	public function __construct($isLoadedWithDB = false, $_model_loadedFields = []) {
		$this->_model_isLoadedWithDB = $isLoadedWithDB;
		$this->_model_loadedFields = count($_model_loadedFields) == 0 ? null : $_model_loadedFields;
	}

	public function __clone() {
		$this->_model_isDeleted = false;
		$this->_model_isLoadedWithDB = false;
		if (is_string( static::keyField() )) {
			$this->{ static::keyField() } = null;
		}
    }

	// STATIC

	static function newModel() {
		$class = get_called_class();
		$model = new $class(false);
		return $model;
	}

	static function lastId() {
		if (static::keyField() == null) {
			return null;
		}
		$db = static::database();
		return $db->fetchLastId(static::tableName(), static::keyField());
	}

	// --

	static function loadWithWhereQuery($SQLWhere, $params = [],  $fields = []) { // Model or null
		$SQLWhere .= ' LIMIT 1';
		$s = self::statementWithWhereQuery($SQLWhere, $params, $fields);
		return $s->fetchObject( get_called_class() , [ self::LOADED_WITH_DB, $fields ]);
	}

	static function loadAllWithWhereQuery($SQLWhere, $params = [],  $fields = []) { // [ Model ] or (ModelConfig::$fetchAll_nullValue)
		$statement = self::statementWithWhereQuery($SQLWhere, $params, $fields);
		return $statement->fetchAllObjects( get_called_class() , [ self::LOADED_WITH_DB, $fields ]);
	}

	static function loadAll($fields = []) { // [ Model ] or (ModelConfig::$fetchAll_nullValue)
		$statement = self::statementWithWhereQuery(null, [], $fields);
		return $statement->fetchAllObjects( get_called_class() , [ self::LOADED_WITH_DB, $fields ]);
	}

	static function loadWithStatement(Statement $statement, $fields = []) { // Model or null
		// conversion
		$statement->setJSONColumns( static::jsonFields() );
		$statement->setDateColumns( static::dateFields() );
		// --
		return $statement->fetchObject( get_called_class() , [ self::LOADED_WITH_DB, $fields ]);
	}

	static function loadAllWithStatement(Statement $statement, $fields = []) { // [ Model ] or (ModelConfig::$fetchAll_nullValue)
		// conversion
		$statement->setJSONColumns( static::jsonFields() );
		$statement->setDateColumns( static::dateFields() );
		// --
		return $statement->fetchAllObjects( get_called_class() , [ self::LOADED_WITH_DB, $fields ]);
	}

	static function loadWithField( $field, $value, $fields = [],  $newModel = false ) { // Model or null
		$SQLWhere = Utils::quoteColumns($field) . ' = ?';
		$model = static::loadWithWhereQuery($SQLWhere, [ $value ], $fields);
		if ($model == null && $newModel) {
			$model = static::newModel();
			$model->{ $field } = $value;
		}
		return $model;
	}

	static function loadWithId( $value, $fields = [],  $newModel = false ) { // Model or null
		if (static::keyField() == null) {
			return null;
		}
		return static::loadWithField( static::keyField(), $value, $fields, $newModel );
	}

	// PUBLIC

	public function save($fields = []) {
		if ($this->_model_isDeleted) {
			// object is deleted
			return;
		}
		if ((count($fields) == 0) && $this->_model_loadedFields) {
			$fields = $this->_model_loadedFields;
		}
		$fields = static::getTableFields($fields);
		if ($this->_model_isLoadedWithDB) {
			$this->save_update($fields);
		} else {
			$this->save_insert($fields);
			$this->_model_isLoadedWithDB = true;
		}
	}

	public function delete() {
		$keyField = $this->{ static::keyField() };
		if ($keyField == null) {
			return false;
		}
		$table = Utils::quoteColumns( static::tableName() );
		$where = Utils::quoteColumns(static::keyField()) . ' = :keyvalue';
		$query = "DELETE FROM $table WHERE $where";
		$db = static::database();
		$db->prepare($query)
			->bindNamedValue('keyvalue', $keyField)
			->execute();
		$this->_model_isDeleted = true;
	}

	// PROTECTED

	protected function save_update(&$fields) {
		$db = static::database();
		$set = [];
		$params = [];
		$i = 0;
		$keyField = static::keyField();
		if (!is_string($keyField)) {
			throw XPDOException::keyFieldIsNull( get_class() );
		}
		foreach ($fields as $column) {
			if ($column == $keyField) {
				continue; // existing key field cannot be overridden
			}
			$param = 'param' . $i;
			$set[] = Utils::quoteColumns($column) . ' = :' . $param;
			$params[$param] = $this->{ $column };
			$i++;
		}
		if (count($set) == 0) {
			throw XPDOException::emptyUpdateFields(static::class, 'save_update');
		}
		$where = Utils::quoteColumns($keyField) . ' = :keyvalue';
		$params['keyvalue'] = $this->{ $keyField };
		$table = Utils::quoteColumns( static::tableName() );
		$setStr = implode(', ', $set);
		$query = "UPDATE $table SET $setStr WHERE $where";
		$db->prepare($query)
			->bindNamedValues($params)
			->execute();
	}

	protected function save_insert(&$fields) {
		$db = static::database();
		$table = Utils::quoteColumns( static::tableName() );
		$keyField = static::keyField();
		$keyFieldAutoIncrement = static::keyFieldAutoIncrement();
		$values = [];
		$params = [];
		$columns = [];
		$i = 0;
		foreach ($fields as $column) {
			if ($column == $keyField && $keyFieldAutoIncrement) {
				continue; // existing auto increment key field is cannot be writing
			}
			$param = 'param' . $i;
			$values[] = ':' . $param;
			$columns[] = $column;
			if (!isset($this->{ $column })) {
				$this->{ $column } = null;
			}
			$params[$param] = $this->{ $column };
			$i++;
		}
		$columns = Utils::selectColumns($columns);
		$valuesStr = implode(', ', $values);
		$query = "INSERT INTO $table ( $columns ) VALUES ( $valuesStr )";
		$db->prepare($query)
			->bindNamedValues($params)
			->execute();

		if (is_string($keyField) && $keyFieldAutoIncrement) {
			$this->{ $keyField } = static::lastId();
		}
	}

	public function relation() // Relation
	{
		if (!$this->_model_relation) {
			$relationClass = ModelConfig::$relationClass;
			$this->_model_relation = new $relationClass(get_class($this));
		}
		$this->_model_relation->__model = $this;
		return $this->_model_relation;
	}

	// Magic methods

	public function __get( $id ) {
		if (ModelConfig::$relationMagicMethods) {
			$r = static::relations();
			if (isset($r[$id])) {
				return $this->relation()->{ $id };
			}
		}
		if (isset($this->id)) {
			return $this->id;
		}
		return null;
	}

	public function __set( $id, $val ) {
		if (ModelConfig::$relationMagicMethods) {
			$r = static::relations();
			if (isset($r[$id])) {
				$this->relation()->{ $id } = $val;
				return;
			}
		}
		$this->{ $id } = $val;
	}

	public function __call($name, $arguments) {
		if (ModelConfig::$relationMagicMethods) {
			if (in_array($name, ['toManyAdd', 'toManyAddAll', 'toManyRemove', 'toManyRemoveAll', 'relation_orderBy'])) {
				if (strpos($name, 'relation_') === 0) {
					$name = substr($name, strlen('relation_'));
				}
				return \call_user_func_array([$this->relation(), $name], $arguments);
			}
		}
		return null;
    }
}