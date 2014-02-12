<?php

/**
 * Database implemenation base class.
 * Copyright (c) 2012. by Way2CU
 *
 * @author Mladen Mijatov
 */

class DatabaseType {
	const MYSQL = 0;
	const PGSQL = 1;
	const SQLITE = 2;
}


abstract class Database {
	protected $handle = null;
	protected $active = false;
	protected $num_rows = 0;

	/**
	 * Connect to database server.
	 *
	 * @param string $user
	 * @param string $password
	 * @param string $host
	 * @return boolean
	 */
	public abstract function connect($config);

	/**
	 * Select default database.
	 *
	 * @param string $database
	 * @return boolean
	 */
	public abstract function select($database);

	/**
	 * Check if database with specified name exists.
	 *
	 * @param string $database
	 * @return boolean
	 */
	public abstract function exists($database);

	/**
	 * Create database.
	 *
	 * @param string $database
	 * @return boolean
	 */
	public abstract function create($database);

	/**
	 * Drop database.
	 *
	 * @param string $database
	 * @return boolean
	 */
	public abstract function drop($database);

	/**
	 * Execute single query returning boolean value.
	 *
	 * @param string $sql
	 * @return boolean
	 */
	public abstract function query($sql);

	/**
	 * Executes mutliple queries separated by semicolon.
	 *
	 * @param string $sql
	 * @return boolean
	 */
	public abstract function multi_query($sql);

	/**
	 * Get list of results.
	 *
	 * @param string $sql
	 * @return array
	 */
	public abstract function get_results($sql);

	/**
	 * Get a single row from result list.
	 *
	 * @param string $sql
	 * @param integer $index
	 * @return object
	 */
	public abstract function get_row($sql, $index=0);

	/**
	 * Get value of single varible.
	 *
	 * @param string $sql
	 * @return variable
	 */
	public abstract function get_var($sql);

	/**
	 * Get ID of last inserted row.
	 *
	 * @return integer
	 */
	public abstract function get_inserted_id();

	/**
	 * Get number of affected rows in previous SQL.
	 *
	 * @return integer
	 */
	public abstract function num_rows();

	/**
	 * Get database status.
	 *
	 * @return boolean
	 */
	public function is_active() {
		return $this->active;
	}

	/**
	 * Convenience method to drop specified list of tables.
	 *
	 * @param array/string $tables
	 */
	public abstract function drop_tables($tables);

	/**
	 * Escape string using database engine.
	 *
	 * @param string $string
	 * @return string
	 */
	public abstract function escape_string($string);

	/**
	 * Get database specific time format from UNIX timestamp.
	 *
	 * @param integer $timestamp
	 * @return string
	 */
	public abstract function format_time($timestamp);

	/**
	 * Get database specific date format from UNIX timestamp.
	 *
	 * @param integer $timestamp
	 * @return string
	 */
	public abstract function format_date($timestamp);

	/**
	 * Get database specific timestamp format from UNIX timestamp.
	 *
	 * @param integer $timestamp
	 * @return string
	 */
	public abstract function format_timestamp($timestamp);
}

?>
