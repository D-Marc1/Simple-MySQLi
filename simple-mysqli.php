<?php

class SimpleMySQLi
{
    private $mysqli;
    private $typeRequired;
    private $defaultFetchType;
    private $allowedFetchTypes = ['assoc', 'obj', 'num', 'singleRowAssoc', 'singleRowObj', 'singleRowNum'];

    /**
     * SimpleMySQLi constructor.
     * @param string $host
     * @param string $username
     * @param string $password
     * @param string $dbName
     * @param bool $typeRequired
     * @param string $charset
     * @param string $defaultFetchType
     * @throws Exception
     */
    public function __construct(string $host, string $username, string $password, string $dbName, bool $typeRequired = true, string $charset = 'utf8', string $defaultFetchType = 'assoc')
    {
        $this->typeRequired = $typeRequired;
        $this->defaultFetchType = $defaultFetchType;
        if (!in_array($defaultFetchType, $this->allowedFetchTypes)) { //check if it is an allowed fetch type
            $allowedComma = implode(', ', $this->allowedFetchTypes);
            throw new Exception("The variable 'defaultFetchType' must be $allowedComma. You entered $defaultFetchType.");
        }
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $this->mysqli = new mysqli($host, $username, $password, $dbName);
        $this->mysqli->set_charset($charset);
    }

    /**
     * @param string $sql
     * @param array $values
     * @param bool $getInsertId
     * @param string $types
     * @return int|object
     */
    public function insert(string $sql, array $values, bool $getInsertId = false, string $types = '')
    {
        if (!$this->typeRequired) {
            $types = str_repeat('s', count($values));
        }
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param($types, ...$values);
        $stmt->execute();
        $affectedRows = $stmt->affected_rows;
        $insertId = null;
        if ($getInsertId) {
            $insertId = $this->mysqli->insert_id;
        }
        $stmt->close();
        if ($getInsertId) {
            return (object)['affected_rows' => $affectedRows, 'insert_id' => $insertId];
        } else {
            return $affectedRows;
        }
    }

    /**
     * @param string $sql
     * @param array $values
     * @param string $types
     * @return int
     */
    public function update(string $sql, array $values, string $types = '')
    {
        if (!$this->typeRequired) {
            $types = str_repeat('s', count($values));
        }
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param($types, ...$values);
        $stmt->execute();
        $affectedRows = $stmt->affected_rows;
        $stmt->close();

        return $affectedRows;
    }

    /**
     * @param string $sql
     * @param array $values
     * @param string $types
     * @return int
     */
    public function delete(string $sql, array $values, string $types = '')
    {
        if (!$this->typeRequired) {
            $types = str_repeat('s', count($values));
        }
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param($types, ...$values);
        $stmt->execute();
        $affectedRows = $stmt->affected_rows;
        $stmt->close();

        return $affectedRows;
    }

    /**
     * @param string $sql
     * @param array $values
     * @param string $fetchType
     * @param string $types
     * @return array
     * @throws Exception
     */
    public function select(string $sql, array $values = [], string $fetchType = '', string $types = '')
    {
        $arr = [];
        if (!$fetchType) {
            $fetchType = $this->defaultFetchType;
        }
        if (!in_array($fetchType, $this->allowedFetchTypes)) { //check if it is an allowed fetch type
            $allowedComma = implode(', ', $this->allowedFetchTypes);
            throw new Exception("The variable fetchType must be $allowedComma. You entered $fetchType");
        }
        if (!$this->typeRequired) {
            $types = str_repeat('s', count($values));
        }
        $stmt = $this->mysqli->prepare($sql);
        if ($values) {
            $stmt->bind_param($types, ...$values);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        if ($fetchType === 'singleRowNum') {
            $arr = $result->fetch_row();
        } else {
            if ($fetchType === 'singleRowAssoc') {
                $arr = $result->fetch_assoc();
            } else {
                if ($fetchType === 'singleRowObj') {
                    $arr = $result->fetch_object();
                } else {
                    if ($fetchType === 'obj') {
                        while ($row = $result->fetch_object()) {
                            $arr[] = $row;
                        }
                    } else {
                        if ($fetchType === 'num') {
                            $arr = $result->fetch_all(MYSQLI_NUM);
                        } else {
                            $arr = $result->fetch_all(MYSQLI_ASSOC);
                        }
                    }
                }
            }
        }
        $stmt->close();

        return ($arr ?: []); //account for single row fetching, since those functions return null, not empty array
    }

    /**
     * @param $sql
     * @param array $values
     * @param array $types
     * @throws Exception
     */
    public function transaction($sql, array $values, array $types = [])
    {
        try {
            $this->mysqli->autocommit(false);
            for ($x = 0; $x < count($values); $x++) {
                if (!$this->typeRequired) {
                    $types[$x] = str_repeat('s', count($values[$x]));
                }
                $daSql = (!is_array($sql) ? $sql : $sql[$x]);
                $stmt = $this->mysqli->prepare($daSql);
                $stmt->bind_param($types[$x], ...$values[$x]);
                $stmt->execute();
                $stmt->close();
            }
            $this->mysqli->autocommit(true);
        } catch (Exception $e) {
            $this->mysqli->rollback();
            throw $e;
        }
    }

    public function close()
    {
        $this->mysqli->close();
    }
}
