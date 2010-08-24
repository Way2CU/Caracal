<?php

/**
 * FLASH INTEGRATION MODULE
 *
 * @author MeanEYE.rcf
 */

class swfobject extends Module {
	/**
	 * Minimum required flash version
	 * @var string
	 */
	var $flash_version = '9.0.0';

	/**
	 * Constructor
	 *
	 * @return swfobject
	 */
	function __construct() {
		$this->file = __FILE__;
		parent::__construct();
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
			case 'embed':
				$flash_params = array(
									'wmode'		=> 'transparent',
									'menu'		=> false,
								);
				$this->embedSWF($level, $params['url'], $params['id'],
								$params['width'], $params['height'], array(), $flash_params);
				break;

			default:
				break;
		}

		// global control actions
		switch ($params['backend_action']) {
			default:
				break;
		}
	}

	/**
	 * Event called upon module registration
	 */
	function onRegister() {
		global $ModuleHandler;

		// load module style and scripts
		if ($ModuleHandler->moduleExists('head_tag')) {
			$head_tag = $ModuleHandler->getObjectFromName('head_tag');
			$head_tag->addTag('script', array('src'=>url_GetFromFilePath($this->path.'include/swfobject.js'), 'type'=>'text/javascript'));
		}
	}

	/**
	 * Embed flash player with specified parameters
	 *
	 * @param string $url
	 * @param string $target_id
	 * @param integer $width
	 * @param integer $height
	 * @param array $flash_vars
	 * @param array $params
	 */
	function embedSWF($level, $url, $target_id, $width, $height, $flash_vars=array(), $params=array()) {
		$template = new TemplateHandler('embed.xml', $this->path.'templates/');
		$template->setMappedModule($this->name);

		$p_flashvars = $this->getParams($flash_vars);
		$p_params = $this->getParams($params);
		$script = "swfobject.embedSWF('{$url}', '{$target_id}', '{$width}', '{$height}', '{$this->flash_version}', null, {$p_flashvars}, {$p_params});";

		$params = array(
					'params'		=> $p_params,
					'flash_vars'	=> $p_flashvars,
					'url'			=> $url,
					'id'			=> $target_id,
					'width'			=> $width,
					'height'		=> $height,
					'version'		=> $this->flash_version,
					'script'		=> $script
				);

		$template->restoreXML();
		$template->setLocalParams($params);
		$template->parse($level);
	}

	/**
	 * Get
	 * @param unknown_type $var_name
	 * @param unknown_type $params
	 * @return string
	 */
	function getParams($params) {
		$result = "";

		foreach($params as $key=>$value) {
			switch(gettype($value)) {
				case 'string':
					$value = "'{$value}'";
					break;

				case 'boolean':
					$value = $value ? 'true' : 'false';
					break;

				case 'integer':
					$value = strval($value);
					break;

				default:
					$value = '';
					break;
			}

			$last = $key == end(array_keys($params));
			$result .= "{$key}: {$value}".($last ? '' : ', ');
		}

		$result = "{".$result."}";

		return $result;
	}
}
