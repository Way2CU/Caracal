<?php

/**
 * Order Editor
 *
 * Generic convenicence class used for ordering common elements in database. It comes with its
 * own user interface for ordering elements. Class is not meant to be used on its own but through
 * backend module.
 *
 * Author: Mladen Mijatov
 */
namespace Modules\Backend;

use \TemplateHandler as TemplateHandler;


final class OrderEditor {
	private $parent;
	private $manager;
	private $title_field = 'title';
	private $order_field = 'order';
	private $index_field = 'id';
	private $form_action = null;
	private $window_size = 300;

	public function __construct($parent, $manager) {
		$this->parent = $parent;
		$this->manager = $manager;
	}

	/**
	 * Render interface for editing.
	 */
	public function show_interface() {
		$template = new TemplateHandler('order_list.xml', $this->parent->path.'templates/');
		$template->set_mapped_module($this->parent->name);
		$template->register_tag_handler('cms:list', $this, 'tag_List');

		$params = array(
				'width'       => $this->window_size,
				'form_action' => $this->form_action
			);

		$template->set_local_params($params);
		$template->restore_xml();
		$template->parse();
	}

	/**
	 * Save changes in order to the database. Return value represents whether order was updated.
	 * TODO: This works only on MySQL, needs more generic version for other servers.
	 *
	 * @return boolean
	 */
	public function save_changes() {
		global $db_type;

		// make sure database type is supported
		if ($db_type != \DatabaseType::MYSQL)
			return false;

		// prepare data
		$table = $this->manager->get_table_name();
		$order = fix_id(explode(',', $_REQUEST['order']));

		// prepare sql request
		$sql = "
			SELECT @i := 0;
			UPDATE `{$table}`
			SET `{$this->order_field}` = (SELECT @i := @i + 1)
			ORDER BY FIELD (`{$this->index_field}`, {$order}) ASC;";

		// update order
		$this->manager->get_result($sql);

		return true;
	}

	/**
	 * Set action where interface form should submit.
	 *
	 * @param string $action
	 */
	public function set_form_action($action) {
		$this->form_action = $action;
	}

	/**
	 * Set ordering window size.
	 *
	 * @param integer $size
	 */
	public function set_window_size($size) {
		$this->window_size = $size;
	}

	/**
	 * Set field name to be used for displaying ordering interface.
	 *
	 * @param string $field_name
	 */
	public function set_title_field($field_Name) {
		$this->title_field = $field_name;
	}

	/**
	 * Set field name to update with order of elements.
	 *
	 * @param string $field_name
	 */
	public function set_order_field($field_name) {
		$this->order_field = $field_name;
	}

	/**
	 * Set field name used for addressing database records.
	 *
	 * @param string $field_name
	 */
	public function set_index_field($field_name) {
		$this->index_field = $field_name;
	}

	/**
	 * Render list tag with specified manager.
	 *
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_List($tag_params, $children) {
		$fields = array($this->index_field, $this->title_field, $this->order_field);

		// get items from teh database
		$items = $this->manager->get_items($fields, array(), array($this->order_field), true);

		if (count($items) == 0)
			return;

		// load template
		$template = $this->parent->load_template($tag_params, 'order_list_item.xml');

		// render items
		foreach ($items as $item) {
			$params = array(
					'id'    => $item->{$this->index_field},
					'title' => $item->{$this->title_field},
					'order' => $item->{$this->order_field}
				);

			$template->set_local_params($params);
			$template->restore_xml();
			$template->parse();
		}
	}
}

?>
