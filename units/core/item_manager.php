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
	var $string_fields = array('CHAR', 'TEXT', 'VARCHAR', 'DATE', 'TIMESTAMP', 'ML_VARCHAR', 'ML_TEXT', 'ML_CHAR');

	/**
	 * List of multi-language fields
	 * @var array
	 */
	var $ml_fields = array('ML_VARCHAR', 'ML_TEXT', 'ML_CHAR');

	/**
	 * List of item fields
	 * @var array
	 */
	var $fields = array();

	/**
	 * List of field types
	 * @var array
	 */
	var $field_types = array();

	/**
	 * Table name
	 * @var string
	 */
	var $table_name;

	/**
	 * Store languages in local variable
	 * @var array
	 */
	var $languages = array();

	/**
	 * Constructor
	 *
	 * @global resource $db
	 * @param string $table_name
	 * @return db_item
	 */
	function __construct($table_name) {
		global $LanguageHandler;

		$this->table_name = $table_name;
		$this->languages = $LanguageHandler->getLanguages(false);

		sort($this->languages, SORT_STRING);
	}

	/**
	 * Adds new field definition to the object
	 *
	 * @param string $field_name
	 * @param string $field_type
	 */
	function addProperty($field_name, $field_type) {
		$field_type = strtoupper($field_type);
		$this->fields[] = $field_name;
		$this->field_types[$field_name] = $field_type;

		if (in_array($field_type, $this->ml_fields))
			foreach($this->languages as $lang)
				$this->field_types["{$field_name}_{$lang}"] = strtoupper($field_type);
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

		// pack multi-language fields
		$ml_fields = $this->getMultilanguageFields($fields);
		if (count($ml_fields) > 0)
			foreach($ml_fields as $field) {
				$data = array();

				foreach($this->languages as $language) {
					$data[$language] = $result->{$field.'_'.$language};
					unset($result->{$field.'_'.$language});
				}

				$result->$field = $data;
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
				$query .= ' ORDER BY '.$this->getFields($order_by).$order;
			}

			if (!empty($query))
				$result = $db->get_results($query);
		}

		// pack multi-language fields
		$ml_fields = $this->getMultilanguageFields($fields);
		if (count($ml_fields) > 0 && count($result) > 0)
			foreach($result as $item)
				foreach($ml_fields as $field) {
					$data = array();

					foreach($this->languages as $language) {
						$data[$language] = $item->{$field.'_'.$language};
						unset($item->{$field.'_'.$language});
					}

					$item->$field = $data;
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
	 * Returns single value of SQL query
	 * @param string $sql
	 * @return value
	 */
	function sqlResult($sql) {
		global $db, $db_active;

		$result = null;
		if ($db_active == 1) {
			$result = $db->get_var($sql);
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
				$this->expandMultilanguageFields($data);
				$result = 'INSERT INTO `'.$this->table_name.'` ('.$this->getFields($data, true).') VALUES ('.$this->getData($data).')';
				break;

			case DB_UPDATE:
				$this->expandMultilanguageFields($data);
				$result = 'UPDATE `'.$this->table_name.'` SET '.$this->getDelimitedData($data).' WHERE '.$this->getDelimitedData($conditionals, ' AND ');
				break;

			case DB_DELETE:
				$this->expandMultilanguageFields($conditionals);
				$result = 'DELETE FROM `'.$this->table_name.'` WHERE '.$this->getDelimitedData($conditionals, ' AND ');
				break;

			case DB_SELECT:
				$this->expandMultilanguageFields($data, false);
				$result = 'SELECT '.$this->getFields($data).' FROM `'.$this->table_name.'`';
				if (!empty($conditionals)) $result .= ' WHERE '.$this->getDelimitedData($conditionals, ' AND ');
				break;
		}

		return $result;
	}

	/**
	 * Expand multi-language fields to match real ones in table
	 * @param pointer $fields
	 * @param boolean $has_keys
	 * @return array
	 */
	function expandMultilanguageFields(&$fields, $has_keys=true) {
		$temp = $fields;

		if ($has_keys) {
			foreach ($temp as $field => $data)
				if (in_array($this->field_types[$field], $this->ml_fields)) {
					foreach($this->languages as $language)
						$fields["{$field}_{$language}"] = $data[$language];

					unset($fields[$field]);
				}

		} else {
			foreach ($temp as $field)
				if (in_array($this->field_types[$field], $this->ml_fields)) {
					foreach($this->languages as $language)
						$fields[] = "{$field}_{$language}";

					unset($fields[array_search($field, $fields)]);
				}
		}
	}

	/**
	 * Checks if data contains multi-language fields
	 * @param array $fields
	 * @return boolean
	 */
	function getMultilanguageFields($fields) {
		$result = array();

		foreach($fields as $field)
			if (in_array($this->field_types[$field], $this->ml_fields))
				$result[] = $field;

		return $result;
	}

	/**
	 * Get comma separated fields
	 *
	 * @param array $data
	 * @return string
	 */
	function getFields($data, $from_keys=false) {
		$result = array();
		$fields = $from_keys ? array_keys($data) : $data;

		foreach($fields as $field)
			$result[] = "`{$field}`";

		return implode(', ', $result);
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
			$is_string = in_array($this->field_types[$field_name], $this->string_fields);
			$tmp[] = ($is_string) ? "'{$field_value}'" : $field_value;
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
			$is_string = in_array($this->field_types[$field_name], $this->string_fields);

			if (is_array($field_value)) {
				if (array_key_exists('operator', $field_value)) {
					// value is a conditioned
					$tmp[] = "`{$field_name}` {$field_value['operator']} ".($is_string ? "'{$field_value['value']}'" : $field_value['value']);
				} else {
					// condition is a list, treat it that way
					$tmp[] = "`{$field_name}` IN (".($is_string ? "'".implode("', '", $field_value)."'" : implode(', ', $field_value)).")";
				}
			} else {
				$tmp[] = "`{$field_name}` = ".($is_string ? "'{$field_value}'" : $field_value);
			}
		}

		$result = implode($delimiter, $tmp);

		unset($tmp);
		return $result;
	}

	/**
	 * Return list of all defined fields
	 *
	 * @return array
	 */
	function getFieldNames() {
		return $this->fields;
	}
}
?>
