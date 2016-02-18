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
		$this->handle = new mysqli($config['host'], $config['user'], $config['pass']);

		if (!mysqli_connect_error()) {
			$result = true;
			$this->active = true;

			// set default protocol encoding
			$this->handle->set_charset('utf8');
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
			$result = $this->handle->select_db($database);

		return $result;
	}

	/**
	 * Check if database with specified name exists.
	 *
	 * @param string $database
	 * @return boolean
	 */
	public function exists($database) {
		$sql = "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = `{$database}`";
		$response = $this->handle->query($sql);

		$result = $response->num_rows > 0;
		$response->free();

		return $result;
	}

	/**
	 * Create database.
	 *
	 * @param string $database
	 * @return boolean
	 */
	public function create($database) {
		$sql = "CREATE DATABASE `{$database}`";
		return $this->handle->query($sql) === true;
	}

	/**
	 * Drop database.
	 *
	 * @param string $database
	 * @return boolean
	 */
	public function drop($database) {
		$sql = "DROP DATABASE `{$database}`";
		$result = $this->handle->query($sql) === true;

		return $result;
	}

	/**
	 * Execute query returning boolean value.
	 *
	 * @param string $sql
	 * @return boolean
	 */
	public function query($sql) {
		return $this->handle->query($sql) === true;
	}

	/**
	 * Execute single query returning boolean value.
	 *
	 * @param string $sql
	 * @return boolean
	 */
	public function multi_query($sql) {
		$this->handle->multi_query($sql);
		while ($this->handle->more_results() && $this->handle->next_result());

		return $this->handle->errno == 0;
	}

	/**
	 * Get list of results.
	 *
	 * @param string $sql
	 * @return array
	 */
	public function get_results($sql) {
		$result = array();

		if ($db_result = $this->handle->query($sql, MYSQLI_STORE_RESULT)) {
			while ($row = $db_result->fetch_object())
				$result[] = $row;

			$db_result->close();
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

		if ($db_result = $this->handle->query($sql, MYSQLI_STORE_RESULT)) {
			if (!$db_result->data_seek($index))
				return;

			$result = $db_result->fetch_array();
			$db_result->close();
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

		if ($db_result = $this->handle->query($sql, MYSQLI_STORE_RESULT)) {
			$row = $db_result->fetch_array(MYSQLI_NUM);

			if (!is_null($row) && count($row) > 0)
				$result = $row[0];

			$db_result->close();
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
		$sql = 'DROP TABLES `'.implode('`, `', $tables).'`';
		return $this->handle->query($sql) === true;
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
