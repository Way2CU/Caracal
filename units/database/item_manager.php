<?php

/**
 * Database Item Base Class
 *
 * Author: Mladen Mijatov
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
	protected $string_fields = array(
							'CHAR', 'TEXT', 'VARCHAR', 'DATE', 'TIMESTAMP',
							'ML_VARCHAR', 'ML_TEXT', 'ML_CHAR'
						);

	/**
	 * List of multi-language fields
	 * @var array
	 */
	protected $ml_fields = array('ML_VARCHAR', 'ML_TEXT', 'ML_CHAR');

	/**
	 * List of item fields
	 * @var array
	 */
	protected $fields = array();

	/**
	 * List of field types
	 * @var array
	 */
	protected $field_types = array();

	/**
	 * Table name
	 * @var string
	 */
	protected $table_name;

	/**
	 * Store languages in local variable
	 * @var array
	 */
	protected $languages = array();

	/**
	 * Constructor
	 *
	 * @global resource $db
	 * @param string $table_name
	 * @return db_item
	 */
	protected function __construct($table_name) {
		$this->table_name = $table_name;
		$this->languages = MainLanguageHandler::getInstance()->getLanguages(false);

		sort($this->languages, SORT_STRING);
	}

	/**
	 * Adds new field definition to the object
	 *
	 * @param string $field_name
	 * @param string $field_type
	 */
	public function addProperty($field_name, $field_type) {
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
	 * @param array $data
	 */
	public function insertData($data) {
		global $db;

		$sql = $this->_getQuery(DB_INSERT, $data);

		if (!empty($sql))
			$db->query($sql);
	}

	/**
	 * Updates data with given conditions
	 *
	 * @global resource $db
	 * @param array $data
	 * @param array $conditionals
	 */
	public function updateData($data, $conditionals) {
		global $db;

		$sql = $this->_getQuery(DB_UPDATE, $data, $conditionals);

		if (!empty($sql))
			$db->query($sql);
	}

	/**
	 * Removes items in table with given conditions
	 *
	 * @global resource $db
	 * @param array $conditionals
	 */
	public function deleteData($conditionals, $limit=null) {
		global $db;

		$sql = $this->_getQuery(DB_DELETE, array(), $conditionals, null, null, $limit);

		if (!empty($sql))
			$db->query($sql);
	}

	/**
	 * Return array containing single item data
	 *
	 * @param array $fields
	 * @param array $conditionals
	 * @param array $order_by
	 * @param boolean $ascending
	 * @return array
	 */
	public function getSingleItem($fields, $conditionals, $order_by=array(), $ascending=True) {
		$items = $this->getItems($fields, $conditionals, $order_by, $ascending, 1);
		$result = count($items) > 0 ? $items[0] : null;

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
	public function getItems($fields, $conditionals, $order_by=array(), $ascending=true, $limit=null) {
		global $db;

		$result = array();

		$sql = $this->_getQuery(DB_SELECT, $fields, $conditionals, $order_by, $ascending, $limit);

		if (!empty($sql))
			$result = $db->get_results($sql);

		// pack multi-language fields
		$ml_fields = $this->_getMultilanguageFields($fields);
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
	public function getItemValue($item, $conditionals=array()) {
		global $db, $language;

		if (array_key_exists($item, $this->field_types) && in_array($this->field_types[$item], $this->ml_fields))
			$item = "{$item}_{$language}";

		$sql = "SELECT {$item} FROM {$this->table_name}";
		if (!empty($conditionals))
			$sql .= " WHERE ".$this->_getDelimitedData($conditionals, ' AND ');

		$result = $db->get_var($sql);

		return $result;
	}

	/**
	 * Returns single value of SQL query
	 *
	 * @param string $sql
	 * @return value
	 */
	public function sqlResult($sql) {
		global $db;
		return $db->get_var($sql);
	}

	/**
	 * Return list of all defined fields
	 *
	 * @return array
	 */
	public function getFieldNames() {
		return $this->fields;
	}

	public function getInsertedID() {
		global $db;
		return $db->get_inserted_id();
	}

	/**
	 * Forms database query for specified command
	 *
	 * @param integer $command
	 * @param array $data
	 * @param array $conditionals
	 * @return string
	 */
	private function _getQuery($command, $data=array(), $conditionals=array(), $order_by=null, $order_asc=null, $limit = null) {
		$result = '';

		switch ($command) {
			case DB_INSERT:
				$this->_expandMultilanguageFields($data);
				$result = 'INSERT INTO `'.$this->table_name.'` ('.$this->_getFields($data, true).') VALUES ('.$this->_getData($data).')';
				break;

			case DB_UPDATE:
				$this->_expandMultilanguageFields($data);
				$result = 'UPDATE `'.$this->table_name.'` SET '.$this->_getDelimitedData($data)

				if (count($conditionals) > 0)
					$result .= ' WHERE '.$this->_getDelimitedData($conditionals, ' AND ');

				if (!is_null($limit))
					$result .= ' LIMIT '.(is_numeric($limit) ? $limit : $limit[1].' OFFSET '.$limit[0]);
				break;

			case DB_DELETE:
				$this->_expandMultilanguageFields($conditionals);
				$result = 'DELETE FROM `'.$this->table_name.'` WHERE '.$this->_getDelimitedData($conditionals, ' AND ');

				if (!is_null($limit))
					$result .= ' LIMIT '.(is_numeric($limit) ? $limit : $limit[1].' OFFSET '.$limit[0]);
				break;

			case DB_SELECT:
				$this->_expandMultilanguageFields($data, false);
				$this->_expandMultilanguageFields($order_by, false, true);
				$result = 'SELECT '.$this->_getFields($data).' FROM `'.$this->table_name.'`';

				if (!empty($conditionals))
					$result .= ' WHERE '.$this->_getDelimitedData($conditionals, ' AND ');

				if (!is_null($order_by) && !empty($order_by))
					$result .= ' ORDER BY '.$this->_getFields($order_by).($order_asc ? ' ASC' : ' DESC');

				if (!is_null($limit))
					$result .= ' LIMIT '.(is_numeric($limit) ? $limit : $limit[1].' OFFSET '.$limit[0]);
				break;
		}

		if (defined('SQL_DEBUG')) trigger_error($result, E_USER_NOTICE);
		return $result;
	}

	/**
	 * Expand multi-language fields to match real ones in table
	 *
	 * @param pointer $fields
	 * @param boolean $has_keys
	 * @param boolean $only_current
	 * @return array
	 */
	private function _expandMultilanguageFields(&$fields, $has_keys=true, $only_current=false) {
		$temp = $fields;
		$current_language = $_SESSION['language'];

		if ($has_keys) {
			foreach ($temp as $field => $data)
				if (in_array($field, $this->fields) && in_array($this->field_types[$field], $this->ml_fields)) {

					if (!$only_current) {
						// expand multi-language field to all languages
						foreach($this->languages as $language)
							$fields["{$field}_{$language}"] = $data[$language];

					} else {
						$fields["{$field}_{$current_language}"] = $data[$current_language];
					}

					unset($fields[$field]);
				}

		} else {
			foreach ($temp as $field)
				if (in_array($field, $this->fields) && in_array($this->field_types[$field], $this->ml_fields)) {

					if (!$only_current) {
						// expand multi-language field to all languages
						foreach($this->languages as $language)
							$fields[] = "{$field}_{$language}";

					} else {
						// expand multi-language field only to current language
						$fields[] = "{$field}_{$current_language}";
					}

					unset($fields[array_search($field, $fields)]);
				}
		}
	}

	/**
	 * Checks if data contains multi-language fields
	 * @param array $fields
	 * @return boolean
	 */
	private function _getMultilanguageFields($fields) {
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
	private function _getFields($data, $from_keys=false) {
		$result = array();
		$fields = $from_keys ? array_keys($data) : $data;

		foreach($fields as $field)
			if (array_key_exists($field, $this->field_types))
				$result[] = "`{$field}`"; else
				$result[] = "{$field}";

		return implode(', ', $result);
	}

	/**
	 * Creates string with comma delimited values
	 *
	 * @param array $data
	 * @return string
	 */
	private function _getData($data) {
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
	private function _getDelimitedData($data, $delimiter = ', ') {
		$result = '';
		$tmp = array();

		foreach($data as $field_name => $field_value) {
			$is_string = in_array($this->field_types[$field_name], $this->string_fields);
			$field = array_key_exists($field_name, $this->field_types) ? "`{$field_name}`" : $field_name;

			if (is_array($field_value)) {
				if (array_key_exists('operator', $field_value)) {
					// value is a conditioned
					if (!is_array($field_value['value'])) {
						$tmp[] = "{$field} {$field_value['operator']} ".($is_string ? "'{$field_value['value']}'" : $field_value['value']);
					} else {
						$tmp[] = "{$field} {$field_value['operator']} (".($is_string ? "'".implode("', '", $field_value['value'])."'" : implode(', ', $field_value['value'])).")";
					}
				} else {
					// condition is a list, treat it that way
					$tmp[] = "{$field} IN (".($is_string ? "'".implode("', '", $field_value)."'" : implode(', ', $field_value)).")";
				}
			} else {
				$tmp[] = "{$field} = ".($is_string ? "'{$field_value}'" : $field_value);
			}
		}

		$result = implode($delimiter, $tmp);

		unset($tmp);
		return $result;
	}

	/**
	 * Check if table manager is associated with exists.
	 *
	 * @return boolean
	 */
	public function tableExists() {
		global $db, $db_use;
		$result = false;

		if ($db_use) {
			$db->query("SHOW TABLES LIKE '{$this->table_name}'");
			$result = $db->num_rows() > 0;
		}

		return $result;
	}
}
?>
