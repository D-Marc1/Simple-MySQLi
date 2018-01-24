<?php

class SimpleMySQLiException extends Exception {}

/**
 * Class SimpleMySQLi
 */
class SimpleMySQLi {
	private $mysqli;
	private $stmtResult; //used to store get_result()
	private $stmt;
	private $defaultFetchType;
	private const ALLOWED_FETCH_TYPES_BOTH = [
		'assoc', 'obj', 'num', 'col'
	];
	private const ALLOWED_FETCH_TYPES_FETCH_ALL = [
		'keyPair', 'keyPairArr', 'group', 'groupCol'
	];

	/**
	 * SimpleMySQLi constructor
	 * @param string $host Hostname or an IP address, like localhost or 127.0.0.1
	 * @param string $username Database username
	 * @param string $password Database password
	 * @param string $dbName Database name
	 * @param string $charset (optional) Default character encoding
	 * @param string $defaultFetchType (optional) Default fetch type. Can be:
	 *               'assoc' - Associative array
	 *               'obj' - Object array
	 *               'num' - Number array
	 *               'col' - 1D array. Same as PDO::FETCH_COLUMN
	 * @throws SimpleMySQLiException If $defaultFetchType specified isn't one of the allowed fetch modes
	 * @throws mysqli_sql_exception If any mysqli function failed due to mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)
	 */
	public function __construct(string $host, string $username, string $password, string $dbName, string $charset = 'utf8', string $defaultFetchType = 'assoc') {
		$this->defaultFetchType = $defaultFetchType;

		if(!in_array($defaultFetchType, self::ALLOWED_FETCH_TYPES_BOTH)) { //check if it is an allowed fetch type
			$allowedComma = implode("','", self::ALLOWED_FETCH_TYPES_BOTH);
			throw new SimpleMySQLiException("The variable 'defaultFetchType' must be '$allowedComma'. You entered '$defaultFetchType'");
		}

		mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
		$this->mysqli = new mysqli($host, $username, $password, $dbName);
		$this->mysqli->set_charset($charset);
	}

	/**
	 * All queries go here. If select statement, needs to be used with either `fetch()` for single row and loop fetching or
	 *`fetchAll()` for fetching all results.
	 * @param string $sql SQL query
	 * @param array $values (optional) Values or variables to bind to query. Can be empty for selecting all rows
	 * @param string $types (optional) Variable type for each bound value/variable
	 * @throws mysqli_sql_exception If any mysqli function failed due to mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)
	 */
	public function query(string $sql, array $values = [], string $types = '') {
		if(!$types) $types = str_repeat('s', count($values)); //String type for all variables if not specified

		$stmt = $this->stmt = $this->mysqli->prepare($sql);
		if($values) $stmt->bind_param($types, ...$values);
		$stmt->execute();
		$this->stmtResult = $stmt->get_result();

		return $this;
	}

	/**
	 * Get affected rows
	 * @throws mysqli_sql_exception If mysqli function failed due to mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)
	 */
	public function affectedRows() {
		return $this->mysqli->affected_rows;
	}

	/**
	 * Get the latest primary key inserted
	 * @throws mysqli_sql_exception If mysqli function failed due to mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)
	 */
	public function insertId() {
		return $this->mysqli->insert_id;
	}

	/**
	* Fetch one row at a time
	* @param string $fetchType (optional) This overrides the default fetch type set in the constructor. Can be an default type
	* @return mixed Array of either fetch type specified or default fetch mode. Can be a scalar too. Null if no more rows
	* @throws SimpleMySQLiException If $fetchType specified isn't one of the allowed fetch modes in $defaultFetchType
	*                               If fetch mode specification is violated
	* @throws mysqli_sql_exception If any mysqli function failed due to mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)
	*/
	public function fetch(string $fetchType = '') {
		$stmtResult = $this->stmtResult;
		$row = [];

		if(!$fetchType) $fetchType = $this->defaultFetchType; //Go with default fetch mode if not specified

		if(!in_array($fetchType, self::ALLOWED_FETCH_TYPES_BOTH)) { //Check if it is an allowed fetch type
			$allowedComma = implode("','", self::ALLOWED_FETCH_TYPES_BOTH);
			throw new SimpleMySQLiException("The variable 'fetchType' must be '$allowedComma'. You entered '$fetchType'");
		}

		if($fetchType === 'num') $row = $stmtResult->fetch_row();
		else if($fetchType === 'assoc') $row = $stmtResult->fetch_assoc();
		else if($fetchType === 'obj') $row = $stmtResult->fetch_object();
		else if($fetchType === 'col') {
			if($stmtResult->field_count !== 1) {
				throw new SimpleMySQLiException("The fetch type: '$fetchType' must have exactly 1 column in query");
			}
			$row = $stmtResult->fetch_row()[0];
		}

		return $row;
	}

	/**
	* Fetch all results in array
	* @param string $fetchType (optional) This overrides the default fetch type set in the constructor. Can be any default type and:
	*               'keyPair' - Unique key (1st column) to single value (2nd column). Same as PDO::FETCH_KEY_PAIR
	*               'keyPairArr' - Unique key (1st column) to array. Same as PDO::FETCH_UNIQUE
	*               'group' - Group by common values in the 1st column into associative subarrays. Same as PDO::FETCH_GROUP
	*               'groupCol' - Group by common values in the 1st column into 1D subarray. Same as PDO::FETCH_GROUP | PDO::FETCH_COLUMN
	* @return array Full array of $fetchType specified; [] if no rows
	* @throws SimpleMySQLiException If $fetchType specified isn't one of the allowed fetch modes in $defaultFetchType
	*                               If fetch mode specification is violated
	* @throws mysqli_sql_exception If any mysqli function failed due to mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)
	*/
	public function fetchAll(string $fetchType = '') {
		$stmtResult = $this->stmtResult;
		$arr = [];

		if(!$fetchType) $fetchType = $this->defaultFetchType; //Go with default fetch mode if not specified

		$comboAllowedTypes = array_merge(self::ALLOWED_FETCH_TYPES_BOTH, self::ALLOWED_FETCH_TYPES_FETCH_ALL); //fetchAll() can take any fetch type

		if(!in_array($fetchType, $comboAllowedTypes)) { //Check if it is an allowed fetch type
			$allowedComma = implode("','", $comboAllowedTypes);
			throw new SimpleMySQLiException("The variable 'fetchType' must be '$allowedComma'. You entered '$fetchType'");
		}

		//All of the fetch types
		if($fetchType === 'num') $arr = $stmtResult->fetch_all(MYSQLI_NUM);
		else if($fetchType === 'assoc') $arr = $stmtResult->fetch_all(MYSQLI_ASSOC);
		else if($fetchType === 'obj') {
			while($row = $stmtResult->fetch_object()) {
				$arr[] = $row;
			}
		}
		else if($fetchType === 'col') {
			if($stmtResult->field_count !== 1) {
				throw new SimpleMySQLiException("The fetch type: '$fetchType' must have exactly 1 column in query");
			}
			while($row = $stmtResult->fetch_row()) {
				$arr[] = $row[0];
			}
		}
		else if($fetchType === 'keyPair' || $fetchType === 'groupCol') {
			if($stmtResult->field_count !== 2) {
				throw new SimpleMySQLiException("The fetch type: '$fetchType' must have exactly two columns in query");
			}
			while($row = $stmtResult->fetch_row()) {
				if($fetchType === 'keyPair') $arr[$row[0]] = $row[1];
				else if($fetchType === 'groupCol') $arr[$row[0]][] = $row[1];
			}
		}
		else if($fetchType === 'keyPairArr' || $fetchType === 'group') {
			$firstColName = $stmtResult->fetch_field_direct(0)->name;
			while($row = $stmtResult->fetch_assoc()) {
				$firstColVal = $row[$firstColName];
				unset($row[$firstColName]);
				if($fetchType === 'keyPairArr') $arr[$firstColVal] = $row;
				else if($fetchType === 'group') $arr[$firstColVal][] = $row;
			}
		}

		return $arr;
	}

	/**
	 * @param array|string $sql SQL query. Can be array for different queries or a string for the same query with different values
	 * @param array $values Values or variables to bind to query
	 * @param array $types (optional) Variable type for each bound value/variable
	 * @throws SimpleMySQLiException If there is a mismatch in parameter values, parameter types or SQL
	 * @throws mysqli_sql_exception If transaction failed due to mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)
	 */
	public function transaction($sql, array $values, array $types = []) {
		try {
			$this->mysqli->autocommit(FALSE);

			$isArray = true;
			$countValues = count($values);

			if($types) $countTypes = count($types);

			if(!is_array($sql)) {
				$currSql = $sql;
				$isArray = false;
			}
			else $countSql = count($sql); //Only count sql if array

			if($isArray && $countValues !== $countSql) { //If SQL array and type amounts don't match
				throw new SimpleMySQLiException("The paramters 'sql' and 'values' must correlate if 'sql' is an array. You entered 'sql' array count: $countSql and 'types' array count: $countValues");
			}
			else if($types && $countValues !== $countTypes) {
				throw new SimpleMySQLiException("The paramters 'values' and 'types' must correlate. You entered 'values' array count: $countValues and 'types' array count: $countTypes");
			}

			for($x = 0; $x < $countValues; $x++) {
				if(!$types) $currTypes = str_repeat('s', count($values[$x])); //String type for all variables if not specified
				else $currTypes = $types[$x];

				if($isArray) $currSql = $sql[$x]; //Either different queries or the same one with different values

				$stmt = $this->mysqli->prepare($currSql);
				$stmt->bind_param($currTypes, ...$values[$x]);
				$stmt->execute();
				$stmt->close();
			}
			$this->mysqli->autocommit(TRUE);
		} catch(Exception $e) {
			$this->mysqli->rollback();
			throw $e;
		}
	}

	/**
	 * Closes MySQL connection
	 * @throws mysqli_sql_exception If mysqli function failed due to mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)
	 */
	public function close() {
		$this->mysqli->close();
	}
}
