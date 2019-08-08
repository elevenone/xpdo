<?php 

namespace aphp\XPDO;

/*
Utils::jsonEncode($value)
Utils::jsonDecode($value)
Utils::quoteColumns($columnOrColumns)
Utils::selectColumns($columns)
Utils::SQLite_tableColumns(\PDO $pdo, $table)
Utils::MYSQL_tableColumns(\PDO $pdo, $table)

Utils::sort(&$models, $field, $asc = true)
Utils::sort2(&$models, $field1, $field2, $asc1 = true, $asc2 = true)
*/

class Utils {
	// CONST
	const QUOTE = '`';
	static $_logContext = [
		'traceLevel' => 10,
		'traceTopFiles' => [ 'Statement.php', 'Database.php', 'Model.php' ]
	];
	
	static $_jsonBindDetection = true;
	static $_jsonEncodeOptions = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT;

	// STATIC
	static function jsonEncode($value) {
		$value = json_encode($value, self::$_jsonEncodeOptions);
		if ($value === false) {
			throw Utils_Exception::jsonEncodeException($value);
		}
		return $value;
	}
	
	static function jsonDecode($value) {
		$value = json_decode($value, true);
		if ($value === null) {
			throw Utils_Exception::jsonDecodeException($value);
		}
		return $value;
	}
	
	static function quoteColumns($columnOrColumns) {
		if (is_array($columnOrColumns)) {
			$columns = [];
			foreach ($columnOrColumns as $val) {
				if (substr($val,0,1) != self::QUOTE) {
					$columns[] = self::QUOTE . $val . self::QUOTE;
				} else {
					$columns[] = $val;
				}
			}
			return $columns;
		} elseif (substr($columnOrColumns,0,1) != self::QUOTE) {
			return self::QUOTE . $columnOrColumns . self::QUOTE;
		}
		return $columnOrColumns;
	}

	static function selectColumns($columns) { // $columns = array
		if (count($columns) > 0) {
			$columns = self::quoteColumns($columns);
			return implode(', ', $columns);
		}
		return '*';
	}

	static function SQLite_tableColumns(\PDO $pdo, $table) {
		$s = $pdo->prepare("PRAGMA table_info( '$table' )");
		$s->execute();
		$array = $s->fetchAll(\PDO::FETCH_ASSOC); // cid, name ....
		if (is_array($array)) {
			$columns = [];
			foreach ($array as $values) {
				$columns[] = $values['name'];
			}
			return $columns;
		}
		throw Utils_Exception::tableColumns($table);
	}

	static function MYSQL_tableColumns(\PDO $pdo, $table) {
		$s = $pdo->prepare("DESC `$table`");
		$s->execute();
		$array = $s->fetchAll(\PDO::FETCH_ASSOC); // Field, Type ..
		if (is_array($array)) {
			$columns = [];
			foreach ($array as $values) {
				$columns[] = $values['Field'];
			}
			return $columns;
		}
		throw Utils_Exception::tableColumns($table);
	}

	static function interpolateQuery($query, $params) {
		$keys = array();
		$values = $params;
	
		# build a regular expression for each parameter
		foreach ($params as $key => $value) {
			if (is_string($key)) {
				$keys[] = '/:'.$key.'/';
			} else {
				$keys[] = '/[?]/';
			}
	
			if (is_array($value))
				$values[$key] = implode(',', $value);
	
			if (is_null($value))
				$values[$key] = 'NULL';
		}
		// Walk the array to see if we can add single-quotes to strings
		array_walk($values, create_function('&$v, $k', 'if (!is_numeric($v) && $v!="NULL") $v = "\'".$v."\'";'));
	
		$query = preg_replace($keys, $values, $query, 1, $count);
	
		return $query;
	}

	static function sort(&$models, $field, $asc = true) 
	{
		usort($models, function ($a, $b) use ($field, $asc){
			return $asc ? strnatcmp($a->{$field}, $b->{$field}) : strnatcmp($b->{$field}, $a->{$field});
		});
	}

	static function sort2(&$models, $field1, $field2, $asc1 = true, $asc2 = true) 
	{
		usort($models, function ($a, $b) use ($field1, $field2, $asc1, $asc2) {
			$rdiff = $asc1 ? strnatcmp($a->{$field1}, $b->{$field1}) : strnatcmp($b->{$field1}, $a->{$field1});
			if ($rdiff) return $rdiff;
			return $asc2 ? strnatcmp($a->{$field2}, $b->{$field2}) : strnatcmp($b->{$field2}, $a->{$field2});
		});
	}
}