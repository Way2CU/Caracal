<?php

/**
 * Tips Module
 *
 * @author MeanEYE.rcf
 */

class tips extends Module {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct(__FILE__);

		// load module style and scripts
		if (class_exists('head_tag')) {
			$head_tag = head_tag::getInstance();
			//$head_tag->addTag('link', array('href'=>url_GetFromFilePath($this->path.'include/_blank.css'), 'rel'=>'stylesheet', 'type'=>'text/css'));
			//$head_tag->addTag('script', array('src'=>url_GetFromFilePath($this->path.'include/_blank.js'), 'type'=>'text/javascript'));
		}

		// register backend
		if (class_exists('backend')) {
			$backend = backend::getInstance();

			$tips_menu = new backend_MenuItem(
					$this->getLanguageConstant('menu_tips'),
					url_GetFromFilePath($this->path.'images/icon.png'),
					'javascript:void(0);',
					$level=5
				);

			$tips_menu->addChild('', new backend_MenuItem(
								$this->getLanguageConstant('menu_tips_manage'),
								url_GetFromFilePath($this->path.'images/manage.png'),

								window_Open( // on click open window
											'tips',
											500,
											$this->getLanguageConstant('title_tips_manage'),
											true, true,
											backend_UrlMake($this->name, 'tips')
										),
								$level=5
							));

			$backend->addMenu($this->name, $tips_menu);
		}
	}

	/**
	 * Public function that creates a single instance
	 */
	public static function getInstance() {
		if (!isset(self::$_instance))
			self::$_instance = new self();

		return self::$_instance;
	}

	/**
	 * Transfers control to module functions
	 *
	 * @param integer $level
	 * @param array $params
	 * @param array $children
	 */
	public function transferControl($level, $params = array(), $children = array()) {
		// global control actions
		if (isset($params['action']))
			switch ($params['action']) {
				case 'show':
					$this->tag_Tip($level, $params, $children);
					break;

				case 'show_list':
					$this->tag_TipList($level, $params, $children);
					break;

				default:
					break;
			}

		// global control actions
		if (isset($params['backend_action']))
			switch ($params['backend_action']) {
				case 'tips':
					$this->showTips($level);
					break;

				case 'tips_new':
					$this->addTip($level);
					break;

				case 'tips_change':
					$this->changeTip($level);
					break;

				case 'tips_save':
					$this->saveTip($level);
					break;

				case 'tips_delete':
					$this->deleteTip($level);
					break;

				case 'tips_delete_commit':
					$this->deleteTip_Commit($level);
					break;

				default:
					break;
			}
	}

	/**
	 * Event triggered upon module initialization
	 */
	public function onInit() {
		global $db_active, $db;

		$list = MainLanguageHandler::getInstance()->getLanguages(false);

		$sql = "
			CREATE TABLE `tips` (
				`id` INT NOT NULL AUTO_INCREMENT ,";

		foreach($list as $language)
			$sql .= "`content_{$language}` TEXT NOT NULL ,";

		$sql .= "
				`visible` BOOLEAN NOT NULL DEFAULT '0',
				PRIMARY KEY ( `id` )
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";

		if ($db_active == 1) $db->query($sql);
	}

	/**
	 * Event triggered upon module deinitialization
	 */
	public function onDisable() {
		global $db_active, $db;

		$sql = "DROP TABLE IF EXISTS `tips`;";

		if ($db_active == 1) $db->query($sql);
	}

	/**
	 * Show tips management form
	 *
	 * @param integer $level
	 */
	private function showTips($level) {
		$template = new TemplateHandler('list.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'link_new'		=> window_OpenHyperlink(
										$this->getLanguageConstant('new'),
										'tips_new', 400,
										$this->getLanguageConstant('title_tips_new'),
										true, false,
										$this->name,
										'tips_new'
									),
					);

		$template->registerTagHandler('_tip_list', &$this, 'tag_TipList');
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse($level);
	}

	/**
	 * Print form for adding new tip to the system
	 *
	 * @param integer $level
	 */
	private function addTip($level) {
		$template = new TemplateHandler('add.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'form_action'	=> backend_UrlMake($this->name, 'tips_save'),
					'cancel_action'	=> window_Close('tips_new')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse($level);
	}

	/**
	 * Change tip form
	 *
	 * @param integer $level
	 */
	private function changeTip($level) {
		$id = fix_id($_REQUEST['id']);
		$manager = TipManager::getInstance();

		$item = $manager->getSingleItem($manager->getFieldNames(), array('id' => $id));

		if (is_object($item)) {
			$template = new TemplateHandler('change.xml', $this->path.'templates/');
			$template->setMappedModule($this->name);

			$params = array(
						'id'			=> $item->id,
						'content'		=> unfix_chars($item->content),
						'visible'		=> $item->visible,
						'form_action'	=> backend_UrlMake($this->name, 'tips_save'),
						'cancel_action'	=> window_Close('tips_change')
					);

			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse($level);
		}
	}

	/**
	 * Save changed or new data
	 *
	 * @param integer $level
	 */
	private function saveTip($level) {
		$id = isset($_REQUEST['id']) ? fix_id($_REQUEST['id']) : null;
		$manager = TipManager::getInstance();
		$data = array(
					'content'	=> fix_chars($this->getMultilanguageField('content')),
					'visible'	=> fix_id($_REQUEST['visible'])
				);

		if (is_null($id)) {
			$window = 'tips_new';
			$manager->insertData($data);
		} else {
			$window = 'tips_change';
			$manager->updateData($data,	array('id' => $id));
		}

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $this->getLanguageConstant('message_tip_saved'),
					'button'	=> $this->getLanguageConstant('close'),
					'action'	=> window_Close($window).";".window_ReloadContent('tips'),
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse($level);
	}

	/**
	 * Show confirmation dialog before removing tip
	 *
	 * @param integer $level
	 */
	private function deleteTip($level) {
		global $language;

		$id = fix_id(fix_chars($_REQUEST['id']));
		$manager = TipManager::getInstance();

		$item = $manager->getSingleItem(array('content'), array('id' => $id));

		$template = new TemplateHandler('confirmation.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'		=> $this->getLanguageConstant("message_tip_delete"),
					'name'			=> $item->content[$language],
					'yes_text'		=> $this->getLanguageConstant("delete"),
					'no_text'		=> $this->getLanguageConstant("cancel"),
					'yes_action'	=> window_LoadContent(
											'tips_delete',
											url_Make(
												'transfer_control',
												'backend_module',
												array('module', $this->name),
												array('backend_action', 'tips_delete_commit'),
												array('id', $id)
											)
										),
					'no_action'		=> window_Close('tips_delete')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse($level);
	}

	/**
	 * Perform tip removal
	 *
	 * @param integer $level
	 */
	private function deleteTip_Commit($level) {
		$id = fix_id(fix_chars($_REQUEST['id']));
		$manager = TipManager::getInstance();

		$manager->deleteData(array('id' => $id));

		$template = new TemplateHandler('message.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'message'	=> $this->getLanguageConstant("message_tip_deleted"),
					'button'	=> $this->getLanguageConstant("close"),
					'action'	=> window_Close('tips_delete').";".window_ReloadContent('tips')
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse($level);
	}

	/**
	 * Tip tag handler
	 *
	 * @param integer $level
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_Tip($level, $tag_params, $children) {
		$manager = TipManager::getInstance();
		$order_by = array();
		$conditions = array();

		if (isset($tag_params['id'])) {
			$conditions['id'] = $tag_params['id'];
		} else if (isset($tag_params['random']) && $tag_params['random']) {
			$order_by[] = 'RAND()';
		} else {
			$order_by[] = 'id';
		}

		$item = $manager->getSingleItem($manager->getFieldNames(), $conditions, $order_by, false);

		if (isset($tag_params['template'])) {
			if (isset($tag_params['local']) && $tag_params['local'] == 1)
				$template = new TemplateHandler($tag_params['template'], $this->path.'templates/'); else
				$template = new TemplateHandler($tag_params['template']);
		} else {
			$template = new TemplateHandler('list_item.xml', $this->path.'templates/');
		}
		$template->setMappedModule($this->name);

		if (is_object($item)) {
			$params = array(
						'id'		=> $item->id,
						'content'	=> $item->content,
						'visible'	=> $item->visible,
					);

			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse($level);
		}
	}

	/**
	 * Tag handler for tip list
	 *
	 * @param integer $level
	 * @param array $tag_params
	 * @param array $children
	 */
	public function tag_TipList($level, $tag_params, $children) {
		$manager = TipManager::getInstance();
		$conditions = array();

		if (isset($tag_params['only_visible']) && $tag_params['only_visible'] == 1)
			$conditions['visible'] = 1;

		if (isset($tag_params['template'])) {
			if (isset($tag_params['local']) && $tag_params['local'] == 1)
				$template = new TemplateHandler($tag_params['template'], $this->path.'templates/'); else
				$template = new TemplateHandler($tag_params['template']);
		} else {
			$template = new TemplateHandler('list_item.xml', $this->path.'templates/');
		}

		$template->setMappedModule($this->name);

		// get items
		$items = $manager->getItems($manager->getFieldNames(), $conditions);

		if (count($items) > 0)
			foreach($items as $item) {
				$params = array(
							'id'			=> $item->id,
							'content'		=> $item->content,
							'visible'		=> $item->visible,
							'item_change'	=> url_MakeHyperlink(
													$this->getLanguageConstant('change'),
													window_Open(
														'tips_change', 		// window id
														400,				// width
														$this->getLanguageConstant('title_tips_change'), // title
														false, false,
														url_Make(
															'transfer_control',
															'backend_module',
															array('module', $this->name),
															array('backend_action', 'tips_change'),
															array('id', $item->id)
														)
													)
												),
							'item_delete'	=> url_MakeHyperlink(
													$this->getLanguageConstant('delete'),
													window_Open(
														'tips_delete', 	// window id
														400,				// width
														$this->getLanguageConstant('title_tips_delete'), // title
														false, false,
														url_Make(
															'transfer_control',
															'backend_module',
															array('module', $this->name),
															array('backend_action', 'tips_delete'),
															array('id', $item->id)
														)
													)
												),
							'item_read'		=> '',
						);

				$template->restoreXML();
				$template->setLocalParams($params);
				$template->parse($level);
			}
	}
}


class TipManager extends ItemManager {
	private static $_instance;

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct('tips');

		$this->addProperty('id', 'int');
		$this->addProperty('content', 'ml_text');
		$this->addProperty('visible', 'boolean');
	}

	/**
	 * Public function that creates a single instance
	 */
	public static function getInstance() {
		if (!isset(self::$_instance))
			self::$_instance = new self();

		return self::$_instance;
	}
}
