<?php

/**
 * MySQL Support Implementation
 * Copyright (c) 2012. by Way2CU
 *
 * @author Mladen Mijatov
 */

require_once('base.php');


class Database_MySQL extends Database {
	/**
	 * Connect to database server.
	 *
	 * @param string $user
	 * @param string $password
	 * @param string $host
	 * @return boolean
	 */
	public function connect($config) {
		$result = false;

		// report errors through exceptions, this is the default since PHP 8.1
		// but is set explicitly to get the same behavior on older versions
		mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

		try {
			$this->handle = new mysqli($config['host'], $config['user'], $config['pass']);

			// set default protocol encoding
			$this->handle->set_charset('utf8');

			$this->active = true;
			$result = true;

		} catch (mysqli_sql_exception $error) {
			trigger_error('MySQL: Unable to connect. '.$error->getMessage(), E_USER_WARNING);
		}

		return $result;
	}

	/**
	 * Select default database.
	 *
	 * @param string $database
	 * @return boolean
	 */
	public function select($database) {
		$result = false;

		if ($this->active)
			try {
				$result = $this->handle->select_db($database);

			} catch (mysqli_sql_exception $error) {
				trigger_error('MySQL: Unable to select database. '.$error->getMessage(), E_USER_WARNING);
			}

		return $result;
	}

	/**
	 * Check if database with specified name exists.
	 *
	 * @param string $database
	 * @return boolean
	 */
	public function exists($database) {
		$result = false;

		try {
			$sql = "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = `{$database}`";
			$response = $this->handle->query($sql);

			$result = $response->num_rows > 0;
			$response->free();

		} catch (mysqli_sql_exception $error) {
			trigger_error('MySQL: Unable to check database. '.$error->getMessage(), E_USER_WARNING);
		}

		return $result;
	}

	/**
	 * Create database.
	 *
	 * @param string $database
	 * @return boolean
	 */
	public function create($database) {
		$result = false;

		try {
			$sql = "CREATE DATABASE `{$database}`";
			$result = $this->handle->query($sql) === true;

		} catch (mysqli_sql_exception $error) {
			trigger_error('MySQL: Unable to create database. '.$error->getMessage(), E_USER_WARNING);
		}

		return $result;
	}

	/**
	 * Drop database.
	 *
	 * @param string $database
	 * @return boolean
	 */
	public function drop($database) {
		$result = false;

		try {
			$sql = "DROP DATABASE `{$database}`";
			$result = $this->handle->query($sql) === true;

		} catch (mysqli_sql_exception $error) {
			trigger_error('MySQL: Unable to drop database. '.$error->getMessage(), E_USER_WARNING);
		}

		return $result;
	}

	/**
	 * Execute query returning boolean value.
	 *
	 * @param string $sql
	 * @return boolean
	 */
	public function query($sql) {
		$result = false;

		try {
			$result = $this->handle->query($sql) === true;

		} catch (mysqli_sql_exception $error) {
			trigger_error('MySQL: Query failed. '.$error->getMessage(), E_USER_WARNING);
		}

		return $result;
	}

	/**
	 * Execute single query returning boolean value.
	 *
	 * @param string $sql
	 * @return boolean
	 */
	public function multi_query($sql) {
		$result = false;

		try {
			$this->handle->multi_query($sql);
			while ($this->handle->more_results() && $this->handle->next_result());

			$result = $this->handle->errno == 0;

		} catch (mysqli_sql_exception $error) {
			trigger_error('MySQL: Unable to execute queries. '.$error->getMessage(), E_USER_WARNING);
		}

		return $result;
	}

	/**
	 * Get list of results.
	 *
	 * @param string $sql
	 * @return array
	 */
	public function get_results($sql) {
		$result = array();

		try {
			if ($db_result = $this->handle->query($sql, MYSQLI_STORE_RESULT)) {
				while ($row = $db_result->fetch_object())
					$result[] = $row;

				$db_result->close();
			}

		} catch (mysqli_sql_exception $error) {
			trigger_error('MySQL: Unable to get results. '.$error->getMessage(), E_USER_WARNING);
		}

		return $result;
	}

	/**
	 * Get a single row from result list.
	 *
	 * @param string $sql
	 * @param integer $index
	 * @return array
	 */
	public function get_row($sql, $index=0) {
		$result = null;

		try {
			if ($db_result = $this->handle->query($sql, MYSQLI_STORE_RESULT)) {
				if ($db_result->data_seek($index))
					$result = $db_result->fetch_array();

				$db_result->close();
			}

		} catch (mysqli_sql_exception $error) {
			trigger_error('MySQL: Unable to get row. '.$error->getMessage(), E_USER_WARNING);
		}

		return $result;
	}

	/**
	 * Get value of single varible.
	 *
	 * @param string $sql
	 * @return variable
	 */
	public function get_var($sql) {
		$result = null;

		try {
			if ($db_result = $this->handle->query($sql, MYSQLI_STORE_RESULT)) {
				$row = $db_result->fetch_array(MYSQLI_NUM);

				if (!is_null($row) && count($row) > 0)
					$result = $row[0];

				$db_result->close();
			}

		} catch (mysqli_sql_exception $error) {
			trigger_error('MySQL: Unable to get value. '.$error->getMessage(), E_USER_WARNING);
		}

		return $result;
	}

	/**
	 * Get ID of last inserted row.
	 *
	 * @return integer
	 */
	public function get_inserted_id() {
		return $this->handle->insert_id;
	}

	/**
	 * Get number of affected rows in previous SQL.
	 *
	 * @return integer
	 */
	public function num_rows() {
		return $this->handle->affected_rows;
	}

	/**
	 * Convenience method to drop specified list of tables.
	 *
	 * @param array/string $tables
	 */
	public function drop_tables($tables) {
		$result = false;

		try {
			$sql = 'DROP TABLES `'.implode('`, `', $tables).'`';
			$result = $this->handle->query($sql) === true;

		} catch (mysqli_sql_exception $error) {
			trigger_error('MySQL: Unable to drop tables. '.$error->getMessage(), E_USER_WARNING);
		}

		return $result;
	}

	/**
	 * Escape string using database engine.
	 *
	 * @param string $string
	 * @return string
	 */
	public function escape_string($string) {
		return $this->handle->real_escape_string($string);
	}

	/**
	 * Get database specific time format from UNIX timestamp.
	 *
	 * @param integer $timestamp
	 * @return string
	 */
	public function format_time($timestamp) {
		return date('H:i:s', $timestamp);
	}

	/**
	 * Get database specific date format from UNIX timestamp.
	 *
	 * @param integer $timestamp
	 * @return string
	 */
	public function format_date($timestamp) {
		return date('Y-m-d', $timestamp);
	}

	/**
	 * Get database specific timestamp format from UNIX timestamp.
	 *
	 * @param integer $timestamp
	 * @return string
	 */
	public function format_timestamp($timestamp) {
		return date('Y-m-d H:i:s', $timestamp);
	}
}

?>
