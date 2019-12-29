<?php

namespace aphp\XPDO;

# ------------------------------------
# Header
# ------------------------------------

abstract class DatabaseH {
	abstract public function SQLiteInit($fileName);
	abstract public function MySQLInit($user, $password, $dbname, $host = 'localhost');

	abstract public function prepare($queryString); // aphp\XPDO\Statement;
	abstract public function exec($queryString); // int
	abstract public function fetchLastId($table, $idColumn); // value OR null ??

	abstract public function isMYSQL();
	abstract public function isSQLite();
	abstract public function getPDO(); // PDO

	abstract public function setFetchCacheEnabled($enabled);
	abstract public function resetFetchCache();

	// Transaction
	public function transactionBegin() {
		return $this->getPDO()->beginTransaction();
	}
	public function transactionCommit() {
		return $this->getPDO()->commit();
	}
	public function transactionRollBack() {
		return $this->getPDO()->rollBack();
	}
}

# ------------------------------------
# Database
# ------------------------------------

class Database extends DatabaseH {
	use \aphp\Foundation\TraitSingleton; // trait
	use \Psr\Log\LoggerAwareTrait; // trait

	// PROTECTED
	protected $_pdo = null; // PDO
	protected $_isMYSQL = false;
	protected $_isSQLite = false;
	public $_fetchCache = null;

	// PUBLIC
	public function SQLiteInit($fileName) {
		$this->_pdo = new \PDO('sqlite:'.$fileName);
		$this->_pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
		$this->_isSQLite = true;
	}

	public function MySQLInit($user, $password, $dbname, $host = 'localhost') {
		$this->_pdo = new \PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $password);
		$this->_pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
		$this->_isMYSQL = true;
	}

	public function prepare($queryString) {
		$pdo = $this->getPDO();
		// -- cacheLogic
		$selectQuery = is_array($this->_fetchCache) && preg_match('#^SELECT#i', $queryString);
		$queryHash = '';
		if ($selectQuery) {
			$queryHash = md5($queryString);
			if ($statement = @$this->_fetchCache[$queryHash]) {
				return $statement;
			}
		} else {
			$this->resetFetchCache();
		}
		// --
		$statement = new Statement();
		$statement->_query = $queryString;
		$statement->_database = $this;
		$statement->_pdoStatement = $pdo->prepare($queryString);
		if ($this->logger) {
			$statement->setLogger($this->logger);
		}
		// -- cacheLogic
		if ($selectQuery) {
			$this->_fetchCache[$queryHash] = $statement;
			$statement->_cached = true;
		}
		// --
		return $statement;
	}

	public function exec($queryString) {
		$pdo = $this->getPDO();
		return $pdo->exec($queryString);
	}

	public function fetchLastId($table, $idColumn) {
		$id    = Utils::quoteColumns($idColumn);
		$table = Utils::quoteColumns($table);
		$statement = $this->prepare("SELECT $id FROM $table ORDER BY $id DESC LIMIT 1");
		return $statement->fetchOne();
	}

	public function isMYSQL() {
		return $this->_isMYSQL;
	}

	public function isSQLite() {
		return $this->_isSQLite;
	}

	public function getPDO() {
		if ($this->_pdo) {
			return $this->_pdo;
		}
		throw XPDOException::pdoIsNull();
	}

	public function setFetchCacheEnabled($enabled) {
		$this->_fetchCache = $enabled ? [] : null;
	}

	public function resetFetchCache() {
		if (is_array($this->_fetchCache)) {
			$this->_fetchCache = [];
		}
	}
}