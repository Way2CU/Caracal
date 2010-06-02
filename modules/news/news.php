<?php

/**
 * NEWS MODULE
 *
 * @author MeanEYE[rcf]
 */

class news extends Module {

	/**
	 * Constructor
	 *
	 * @return journal
	 */
	function news() {
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
			case 'test':
				$template_file = (isset($global_params['template'])) ? $global_params['template'] : 'item.xml';
				$template = new TemplateHandler($template_file, $this->path.'templates/');
				$template->setMappedModule($this->name);

				$params = array(
					'number'		=> $number,
					'short_name'	=> $short,
					'long_name'		=> $long,
					'url' 			=> $link
				);

				$template->registerTagHandler('_custom', &$this, 'customTag');
				$template->restoreXML();
				$template->setLocalParams($params);
				$template->parse($level);
				break;

			default:
				break;
		}
	}

	function customTag($level, $params, $children) {
		print 'Custom tag handle working!';
		print_r($params);
	}

	/**
	 * Event called upon module initialisation
	 */
	function onInit() {

	}

	/**
	 * Event called upon module registration
	 */
	function onRegister() {
		global $ModuleHandler;

		// load module style and scripts
		if ($ModuleHandler->moduleExists('head_tag')) {
			$head_tag = $ModuleHandler->getObjectFromName('head_tag');
			//$head_tag->addTag('link', array('href'=>url_GetFromFilePath($this->path.'include/news.css'), 'rel'=>'stylesheet', 'type'=>'text/css'));
			//$head_tag->addTag('script', array('src'=>url_GetFromFilePath($this->path.'include/news.js'), 'type'=>'text/javascript'));
		}

		// register backend
		if ($ModuleHandler->moduleExists('backend')) {
			$backend = $ModuleHandler->getObjectFromName('backend');

			$menu = new backend_MenuItem(
							$this->language->getText('title'),
							'',
							backend_Window(
								$this->name,
								320, 240,
								$this->language->getText('title'),
								true, true,
								$this->name,
								'test'
							),
							$level=0
						);

			$menu->addChild('',
					new backend_MenuItem(
							$this->language->getText('title'),
							'',
							backend_Window(
								$this->name.'1',
								320, 240,
								$this->language->getText('title'),
								true, true,
								$this->name,
								'test'
							),
							$level=0
						));
			$menu->addChild('',
					new backend_MenuItem(
							$this->language->getText('title'),
							'',
							backend_Window(
								$this->name.'2',
								320, 240,
								$this->language->getText('title'),
								true, true,
								$this->name,
								'test'
							),
							$level=0
						));
			$menu->addChild('',
					new backend_MenuItem(
							$this->language->getText('title'),
							'',
							backend_Window(
								$this->name.'3',
								320, 240,
								$this->language->getText('title'),
								true, true,
								$this->name,
								'test'
							),
							$level=0
						));

			$backend->addMenu($this->name, $menu);
			$backend->addMenu($this->name.'1', $menu);
		}
	}
}
