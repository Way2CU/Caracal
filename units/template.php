<?php

/**
 * TEMPLATE HANDLER
 *
 * @version 1.0
 * @author MeanEYE
 * @copyright RCF Group, 2008.
 */

if (!defined('_DOMAIN') || _DOMAIN !== 'RCF_WebEngine') die ('Direct access to this file is not allowed!');

class TemplateHandler {
	/**
	 * XML parser
	 * @var resource
	 */
	var $engine;

	/**
	 * Raw XML data
	 * @var string
	 */
	var $data;

	/**
	 * If XML parser is active and ready
	 * @var boolean
	 */
	var $active;

	/**
	 * Transfer params available from within template
	 * @var array
	 */
	var $params;

	/**
	 * Handling module name
	 * @var string
	 */
	var $mapped_module;

	/**
	 * Tags that need to be formated as block
	 * @var array
	 */
	var $block_tags = array(
						'div', 'ol', 'ul', 'li', 'object', 'table','thead', 'tbody',
						'tr', 'td', 'th', 'head', 'body', 'html', 'form', 'fieldset',
						'select', 'style', 'script', 'label', 'p'
						);

	/**
	 * Custom tag handlers
	 * @var array
	 */
	var $handlers = array();

	/**
	 * Constructor
	 *
	 * @param string $file
	 * @return TemplateHandler
	 */
	function TemplateHandler($file = "", $path = "") {
		global $template_path;

		$this->active = false;
		$this->params = array();
		$path = (empty($path)) ? $template_path : $path;

		// if file exits then load
		if (!empty($file) && file_exists($path.$file)) {
			$this->data = @file_get_contents($path.$file);
			$this->engine = new XMLParser($this->data, $path.$file);
			$this->engine->Parse();

			$this->active = true;
		}
	}


	/**
	 * Restores XML to original state
	 */
	function restoreXML() {
		if (isset($this->engine))
			$this->engine->Parse();
	}

	/**
	 * Sets local params
	 *
	 * @param array $params
	 */
	function setLocalParams($params) {
		$this->params = $params;
	}

	/**
	 * Sets mapped module name for section content parsing
	 *
	 * @param string $module
	 */
	function setMappedModule($module) {
		$this->mapped_module = $module;
	}

	/**
	 * Parse loaded template
	 *
	 * @param integer $level Current level of parsing
	 * @param array $tags Leave blank, used for recursion
	 */
	function parse($level, $tags=array(), $parent_block=true) {
		global $LanguageHandler, $ModuleHandler, $section, $action, $language, $template_path, $system_template_path;

		if ((!$this->active) && empty($tags)) return;

		// take the tag list for parsing
		$tag_array = (empty($tags)) ? $this->engine->document->tagChildren : $tags;

		// used for nicer output
		$tag_space = str_repeat("\t", $level);

		// start parsing tags
		$count = count($tag_array);
		for ($i=0; $i<$count; $i++) {
			$tag = $tag_array[$i];

			// if tag has eval set
			if (isset($tag->tagAttrs['eval'])) {
				// get evaluation values
				$params = explode(',', $tag->tagAttrs['eval']);

				foreach ($params as $param) {
					// prepare module includes for evaluation
					$settings = array();
					if (isset($this->mapped_module) && $ModuleHandler->moduleExists($this->mapped_module)) {
						$module = $ModuleHandler->getObjectFromName($this->mapped_module);
						$settings = $module->settings;
					}

					$params = $this->params;
					$to_eval = $tag->tagAttrs[$param];

					$tag->tagAttrs[$param] = eval('global $ModuleHandler, $SectionHandler, $LanguageHandler,
								$section, $action, $language; return '.$to_eval.';');
					unset($result);
				}
			}

			// now parse the tag
			switch ($tag->tagName) {
				// transfer control to module
				case '_module':
					if ($ModuleHandler->moduleExists($tag->tagAttrs['name'])) {
						$module = $ModuleHandler->getObjectFromName($tag->tagAttrs['name']);
						$module->transferControl($level, $tag->tagAttrs, $tag->tagChildren);
					}
					break;

				// load other template
				case '_template':
					$file = $tag->tagAttrs['file'];
					$path = (key_exists('path', $tag->tagAttrs)) ? $tag->tagAttrs['path'] : '';

					if (!empty($this->mapped_module) && $ModuleHandler->moduleExists($this->mapped_module)) {
						$module = $ModuleHandler->getObjectFromName($this->mapped_module);
						$path = preg_replace('/^%module%/i', $module->path, $path);
						$path = preg_replace('/^%templates%/i', $template_path, $path);
					}

					$new = new TemplateHandler($file, $path);
					$new->setLocalParams($this->params);
					$new->parse($level);
					break;

				// raw text copy
				case '_raw':
					if (key_exists('file', $tag->tagAttrs)) {
						// if file attribute is specified
						$file = $tag->tagAttrs['file'];
						$path = (key_exists('path', $tag->tagAttrs)) ? $tag->tagAttrs['path'] : $template_path;

						$text= file_get_contents($path.$file);

					} elseif (key_exists('text', $tag->tagAttrs)) {
						// if text attribute is specified
						$text = $tag->tagAttrs['text'];

					} else {
						// in any other case we display data inside tag
						$text = $tag->tagData;
					}

					if ($parent_block)
						echo "{$tag_space}{$text}\n"; else
						echo $text;
					break;

				// multi language constants
				case '_text':
					$constant = $tag->tagAttrs['constant'];
					$language = (key_exists('language', $tag->tagAttrs)) ? $tag->tagAttrs['language'] : $language;
					$text = "";

					// check if constant is module based
					if (key_exists('module', $tag->tagAttrs)) {
						// call the apropriate module
						if ($ModuleHandler->moduleExists($tag->tagAttrs['module'])) {
							$module = $ModuleHandler->getObjectFromName($tag->tagAttrs['module']);
							$text = $module->getLanguageConstant($constant, $language);
						}
					} else {
						// use default language handler
						$text = $LanguageHandler->getText($constant, $language);
					}

					if ($parent_block)
						echo "{$tag_space}{$text}\n"; else
						echo $text;
					break;

				// call section specific data
				case '_section_data':
					if (!empty($this->mapped_module) && $ModuleHandler->moduleExists($this->mapped_module)) {
						$module = $ModuleHandler->getObjectFromName($this->mapped_module);
						$file = $module->getSectionFile($section, $action, $language);

						$new = new TemplateHandler(basename($file), dirname($file).'/');
						$new->setLocalParams($this->params);
						$new->setMappedModule($this->mapped_module);
						$new->parse($level);
					} else {
						// log error
						print "Mapped module ({$this->mapped_module}) is not loaded!";
					}
					break;

				// print milti-language data
				case '_language_data':
					$name = isset($tag->tagAttrs['param']) ? $tag->tagAttrs['param'] : null;

					if (!isset($this->params[$name]) || !is_array($this->params[$name]) || is_null($name)) break;

					$template = new TemplateHandler('language_data.xml', $system_template_path);
					$template->setMappedModule($this->mapped_module);

					foreach($this->params[$name] as $lang => $data) {
						$params = array(
									'param'		=> $name,
									'language'	=> $lang,
									'data'		=> $data,
								);
						$template->restoreXML();
						$template->setLocalParams($params);
						$template->parse($level);
					}

					break;

				// conditional tag
				case '_if':
					$settings = array();
					if (isset($this->mapped_module) && $ModuleHandler->moduleExists($this->mapped_module)) {
						$module = $ModuleHandler->getObjectFromName($this->mapped_module);
						$settings = $module->settings;
					}

					$params = $this->params;
					$to_eval = $tag->tagAttrs['condition'];
					if (eval('global $ModuleHandler, $SectionHandler, $LanguageHandler,
								$section, $action, $language; return '.$to_eval.';'))
							$this->parse($level, $tag->tagChildren);
					break;

				// variable
				case '_var':
					$settings = array();
					if (isset($this->mapped_module) && $ModuleHandler->moduleExists($this->mapped_module)) {
						$module = $ModuleHandler->getObjectFromName($this->mapped_module);
						$settings = $module->settings;
					}

					$params = $this->params;
					$to_eval = $tag->tagAttrs['name'];
					echo eval('global $ModuleHandler, $SectionHandler, $LanguageHandler,
								$section, $action, $language; return '.$to_eval.';');
					break;

				// default action for parser, draw tag
				default:
					if (in_array($tag->tagName, array_keys($this->handlers))) {

						// custom tag handler is set...
						$handle = $this->handlers[$tag->tagName];
						$obj = $handle['object'];
						$function = $handle['function'];

						$obj->$function($level, $tag->tagAttrs, $tag->tagChildren);

					} else {

						// default tag handler
						if (in_array($tag->tagName, $this->block_tags)) {
							// if tag is block
							$break = !(empty($tag->tagChildren) && empty($tag->tagData));

							echo $tag_space."<".
								$tag->tagName.$this->getTagParams($tag->tagAttrs).
								">".($break ? "\n" : "");

							if (!empty($tag->tagChildren))
								$this->parse($level+1, $tag->tagChildren, true);

							if (!empty($tag->tagData))
								echo ($break ? $tag_space."\t" : "").$tag->tagData."\n";

							echo ($break ? $tag_space : "")."</{$tag->tagName}>\n";
						} else {
							// if tag is not block, then strip formatting
							echo $tag_space."<".$tag->tagName.$this->getTagParams($tag->tagAttrs).">";

							if (count($tag->tagChildren) > 0 || !empty($tag->tagData)) {
								if (count($tag->tagChildren) > 0) $this->parse($level+1, $tag->tagChildren, false);
								if (!empty($tag->tagData)) echo $tag->tagData;
								echo "</{$tag->tagName}>\n";
							} else echo "\n";
						}

					}
					break;
			}
		}
	}

	/**
	 * Return formated parameter tags
	 *
	 * @param resource $params
	 */
	function getTagParams($params) {
		$result = "";

		if (count($params))
			foreach ($params as $param=>$value)
				if ($param !== 'eval')
					$result .= ' '.$param.'="'.$value.'"';

		return $result;
	}

	/**
	 * Registers handler function for specified tag
	 *
	 * @param string $tag_name
	 * @param pointer $handler
	 * @example function tagHandler($level, $params, $children)
	 */
	function registerTagHandler($tag_name, &$object, $function_name) {
		$this->handlers[$tag_name] = array(
					'object' 	=> &$object,
					'function'	=> $function_name
				);
	}
}

?>
