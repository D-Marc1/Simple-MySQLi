# Simple MySQLi - MySQLi Wrapper

Using MySQLi prepared statements is a great way to prevent against SQL injection, but it can start feeling tedious after a while. I thought this could be improved a little, which is why wanted to create a highly abstracted MySQLi database wrapper, while also ensuring that the SQL queries aren't broken up into proprietary syntactic sugar chaining. This way, so you can have extremely concise code, while still keeping your SQL syntax intact. I'll concede that I'm slightly reinventing the wheel, as there are already some wrappers with similarities to mine, but I figured I'd make an extremely lightweight, yet powerful enough one for most people to use.

I specifically chose MySQLi over PDO in case I decide to add any of the MySQL-specific features it has in the future, like asynchronous queries for instance. Currently, I'm not using any, but I wanted to keep the option open.

On a side note, if you'd like to know how to use MySQLi the "vanilla way", check out [this tutorial on MySQLi Prepared Statements](https://websitebeaver.com/prepared-statements-in-php-mysqli-to-prevent-sql-injection).

# Why Should I Use Simple MySQLi?

- Concise Code
- SQL queries are the same
- Accounts for most common uses
- Variable type is optional
- Bind variables *and* values (not sure how useful this is though)

# Why Not Use It?

- No named parameters
- If you need to use some of the more obscure MySQLi functions, then this is certainly not the right fit for you.

The purpose of this class is to keep things as simple as possible, while accounting for the most common uses. If there's something you'd like me to add, feel free to suggest it or send a pull request.

# Supported Versions

PHP 7.0+

# Table of Contents

- [Examples](#examples)
  - [Insert](#insert)
  - [Update](#update)
  - [Delete](#delete)
  - [Select](#select)
    - [Fetch Each Column as Separate Array Variable](#fetch-each-column-as-separate-array-variable)
    - [Fetch Associative Array](#fetch-associative-array)
    - [Fetch Single Row](#fetch-single-row)
    - [Fetch Array of Objects](#fetch-array-of-objects)
  - [Like](#like)
  - [Where In Array](#where-in-array)
    - [With Other Placeholders](#with-other-placeholders)
  - [Transactions](#transactions)
    - [Same Template, Different Values](#same-template-different-values)
- [Documentation](#documentation)
  - [Constructor](#constructor)
  - [insert()](#insert-function)
  - [update()](#update-function)
  - [delete()](#delete-function)
  - [select()](#select-function)
  - [transaction()](#transaction-function)
  - [close()](#close-function)

# Examples

Let's get straight to the point! The best way to learn is by examples.

## Create a New MySQL Connection

One of the aspects of MySQLi I actually like a lot is the fact that error reporting is automatically turned off. Unfortunately I wasn't able to replicate this, as I throw an excpetion on the the constructor, therefore potentially exposing the parameter values. This is why I turned on mysqli reporting by doing `mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)` — since you'll be wrapping it in a `try/catch` block anyway. So you must either wrap it around in a `try/catch` block or create your own custom handler. Make sure you use `$e->getMessage()` and not `$e` so your password isn't exposed. Either way, you must solely report your errors in your error log. To do this, make sure your `php.ini` file has the following settings in production: `display_errors = Off` and `log_errors = On`. Keep in mind that using `echo`, `die` or `exit` to print the error message is extremely dangerous as well.

**Try/Catch**

```php
try {
  $typeRequired = false; //Doesn't enforce specifying types and will treat everything as a string
  $mysqli = new SimpleMySQLi("localhost", "username", "password", "dbName", $typeRequired, "utf8", "assoc");
} catch(Exception $e) {
  error_log($e->getMessage());
  exit('Someting weird happened'); //Should be a message a typical user could understand
}
```

**Custom Exception Handler**

This is pretty neat, since you can avoid nesting. It is commonly used to redirect to a single error page, but can be used like the following as well. You can reassign this to give specific messages on your AJAX pages as well.

```php
set_exception_handler(function($e) {
  error_log($e->getMessage());
  exit('Someting weird happened'); //Should be a message a typical user could understand
});
$mysqli = new SimpleMySQLi("localhost", "username", "password", "dbName", $typeRequired, "utf8", "assoc");
```

## Insert

```php
$insert = $mysqli->insert("INSERT INTO myTable (name, age) VALUES (?, ?)", [$_POST['name'], $_POST['age']]);
echo $insert->affected_rows;
```

```php
//returns $stmt->affected_rows by default; if set to true, then it will print object with $mysqli->insert_id too
$insert = $mysqli->insert("INSERT INTO myTable (name, age) VALUES (?, ?)", [$_POST['name'], $_POST['age']], true);
echo $insert->affected_rows;
echo $insert->insert_id;
```

## Update

```php
$update = $mysqli->update("UPDATE myTable SET name = ? WHERE id = ?", [$_POST['name'], $_SESSION['id']]);
echo $update->affected_rows;
```

## Delete

```php
$delete = $mysqli->delete("DELETE FROM myTable WHERE id = ?", [$_SESSION['id']]);
echo $delete->affected_rows;
```

## Select

### Fetch Each Column as Separate Array Variable

```php
$arr = $mysqli->select("SELECT name, email, number FROM events WHERE id <= ?", [$_SESSION['id']]);
$names = array_column($arr, 'name');
$emails = array_column($arr, 'email');
$numbers = array_column($arr, 'number');
```

### Fetch Associative Array

```php
$arr = $mysqli->select("SELECT id, name, age FROM events WHERE id <= ?", [4], 'assoc'); //not necessary to specify 'assoc' if default fetch type
```

### Fetch Single Row

```php
$arr = $mysqli->select("SELECT id, name, age FROM events WHERE id <= ?", [12], 'singleRowAssoc'); //not necessary to specify 'singleRowAssoc' if default fetch type
```

### Fetch Single Row Like bind_result()

```php
$arr = $mysqli->select("SELECT id, name, age FROM myTable WHERE name = ?", [$_POST['name']], 'singleRowNum'); //must use number array to use in list
list($id, $name, $age) = $arr;
```

### Fetch Array of Objects

```php
$arr = $mysqli->select("SELECT id, name, age FROM events WHERE id <= ?", [4], 'obj'); //not necessary to specify 'obj' if default fetch type
```

## Like

```php
$search = "%{$_POST['search']}%";
$arr = $mysqli->select("SELECT id, name, age FROM events WHERE name LIKE ?", [$search]);
```

## Where In Array

```php
$inArr = [12, 23, 44];
$clause = implode(',', array_fill(0, count($inArr), '?'));
$arr = $mysqli->select("SELECT event_name, description, location FROM events WHERE id IN($clause)", $inArr);
```

### With Other Placeholders

```php
$inArr = [12, 23, 44];
$clause = implode(',', array_fill(0, count($inArr), '?'));
$fullArr = array_merge($inArr, [5]);
$arr = $mysqli->select("SELECT event_name, description, location FROM events WHERE id IN($clause) AND id < ?", $fullArr);
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

Unfortunately, I wasn't able to figure out a good way to do this the efficient method of simply reassigning the variable name. I don't think this is an extremely common use case anyway, but perhaps in the future I'll think of a good way to implement it. It's still extremely short code and I doubt anyone would seriously notice a huge performance difference anyway.

```php
$sql = "INSERT INTO myTable (name, age) VALUES (?, ?)";
$arrOfValues = [[$_POST['name'], $_POST['age']], ['Pablo', 34], [$_POST['name'], 22]];
$mysqli->transaction($sql, $arrOfValues);
```

## Error Handling

Either wrap all your queries with one `try/catch` or use the `set_exception_handler()` function to either redirect to a global error page or a separate one for each page. **Don't forget to take out echo in production**, as you obviously do not need the client to see this information.

**Try/Catch**

```php
//include mysqli_connect.php
try {
  $insert = $mysqli->insert("INSERT INTO myTable (name, age) VALUES (?, ?)", [$_POST['name'], $_POST['age']]);
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
$insert = $mysqli->insert("INSERT INTO myTable (name, age) VALUES (?, ?)", [$_POST['name'], $_POST['age']]);
```

# Documentation

## Constructor

```php
new SimpleMySQLi(string $host, string $username, string $password, string $dbName, bool $typeRequired = true, string $charset = 'utf8', string $defaultFetchType = 'assoc')
```

**Parameters**

- **string $host** - the host, like localhost for example
- **string $username** - database username
- **string $password** - database password
- **string $dbName** - database name
- **bool $typeRequired = true** (optional) - true/false if variable type is required to be specified. If false, it will treat everything as a string
- **string $charset = 'utf8'** (optional) - default character encoding
- **string defaultFetchType = 'assoc'** (optional) - default fetch type. Can be:
  - **'assoc'** - associative array
  - **'obj'** - object array
  - **'num'** - number array
  - **'singleRowAssoc'** - single row with associative keys
  - **'singleRowObj'** - single row as object
  - **'singleRowNum'** - single row with numbers

**Throws**

- Throws exception if `$defaultFetchType` specified isn't one of the allowed fetch modes
- Throws exception with reason if any mysqli function failed due to `mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)`

## Insert Function

```php
function insert(string $sql, array $values, bool $getInsertId = false, string $types = '')
```

**Parameters**

- **string $sql** - SQL query
- **array $values** - values or variables to bind to query
- **bool $getInsertId = false** (optional) - if true, returns the primary key of the latest inserted rows in an object with affectedRows and insertId
- **string $types = ''** (optional) - variable type for each bound values/variable

**Returns**

- **an object that can be called with $insert->affected_row**
- **an object that can be called $insert->affected_row and $insert->insert_id** if `$getInsertId = true`

**Throws**

- Throws exception with reason if any mysqli function failed due to `mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)`

## Update Function

```php
function update(string $sql, array $values, string $types = '')
```

**Parameters**

- **string $sql** - SQL query
- **array $values** - values or variables to bind to query
- **string $types = ''** (optional) - variable type for each bound values/variable

**Returns**

- **an object that can be called with $insert->affected_row**

**Throws**

- Throws exception with reason if any mysqli function failed due to `mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)`

## Delete Function

```php
function delete(string $sql, array $values, string $types = '')
```

**Parameters**

- **string $sql** - SQL query
- **array $values** - values or variables to bind to query
- **string $types = ''** (optional) - variable type for each bound values/variable

**Returns**

- **an object that can be called with $insert->affected_row**

**Throws**

- Throws exception with reason if any mysqli function failed due to `mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)`

## Select Function

```php
function select(string $sql, array $values = [], string $fetchType = '', string $types = '')
```

**Parameters**

- **string $sql** - SQL query
- **array $values = []** (optional) - values or variables to bind to query; can be empty for selecting all rows
- **string $fetchType = ''** (optional) - This overrides the default fetch type set in the constructor. Check [here](#constructor) for possible values
- **string $types = ''** (optional) - variable type for each bound values/variable

**Returns**

- **Array of `$fetchType` specified**
- **[]** if select yields 0 rows

**Throws**

- Throws exception if `$fetchType` specified isn't one of the allowed fetch modes
- Throws exception with reason if any mysqli function failed due to `mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)`

## Transaction Function

```php
function transaction(mixed $sql, array $values, array $types = [])
```

**Parameters**

- **mixed $sql** - SQL query; can be array for different queries or a string for the same query with different values
- **array $values** - values or variables to bind to query
- **string $types = ''** (optional) - variable type for each bound values/variable

**Throws**

- Throws exception if transaction fails
- Throws exception with reason if any mysqli function failed due to `mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)`

## Close Function

```php
function close()
```

**Description**

Closes the MySQL connection.
