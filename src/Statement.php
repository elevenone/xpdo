<?php 

namespace aphp\XPDO;

# ------------------------------------
# Header
# ------------------------------------

abstract class StatementH {
	const TYPE_JSON = 'json';
	const TYPE_DATE = 'date';
	
	public $_pdoStatement; // PDOStatement
	public $_query;
	public $_database; // aphp\XPDO\Database
	public $_params = [];
	protected $_jsonColumns = [];
	protected $_dateColumns = [];

	abstract public function bindNamedValue($name, $value); // Statement
	abstract public function bindNamedValues($params); // Statement, $params = array()
	abstract public function bindValues($params);      // Statement, $params = array()
	
	abstract public function setJSONColumns($colums); // $colums = array()
	abstract public function setDateColumns($colums);

	abstract public function execute(); // Statement

	abstract public function fetchAll(); // array[row][column] OR null
	abstract public function fetchLine(); // array[column] OR null
	abstract public function fetchOne(); // value OR null
	abstract public function fetchLastId($table, $idColumn); // value OR null

	// Blob interface
	abstract public function bindNamedBlob($name, &$blob); // Statement
	abstract public function bindNamedBlobAsFilename($name, $filename); // Statement
	abstract public function fetchBlob($columnName, &$blob); // true OR false

	// Object Interface
	abstract public function fetchObject($className, $constructorParams = null); // object OR null
	abstract public function fetchAllObjects($className, $constructorParams = null); // [object] OR null
}

# ------------------------------------
# Statement
# ------------------------------------

class Statement extends StatementH {
	use \Psr\Log\LoggerAwareTrait; // trait

	public function bindNamedValue($name, $value) {
		$type = $this->getPDOParamType($value);
		// conversion
		$this->paramEncode($value, $type);
		// --
		$this->_pdoStatement->bindValue($name, $value, $type);
		if ($this->logger) {
			$this->_params[ $name ] = $value;
		}
		return $this;
	}

	public function bindNamedValues($params) { // $params = array()
		foreach ($params as $name=>$value) {
			$this->bindNamedValue($name, $value);
		}
		return $this;
	} 

	public function bindValues($params) {  // $params = array()
		foreach ($params as &$value) {
			$type = $this->getPDOParamType($value);
			// conversion
			$this->paramEncode($value, $type);
		}
		// --
		$this->executeValues = $params;
		if ($this->logger) {
			$this->_params = $params;
		}
		return $this;
	}

	// --
	
	public function setJSONColumns($colums) {
		if (is_array($colums)) {
			$this->_jsonColumns = $colums;
		}
		return $this;
	}

	public function setDateColumns($colums) {
		if (is_array($colums)) {
			$this->_dateColumns = $colums;
		}
		return $this;
	}

	public function execute() {
		if ($this->logger) {
			$query = Utils::interpolateQuery($this->_query, $this->_params);
			$this->logger->info($query, Utils::$_logContext);
		}
		if (is_array($this->executeValues)) {
			$this->_pdoStatement->execute( $this->executeValues );
			$this->executeValues = null;
		} else {
			$this->_pdoStatement->execute();
		}
		return $this;
	}

	// --

	public function fetchAll() {
		$this->execute();
		$array = $this->_pdoStatement->fetchAll(\PDO::FETCH_ASSOC);
		if (is_array($array) && count($array) > 0) {
			// conversion
			$this->columnsDecode($array, 'fetchAll', $this->_jsonColumns, function($v) { return Utils::jsonDecode($v); });
			$this->columnsDecode($array, 'fetchAll', $this->_dateColumns, function($v) { return new DateTime($v); });
			// ---
			return $array;
		}
		return null;
	}

	public function fetchLine() {
		$this->execute();
		$array = $this->_pdoStatement->fetch(\PDO::FETCH_ASSOC);
		if (is_array($array)) {
			// conversion
			$this->columnsDecode($array, 'fetchLine', $this->_jsonColumns, function($v) { return Utils::jsonDecode($v); });
			$this->columnsDecode($array, 'fetchLine', $this->_dateColumns, function($v) { return new DateTime($v); });
			// ---
			return $array;
		}
		return null;
	}

	public function fetchOne() {
		$this->execute();
		$array = $this->_pdoStatement->fetch(\PDO::FETCH_NUM);
		if (is_array($array) && count($array)>0) {
			// conversion
			$this->columnsDecode($array[0], 'fetchOne', $this->_jsonColumns, function($v) { return Utils::jsonDecode($v); });
			$this->columnsDecode($array[0], 'fetchOne', $this->_dateColumns, function($v) { return new DateTime($v); });
			// ---
			return $array[0];
		}
		return null;
	}

	public function fetchLastId($table, $idColumn) {
		return $this->_database->fetchLastId($table, $idColumn);
	}

	// Blob interface

	public function bindNamedBlob($name, &$blob) {
		$this->_pdoStatement->bindParam($name, $blob, \PDO::PARAM_LOB);
	}

	public function bindNamedBlobAsFilename($name, $filename) {
		$fp = @fopen($filename, 'rb'); // read binary
		if ($fp === false) {
			throw XPDOException::bindNamedBlobAsFilenameException($name, $this->_query, $filename);
		}
		$this->bindNamedBlob($name, $fp);
	}

	public function fetchBlob($columnName, &$blob) { // true OR false
		$this->execute();
		$this->_pdoStatement->bindColumn($columnName, $blob, \PDO::PARAM_LOB);
		return $this->_pdoStatement->fetch(\PDO::FETCH_BOUND);
	}

	// Object Interface

	public function fetchObject($className, $constructorParams = null) {
		$this->execute();
		if ($constructorParams) {
			$object = $this->_pdoStatement->fetchObject($className, $constructorParams);
		} else {
			$object = $this->_pdoStatement->fetchObject($className);
		}
		if (is_a($object, $className)) {
			// conversion
			$this->columnsDecode($object, 'fetchObject', $this->_jsonColumns, function($v) { return Utils::jsonDecode($v); });
			$this->columnsDecode($object, 'fetchObject', $this->_dateColumns, function($v) { return new DateTime($v); });
			// --
			return $object;
		}
		return null;
	}

	public function fetchAllObjects($className, $constructorParams = null) {
		$this->execute();
		if ($constructorParams) {
			$array = $this->_pdoStatement->fetchAll(\PDO::FETCH_CLASS, $className, $constructorParams);
		} else {
			$array = $this->_pdoStatement->fetchAll(\PDO::FETCH_CLASS, $className);
		}
		if (
			is_array($array) && 
			count($array) > 0 && 
			is_a($array[0], $className)
		) {
			// conversion
			$this->columnsDecode($array, 'fetchAllObjects', $this->_jsonColumns, function($v) { return Utils::jsonDecode($v); });
			$this->columnsDecode($array, 'fetchAllObjects', $this->_dateColumns, function($v) { return new DateTime($v); });
			// --
			return $array;
		}
		return null;
	}

	// PROTECTED
	
	protected $executeValues = null;
	protected function getPDOParamType( &$value ) {
		if (is_int($value))    return \PDO::PARAM_INT;
		if (is_string($value)) return \PDO::PARAM_STR;
		if (is_float($value)) return \PDO::PARAM_STR; // FLOAT is not exists
		if ($value == null)   return \PDO::PARAM_NULL;
		if (Utils::$_jsonBindDetection && is_array($value)) return self::TYPE_JSON;
		if (is_a($value, DateTime::class)) return self::TYPE_DATE;
		throw XPDOException::bindInvalidType($value, $this->_query);
	}
	
	protected function columnsDecode( &$fetchResult , $callMethod, $columns, $decoderClosure ) {
		if (count($columns) > 0) {
			if ($callMethod == 'fetchAll' || $callMethod == 'fetchAllObjects') {
				foreach ($fetchResult as &$value) {
					$this->columnsDecode($value, 'fetchObject', $columns, $decoderClosure);
				}
			} elseif ($callMethod == 'fetchObject' || $callMethod == 'fetchLine') {
				foreach ($columns as $column) {
					if (is_object($fetchResult)) {
						// object
						$fetchResult->{ $column } = call_user_func_array($decoderClosure, [ $fetchResult->{ $column } ]);
					} else {
						// array
						$fetchResult[ $column ] = call_user_func_array($decoderClosure, [ $fetchResult[ $column ] ]);
					}
				}
			} elseif ($callMethod == 'fetchOne') {
				$fetchResult = call_user_func_array($decoderClosure, [ $fetchResult ]);
			}
		}
	}

	protected function paramEncode(&$value, &$type) {
		if ($type === self::TYPE_JSON) {
			$value = Utils::jsonEncode($value);
			$type = $this->getPDOParamType($value);
		} elseif ($type === self::TYPE_DATE) {
			$value = $value->getText();
			$type = $this->getPDOParamType($value);
		}
	}
}