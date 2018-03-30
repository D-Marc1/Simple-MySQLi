# Changelog

- [**1.4.6**](https://github.com/WebsiteBeaver/Simple-MySQLi/tree/1.4.6) - March 29, 2018

  - Add composer and return type for `$this`.

- [**1.4.5**](https://github.com/WebsiteBeaver/Simple-MySQLi/tree/1.4.5) - March 28, 2018

	- Remove `setRowsMatched()`, in favor of the new getter method `rowsMatched()`

- [**1.4.4**](https://github.com/WebsiteBeaver/Simple-MySQLi/tree/1.4.4) - March 27, 2018

	- Enforce return type declaration on methods when possible
	- Switch to more consistent if style

- [**1.4.3**](https://github.com/WebsiteBeaver/Simple-MySQLi/tree/1.4.3) - March 25, 2018

  - Fix off-by-one error to close stmt with `transaction()` on prepare once, execute multiple

- [**1.4.2**](https://github.com/WebsiteBeaver/Simple-MySQLi/tree/1.4.2) - March 23, 2018

  - Fix `transaction()` on prepare once, execute multiple

- [**1.4.1**](https://github.com/WebsiteBeaver/Simple-MySQLi/tree/1.4.1) - March 20, 2018

  - Add `setRowsMatched()` to use rows matched, instead of rows changed on UPDATE query

- [**1.4.0**](https://github.com/WebsiteBeaver/Simple-MySQLi/tree/1.4.0) - March 13, 2018

  - Add `execute()`, `whereIn()`, `numRows()`, `transactionCallback()`, `freeResult()`, `closeStmt()`
  - Default charset is now 'utf8mb4' instead of 'utf8'
  - Don't automatically free result anymore on `fetchAll()`
  - Add ability to fetch into class

- [**1.3.2**](https://github.com/WebsiteBeaver/Simple-MySQLi/tree/1.3.2) - January 25, 2018

  - Add `affectedRowsInfo()` and free fetch results

- [**1.3.1**](https://github.com/WebsiteBeaver/Simple-MySQLi/tree/1.3.1) - January 24, 2018

  - Fix `fetchAll()` types `keyPairArr` and `group`

- [**1.3.0**](https://github.com/WebsiteBeaver/Simple-MySQLi/tree/1.3.0) - January 24, 2018

  - All queries now use a global `query()` functions
  - `affectedRows()` and `insertId()` added as separate functions instead of returned in object due to switching to `query()`

- [**1.2.0**](https://github.com/WebsiteBeaver/Simple-MySQLi/tree/1.2.0) - January 18, 2018

  - Select statements now must be chained with `fetch()` for one row at a time and `fetchAll()` for all results

- [**1.1.2**](https://github.com/WebsiteBeaver/Simple-MySQLi/tree/1.1.2) - January 15, 2018

  - Fix return on `delete()`

- [**1.1.1**](https://github.com/WebsiteBeaver/Simple-MySQLi/tree/1.1.1) - January 11, 2018

  - Fix transactions

- [**1.1.0**](https://github.com/WebsiteBeaver/Simple-MySQLi/tree/1.1.0) - January 3, 2018

  - Add new fetch modes for convenience: 'scalar', 'col', 'keyPair', 'keyPairArr', 'group', 'groupCol'

- [**1.0.0**](https://github.com/WebsiteBeaver/Simple-MySQLi/tree/1.0.0) - December 28, 2017

  - Initial Release
