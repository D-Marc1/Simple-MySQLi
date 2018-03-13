# Simple MySQLi - MySQLi Wrapper

Using MySQLi prepared statements is a great way to prevent against SQL injection, but it can start feeling tedious after a while. I thought this could be improved a little, which is why wanted to create a highly abstracted MySQLi database wrapper, while also ensuring that the SQL queries aren't broken up into proprietary syntactic sugar chaining. This way, so you can have extremely concise code, while still keeping your SQL syntax intact. I'll concede that I'm slightly reinventing the wheel, as there are already some wrappers with similarities to mine, but I figured I'd make an extremely lightweight, yet powerful enough one for most people to use.

I specifically chose MySQLi over PDO in case I decide to add any of the MySQL-specific features it has in the future, like asynchronous queries for instance. Currently, I'm not using any, but I wanted to keep the option open.

On a side note, if you'd like to know how to use MySQLi the "vanilla way", check out [this tutorial on MySQLi Prepared Statements](https://websitebeaver.com/prepared-statements-in-php-mysqli-to-prevent-sql-injection).

# Why Should I Use Simple MySQLi?

- Concise Code
- Awesome fetch modes
- SQL queries are the same
- Accounts for most common uses
- Variable type is optional
- Bind variables and values (not sure how useful this is though)

# Why Not Use It?

- No named parameters
- If you need to use some of the more obscure MySQLi functions, then this is certainly not the right fit for you.

The purpose of this class is to keep things as simple as possible, while accounting for the most common uses. If there's something you'd like me to add, feel free to suggest it or send a pull request.

# Supported Versions

PHP 7.1+

# Table of Contents

- [Examples](#examples)
  - [Insert](#insert)
  - [Update](#update)
  - [Delete](#delete)
  - [Select](#select)
    - [Fetch Each Column as Separate Array Variable](#fetch-each-column-as-separate-array-variable)
    - [Fetch Associative Array](#fetch-associative-array)
    - [Fetch Array of Objects](#fetch-array-of-objects)
    - [Fetch Single Row](#fetch-single-row)
    - [Fetch Single Row Like bind_result()](#fetch-single-row-like-bind_result)
    - [Fetch Single Row, Single Column (Scalar)](#fetch-single-row-single-column-scalar)
    - [Fetch Single Column as Array](#fetch-single-column-as-array)
    - [Fetch Each Column as Separate Array Variable](#fetch-each-column-as-separate-array-variable)
    - [Fetch Key-Pair](#fetch-key-pair)
    - [Fetch Key, Array as Pair](#fetch-key-array-as-pair)
    - [Fetch in Groups](#fetch-in-groups)
    - [Fetch in Groups, One Column](#fetch-in-groups-one-column)
  - [Like](#like)
  - [Where In Array](#where-in-array)
    - [With Other Placeholders](#with-other-placeholders)
  - [Transactions](#transactions)
    - [Same Template, Different Values](#same-template-different-values)
- [Documentation](#documentation)
  - [Constructor](#constructor)
  - [query()](#query)
	- [execute()](#execute)
	- [whereIn()](#wherein)
	- [numRows()](#numrows)
  - [affectedRows()](#affectedrows)
  - [affectedRowsInfo()](#affectedrowsinfo)
  - [insertId()](#insertid)
  - [fetch()](#fetch)
  - [fetchAll()](#fetchall)
  - [transaction()](#transaction)
	- [transactionCallback()](#transactioncallback)
	- [freeResult()](#freeresult)
  - [closeStmt()](#closestmt)
	- [close()](#close)
- [Changelog](#changelog)

# Examples

Let's get straight to the point! The best way to learn is by examples.

## Create a New MySQL Connection

One of the aspects of MySQLi I actually like a lot is the fact that error reporting is automatically turned off. Unfortunately I wasn't able to replicate this, as I throw an excpetion on the the constructor, therefore potentially exposing the parameter values. This is why I turned on mysqli reporting by doing `mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)` — since you'll be wrapping it in a `try/catch` block anyway. So you must either wrap it around in a `try/catch` block or create your own custom handler. Make sure you use `$e->getMessage()` and not `$e` so your password isn't exposed. Either way, you must solely report your errors in your error log. To do this, make sure your `php.ini` file has the following settings in production: `display_errors = Off` and `log_errors = On`. Keep in mind that using `echo`, `die` or `exit` to print the error message is extremely dangerous as well.

**Try/Catch**

```php
try {
  $mysqli = new SimpleMySQLi("localhost", "username", "password", "dbName", "utf8mb4", "assoc");
} catch(Exception $e) {
  error_log($e->getMessage());
  exit('Someting weird happened'); //Should be a message a typical user could understand
}
```

**Custom Exception Handler**

This is pretty neat, since you can avoid nesting. It is commonly used to redirect to a single error page, but can be used like the following as well. You can reassign this to give specific messages on your AJAX pages as well. This will catch all of your exceptions on each page this is used on. So you'll either have to call `set_exception_handler()` on each page or use `restore_exception_handler()` to revert to the previous one.

```php
set_exception_handler(function($e) {
  error_log($e->getMessage());
  exit('Someting weird happened'); //Should be a message a typical user could understand
});
$mysqli = new SimpleMySQLi("localhost", "username", "password", "dbName", "utf8mb4", "assoc");
```

## Insert

```php
$insert = $mysqli->query("INSERT INTO myTable (name, age) VALUES (?, ?)", [$_POST['name'], $_POST['age']]);
echo $insert->affectedRows(); //Can be $mysqli->affectedRows()
echo $insert->insertId(); //Can be $mysqli->insertId()
```

## Update

```php
$update = $mysqli->query("UPDATE myTable SET name = ? WHERE id = ?", [$_POST['name'], $_SESSION['id']]);
echo $update->affectedRows(); //Can be $mysqli->affectedRows()
var_export($update->affectedRowsInfo()); //For more specific version. Can be $mysqli->affectedRowsInfo()
```

## Delete

```php
$delete = $mysqli->query("DELETE FROM myTable WHERE id = ?", [$_SESSION['id']]);
echo $delete->affectedRows(); //Can be $mysqli->affectedRows()
```

## Select

You can either fetch your entire result in an array with `fetchAll()` or loop through each row individually with `fetch()`, if you're planning on modifying the array. You could obviously use `fetchAll()` for any scenario, but using `fetch()` is more efficient memory-wise if you're making changes to the array, as it will save you from having to loop through it a second time. However, from my experience, most queries don't need any modifications, so `fetchAll()` should primarily be used. If you just need one row, then obviously `fetch()` should be used.

### Fetch Associative Array

```php
$arr = $mysqli->query("SELECT id, name, age FROM events WHERE id <= ?", [4])->fetchAll("assoc");
if(!$arr) exit('No rows');
var_export($arr);
```

Output:

```php
[
  ['id' => 24 'name' => 'Jerry', 'age' => 14],
  ['id' => 201 'name' => 'Alexa', 'age' => 22]
]
```

### Fetch Array of Objects

```php
$arr = $mysqli->query("SELECT id, name, age FROM events WHERE id <= ?", [4])->fetchAll("obj");
if(!$arr) exit('No rows');
var_export($arr);
```

Output:

```php
[
  stdClass Object ['id' => 24 'name' => 'Jerry', 'age' => 14],
  stdClass Object ['id' => 201 'name' => 'Alexa', 'age' => 22]
]
```

### Fetch Single Row

```php
$arr = $mysqli->query("SELECT id, name, age FROM events WHERE id <= ?", [12])->fetch("assoc");
if(!$arr) exit('No rows');
var_export($arr);
```

Output:

```php
['id' => 24 'name' => 'Jerry', 'age' => 14]
```

### Fetch Single Row Like bind_result()

```php
$arr = $mysqli->query("SELECT id, name, age FROM myTable WHERE name = ?", [$_POST['name']])->fetchAll("num"); //must use number array to use in list
if(!$arr) exit('No rows');
list($id, $name, $age) = $arr;
echo $age; //Output 34
```

### Fetch Single Row, Single Column (Scalar)

```php
$count = $mysqli->query("SELECT COUNT(*) FROM myTable WHERE name = ?", [$_POST['name']])->fetch("col");
if(!$count) exit('No rows');
echo $count; //Output: 284
```

### Fetch Single Column as Array

```php
$heights = $mysqli->query("SELECT height FROM myTable WHERE id < ?", [500])->fetchAll("col");
if(!$heights) exit('No rows');
var_export($heights);
```

Output:

```php
[78, 64, 68, 54, 58, 63]
```

### Fetch Each Column as Separate Array Variable

```php
$result = $mysqli->query("SELECT name, email, number FROM events WHERE id <= ?", [450]);
while($row = $result->fetch("assoc")) {
  $names[] = $row['name'];
  $emails[] = $row['email'];
  $numbers[] = $row['number'];
}
if(!isset($names) || !isset($emails) || !isset($numbers)) exit('No rows');
var_export($names);
```

Output:

```php
['Bobby', 'Jessica', 'Victor', 'Andrew', 'Mallory']
```

### Fetch Key-Pair

```php
//First column must be unique, like a primary key; can only select 2 columns
$arr = $mysqli->query("SELECT id, name FROM myTable WHERE age <= ?", [25])->fetchAll("keyPair");
if(!$arr) exit('No rows');
var_export($arr);
```

Output:

```php
[7 => 'Jerry', 10 => 'Bill', 29 => 'Bobby']
```

### Fetch Key, Array as Pair

```php
//First column must be unique, like a primary key
$arr = $mysqli->query("SELECT id, max_bench, max_squat FROM myTable WHERE weight < ?", [205])->fetchAll("keyPairArr");
if(!$arr) exit('No rows');
var_export($arr);
```

Output:

```php
[
  17 => ['max_bench' => 230, 'max_squat' => 175],
  84 => ['max_bench' => 195, 'max_squat' => 235],
  136 => ['max_bench' => 135, 'max_squat' => 285]
]
```

### Fetch in Groups

```php
//First column must be common value to group by
$arr = $mysqli->query("SELECT eye_color, name, weight FROM myTable WHERE age < ?", [29])->fetchAll("group");
if(!$arr) exit('No rows');
var_export($arr);
```

Output:

```php
[
  'green' => [
    ['name' => 'Patrick', 'weight' => 178],
    ['name' => 'Olivia', 'weight' => 132]
  ],
  'blue' => [
    ['name' => 'Kyle', 'weight' => 128],
    ['name' => 'Ricky', 'weight' => 143]
  ],
  'brown' => [
    ['name' => 'Jordan', 'weight' => 173],
    ['name' => 'Eric', 'weight' => 198]
  ]
]
```

### Fetch in Groups, One Column

```php
//First column must be common value to group by
$arr = $mysqli->query("SELECT eye_color, name FROM myTable WHERE age < ?", [29])->fetchAll("groupCol");
if(!$arr) exit('No rows');
var_export($arr);
```

Output:

```php
[
  'green' => ['Patrick', 'Olivia'],
  'blue' => ['Kyle', 'Ricky'],
  'brown' => ['Jordan', 'Eric']
]
```

## Like

```php
$search = "%{$_POST['search']}%";
$arr = $mysqli->query("SELECT id, name, age FROM events WHERE name LIKE ?", [$search])->fetchAll();
if(!$arr) exit('No rows');
var_export($arr);
```

## Where In Array

```php
$inArr = [12, 23, 44];
$clause = whereIn($inArr); //Create question marks
$arr = $mysqli->query("SELECT event_name, description, location FROM events WHERE id IN($clause)", $inArr)->fetchAll();
if(!$arr) exit('No rows');
var_export($arr);
```

### With Other Placeholders

```php
$inArr = [12, 23, 44];
$clause = whereIn($inArr); //Create question marks
$fullArr = array_merge($inArr, [5]); //Merge WHERE IN questions marks with rest of query
$arr = $mysqli->query("SELECT event_name, description, location FROM events WHERE id IN($clause) AND id < ?", $fullArr)->fetchAll();
if(!$arr) exit('No rows');
var_export($arr);
```

## Transactions

This is probably my favorite aspect of this class, since the difference in terms of lines of code is absurd.

```php
$sql[] = "INSERT INTO myTable (name, age) VALUES (?, ?)";
$sql[] = "UPDATE myTable SET name = ? WHERE id = ?";
$sql[] = "UPDATE myTable SET name = ? WHERE id = ?";
$arrOfValues = [[$_POST['name'], $_POST['age']], ['Pablo', 34], [$_POST['name'], $_SESSION['id']]];
$mysqli->transaction($sql, $arrOfValues);
```

### Same Template, Different Values

```php
$sql = "INSERT INTO myTable (name, age) VALUES (?, ?)";
$arrOfValues = [[$_POST['name'], $_POST['age']], ['Pablo', 34], [$_POST['name'], 22]];
$mysqli->transaction($sql, $arrOfValues);
```

## Transactions with Callbacks

The regular way of doing transactions in Simple MySQLi is exceedingly concise and can be used in most cases. However, sometimes you might want a little more control. For instance, under the hood, it only if each query is greater than one. This isn't suitable for a query like INSERT multiple or DELETE/UPDATE query that affects multiple rows. 

There's no need to start the transaction, nor deal with rollback. If you want to rollback, simply throw an exception, and it'll rollback for you, while printing the exception solely in the error log. Execute allows you to efficiently reuse your prepared statement with different values.

```php
$mysqli->transactionCallback(function($mysqli) {
  $insert = $mysqli->query("INSERT INTO table (sender, receiver) VALUES (?, ?)", [28, 330]);
  if($insert->affectedRows() < 1) throw new Exception('Error inserting');
  echo $insert->insertId();
  $insert->execute([243, 49]); //reuse same insert query
  $delete = $mysqli->query("DELETE FROM table WHERE max_bench < ?", [125]);
});
```

## Error Handling

Either wrap all your queries with one `try/catch` or use the `set_exception_handler()` function to either redirect to a global error page or a separate one for each page. **Don't forget to take out echo in production**, as you obviously do not need the client to see this information.

### Gotcha with Exception Handling

For some reason, `mysqli_sql_exception` doesn't correctly convert errors to exceptions when too many bound variables or types on `bind_param()`. This is why you should probably set a global error handler to convert this error to an exception. I'm showing how to convert all errors to exceptions, but it should be noted that a lot of programmers view this as controversial. In this case you only really have to worry about `E_WARNING` anyway.

```php
set_error_handler(function($errno, $errstr, $errfile, $errline) {
  throw new Exception("$errstr on line $errline in file $errfile");
});
```

**Try/Catch**

```php
//include mysqli_connect.php
try {
  $insert = $mysqli->query("INSERT INTO myTable (name, age) VALUES (?, ?)", [$_POST['name'], $_POST['age']]);
} catch (Exception $e) {
  error_log($e);
  exit('Error inserting');
}
```

**Custom Exception Handler**

```php
//include mysqli_connect.php
set_exception_handler(function($e) {
  error_log($e);
  exit('Error inserting');
});
$insert = $mysqli->query("INSERT INTO myTable (name, age) VALUES (?, ?)", [$_POST['name'], $_POST['age']]);
```

# Documentation

## Constructor

```php
new SimpleMySQLi(string $host, string $username, string $password, string $dbName, string $charset = 'utf8mb4', string $defaultFetchType = 'assoc')
```

**Parameters**

- **string $host** - Hostname or an IP address, like localhost or 127.0.0.1
- **string $username** - Database username
- **string $password** - Database password
- **string $dbName** - Database name
- **string $charset = 'utf8mb4'** (optional) - Default character encoding
- **string defaultFetchType = 'assoc'** (optional) - Default fetch type. Can be:
  - **'assoc'** - Associative array
  - **'obj'** - Object array
  - **'num'** - Number array
  - **'col'** - 1D array. Same as `PDO::FETCH_COLUMN`


**Throws**

- **SimpleMySQLiException** if `$defaultFetchType` specified isn't one of the allowed fetch modes
- **mysqli_sql_exception** If any mysqli function failed due to `mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)`

## query()

```php
function query(string $sql, array $values = [], string $types = '')
```

**Description**

All queries go here. If select statement, needs to be used with either `fetch()` for single row and loop fetching or `fetchAll()` for fetching all results.

**Parameters**

- **string $sql** - SQL query
- **array $values = []** (optional) - values or variables to bind to query; can be empty for selecting all rows
- **string $types = ''** (optional) - variable type for each bound value/variable

**Returns**

`$this`

**Throws**

- **mysqli_sql_exception** If any mysqli function failed due to `mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)`

## execute()

```php
function execute(array $values = [], string $types = '')
```

**Description**

Used in order to be more efficient if same SQL is used with different values

**Parameters**

- **array $values = []** (optional) - values or variables to bind to query; can be empty for selecting all rows
- **string $types = ''** (optional) - variable type for each bound value/variable

**Returns**

`$this`

**Throws**

- **mysqli_sql_exception** If any mysqli function failed due to `mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)`

## whereIn()

```php
function whereIn(array $inArr)
```

**Description**

Create correct number of questions marks for `WHERE IN()` array.

**Parameters**

- **array $inArr = []** - array used in WHERE IN clause

**Returns**

Correct number of question marks

**Throws**

- **mysqli_sql_exception** If any mysqli function failed due to `mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)`


## numRows()

```php
function numRows()
```

**Description**

Get number of rows from SELECT.

**Returns**

- **$mysqli->num_rows**

**Throws**

- **mysqli_sql_exception** If any mysqli function failed due to `mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)`


## affectedRows()

```php
function affectedRows()
```

**Description**

Get affected rows. Can be used instead of numRows() in SELECT

**Returns**

- **$mysqli->affected_rows**

**Throws**

- **mysqli_sql_exception** If any mysqli function failed due to `mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)`

## affectedRowsInfo()

```php
function affectedRowsInfo()
```

**Description**

A more specific version of affectedRows() to give you more info what happened. Uses $mysqli::info under the hood. Can be used for the [following cases](http://php.net/manual/en/mysqli.info.php)

**Returns**

- **Associative array converted from result string**

**Throws**

- **mysqli_sql_exception** If any mysqli function failed due to `mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)`

## insertId()

```php
function insertId()
```

**Description**

Get the latest primary key inserted

**Returns**

- **$mysqli->insert_id**

**Throws**

- **mysqli_sql_exception** If any mysqli function failed due to `mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)`

## fetch()

```php
function fetch(string $fetchType = '')
```

**Description**

Fetch one row at a time

**Parameters**

- **string $fetchType = ''** (optional) - This overrides the default fetch type set in the constructor. Check [here](#constructor) for possible values

**Returns**

- **1 array row of `$fetchType` specified**
- **Scalar** If 'col' type selected
- **NULL** if at the end of loop (same behavior as vanilla MySQLi)

**Throws**

- **SimpleMySQLiException**
  - If $fetchType specified isn't one of the allowed fetch modes
  - If fetch mode specification is violated
- **mysqli_sql_exception** If any mysqli function failed due to `mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)`

## fetchAll()

```php
function fetchAll(string $fetchType = '')
```

**Description**

Fetch all results in array

**Parameters**

- **string $fetchType = ''** (optional) - This overrides the default fetch type set in the constructor. Check [here](#constructor) for possible values. `fetchAll()` also has additional fetch modes:
  - **'keyPair'** - Unique key (1st column) to single value (2nd column). Same as `PDO::FETCH_KEY_PAIR`
  - **'keyPairArr'** - Unique key (1st column) to array. Same as `PDO::FETCH_UNIQUE`
  - **'group'** - Group by common values in the 1st column into associative subarrays. Same as `PDO::FETCH_GROUP`
  - **'groupCol'** - Group by common values in the 1st column into 1D subarray. Same as `PDO::FETCH_GROUP | PDO::FETCH_COLUMN`

**Returns**

- **Full array of `$fetchType` specified**
- **[]** if no results

**Throws**

- **SimpleMySQLiException**
  - If $fetchType specified isn't one of the allowed fetch modes
  - If fetch mode specification is violated
- **mysqli_sql_exception** If any mysqli function failed due to `mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)`

## transaction()

```php
function transaction(array|string $sql, array $values, array $types = [])
```

**Parameters**

- **array|string $sql** - SQL query; can be array for different queries or a string for the same query with different values
- **array $values** - values or variables to bind to query
- **string $types = ''** (optional) - variable type for each bound value/variable

**Throws**

- **SimpleMySQLiException** If there is a mismatch in parameter values, parameter types or SQL
- **mysqli_sql_exception** If transaction failed due to `mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)`

## transactionCallback()

```php
function transactionCallback(callable $callback($this))
```

**Description**

Do stuff inside of transaction

**Parameters**

- **callable $callback($this)** - Closure to do transaction operations inside. Parameter value is `$this`

**Throws**

- **mysqli_sql_exception** If transaction failed due to `mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)`

## freeResult()

```php
function freeResult()
```

**Description**

Frees MySQL result

**Throws**

- **mysqli_sql_exception** If mysqli function failed due to `mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)`

## closeStmt()

```php
function closeStmt()
```

**Description**

Closes MySQL prepared statement

**Throws**

- **mysqli_sql_exception** If mysqli function failed due to `mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)`

## close()

```php
function close()
```

**Description**

Closes the MySQL connection.

**Throws**

- **mysqli_sql_exception** If mysqli function failed due to `mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)`

# Changelog

Changelog can be found [here](CHANGELOG.md)
