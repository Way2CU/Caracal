<?php

/**
 * SITE LINKS MODULE
 * This module provides a number of useful ways of printing and organising
 * links on your web site.
 *
 * @author MeanEYE.rcf
 */

class links extends Module {

	/**
	 * Constructor
	 *
	 * @return links
	 */
	function links() {
		$this->file = __FILE__;
		parent::Module();
	}

	/**
	 * Transfers control to module functions
	 *
	 * @param string $action
	 * @param integer $level
	 */
	function transferControl($level, $params = array(), $children = array()) {
		// global control actions
		switch ($params['action']) {
			default:
				break;
		}

		// global control actions
		switch ($params['backend_action']) {
			case 'links_list':
				$this->showList($level);
				break;

			default:
				break;
		}
	}

	/**
	 * Event triggered upon module initialization
	 */
	function onInit() {
		global $db_active, $db;

		$sql = "
			CREATE TABLE IF NOT EXISTS `links` (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`text` varchar(50) COLLATE utf8_bin NOT NULL,
				`description` text COLLATE utf8_bin,
				`url` varchar(255) COLLATE utf8_bin NOT NULL,
				`external` tinyint(1) NOT NULL DEFAULT '1',
				`sponsored` tinyint(1) NOT NULL DEFAULT '0',
				`display_limit` int(11) NOT NULL DEFAULT '0',
				`sponsored_clicks` int(11) NOT NULL DEFAULT '0',
				`total_clicks` int(11) NOT NULL DEFAULT '0',
				PRIMARY KEY (`id`),
				KEY `sponsored` (`sponsored`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=0;";

		if ($db_active == 1) $db->query($sql);
	}

	/**
	 * Event triggered upon module deinitialization
	 */
	function onDisable() {
		global $db, $db_active;

		$sql = "DROP TABLE IF EXISTS `links`;";
		if ($db_active == 1) $db->query($sql);
	}

	/**
	 * Event called upon module registration
	 */
	function onRegister() {
		global $ModuleHandler;

		// load module style and scripts
		if ($ModuleHandler->moduleExists('head_tag')) {
			$head_tag = $ModuleHandler->getObjectFromName('head_tag');
			//$head_tag->addTag('link', array('href'=>url_GetFromFilePath($this->path.'include/links.css'), 'rel'=>'stylesheet', 'type'=>'text/css'));
			//$head_tag->addTag('script', array('src'=>url_GetFromFilePath($this->path.'include/links.js'), 'type'=>'text/javascript'));
		}

		// register backend
		if ($ModuleHandler->moduleExists('backend')) {
			$backend = $ModuleHandler->getObjectFromName('backend');

			$links_menu = new backend_MenuItem(
					$this->getLanguageConstant('menu_links'),
					url_GetFromFilePath($this->path.'images/icon.png'),
					'javascript:void(0);',
					$level=5
				);

			$links_menu->addChild('', new backend_MenuItem(
								$this->getLanguageConstant('menu_links_manage'),
								url_GetFromFilePath($this->path.'images/manage.png'),
								window_Open( // on click open window
											$this->name.'_manage',
											650,
											$this->getLanguageConstant('title_links_manage'),
											true, true,
											backend_UrlMake($this->name, 'links_list')
										),
								$level=5
							));

			$links_menu->addChild('', new backend_MenuItem(
								$this->getLanguageConstant('menu_links_groups'),
								url_GetFromFilePath($this->path.'images/groups.png'),
								window_Open( // on click open window
											$this->name.'_groups',
											650,
											$this->getLanguageConstant('title_links_groups'),
											true, true,
											backend_UrlMake($this->name, 'links_groups')
										),
								$level=5
							));

			$links_menu->addChild('', new backend_MenuItem(
								$this->getLanguageConstant('menu_links_overview'),
								url_GetFromFilePath($this->path.'images/overview.png'),
								window_Open( // on click open window
											$this->name.'_overview',
											650,
											$this->getLanguageConstant('title_links_overview'),
											true, true,
											backend_UrlMake($this->name, 'links_groups')
										),
								$level=6
							));

			$backend->addMenu($this->name, $links_menu);
		}
	}

	/**
	 * Show links window
	 *
	 * @param integer $level
	 */
	function showList($level) {
		$template = new TemplateHandler('links_list.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$params = array(
					'link_new'		=> backend_WindowHyperlink(
										$this->getLanguageConstant('add'),
										$this->name.'_video_add', 400,
										$this->getLanguageConstant('title_video_add'),
										true, false,
										$this->name,
										'video_add'
									),
					'link_groups'	=> backend_WindowHyperlink(
										$this->getLanguageConstant('groups'),
										$this->name.'_video_add', 400,
										$this->getLanguageConstant('title_video_add'),
										true, false,
										$this->name,
										'video_add'
									),
					'link_overview'	=> url_MakeHyperlink(
										$this->getLanguageConstant('overview'),
										window_Open( // on click open window
											$this->name.'_overview',
											650,
											$this->getLanguageConstant('title_links_overview'),
											true, true,
											backend_UrlMake($this->name, 'links_groups')
										)
									)
					);

		$template->registerTagHandler('_link_list', &$this, 'tag_LinkList');
		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse($level);
	}

	/**
	 * Show content of a form used for creation of new `link` object
	 *
	 * @param integer $level
	 */
	function addLink($level) {

	}

	/**
	 * Show content of a form in editing state for sepected `link` object
	 *
	 * @param integer $level
	 */
	function changeLink($level) {

	}

	/**
	 * Save changes existing (or new) to `link` object and display result
	 *
	 * @param integer $level
	 */
	function saveLink($level) {

	}

	/**
	 * Present user with confirmation dialog before removal of specified `link` object
	 *
	 * @param integer $level
	 */
	function deleteLink($level) {

	}

	/**
	 * Remove specified `link` object and inform user about operation status
	 *
	 * @param integer $level
	 */
	function deleteLink_Commit($level) {

	}

	/**
	 * Tag handler for `link` object
	 *
	 * @param integer $level
	 * @param array $params
	 * @param array $children
	 */
	function tag_Link($level, $params, $children) {

	}

	/**
	 * Tag handler for printing link lists
	 *
	 * @param integer $level
	 * @param array $params
	 * @param array $children
	 */
	function tag_LinkList($level, $params, $children) {
		$manager = new LinksManager();

		$items = $manager->getItems(
								$manager->getFieldNames(),
								array(),
								array('id')
							);

		$template = new TemplateHandler(
								isset($params['template']) ? $params['template'] : 'links_item.xml',
								$this->path.'templates/'
							);
		$template->setMappedModule($this->name);

		if (count($items) > 0)
		foreach ($items as $item) {
			$params = array(
						);

			$template->registerTagHandler('_link', &$this, 'tag_Link');
			$template->registerTagHandler('_link_group', &$this, 'tag_LinkGroupList');
			$template->restoreXML();
			$template->setLocalParams($params);
			$template->parse($level);
		}
	}

	function tag_LinkGroupList($level, $params, $children) {

	}
}

class LinksManager extends ItemManager {

	function LinksManager() {
		parent::ItemManager('links');

		$this->addProperty('id', 'int');
		$this->addProperty('text', 'varchar');
		$this->addProperty('description', 'text');
		$this->addProperty('url', 'varchar');
		$this->addProperty('external', 'boolean');
		$this->addProperty('sponsored', 'boolean');
		$this->addProperty('display_limit', 'integer');
		$this->addProperty('sponsored_clicks', 'integer');
		$this->addProperty('total_clicks', 'integer');
	}
}