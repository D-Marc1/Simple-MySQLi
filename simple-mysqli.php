<?php
class SimpleMySQLiException extends Exception {}

/**
 * Class SimpleMySQLi
 *
 * @version 1.5.5
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
		'keyPair', 'keyPairArr', 'group', 'groupCol', 'groupObj'
	];

	/**
	 * SimpleMySQLi constructor
	 *
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
	public function __construct(string $host, string $username, string $password, string $dbName, string $charset = 'utf8mb4', string $defaultFetchType = 'assoc') {
		$this->defaultFetchType = $defaultFetchType;

		if(!in_array($defaultFetchType, self::ALLOWED_FETCH_TYPES_BOTH, true)) { //check if it is an allowed fetch type
			$allowedComma = implode("','", self::ALLOWED_FETCH_TYPES_BOTH);
			throw new SimpleMySQLiException("The variable 'defaultFetchType' must be '$allowedComma'. You entered '$defaultFetchType'");
		}

		mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
		$this->mysqli = new mysqli($host, $username, $password, $dbName);
		$this->mysqli->set_charset($charset);
	}

	/**
	 * All queries go here. If select statement, needs to be used with either `fetch()` for single row and loop fetching or
	 *`fetchAll()` for fetching all results
	 *
	 * @param string $sql SQL query
	 * @param array|string|int $values (optional) Values or variables to bind to query. Can be empty for selecting all rows
	 * @param string $types (optional) Variable type for each bound value/variable
	 * @return $this
	 * @throws mysqli_sql_exception If any mysqli function failed due to mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)
	 */
	public function query(string $sql, $values = [], string $types = ''): self {
		if(!is_array($values)) $values = [$values]; //Convert scalar to array

		if(!$types) $types = str_repeat('s', count($values)); //String type for all variables if not specified

		if(!$values) {
			$this->stmtResult = $this->mysqli->query($sql); //Use non-prepared query if no values to bind for efficiency
		} else {
			$stmt = $this->stmt = $this->mysqli->prepare($sql);
			$stmt->bind_param($types, ...$values);
			$stmt->execute();
			$this->stmtResult = $stmt->get_result();
		}

		return $this;
	}

	/**
	 * Used in order to be more efficient if same SQL is used with different values. Is really a re-execute function
	 *
	 * @param array $values (optional) Values or variables to bind to query. Can be empty for selecting all rows
	 * @param string $types (optional) Variable type for each bound value/variable
	 * @return $this
	 * @throws mysqli_sql_exception If any mysqli function failed due to mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)
	 */
	public function execute($values = [], string $types = ''): self {
		if(!is_array($values)) $values = [$values]; //Convert scalar to array

		if(!$types) $types = str_repeat('s', count($values)); //String type for all variables if not specified

		$stmt = $this->stmt;
		$stmt->bind_param($types, ...$values);
		$stmt->execute();
		$this->stmtResult = $stmt->get_result();

		return $this;
	}

	/**
	 * Create correct number of questions marks for WHERE IN() array
	 *
	 * @param array $inArr Array used in WHERE IN clause
	 * @return string Correct number of question marks
	 * @throws mysqli_sql_exception If mysqli function failed due to mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)
	 */
	public function whereIn(array $inArr): string {
		return implode(',', array_fill(0, count($inArr), '?')); //create question marks
	}

	/**
	 * Get number of rows from SELECT
	 *
	 * @return int $mysqli->num_rows
	 * @throws mysqli_sql_exception If mysqli function failed due to mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)
	 */
	public function numRows(): int {
		return $this->stmtResult->num_rows;
	}

	/**
	 * Get affected rows. Can be used instead of numRows() in SELECT
	 *
	 * @return int $mysqli->affected_rows or rows matched if setRowsMatched() is used
	 * @throws mysqli_sql_exception If mysqli function failed due to mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)
	 */
	public function affectedRows(): int {
		return $this->mysqli->affected_rows;
	}

	/**
	 * A more specific version of affectedRows() to give you more info what happened. Uses $mysqli::info under the hood
	 * Can be used for the following cases http://php.net/manual/en/mysqli.info.php
	 *
	 * @return array Associative array converted from result string
	 * @throws mysqli_sql_exception If mysqli function failed due to mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)
	 */
	public function info(): array {
		preg_match_all('/(\S[^:]+): (\d+)/', $this->mysqli->info, $matches);
		return array_combine($matches[1], $matches[2]);
	}

	/**
	 * Get rows matched instead of rows changed. Can strictly be used on UPDATE. Otherwise returns false
	 *
	 * @return int Rows matched
	 * @throws mysqli_sql_exception If mysqli function failed due to mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)
	 */
	public function rowsMatched(): int {
		return $this->info()['Rows matched'] ?? false;
	}

	/**
	 * Get the latest primary key inserted
	 *
	 * @return int $mysqli->insert_id
	 * @throws mysqli_sql_exception If mysqli function failed due to mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)
	 */
	public function insertId(): int {
		return $this->mysqli->insert_id;
	}

	/**
	* Fetch one row at a time
	*
	* @param string $fetchType (optional) This overrides the default fetch type set in the constructor
	* @param string $className (optional) Class name to fetch into if 'obj' $fetchType
	* @param array $classParams (optional) Array of constructor parameters for class if 'obj' $fetchType
	* @return mixed Array of either fetch type specified or default fetch mode. Can be a scalar too. Null if no more rows
	* @throws SimpleMySQLiException If $fetchType specified isn't one of the allowed fetch modes in $defaultFetchType
	*                               If fetch mode specification is violated
	* @throws mysqli_sql_exception If any mysqli function failed due to mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)
	*/
	public function fetch(string $fetchType = '', string $className = 'stdClass', array $classParams = []) {
		$stmtResult = $this->stmtResult;
		$row = [];

		if(!$fetchType) $fetchType = $this->defaultFetchType; //Go with default fetch mode if not specified

		if(!in_array($fetchType, self::ALLOWED_FETCH_TYPES_BOTH, true)) { //Check if it is an allowed fetch type
			$allowedComma = implode("','", self::ALLOWED_FETCH_TYPES_BOTH);
			throw new SimpleMySQLiException("The variable 'fetchType' must be '$allowedComma'. You entered '$fetchType'");
		}

		if($fetchType !== 'obj' && $className !== 'stdClass') {
			throw new SimpleMySQLiException("You can only specify a class name with 'obj' as the fetch type");
		}

		if($fetchType === 'num') {
			$row = $stmtResult->fetch_row();
		} else if($fetchType === 'assoc') {
			$row = $stmtResult->fetch_assoc();
		} else if($fetchType === 'obj') {
			if($classParams) {
				$row = $stmtResult->fetch_object($className, $classParams);
			} else {
				$row = $stmtResult->fetch_object($className);
			}
		} else if($fetchType === 'col') {
			if($stmtResult->field_count !== 1) {
				throw new SimpleMySQLiException("The fetch type: '$fetchType' must have exactly 1 column in query");
			}

			$row = $stmtResult->fetch_row()[0];
		}

		return $row;
	}

	/**
	* Fetch all results in array
	*
	* @param string $fetchType (optional) This overrides the default fetch type set in the constructor. Can be any default type and:
	*               'keyPair' - Unique key (1st column) to single value (2nd column). Same as PDO::FETCH_KEY_PAIR
	*               'keyPairArr' - Unique key (1st column) to array. Same as PDO::FETCH_UNIQUE
	*               'group' - Group by common values in the 1st column into associative subarrays. Same as PDO::FETCH_GROUP
	*               'groupCol' - Group by common values in the 1st column into 1D subarray. Same as PDO::FETCH_GROUP | PDO::FETCH_COLUMN
	*               'groupObj' - Group by common values in the first column into object subarrays. Same as PDO::FETCH_GROUP | PDO::FETCH_CLASS
	* @param string $className (optional) Class name to fetch into if 'obj' $fetchType. Defaults to stdClass
	* @param array $classParams (optional) Array of constructor parameters for class if 'obj' $fetchType
	* @return array Full array of $fetchType specified; [] if no rows
	* @throws SimpleMySQLiException If $fetchType specified isn't one of the allowed fetch modes in $defaultFetchType
	*                               If fetch mode specification is violated
	* @throws mysqli_sql_exception If any mysqli function failed due to mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)
	*/
	public function fetchAll(string $fetchType = '', string $className = 'stdClass', array $classParams = []): array {
		$stmtResult = $this->stmtResult;
		$arr = [];

		if(!$fetchType) $fetchType = $this->defaultFetchType; //Go with default fetch mode if not specified

		$comboAllowedTypes = array_merge(self::ALLOWED_FETCH_TYPES_BOTH, self::ALLOWED_FETCH_TYPES_FETCH_ALL); //fetchAll() can take any fetch type

		if(!in_array($fetchType, $comboAllowedTypes, true)) { //Check if it is an allowed fetch type
			$allowedComma = implode("','", $comboAllowedTypes);
			throw new SimpleMySQLiException("The variable 'fetchType' must be '$allowedComma'. You entered '$fetchType'");
		}

		if($fetchType !== 'obj' && $fetchType !== 'groupObj' && $className !== 'stdClass') {
			throw new SimpleMySQLiException("You can only specify a class name with 'obj' as the fetch type");
		}

		//All of the fetch types
		if($fetchType === 'num') {
			$arr = $stmtResult->fetch_all(MYSQLI_NUM);
		} else if($fetchType === 'assoc') {
			$arr = $stmtResult->fetch_all(MYSQLI_ASSOC);
		} else if($fetchType === 'obj') {
			if($classParams) {
				while($row = $stmtResult->fetch_object($className, $classParams)) {
					$arr[] = $row;
				}
			} else {
				while($row = $stmtResult->fetch_object($className)) {
					$arr[] = $row;
				}
			}
		} else if($fetchType === 'col') {
			if($stmtResult->field_count !== 1) {
				throw new SimpleMySQLiException("The fetch type: '$fetchType' must have exactly 1 column in query");
			}

			while($row = $stmtResult->fetch_row()) {
				$arr[] = $row[0];
			}
		} else if($fetchType === 'keyPair' || $fetchType === 'groupCol') {
			if($stmtResult->field_count !== 2) {
				throw new SimpleMySQLiException("The fetch type: '$fetchType' must have exactly two columns in query");
			}

			while($row = $stmtResult->fetch_row()) {
				if($fetchType === 'keyPair') $arr[$row[0]] = $row[1];
				else if($fetchType === 'groupCol') $arr[$row[0]][] = $row[1];
			}
		} else if($fetchType === 'keyPairArr' || $fetchType === 'group' || $fetchType === 'groupObj') {
			$firstColName = $stmtResult->fetch_field_direct(0)->name;

			if(!$className) $className = 'stdClass';

			if($fetchType === 'groupObj') {
				while($row = $stmtResult->fetch_object($className)) {
					$firstColVal = $row->$firstColName;
					unset($row->$firstColName);
					$arr[$firstColVal][] = $row;
				}
			} else {
				while($row = $stmtResult->fetch_assoc()) {
					$firstColVal = $row[$firstColName];
					unset($row[$firstColName]);

					if($fetchType === 'keyPairArr') $arr[$firstColVal] = $row;
					else if($fetchType === 'group') $arr[$firstColVal][] = $row;
				}
			}
		}

		return $arr;
	}

	/**
	 * Just a normal transaction that will automatically rollback and print your message to your error log.
	 * Will also rollback if `affectedRows()` is less than 1.
	 *
	 * @param array|string $sql SQL query. Can be array for different queries or a string for the same query with different values
	 * @param array $values Values or variables to bind to query
	 * @param array $types (optional) Array of variable type for each bound value/variable
	 * @throws SimpleMySQLiException If there is a mismatch in parameter values, parameter types or SQL
	 * @throws mysqli_sql_exception If transaction failed due to mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)
	 */
	public function atomicQuery($sql, array $values, array $types = []): void {
		try {
			$this->mysqli->autocommit(FALSE);

			$isArray = true;
			$countValues = count($values);

			if($types) $countTypes = count($types); //if variable types specified

			if(!is_array($sql)) {
				$currSQL = $sql;
				$isArray = false;
			} else { //Only count sql if array
				$countSql = count($sql);
			}

			if($isArray && $countValues !== $countSql) { //If SQL array and value amounts don't match
				throw new SimpleMySQLiException("The paramters 'sql' and 'values' must correlate if 'sql' is an array. You entered 'sql' array count: $countSql and 'types' array count: $countValues");
			} else if($types && $countValues !== $countTypes) { //If variable types used and values don't match
				throw new SimpleMySQLiException("The paramters 'values' and 'types' must correlate. You entered 'values' array count: $countValues and 'types' array count: $countTypes");
			}

			for($x = 0; $x < $countValues; $x++) {
				if(!$types) $currTypes = str_repeat('s', count($values[$x])); //String type for all variables if not specified
				else $currTypes = $types[$x];

				if($isArray) $currSQL = $sql[$x]; //Either different queries or the same one with different values

				if($isArray || (!$isArray && $x === 0)) { //Prepared once if same query used multiple times with different values
					$stmt = $this->mysqli->prepare($currSQL);
				}

				$stmt->bind_param($currTypes, ...$values[$x]);
				$stmt->execute();
				if($this->affectedRows() < 1) {
					throw new SimpleMySQLiException("Query did not succeed, with affectedRows() of: {$this->affectedRows()} Query: $currSQL");
				}

				if($isArray || (!$isArray && $x === $countValues - 1)) { //If prepared once, should only close on last values used
					$stmt->close();
				}
			}

			$this->mysqli->autocommit(TRUE);
		} catch(Exception $e) {
			$this->mysqli->rollback();
			throw $e;
		}
	}

	/**
	 * Do stuff inside of transaction
	 *
	 * @param callable $callback Closure to do transaction operations inside. Parameter value is $this
	 * @throws mysqli_sql_exception If transaction failed due to mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)
	 */
	public function transaction(callable $callback): void {
		try {
			$this->mysqli->autocommit(FALSE);
			$callback($this);
			$this->mysqli->autocommit(TRUE);
		} catch(Exception $e) {
			$this->mysqli->rollback();
			throw $e;
		}
	}

	/**
	 * Frees MySQL result
	 *
	 * @throws mysqli_sql_exception If mysqli function failed due to mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)
	 * @return $this
	 */
	public function freeResult(): self {
		$this->stmtResult->free();
		return $this;
	}

	/**
	 * Closes MySQL prepared statement
	 *
	 * @throws mysqli_sql_exception If mysqli function failed due to mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)
	 * @return $this
	 */
	public function closeStmt(): self {
		$this->stmt->close();
		return $this;
	}

	/**
	 * Closes MySQL connection
	 *
	 * @throws mysqli_sql_exception If mysqli function failed due to mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)
	 */
	public function close(): void {
		$this->mysqli->close();
	}
}