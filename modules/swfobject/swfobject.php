<?php

/**
 * Flash Integration Module
 *
 * Author: Mladen Mijatov
 */
use Core\Module;


class swfobject extends Module {
	private static $_instance;
	private $flash_version = '9.0.0';

	/**
	 * Constructor
	 */
	protected function __construct() {
		parent::__construct(__FILE__);

		if (class_exists('head_tag')) {
			$head_tag = head_tag::getInstance();
			$head_tag->addTag('script', array('src'=>url_GetFromFilePath($this->path.'include/swfobject.js'), 'type'=>'text/javascript'));
		}
	}

	/**
	 * Get single instance of ModuleHandler
	 */
	public static function getInstance() {
		if (!isset(self::$_instance))
			self::$_instance = new self();

		return self::$_instance;
	}

	/**
	 * Transfers control to module functions
	 *
	 * @param array $params
	 * @param array $children
	 */
	public function transferControl($params, $children) {
		// global control actions
		if (isset($params['action']))
			switch ($params['action']) {
				case 'embed':
					$flash_params = array(
										'wmode'		=> 'transparent',
										'menu'		=> false,
									);
					$this->embedSWF($params['url'], $params['id'],
									$params['width'], $params['height'], array(), $flash_params);
					break;

				default:
					break;
			}

		// global control actions
		if (isset($params['backend_action']))
			switch ($params['backend_action']) {
				default:
					break;
			}
	}

	public function onInit() {
	}

	public function onDisable() {
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
	public function embedSWF($url, $target_id, $width, $height, $flash_vars=array(), $params=array()) {
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
		$template->parse();
	}

	/**
	 * Get
	 * @param unknown_type $var_name
	 * @param unknown_type $params
	 * @return string
	 */
	private function getParams($params) {
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
