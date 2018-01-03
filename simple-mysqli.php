<?php

class SimpleMySQLiException extends Exception {}

/**
 * Class SimpleMySQLi
 */
class SimpleMySQLi {
	private $mysqli;
	private $defaultFetchType;
	private const ALLOWED_FETCH_TYPES = ['assoc', 'obj', 'num', 'singleRowAssoc', 'singleRowObj',
																			 'singleRowNum', 'scalar', 'col', 'keyPair', 'keyPairArr',
																			 'group', 'groupCol'];

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
	 *               'singleRowAssoc' - Single row with associative keys
	 *               'singleRowObj' - Single row as object
	 *               'singleRowNum' - Single row with numbers
	 *               'scalar' - Single value. Same as PDO::FETCH_COLUMN
	 *               'col' - 1D array. Same as PDO::FETCH_COLUMN
	 *               'keyPair' - Unique key (1st column) to single value (2nd column). Same as PDO::FETCH_KEY_PAIR
	 *               'keyPairArr' - Unique key (1st column) to array. Same as PDO::FETCH_UNIQUE
	 *               'group' - Group by common values in the 1st column into associative subarrays. Same as PDO::FETCH_GROUP
	 *               'groupCol' - Group by common values in the 1st column into 1D subarray. Same as PDO::FETCH_GROUP | PDO::FETCH_COLUMN
	 * @throws Exception If $defaultFetchType specified isn't one of the allowed fetch modes
	 * @throws mysqli_sql_exception If any mysqli function failed due to mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)
	 */
	public function __construct(string $host, string $username, string $password, string $dbName, string $charset = 'utf8', string $defaultFetchType = 'assoc') {
		$this->defaultFetchType = $defaultFetchType;

		if(!in_array($defaultFetchType, self::ALLOWED_FETCH_TYPES)) { //check if it is an allowed fetch type
			$allowedComma = implode("','", self::ALLOWED_FETCH_TYPES);
			throw new SimpleMySQLiException("The variable 'defaultFetchType' must be '$allowedComma'. You entered '$defaultFetchType'");
		}

		mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
		$this->mysqli = new mysqli($host, $username, $password, $dbName);
		$this->mysqli->set_charset($charset);
	}

	/**
	 * @param string $sql SQL query
	 * @param array $values Values or variables to bind to query
	 * @param bool $getInsertId (optional) Returns the latest primary key in object if true
	 * @param string $types (optional) Variable type for each bound value/variable
	 * @return object Only affected rows by default. If $getInsertId is true, then also the latest primary key
	 * @throws mysqli_sql_exception If any mysqli function failed due to mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)
	 */
	public function insert(string $sql, array $values, bool $getInsertId = false, string $types = '') {
		if(!$types) $types = str_repeat('s', count($values)); //String type for all variables if not specified

		$stmt = $this->mysqli->prepare($sql);
		$stmt->bind_param($types, ...$values);
		$stmt->execute();
		$affectedRows = $stmt->affected_rows;
		if($getInsertId) $insertId = $this->mysqli->insert_id;
		$stmt->close();

		if($getInsertId) return (object)['affected_rows' => $affectedRows, 'insert_id' => $insertId];
		else return (object)['affected_rows' => $affectedRows];
	}

	/**
	 * @param string $sql SQL query
	 * @param array $values Values or variables to bind to query
	 * @param string $types (optional) Variable type for each bound value/variable
	 * @return object Affected rows
	 * @throws mysqli_sql_exception If any mysqli function failed due to mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)
	 */
	public function update(string $sql, array $values, string $types = '') {
		if(!$types) $types = str_repeat('s', count($values)); //String type for all variables if not specified

		$stmt = $this->mysqli->prepare($sql);
		$stmt->bind_param($types, ...$values);
		$stmt->execute();
		$affectedRows = $stmt->affected_rows;
		$stmt->close();

		return (object)['affected_rows' => $affectedRows];
	}

	/**
	 * Both update() and delete() are exactly the same.
	 * @param string $sql SQL query
	 * @param array $values Values or variables to bind to query
	 * @param string $types (optional) Variable type for each bound value/variable
	 * @throws mysqli_sql_exception If any mysqli function failed due to mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)
	 */
	public function delete(string $sql, array $values, string $types = '') {
		$this->update($sql, $values, $types);
	}

	/**
	 * @param string $sql SQL query
	 * @param array $values (optional) Values or variables to bind to query. Can be empty for selecting all rows
	 * @param string $fetchType (optional) This overrides the default fetch type set in the constructor
	 * @param string $types (optional) Variable type for each bound value/variable
	 * @return mixed Array of Either fetch type specified or default fetch mode. Can be a scalar too
	 * @throws Exception If $fetchType specified isn't one of the allowed fetch modes in $defaultFetchType
	 * @throws Exception If fetch mode specification is violated
	 * @throws mysqli_sql_exception If any mysqli function failed due to mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)
	 */
	public function select(string $sql, array $values = [], string $fetchType = '', string $types = '') {
		$arr = [];

		if(!$fetchType) $fetchType = $this->defaultFetchType; //Go with default fetch mode if not specified
		if(!in_array($fetchType, self::ALLOWED_FETCH_TYPES)) { //Check if it is an allowed fetch type
			$allowedComma = implode("','", self::ALLOWED_FETCH_TYPES);
			throw new SimpleMySQLiException("The variable 'fetchType' must be '$allowedComma'. You entered '$fetchType'");
		}
		if(!$types) $types = str_repeat('s', count($values)); //String type for all variables if not specified

		$stmt = $this->mysqli->prepare($sql);
		if($values) $stmt->bind_param($types, ...$values);
		$stmt->execute();
		$result = $stmt->get_result();
		//All of the fetch types
		if($fetchType === 'singleRowNum') $arr = $result->fetch_row();
		else if($fetchType === 'singleRowAssoc') $arr = $result->fetch_assoc();
		else if($fetchType === 'singleRowObj') $arr = $result->fetch_object();
		else if($fetchType === 'num') $arr = $result->fetch_all(MYSQLI_NUM);
		else if($fetchType === 'assoc') $arr = $result->fetch_all(MYSQLI_ASSOC);
		else if($fetchType === 'obj') {
			while($row = $result->fetch_object()) {
				$arr[] = $row;
			}
		}
		else if($fetchType === 'col' || $fetchType === 'scalar') {
			if($result->field_count !== 1) {
				throw new SimpleMySQLiException("The fetch type: '$fetchType' must have exactly 1 column in query");
			}
			while($row = $result->fetch_row()) {
				if($fetchType === 'col') $arr[] = $row[0];
				if($fetchType === 'scalar') $arr = $row[0]; //obviously a misnomer, as this is a scalar, not array
			}
		}
		else if($fetchType === 'keyPair' || $fetchType = 'groupCol') {
			if($result->field_count !== 2) {
				throw new SimpleMySQLiException("The fetch type: '$fetchType' must have exactly two columns in query");
			}
			while($row = $result->fetch_row()) {
				if($fetchType === 'keyPair') $arr[$row[0]] = $row[1];
				else if($fetchType = 'groupCol') $arr[$row[0]][] = $row[1];
			}
		}
		else if($fetchType === 'keyPairArr' || $fetchType === 'group') {
			while($row = $result->fetch_row()) {
				$firstCol = $row[0];
				unset($row[0]);
				if($fetchType === 'keyPairArr') $arr[$firstCol] = $row;
				else if($fetchType === 'group') $arr[$firstCol][] = $row;
			}
		}

		$stmt->close();

		return ($arr ?: []); //account for single row fetching, since those functions return null, not empty array
	}

	/**
	 * @param array|string $sql SQL query. Can be array for different queries or a string for the same query with different values
	 * @param array $values Values or variables to bind to query
	 * @param array $types (optional) Variable type for each bound value/variable
	 * @throws Exception If transaction fails
	 * @throws mysqli_sql_exception If any mysqli function failed due to mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)
	 */
	public function transaction($sql, array $values, array $types = []) {
		try {
			$this->mysqli->autocommit(FALSE);
			for($x = 0; $x < count($values); $x++) {
				if(!$types) $types[$x] = str_repeat('s', count($values[$x])); //String type for all variables if not specified
				$daSql = (!is_array($sql) ? $sql : $sql[$x]); //Either different queries or the same one with different values

				$stmt = $this->mysqli->prepare($daSql);
				$stmt->bind_param($types[$x], ...$values[$x]);
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
