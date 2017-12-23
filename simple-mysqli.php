<?php
class SimpleMySQLi {
  private $mysqli;
	private $typeRequired;
	private $defaultFetchType;
	private $allowedFetchTypes = ['assoc', 'obj', 'num', 'singleRowAssoc', 'singleRowObj', 'singleRowNum'];
  public function __construct(string $host, string $username, string $password, string $dbName, bool $typeRequired = true, string $charset = 'utf8', string $defaultFetchType = 'assoc') {
		$this->typeRequired = $typeRequired;
		$this->defaultFetchType = $defaultFetchType;
		if(!in_array($defaultFetchType, $this->allowedFetchTypes)) { //check if it is an allowed fetch type
			$allowedComma = implode(', ',$this->allowedFetchTypes);
			throw new Exception("The variable 'defaultFetchType' must be $allowedComma. You entered $defaultFetchType.");
		}
		mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
		$this->mysqli = new mysqli($host, $username, $password, $dbName);
		$this->mysqli->set_charset($charset);
	}
	public function insert(string $sql, array $values, bool $getInsertId = false, string $types = '') {
		if(!$this->typeRequired) $types = str_repeat('s', count($values));
		$stmt = $this->mysqli->prepare($sql);
		$stmt->bind_param($types, ...$values);
		$stmt->execute();
		$affectedRows = $stmt->affected_rows;
		if($getInsertId) $insertId = $this->mysqli->insert_id;
		$stmt->close();
		if($getInsertId) return [$affectedRows, $insertId];
    else return $affectedRows;
	}
	public function update(string $sql, array $values, string $types = '') {
		if(!$this->typeRequired) $types = str_repeat('s', count($values));
		$stmt = $this->mysqli->prepare($sql);
		$stmt->bind_param($types, ...$values);
		$stmt->execute();
		$affectedRows = $stmt->affected_rows;
		$stmt->close();
    return $affectedRows;
	}
	public function delete(string $sql, array $values, string $types = '') {
		if(!$this->typeRequired) $types = str_repeat('s', count($values));
		$stmt = $this->mysqli->prepare($sql);
		$stmt->bind_param($types, ...$values);
		$stmt->execute();
		$affectedRows = $stmt->affected_rows;
		$stmt->close();
		return $affectedRows;
	}
	public function select(string $sql, array $values = [], string $fetchType = '', string $types = '') {
		if(!$fetchType) $fetchType = $this->defaultFetchType;
		if(!in_array($fetchType, $this->allowedFetchTypes)) { //check if it is an allowed fetch type
			$allowedComma = implode(', ',$this->allowedFetchTypes);
			throw new Exception("The variable fetchType must be $allowedComma. You entered $fetchType");
		}
		if(!$this->typeRequired) $types = str_repeat('s', count($values));
		$stmt = $this->mysqli->prepare($sql);
		if($values) $stmt->bind_param($types, ...$values);
		$stmt->execute();
		$result = $stmt->get_result();
		$numRows = $result->num_rows;
		if($numRows === 0) $arr = 0; //misleading, since not array, but will give the return value 0
		else if($fetchType === 'singleRowNum') $arr = $result->fetch_all(MYSQLI_NUM)[0];
		else if($fetchType === 'singleRowAssoc') $arr = $result->fetch_all(MYSQLI_ASSOC)[0];
		else if($fetchType === 'singleRowObj') $arr = $result->fetch_object();
		else if($fetchType === 'obj') {
			while($row = $result->fetch_object()) {
				$arr[] = $row;
			}
		}
		else if($fetchType === 'num') $arr = $result->fetch_all(MYSQLI_NUM);
		else $arr = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
		return $arr;
	}
	public function transaction($sql, array $values, array $types = []) {
		try {
			$this->mysqli->autocommit(FALSE);
			for($x = 0; $x < count($values); $x++) {
				if(!$this->typeRequired) $types[$x] = str_repeat('s', count($values[$x]));
				$daSql = (!is_array($sql) ? $sql : $sql[$x]);
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
	public function close() {
		$this->mysqli->close();
	}
}
?>
