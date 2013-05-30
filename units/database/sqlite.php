<?php

/**
 * SQLite Support Implementation
 * Copyright (c) 2013. by Way2CU
 *
 * @author Mladen Mijatov
 */

require_once('base.php');


class Database_SQLite extends Database {

	/**
	 * Generate database file name.
	 *
	 * @return string
	 */
	private function getFileName($database) {
		global $data_path;
		return $data_path.$database.'.sqlite';
	}

	/**
	 * Connect to database server.
	 *
	 * @param string $user
	 * @param string $password
	 * @param string $host
	 * @return boolean
	 */
	public function connect($config) {
		$result = true;

		try {
			// try connecting to specified database
			$this->handle = new SQLite3($this->getFileName($config['name']), SQLITE3_OPEN_READWRITE);
			$this->active = true;

		} catch (Exception $error) {
			$result = false;
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
		return file_exists($this->getFileName($database));
	}

	/**
	 * Check if database with specified name exists.
	 *
	 * @param string $database
	 * @return boolean
	 */
	public function exists($database) {
		return file_exists($this->getFileName($database));
	}

	/**
	 * Create database.
	 *
	 * @param string $database
	 * @return boolean
	 */
	public function create($database) {
		$result = true;

		try {
			$this->handle = new SQLite3($this->getFileName($database), SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
			$this->active = true;

		} catch (Exception $error) {
			trigger_error($error);
			$result = false;
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
		$file_name = $this->getFileName($database);

		if (file_exists($file_name)) {
			unlink($file_name);
			$result = true;
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
		return $this->handle->exec($sql) === false;
	}

	/**
	 * Execute single query returning boolean value.
	 *
	 * @param string $sql
	 * @return boolean
	 */
	public function multi_query($sql) {
		return $this->query($sql);
	}

	/**
	 * Get list of results.
	 *
	 * @param string $sql
	 * @return array
	 */
	public function get_results($sql) {
		$result = array();

		if ($db_result = $this->handle->query($sql)) {
			while ($row = $db_result->fetchArray())
				$result[] = $row;

			$db_result->finalize();
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
		$i = 0;
		$result = null;

		if ($db_result = $this->handle->query($sql)) {
			while ($row = $db_result->fetchArray()) {
				if ($index == $i++) {
					$result = $row;
					break;
				}
			}

			$db_result->finalize();
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

		if ($db_result = $this->handle->query($sql)) {
			$row = $db_result->fetchArray(MYSQLI_NUM);

			if (!is_null($row) && count($row) > 0)
				$result = $row[0];

			$db_result->finalize();
		}

		return $result;
	}

	/**
	 * Get ID of last inserted row.
	 *
	 * @return integer
	 */
	public function get_inserted_id() {
		return $this->handle->lastInsertRowID();
	}

	/**
	 * Get number of affected rows in previous SQL.
	 *
	 * @return integer
	 */
	public function num_rows() {
		return $this->handle->changes();
	}

	/**
	 * Convenience method to drop specified list of tables.
	 *
	 * @param array/string $tables
	 */
	public function drop_tables($tables) {
		$sql = 'DROP TABLES `'.implode('`, `', $tables).'`';
		return $this->handle->exec($sql) === true;
	}

	/**
	 * Escape string using database engine.
	 *
	 * @param string $string
	 * @return string
	 */
	public function escape_string($string) {
		return $this->handle->escapeString($string);
	}
}

?>
