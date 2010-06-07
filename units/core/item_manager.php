<?php

/**
 * Database Item Base Class
 * @author MeanEYE[rcf]
 */

define('DB_INSERT', 0);
define('DB_UPDATE', 1);
define('DB_DELETE', 2);
define('DB_SELECT', 3);

class ItemManager {
	/**
	 * List of string based field types
	 * @var array
	 */
	var $string_fields = array('TEXT', 'VARCHAR', 'DATE', 'TIMESTAMP');

	/**
	 * List of item fields
	 * @var array
	 */
	var $fields = array();

	/**
	 * Table name
	 * @var string
	 */
	var $table_name;

	/**
	 * Constructor
	 *
	 * @global resource $db
	 * @param string $table_name
	 * @return db_item
	 */
	function ItemManager($table_name) {
		$this->table_name = $table_name;
	}

	/**
	 * Adds new field definition to the object
	 *
	 * @param string $field_name
	 * @param string $field_type
	 */
	function addProperty($field_name, $field_type) {
		$this->fields[$field_name] = strtoupper($field_type);
	}

	/**
	 * Inserts data into specified table
	 *
	 * @global resource $db
	 * @global boolean $db_active
	 * @param array $data
	 */
	function insertData($data) {
		global $db, $db_active;

		if ($db_active == 1) {
			$query = $this->getQuery(DB_INSERT, $data);

			if (!empty($query))
				$db->query($query);
		}
	}

	/**
	 * Updates data with given conditions
	 *
	 * @global resource $db
	 * @global boolean $db_active
	 * @param array $data
	 * @param array $conditionals
	 */
	function updateData($data, $conditionals) {
		global $db, $db_active;

		if ($db_active == 1) {
			$query = $this->getQuery(DB_UPDATE, $data, $conditionals);

			if (!empty($query))
				$db->query($query);
		}
	}

	/**
	 * Removes items in table with given conditions
	 *
	 * @global resource $db
	 * @global boolean $db_active
	 * @param array $conditionals
	 */
	function deleteData($conditionals) {
		global $db, $db_active;

		if ($db_active == 1) {
			$query = $this->getQuery(DB_DELETE, array(), $conditionals);

			if (!empty($query))
				$db->query($query);
		}
	}

	/**
	 * Return array containing single item data
	 *
	 * @param array $fields
	 * @param array $conditionals
	 * @return array
	 */
	function getSingleItem($fields, $conditionals) {
		global $db, $db_active;

		$result = array();

		if ($db_active == 1) {
			$query = $this->getQuery(DB_SELECT, $fields, $conditionals);

			if (!empty($query))
				$result = $db->get_row($query);
		}

		return $result;
	}

	/**
	 * Get all data for given conditionals
	 *
	 * @param array $fields
	 * @param array $conditionals
	 * @param array $order_by
	 * @param boolean $ascending
	 * @return array
	 */
	function getItems($fields, $conditionals, $order_by=array(), $ascending=True) {
		global $db, $db_active;

		$result = array();

		if ($db_active == 1) {
			$query = $this->getQuery(DB_SELECT, $fields, $conditionals);

			if (!empty($order_by)) {
				$order = $ascending ? ' ASC' : ' DESC';
				$query .= ' ORDER BY '.$this->getDelimitedFields($order_by).$order;
			}

			if (!empty($query))
				$result = $db->get_results($query);
		}

		return $result;
	}
	
	/**
	 * Return value of single column for single item.
	 * @param string $item
	 * @param array $conditionals
	 * @return variable
	 */
	function getItemValue($item, $conditionals=array()) {
		global $db, $db_active;

		$result = null;
		if ($db_active == 1) {
			$query = "SELECT {$item} FROM {$this->table_name}";
			if (!empty($conditionals))
				$query .= " WHERE ".$this->getDelimitedData($conditionals, ' AND ');
			
			$result = $db->get_var($query);
		}
		
		return $result;
	}

	/**
	 * Forms database query for specified command
	 *
	 * @param integer $command
	 * @param array $data
	 * @param array $conditionals
	 * @return string
	 */
	function getQuery($command, $data = array(), $conditionals = array()) {
		$result = '';

		switch ($command) {
			case DB_INSERT:
				$result = 'INSERT INTO `'.$this->table_name.'` ('.$this->getFields($data).') VALUES ('.$this->getData($data).')';
				break;

			case DB_UPDATE:
				$result = 'UPDATE `'.$this->table_name.'` SET '.$this->getDelimitedData($data).' WHERE '.$this->getDelimitedData($conditionals, ' AND ');
				break;

			case DB_DELETE:
				$result = 'DELETE FROM `'.$this->table_name.'` WHERE '.$this->getDelimitedData($conditionals, ' AND ');
				break;

			case DB_SELECT:
				$result = 'SELECT '.$this->getDelimitedFields($data).' FROM `'.$this->table_name.'`';
				if (!empty($conditionals))
					$result .= ' WHERE '.$this->getDelimitedData($conditionals, ' AND ');
				break;
		}

		return $result;
	}

	/**
	 * Get comma separated fields
	 *
	 * @param array $data
	 * @return string
	 */
	function getFields($data) {
		$result = '`'.implode('`, `', array_keys($data)).'`';

		return $result;
	}

	/**
	 * Get comma separated fields from array
	 *
	 * @param array $data
	 * @return string
	 */
	function getDelimitedFields($data) {
		return '`'.implode('`, `', $data).'`';
	}

	/**
	 * Creates string with comma delimited values
	 *
	 * @param array $data
	 * @return string
	 */
	function getData($data) {
		$result = '';
		$tmp = array();

		foreach($data as $field_name => $field_value) {
			$is_string = in_array($this->fields[$field_name], $this->string_fields);
			$tmp[] = ($is_string) ? "'".$field_value."'" : $field_value;
		}

		$result = implode(', ', $tmp);

		unset($tmp);
		return $result;
	}

	/**
	 * Creates string with specified delimiter and field name included
	 *
	 * @param array $data
	 * @param string $delimiter
	 * @return string
	 */
	function getDelimitedData($data, $delimiter = ', ') {
		$result = '';
		$tmp = array();

		foreach($data as $field_name => $field_value) {
			$is_string = in_array($this->fields[$field_name], $this->string_fields);
			$tmp[] = ('`'.$field_name.'` = ').(($is_string) ? "'".$field_value."'" : $field_value);
		}

		$result = implode($delimiter, $tmp);

		unset($tmp);
		return $result;
	}
}
?>
