<?php

namespace aphp\XPDO;

# ------------------------------------
# Header
# ------------------------------------

abstract class DatabaseH
{
	abstract public function SQLiteInit($fileName);
	abstract public function MySQLInit($user, $password, $dbname, $host = 'localhost');
	abstract public function PDOInit(\PDO $pdo);

	/**
	 * @param string $queryString
	 * @return Statement
	 */
	abstract public function prepare($queryString);

	/**
	 * @param string $queryString
	 * @return integer
	 */
	abstract public function exec($queryString);

	/**
	 * @param string $table
	 * @param string $idColumn
	 * @return mixed|null
	 */
	abstract public function fetchLastId($table, $idColumn);

	/**
	 * @return bool
	 */
	abstract public function isMYSQL();

	/**
	 * @return bool
	 */
	abstract public function isSQLite();

	/**
	 * @return \PDO
	 */
	abstract public function getPDO(); // PDO

	/**
	 * @return string mysql,sqlite
	 */
	abstract public function getDriverName();

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

	/** @var \PDO */
	protected $_pdo = null;

	public $_fetchCache = null;

	// PUBLIC
	public function SQLiteInit($fileName) {
		$pdo = new \PDO('sqlite:'.$fileName);
		$this->PDOInit($pdo);
	}

	public function MySQLInit($user, $password, $dbname, $host = 'localhost') {
		$pdo = new \PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $password);
		$this->PDOInit($pdo);
	}

	public function PDOInit(\PDO $pdo) {
		$this->_pdo = $pdo;
		$this->_pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
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
		return $this->getDriverName() == 'mysql';
	}

	public function isSQLite() {
		return $this->getDriverName() == 'sqlite';
	}

	public function getPDO() {
		if ($this->_pdo) {
			return $this->_pdo;
		}
		throw XPDOException::pdoIsNull();
	}

	public function getDriverName() {
		$driver = $this->getPDO()->getAttribute(\PDO::ATTR_DRIVER_NAME);
		return $driver;
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