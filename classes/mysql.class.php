<?php

/*
+---------------------------------------------------------------------------
|
|   mysql.class.php (php 5.1+)
|
|   by Benjam Welker
|   http://iohelix.net
|
+---------------------------------------------------------------------------
|
|   > MySQL PDO module
|   > Date started: 2014-01-17
|
|   > Module Version Number: 0.9.0
|
+---------------------------------------------------------------------------
*/

/**
 * Class Mysql
 * Singleton
 */
class Mysql {

	/**
	 *		PROPERTIES
	 * * * * * * * * * * * * * * * * * * * * * * * * * * */

	/**
	 * @var Mysql instance
	 */
	public static $instance;

	/**
	 * Connection Object
	 *
	 * @var PDO
	 */
	public $conn;

	/**
	 * The connection settings string
	 *
	 * @var string
	 */
	protected $conn_config;

	/**
	 * Default settings
	 *
	 * @var array
	 */
	protected $defaults = [
		'driver' => 'mysql',
		'hostname' => 'localhost',
		'port' => 3306,
		'log_path' => './',
	];

	/**
	 * Actual settings
	 *
	 * @var array
	 */
	protected $settings;

	/**
	 * The query
	 *
	 * @var string
	 */
	public $query;

	/**
	 * The query params
	 *
	 * @var array
	 */
	public $params;

	/**
	 * @var PDOStatement handler
	 */
	public $sth;

	/**
	 * @var bool
	 */
	protected $prepared;

	/**
	 * Debug output for errors
	 *
	 * @var bool
	 */
	protected $debug_error = false;

	/**
	 * Debug output for all queries
	 *
	 * @var bool
	 */
	protected $debug_query = false;

	/**
	 * @var PDO error
	 */
	public $error;

	/**
	 * Error email settings
	 *
	 * @var array
	 */
	protected $email_settings = [
		'errors' => false,
		'subject' => 'Query Error',
		'from' => 'example@example.com',
		'to' => 'example@example.com',
	];

	/**
	 * Error log settings
	 *
	 * @var array
	 */
	protected $log_settings = [
		'errors' => false,
		'path' => './',
	];

	/**
	 * The number of queries run
	 *
	 * @var int
	 */
	public $query_count = 0;

	/**
	 * The time spent running queries
	 *
	 * @var float
	 */
	public $query_time = 0;


	/**
	 *		METHODS
	 * * * * * * * * * * * * * * * * * * * * * * * * * * */

	/**
	 * Class constructor
	 * Not publicly callable, as this is a singleton class
	 *
	 * @param array $settings optional
	 *
	 * @return Mysql Object
	 * @throws Exception
	 * @throws MySQLException
	 */
	protected function __construct($settings = null) {
		if (empty($settings) && isset($GLOBALS['_DEFAULT_DATABASE'])) {
			$settings = $GLOBALS['_DEFAULT_DATABASE'];
		}

		if (empty($settings)) {
			throw new MySQLException(__METHOD__.': Missing MySQL configuration data');
		}

		$settings = array_merge($this->defaults, $settings);
		$this->settings = $settings;

		$this->conn_config = "{$settings['driver']}:host={$settings['hostname']};port={$settings['port']};dbname={$settings['database']};charset=utf8";

		$this->log_settings['path'] = $settings['log_path'];

        $this->connect( );
    }


	/**
	 * Class destructor
	 * cleans up the mess it made
	 *
	 * @param void
	 *
	 * @return void
	 */
	public function __destruct( ) {
		$this->reset( );
		$this->conn = null;
	}


	/**
	 * Return a singleton instance
	 *
	 * @param null $config
	 *
	 * @return Mysql Singleton Reference
	 * @throws MySQLException
	 * @throws Exception
	 */
	public static function get_instance($config = null) {
        if (is_null(self::$instance)) {
            self::$instance = new Mysql($config);
        }

        return self::$instance;
	}


	/**
	 * Connect to the database
	 *
	 * @param void
	 *
	 * @return void
	 * @throws MySQLException
	 */
	public function connect( ) {
		if (empty($this->settings['username']) || empty($this->settings['password'])) {
			throw new MySQLException(__METHOD__.': Missing MySQL user data');
		}

		$this->conn = new PDO($this->conn_config, $this->settings['username'], $this->settings['password']);

		if (empty($this->conn)) {
			throw new MySQLException(__METHOD__.': Unable to connect to database');
		}

		$this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

		// set the DB server timezone to UTC
		$this->conn->query(" SET time_zone = '+00:00'; ");
	}


	/**
	 * Set the error settings
	 *
	 * @param array $settings
	 *
	 * @return void
	 */
	public function set_settings(array $settings) {
		$valid = [
			'log_errors',
			'log_path',
			'email_errors',
			'email_subject',
			'email_from',
			'email_to',
		];

		foreach ($valid as $key) {
			if (isset($settings[$key])) {
				list($type, $idx) = explode('_', $key);
				$var = $type.'_settings';
				$this->{$var} = $settings[$key];
			}
		}
	}


	/**
	 * Set a bitwise debugging level
	 *    0- none
	 *    1- errors only
	 *    2- queries only
	 *    3- all
	 *
	 * @param int $error_level
	 *
	 * @return void
	 */
	public function set_error(int $error_level) {
		$error_level = (int) $error_level;
		$this->debug_error = (0 != (1 & $error_level));
		$this->debug_query = (0 != (2 & $error_level));
	}


	/**
	 * Tests the MySQL connection and tries to reconnect
	 *
	 * @deprecated
	 *
	 * @param void
	 *
	 * @return void
	 */
	public function test_connection( ) {
		// do nothing
	}


	/**
	 * Run the query
	 * Include named parameters as second argument
	 *
	 * @param string $query optional
	 *
	 * @return bool|PDOStatement
	 * @throws MySQLException
	 */
	public function query($query = null) {
		$this->process_args(func_get_args( ), 1);

		if (empty($this->query)) {
			throw new MySQLException(__METHOD__.': No query found');
		}

		$backtrace_file = $this->_get_backtrace( );

		$this->_log(__METHOD__.' in '.basename($backtrace_file['file']).' on '.$backtrace_file['line'].' : '.$this->query);

		if ( ! $this->conn) {
			$this->connect( );
		}

		$done = true;

		try {
			$time = microtime(true);
			if ( ! empty($this->params)) {
				if ( ! $this->prepared && ! empty($this->query)) {
					$this->sth = $this->conn->prepare($this->query);
					$this->prepared = true;
				}

				$this->sth->execute($this->params);
			}
			elseif ( ! $this->prepared && ! empty($this->query)) {
				$this->sth = $this->conn->query($this->query);
				$this->prepared = true;
			}

			$query_time = microtime(true) - $time;
			$this->query_time += $query_time;

			if ($this->debug_query && empty($GLOBALS['AJAX'])) {
				$debug_query = trim(preg_replace('%\s+%', ' ', $this->query));
				if ( ! empty($this->params)) {
					$debug_query = str_replace(array_keys($this->params), $this->params, $debug_query);
				}

				if (('cli' == php_sapi_name( )) && empty($_SERVER['REMOTE_ADDR'])) {
					echo "\n\nMYSQL - ".basename($backtrace_file['file']).' on '.$backtrace_file['line']."- {$debug_query} - Aff(".$this->affected_rows( ).") (".number_format($query_time, 5)." s)\n\n";
				}
				else {
					echo "<div style='background:#FFF;color:#009;'><br /><strong>".basename($backtrace_file['file']).' on '.$backtrace_file['line']."</strong>- {$debug_query} - <strong>Aff(".$this->affected_rows( ).") (".number_format($query_time, 5)." s)</strong></div>";
				}
			}
		}
		catch (PDOException $poo) {
			if (empty($this->tries)) {
				$this->tries = 0;
			}

			if ($this->sth) {
				$error_info = $this->sth->errorInfo( );
			}
			else {
				$error_info = $this->conn->errorInfo( );
			}

			if ((5 >= $this->tries) && ((2013 == $error_info[1]) || (2006 == $error_info[1]))) {
				// try reconnecting a couple of times
				$this->_log('RETRYING #'.$this->tries.': '.$error_info[1]);
				return $this->query(null, ++$this->tries);
			}

			$extra = '';
			if ($backtrace_file) {
				$line = $backtrace_file['line'];
				$file = $backtrace_file['file'];
				$file = substr($file, strlen(realpath($file.'../../../')));
				$extra = ' on line <strong>'.$line.'</strong> of <strong>'.$file.'</strong>';
			}

			$this->error = $error_info[1].': '.$error_info[2];
			$this->_error_report( );

			if ($this->debug_error) {
				if (('cli' == php_sapi_name( )) && empty($_SERVER['REMOTE_ADDR'])) {
					$extra = strip_tags($extra);
					echo "\n\nMYSQL ERROR - There was an error in your query{$extra}:\nERROR: {$this->error}\nQUERY: {$this->query}\n\n";
				}
				else {
					echo "<div style='background:#900;color:#FFF;'>There was an error in your query{$extra}:<br />ERROR: {$this->error}<br />QUERY: {$this->query}</div>";
				}
			}
			else {
				$this->error = 'There was a database error.';
			}

			$done = false;
		}

		if ($done) {
			$this->query_count++;

			// if we just performed an insert, grab the insert_id and return it
			if (preg_match('/^\s*(?:INSERT|REPLACE)\b/i', $this->query)) {
				return $this->fetch_insert_id( );
			}

			return $this->sth;
		}

		if (preg_match('/^\s*SELECT\b/i', $this->query)) {
			// no result found
			return false;
		}
		else {
			// query performed successfully
			return true;
		}
	}


	/**
	 * Returns the number of affected rows in the last query
	 *
	 * @param void
	 *
	 * @return int
	 */
	public function affected_rows( ) {
		return $this->sth->rowCount( );
	}


	/**
	 * Return the number of rows found in the last select
	 *
	 * @deprecated
	 *
	 * @param void
	 *
	 * @return void
	 * @throws MySQLException
	 */
	public function num_rows( ) {
		ini_set('display_errors', true);
		error_reporting(-1);
		throw new MySQLException(__METHOD__.': Method is deprecated');
	}


	/**
	 * Build a WHERE clause from a conditions array
	 *
	 * @param array  $where conditions
	 * @param string $join  method ('AND', 'OR')
	 *
	 * @return string WHERE clause
	 */
	protected function build_where(array $where, $join = 'AND') {
		if (empty($where)) {
			return ' 1 = 1 ';
		}

		$join = trim(strtoupper($join));
		$clauses = [];
		foreach ($where as $clause => $value) {
			if (is_numeric($clause) && is_array($value)) {
				$clauses[] = '( '.$this->build_where($value).' )';
			}
			elseif (in_array(strtoupper(trim($clause)), ['AND', 'OR'])) {
				$clauses[] = '( '.$this->build_where($value, strtoupper(trim($clause))).' )';
			}
			else {
				if (is_null($value)) {
					$value = 'NULL';
				}
				elseif (is_bool($value)) {
					$value = $value ? 'TRUE' : 'FALSE';
				}

				if (false === strpos($clause, ' ')) {
					if (is_array($value)) {
						if (empty($value) && ('AND' === $join)) {
							return ' 1 = 1 ';
						}
						elseif ( ! empty($value)) {
							$clauses[] = $clause." IN ('".implode("','", $value)."')";
						}
					}
					else {
						if (is_numeric($clause)) {
							$clauses[] = $value;
						}
						else {
							if ( ! in_array($value, ['NULL', 'TRUE', 'FALSE']) && (0 !== strpos($value, ':')) && ('?' !== $value)) {
								$value = $this->conn->quote($value);
							}

							$clauses[] = $clause.' = '.$value;
						}
					}
				}
				else {
					if (is_array($value)) {
						if (empty($value) && ('AND' === $join)) {
							return ' 1 = 1 ';
						}
						elseif ( ! empty($value)) {
							$clauses[] = $clause." ANY ('".implode("','", $value)."')";
						}
					}
					else {
						if ( ! in_array($value, ['NULL', 'TRUE', 'FALSE']) && (0 !== strpos($value, ':')) && ('?' !== $value)) {
							$value = "'{$value}'";
						}

						$clauses[] = $clause.' '.$value;
					}
				}
			}
		}

		return implode(' '.$join.' ', $clauses);
	}


	/**
	 * Extract the query parameters and values
	 * from a conditions array
	 *
	 * @param array $where
	 *
	 * @return array params
	 */
	protected function get_params(array $where) {
		$params = [];
// TODO: not quite sure how to build this
// maybe parse through the array, and anywhere a value is found
// insert it into the params array with the key name as it's index,
// and then replace it with the key name with : appended
		return $params;
	}


	/**
	 * Insert|Update|Replace table entry
	 *        $data['field_name'] = value
	 *        $data['field_name2'] = value2
	 * If the field name has a trailing space: $data['field_name ']
	 * then the query will insert the data with no processing
	 * or wrapping quotes (good for function calls, like NOW( )).
	 *
	 * @param string       $table
	 * @param array        $data_array
	 * @param string|array $where
	 * @param bool         $replace
	 *
	 * @return bool
	 * @throws MySQLException
	 */
	public function insert(string $table, $data_array, $where = '', $replace = false) {
		$this->reset( );

		if (is_array($where) && ! empty($where)) {
			$this->params = $this->get_params($where);
			$where = " WHERE ". $this->build_where($where);
		}

		$replace = (bool) $replace;

		if (empty($where)) {
			$query  = (false == $replace) ? ' INSERT ' : ' REPLACE ';
			$query .= ' INTO ';
			$where = '';
		}
		else {
			$query = ' UPDATE ';
		}

		$query .= " `{$table}` ";

		if ( ! is_array($data_array)) {
			throw new MySQLException(__METHOD__.': Trying to insert non-array data');
		}
		else {
			$query .= ' SET ';
			foreach ($data_array as $field => $value) {
				if (is_null($value)) {
					$value = 'NULL';
				}
				elseif (is_bool($value)) {
					$value = ($value ? 'TRUE' : 'FALSE');
				}
				elseif (' ' == substr($field, -1, 1)) { // a trailing space was chosen because it's an illegal field name in MySQL
					$field = trim($field); // and it's easy to trim
				}
				else {
					$key = ":{$field}_val";
					$this->params[$key] = $value;
					$value = $key;
				}

				$query .= " `{$field}` = {$value}, ";
			}

			$query = substr($query, 0, -2).' '; // remove the last comma (but preserve the spaces)
		}

		$query .= " {$where} ";

		$this->prepared = false;
		$this->query = $query;

		$return = $this->query( );

		if (empty($where)) {
			return $this->fetch_insert_id( );
		}
		else {
			return $return;
		}
	}


	/**
	 * Perform insert on multiple entries at once
	 *        $data[0]['field_name'] = value
	 *        $data[0]['field_name2'] = value2
	 *        $data[0]['DBWHERE'] = where clause [optional]
	 *        $data[1]['field_name'] = value
	 *        $data[1]['field_name2'] = value2
	 *        $data[1]['DBWHERE'] = where clause [optional]
	 *
	 * @param string $table
	 * @param array  $data_array
	 * @param bool   $replace optional
	 *
	 * @return array
	 * @throws MySQLException
	 */
	public function multi_insert(string $table, array $data_array, $replace = false) {
		if ( ! is_array($data_array)) {
			throw new MySQLException(__METHOD__.': Trying to multi-insert non-array data');
		}
		else {
			$result = [];

			foreach ($data_array as $key => $row) {
				$where = (isset($row['DBWHERE'])) ? $row['DBWHERE'] : '';
				unset($row['DBWHERE']);
				$result[$key] = $this->insert($table, $row, $where, $replace);
			}
		}

		return $result;
	}


	/**
	 * Delete an entry from a table
	 *
	 * @param string       $table
	 * @param string|array $where
	 *
	 * @return bool
	 * @throws MySQLException
	 */
	public function delete(string $table, $where) {
		$this->reset( );

		if (is_array($where)) {
			$this->params = $this->get_params($where);
			$where = " WHERE ". $this->build_where($where);
		}

		$this->prepared = false;
		$this->query = "
			DELETE
			FROM `{$table}`
			{$where}
		";

		try {
			return $this->query( );
		}
		catch (MySQLException $crap) {
			throw $crap;
		}
	}


	/**
	 * Delete the array of data from the table.
	 *        $table[0] = table name
	 *        $table[1] = table name
	 *        $where[0] = where clause
	 *        $where[1] = where clause
	 * If recursive is true, all combinations of table name
	 * and where clauses will be executed.
	 * If only one table name is set, that table will
	 * be used for all of the queries, looping through
	 * the where array
	 * If only one where clause is set, that where clause
	 * will be used for all of the queries, looping through
	 * the table array
	 *
	 * @param array $table_array
	 * @param array $where_array
	 * @param bool  $recursive optional
	 *
	 * @return array of results
	 * @throws MySQLException
	 */
	public function multi_delete(array $table_array, $where_array, $recursive = false) {
		if ( ! is_array($table_array)) {
			$recursive = false;
			$table_array = (array) $table_array;
		}

		if (is_array($where_array)) {
			list($key,) = each($where_array);
			if (is_string($key)) {
				$recursive = false;
				$where_array = [$where_array];
			}
		}
		else {
			$recursive = false;
			$where_array = [$where_array];
		}

		$result = [];

		if ($recursive) {
			foreach ($table_array as $table) {
				foreach ($where_array as $where) {
					$result[] = $this->delete($table, $where);
				}
			}
		}
		else {
			if (count($table_array) == count($where_array)) {
				for ($i = 0, $count = count($table_array); $i < $count; ++$i) {
					$result[] = $this->delete($table_array[$i], $where_array[$i]);
				}
			}
			elseif (1 == count($table_array)) {
				$table = $table_array[0];
				foreach ($where_array as $where) {
					$result[] = $this->delete($table, $where);
				}
			}
			elseif (1 == count($where_array)) {
				$where = $where_array[0];
				foreach ($table_array as $table) {
					$result[] = $this->delete($table, $where);
				}
			}
			else {
				throw new MySQLException(__METHOD__.': Trying to multi-delete with incompatible array sizes');
			}
		}

		return $result;
	}


	/**
	 * Select entries from a table
	 *
	 * @param string       $table
	 * @param string|array $where
	 *
	 * @return PDOStatement
	 * @throws MySQLException
	 */
	public function fetch(string $table, $where = '') {
		$this->reset( );

		$this->query = "
			SELECT *
			FROM `{$table}`
		";

		if ( ! empty($where)) {
			if (is_array($where)) {
				$this->query .= " WHERE ".$this->build_where($where);
				$this->params = $this->get_params($where);
			}
			else {
				$this->query .= " {$where} ";
			}
		}

		return $this->query( );
	}


	/**
	 * Execute a database query and return the next result row as object.
	 * Each subsequent call to this method returns the next result row.
	 *
	 * @param string $query optional
	 *
	 * @return mixed
	 * @throws MySQLException
	 */
	public function fetch_object($query = null) {
		$this->process_args(func_get_args( ));

		if ($query === $this->query) {
			$this->query( );
		}

		if ( ! $this->sth) {
			throw new MySQLException(__METHOD__.': Query Result Handler Missing');
		}

		return $this->sth->fetchObject( );
	}


	/**
	 * Execute a database query and return result as an indexed array.
	 * Each subsequent call to this method returns the next result row.
	 *
	 * @param string $query optional
	 *
	 * @return mixed
	 * @throws MySQLException
	 */
	public function fetch_row($query = null) {
		$this->process_args(func_get_args( ));

		if ($query === $this->query) {
			$this->query( );
		}

		if ( ! $this->sth) {
			throw new MySQLException(__METHOD__.': Query Result Handler Missing');
		}

		return $this->sth->fetch(PDO::FETCH_NUM);
	}


	/**
	 * Execute a database query and return result as an associative array.
	 * Each subsequent call to this method returns the next result row.
	 *
	 * @param string $query optional
	 *
	 * @return mixed
	 * @throws MySQLException
	 */
	public function fetch_assoc($query = null) {
		$this->process_args(func_get_args( ));

		if ($query === $this->query) {
			$this->query( );
		}

		if ( ! $this->sth) {
			throw new MySQLException(__METHOD__.': Query Result Handler Missing');
		}

		return $this->sth->fetch(PDO::FETCH_ASSOC);
	}


	/**
	 * Execute a database query and return result as both
	 * an associative array and indexed array.
	 * Each subsequent call to this method returns the next result row.
	 *
	 * @param string $query optional
	 *
	 * @return mixed
	 * @throws MySQLException
	 */
	public function fetch_both($query = null) {
		$this->process_args(func_get_args( ));

		if ($query === $this->query) {
			$this->query( );
		}

		if ( ! $this->sth) {
			throw new MySQLException(__METHOD__.': Query Result Handler Missing');
		}

		return $this->sth->fetch(PDO::FETCH_BOTH);
	}


	/**
	 * Execute a database query and return result as
	 * an indexed array of both indexed and associative arrays.
	 * This method returns the entire result set in a single call.
	 *
	 * @param string $query optional
	 * @param string $key table column to key the result array on
	 * @param int $result_type
	 *
	 * @return mixed
	 * @throws MySQLException
	 */
	public function fetch_array($query = null, $key = null, $result_type = PDO::FETCH_ASSOC) {
		$args = func_get_args( );

		// allow the query params to be anywhere
		if ( ! empty($args[1]) && is_array($args[1])) {
			$this->process_args($args, 1, true);

			$key = null;
			if ( ! empty($args[2])) {
				$key = $args[2];
			}

			$result_type = PDO::FETCH_ASSOC;
			if ( ! empty($args[3])) {
				$result_type = $args[3];
			}
		}
		elseif ( ! empty($args[2]) && is_array($args[2])) {
			$this->process_args($args, 2, true);

			// but keep the default value for the third proper argument
			$result_type = PDO::FETCH_ASSOC;
			if ( ! empty($args[3])) {
				$result_type = $args[3];
			}
		}
		else {
			$this->process_args($args, 3, true);
		}

		if ($query === $this->query) {
			$this->query( );
		}

		if ( ! $this->sth) {
			throw new MySQLException(__METHOD__.': Query Result Handler Missing');
		}

		$this->sth->setFetchMode($result_type);

		$results = [];
		foreach ($this->sth as $row) {
			if ( ! is_null($key) && array_key_exists($key, $row)) {
				$results[$row[$key]] = $row;
			}
			else {
				$results[] = $row;
			}
		}

		return $results;
	}


	/**
	 * Execute a database query and return result as
	 * a single result value.
	 * This method only returns the single value at index 0.
	 * Each subsequent call to this method returns the next value.
	 *
	 * @param string $query optional
	 *
	 * @return mixed
	 * @throws MySQLException
	 */
	public function fetch_value($query = null) {
		$this->process_args(func_get_args( ));

		if ($query === $this->query) {
			$this->query( );
		}

		if ( ! $this->sth) {
			throw new MySQLException(__METHOD__.': Query Result Handler Missing');
		}

		return $this->sth->fetchColumn(0);
	}


	/**
	 * Execute a database query and return result as
	 * an indexed array of single result values.
	 * This method returns the entire result set in a single call.
	 *
	 * @param string $query optional
	 *
	 * @return array
	 * @throws MySQLException
	 */
	public function fetch_value_array($query = null) {
		$this->process_args(func_get_args( ));

		if ($query === $this->query) {
			$this->query( );
		}

		if ( ! $this->sth) {
			throw new MySQLException(__METHOD__.': Query Result Handler Missing');
		}

		return $this->sth->fetchAll(PDO::FETCH_COLUMN, 0);
	}


	/**
	 * Paginates a query result set based on supplied information
	 * NOTE: It is not necessary to include the SQL_CALC_FOUND_ROWS
	 * nor the LIMIT clause in the query, in fact, including the
	 * LIMIT clause in the query will probably break MySQL.
	 *
	 * @param int $page optional
	 * @param int $num_per_page optional
	 * @param string $query optional
	 *
	 * @return array
	 * @throws MySQLException
	 */
	public function paginate($page = null, $num_per_page = null, $query = null) {
		$this->process_args(func_get_args( ), 3);

		if ( ! is_null($page)) {
			$this->_page = $page;
		}
		else { // we don't have a page, either increment, or set equal to 1
			$this->_page = ( ! empty($this->_page)) ? ($this->_page + 1) : 1;
		}

		if ( ! is_null($num_per_page)) {
			$this->_num_per_page = $num_per_page;
		}
		else {
			$this->_num_per_page = ( ! empty($this->_num_per_page)) ? $this->_num_per_page : 50;
		}

		if ( ! $this->_page || ! $this->_num_per_page) {
			throw new MySQLException(__METHOD__.': No pagination data given');
		}

		if ($query === $this->query) {
			$this->_page_query = $this->query;
			$this->_page_params = $this->params;

			// add the SQL_CALC_FOUND_ROWS keyword to the query
			if (false === strpos($query, 'SQL_CALC_FOUND_ROWS')) {
				$this->query = preg_replace('/SELECT\\s+(?!SQL_)/i', 'SELECT SQL_CALC_FOUND_ROWS ', $this->_page_query);
			}

			$start = ($this->_num_per_page * ($this->_page - 1));

			// add the LIMIT clause to the query
			$this->query .= "
				LIMIT {$start}, {$this->_num_per_page}
			";

			$this->_page_result = $this->fetch_array( );

			if ( ! $this->_page_result) {
				// no data found
				return [];
			}

			$query = "
				SELECT FOUND_ROWS( ) AS count
			";
			$this->_num_results = $this->fetch_value($query);

			$this->_num_pages = (int) ceil($this->_num_results / $this->_num_per_page);
		}
		else { // we are using the previous data
			if ($this->_num_results < ($this->_num_per_page * ($this->_page - 1))) {
				return [];
			}

			$this->query = $this->_page_query;
			$this->params = $this->_page_params;

			$start = $this->_num_per_page * ($this->_page - 1);

			// add the LIMIT clause to the query
			$this->query .= "
				LIMIT {$start}, {$this->_num_per_page}
			";

			$this->_page_result = $this->fetch_array( );

			if ( ! $this->_page_result) {
				// no data found
				return [];
			}
		}

		// clean up the data and output to user
		$output = [];
		$output['num_rows'] = $this->_num_results;
		$output['num_per_page'] = $this->_num_per_page;
		$output['num_pages'] = $this->_num_pages;
		$output['cur_page'] = $this->_page;
		$output['data'] = $this->_page_result;

		return $output;
	}


	/**
	 * Process the incoming arguments into the
	 * various class properties
	 *
	 * @param array $args passed to the called function
	 * @param int $count of named arguments
	 * @param bool $query_first optional flag indicating the query is the first argument
	 *
	 * @return void
	 */
	protected function process_args($args, $count = 1, $query_first = false) {
		if (empty($args) || empty($args[0]) || (true === $args[0])) {
			return;
		}

		if ($query_first) {
			$query = array_shift($args);
			--$count;
		}

		while ($count) {
			if ( ! $query_first && (1 === $count)) {
				$query = array_shift($args);
				--$count;
				continue;
			}

			array_shift($args);

			--$count;
		}

		if ( ! empty($query)) {
			if ($query !== $this->query) {
				$this->prepared = false;

				if ($this->sth) {
					$this->sth->closeCursor( );
				}

				$this->sth = null;
			}

			$this->query = $query;
			$this->params = [];
		}

		if ( ! empty($args)) {
			$this->params = $args[0];
		}
	}


	/**
	 * Get the insert ID for the last insert
	 *
	 * @param void
	 *
	 * @return string numeric
	 */
	public function fetch_insert_id( ) {
		return $this->conn->lastInsertId( );
	}


	/**
	 * Log a message to the Mysql log file
	 *
	 * @param string $msg
	 *
	 * @return void
	 */
	protected function _log(string $msg) {
		if (false && $this->log_settings['errors'] && class_exists('Log')) {
			Log::write($msg, __CLASS__);
		}
	}

	protected function _error_report( ) {
		// what is this?
	}

	/**
	 * Grab a backtrace so the origin of any errors
	 * can be properly logged
	 *
	 * @param void
	 *
	 * @return bool
	 */
	protected function _get_backtrace( ) {
		// grab the debug_backtrace
		$debug = debug_backtrace(false);

		// parse through it, and find the first instance that isn't from this file
		$file = false;
		foreach ($debug as $file) {
			if (__FILE__ == $file['file']) {
				continue;
			}
			else {
				// $file will be the file that called the mysql function
				break;
			}
		}

		return $file;
	}


	/**
	 * Test the connection
	 *
	 * @return bool
	 * @throws Exception
	 */
	public static function test( ) {
		try {
			self::get_instance( );
			return true;
		}
		catch (MySQLException $e) {
			return false;
		}
	}


	/**
	 * Resets all query data
	 *
	 * @param void
	 *
	 * @action void
	 */
	public function reset( ) {
		if ($this->sth) {
			$this->sth->closeCursor( );
		}

		$this->sth = null;
		$this->params = [];
		$this->query = false;
		$this->prepared = false;
	}

}


/**
 * Class MySQLException
 *
 * @package Iohelix
 */
class MySQLException extends Exception {

	/**
	 *		PROPERTIES
	 * * * * * * * * * * * * * * * * * * * * * * * * * * */

	/**
	 * @var bool
	 */
	protected $backtrace = true;


	/**
	 *		METHODS
	 * * * * * * * * * * * * * * * * * * * * * * * * * * */

	/**
	 * Class constructor
	 * Sets all outside data
	 *
	 * @param string $message error message
	 * @param int    $code    optional error code
	 *
	 * @action instantiates object
	 * @action writes the exception to the log
	 */
	public function __construct(string $message, $code = 1) {
		parent::__construct($message, $code);

		// our own exception handling stuff
		if ( ! empty($GLOBALS['_LOGGING'])) {
			$this->logError( );
		}
	}


	/**
	 * Cleans the message for use
	 *
	 * @param void
	 *
	 * @return string cleaned message
	 */
	public function outputMessage( ) {
		// strip off the __METHOD__ bit of the error, if possible
		$message = $this->message;
		$message = preg_replace('/(?:\\w+::)+\\w+:\\s+/', '', $message);

		return $message;
	}


	/**
	 * Writes the exception to the log file
	 *
	 * @param void
	 *
	 * @action writes the exception to the log
	 *
	 * @return void
	 */
	protected function logError( ) {
		// first, let's make sure we can actually open and write to directory
		// specified by the global variable... and let's also do daily logs for now
		$log_name = 'mysql_exception_'.date('Ymd', time( )).'.log'; // don't use ldate() here

		// okay, write our log message
		$str = date('Y/m/d H:i:s')." == ({$this->code}) {$this->message} : {$this->file} @ {$this->line}\n"; // don't use ldate() here

		if ($this->backtrace) {
			$str .= "---------- [ BACKTRACE ] ----------\n";
			$str .= $this->getTraceAsString( )."\n";
			$str .= "-------- [ END BACKTRACE ] --------\n\n";
		}

		if ($fp = @fopen(LOG_DIR.$log_name, 'a')) {
			fwrite($fp, $str);
			fclose($fp);
		}

		call($str, $bypass = false, $show_from = true, $new_window = false, $error = true);
	}

}
