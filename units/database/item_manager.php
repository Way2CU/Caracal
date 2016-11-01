<?php

/**
 * Item manager base class.
 *
 * This class is used to make working with database easier. It is not meant to be
 * ORM or in place solution. Custom SQL queries are still possible and encouraged.
 *
 * Author: Mladen Mijatov
 */


class LoadQueryError extends Exception {};


final class Query {
	const INSERT = 0;
	const UPDATE = 1;
	const DELETE = 2;
	const SELECT = 3;
	const FIELD_PATTERN = '|`(?P<name>[\w\d_]+)`\s*(?P<type>[\w\d_]+)(?P<definition>.{0,1000}?(?=,\s*[\w`])),|isum';

	 // list of string based field types
	public static $string_fields = array(
							'CHAR', 'TEXT', 'VARCHAR', 'DATE', 'TIMESTAMP', 'TIME',
							'ML_VARCHAR', 'ML_TEXT', 'ML_CHAR'
						);

	 // list of multi-language fields
	public static $multilanguage_fields = array('ML_VARCHAR', 'ML_TEXT', 'ML_CHAR');
	private static $field_map = array(
			'ML_VARCHAR' => 'VARCHAR',
			'ML_TEXT'    => 'TEXT',
			'ML_CHAR'    => 'CHAR'
		);

	/**
	 * Load SQL file and prepare multi-language fields. If module is specified
	 * file will be loaded relative to module's path.
	 *
	 * @param string $file_name
	 * @param object $module
	 * @return string
	 */
	public static function load_file($file_name, $module=null) {
		global $system_queries_path;

		// get path to look for query
		if (!is_null($module))
			$path = $module->path.'queries/'; else
			$path = $system_queries_path;

		// throw error
		if (!file_exists($path.$file_name))
			throw new LoadQueryError("Unable to find specified query file '{$file_name}' in '{$path}'.");

		// load file and find all the fields
		$sql = file_get_contents($path.$file_name);
		preg_match_all(self::FIELD_PATTERN, $sql, $matches);

		// get languages from the system
		$languages = Language::get_languages(false);

		// prepare replace multi-language fields with localized ones
		foreach ($matches['type'] as $index => $field_type) {
			if (!in_array(strtoupper($field_type), self::$multilanguage_fields))
				continue;

			// prepare data to be used in search and replace
			$matched_name = $matches['name'][$index];
			$matched_definition = $matches['definition'][$index];
			$field_name = preg_quote($matched_name);
			$field_definition = preg_quote($matched_definition);
			$real_type = self::field_map[strtoupper($field_type)];

			// prepare search and replace patterns
			$search = "|`{$field_name}`\s*{$field_type}{$field_definition}\s*,|iu";
			$replace = '';
			foreach ($languages as $language)
				$replace .= "`{$matched_name}_{$language}` {$real_type}{$matched_definition},";

			// update query to include all languages
			$sql = preg_replace($search, $replace, $sql);
		}

		return $sql;
	}
}


abstract class ItemManager {

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
	 * Constructor.
	 *
	 * @param string $table_name
	 * @return db_item
	 */
	protected function __construct($table_name) {
		$this->table_name = $table_name;
		$this->languages = Language::get_languages(false);

		sort($this->languages, SORT_STRING);
	}

	/**
	 * Adds new field definition to the object.
	 *
	 * @param string $field_name
	 * @param string $field_type
	 */
	public function add_property($field_name, $field_type) {
		$field_type = strtoupper($field_type);
		$this->fields[] = $field_name;
		$this->field_types[$field_name] = $field_type;

		if (in_array($field_type, Query::$multilanguage_fields))
			foreach($this->languages as $lang)
				$this->field_types["{$field_name}_{$lang}"] = strtoupper($field_type);
	}

	/**
	 * Inserts data into specified table
	 *
	 * @global resource $db
	 * @param array $data
	 */
	public function insert_item($data) {
		global $db;

		$sql = $this->get_query(Query::INSERT, $data);

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
	public function update_items($data, $conditionals) {
		global $db;

		$sql = $this->get_query(Query::UPDATE, $data, $conditionals);

		if (!empty($sql))
			$db->query($sql);
	}

	/**
	 * Removes items in table with given conditions.
	 *
	 * @global resource $db
	 * @param array $conditionals
	 */
	public function delete_items($conditionals, $limit=null) {
		global $db;

		$sql = $this->get_query(Query::DELETE, array(), $conditionals, null, null, $limit);

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
	public function get_single_item($fields, $conditionals, $order_by=array(), $ascending=True) {
		$items = $this->get_items($fields, $conditionals, $order_by, $ascending, 1);
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
	public function get_items($fields, $conditionals, $order_by=array(), $ascending=true, $limit=null) {
		global $db;

		$result = array();

		$sql = $this->get_query(Query::SELECT, $fields, $conditionals, $order_by, $ascending, $limit);

		if (!empty($sql))
			$result = $db->get_results($sql);

		// pack multi-language fields
		$ml_fields = $this->get_multilanguage_fields($fields);
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
	public function get_item_value($item, $conditionals=array()) {
		global $db, $language;

		if (array_key_exists($item, $this->field_types) && in_array($this->field_types[$item], Query::$multilanguage_fields))
			$item = "{$item}_{$language}";

		$sql = "SELECT {$item} FROM {$this->table_name}";
		if (!empty($conditionals))
			$sql .= " WHERE ".$this->get_delimited_data($conditionals, ' AND ');

		$result = $db->get_var($sql);

		return $result;
	}

	/**
	 * Returns single value of SQL query
	 *
	 * @param string $sql
	 * @return value
	 */
	public function get_result($sql) {
		global $db;
		return $db->get_var($sql);
	}

	/**
	 * Return list of all defined fields
	 *
	 * @return array
	 */
	public function get_field_names() {
		return $this->fields;
	}

	/**
	 * Return id of the newly inserted row.
	 *
	 * @return integer
	 */
	public function get_inserted_id() {
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
	private function get_query($command, $data=array(), $conditionals=array(), $order_by=null, $order_asc=null, $limit = null) {
		$result = '';

		switch ($command) {
			case Query::INSERT:
				$this->expand_multilanguage_fields($data);
				$result = 'INSERT INTO `'.$this->table_name.'` ('.$this->get_fields($data, true).') VALUES ('.$this->get_data($data).')';
				break;

			case Query::UPDATE:
				$this->expand_multilanguage_fields($data);
				$result = 'UPDATE `'.$this->table_name.'` SET '.$this->get_delimited_data($data);

				if (count($conditionals) > 0)
					$result .= ' WHERE '.$this->get_delimited_data($conditionals, ' AND ');

				if (!is_null($limit))
					$result .= ' LIMIT '.(is_numeric($limit) ? $limit : $limit[1].' OFFSET '.$limit[0]);
				break;

			case Query::DELETE:
				$this->expand_multilanguage_fields($conditionals);
				$result = 'DELETE FROM `'.$this->table_name.'` WHERE '.$this->get_delimited_data($conditionals, ' AND ');

				if (!is_null($limit))
					$result .= ' LIMIT '.(is_numeric($limit) ? $limit : $limit[1].' OFFSET '.$limit[0]);
				break;

			case Query::SELECT:
				$this->expand_multilanguage_fields($data, false);
				$this->expand_multilanguage_fields($order_by, false, true);
				$result = 'SELECT '.$this->get_fields($data).' FROM `'.$this->table_name.'`';

				if (!empty($conditionals))
					$result .= ' WHERE '.$this->get_delimited_data($conditionals, ' AND ');

				if (!is_null($order_by) && !empty($order_by))
					$result .= ' ORDER BY '.$this->get_fields($order_by).($order_asc ? ' ASC' : ' DESC');

				if (!is_null($limit))
					$result .= ' LIMIT '.(is_numeric($limit) ? $limit : $limit[1].' OFFSET '.$limit[0]);
				break;
		}

		if (defined('SQL_DEBUG')) trigger_error($result, E_USER_NOTICE);
		return $result;
	}

	/**
	 * Expand multi-language fields to match real ones in table.
	 *
	 * @param pointer $fields
	 * @param boolean $has_keys
	 * @param boolean $only_current
	 * @return array
	 */
	private function expand_multilanguage_fields(&$fields, $has_keys=true, $only_current=false) {
		$temp = $fields;
		$current_language = $_SESSION['language'];

		if ($has_keys) {
			foreach ($temp as $field => $data)
				if (in_array($field, $this->fields) && in_array($this->field_types[$field], Query::$multilanguage_fields)) {

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
				if (in_array($field, $this->fields) && in_array($this->field_types[$field], Query::$multilanguage_fields)) {

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
	private function get_multilanguage_fields($fields) {
		$result = array();

		foreach($fields as $field)
			if (in_array($this->field_types[$field], Query::$multilanguage_fields))
				$result[] = $field;

		return $result;
	}

	/**
	 * Get comma separated fields
	 *
	 * @param array $data
	 * @return string
	 */
	private function get_fields($data, $from_keys=false) {
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
	private function get_data($data) {
		$result = '';
		$tmp = array();

		foreach($data as $field_name => $field_value) {
			$is_string = in_array($this->field_types[$field_name], Query::$string_fields);
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
	private function get_delimited_data($data, $delimiter = ', ') {
		$result = '';
		$tmp = array();

		foreach($data as $field_name => $field_value) {
			$is_string = in_array($this->field_types[$field_name], Query::$string_fields);
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
	public function table_exists() {
		global $db, $db_use;
		$result = false;

		if ($db_use) {
			$db->query("SHOW TABLES LIKE '{$this->table_name}'");
			$result = $db->num_rows() > 0;
		}

		return $result;
	}

	/**
	 * Return table name this manager is using.
	 *
	 * @return string
	 */
	public function get_table_name() {
		return $this->table_name;
	}
}

?>
