<?php

/**
 * Template Handler
 *
 * This class uses XML parser to create and show page content. In addition
 * to standard tags some additional ones are provided for easier control.
 *
 * Author: Mladen Mijatov
 */

use Core\Markdown;
use Core\Cache\Manager as Cache;


class TemplateHandler {
	/**
	 * Used for debugging
	 * @var string
	 */
	public $file;

	/**
	 * XML parser
	 * @var resource
	 */
	public $engine;

	/**
	 * If XML parser is active and ready
	 * @var boolean
	 */
	public $active;

	/**
	 * Transfer params available from within template
	 * @var array
	 */
	private $params;

	/**
	 * Transfer params when invoking template load from another template.
	 * @var array
	 */
	private $template_params;

	/**
	 * Handling module name
	 * @var object
	 */
	public $module;

	/**
	 * Custom tag handlers
	 * @var array
	 */
	private $handlers = array();

	/**
	 * Tag children overrides.
	 * @var array
	 */
	private $tag_children = array();

	/**
	 * List of tags that shouldn't be closed
	 * @var array
	 */
	private $tags_without_end = array('br', 'wbr', 'hr', 'img', 'base', 'input', 'link', 'meta');

	/**
	 * Summary list of HTML boolean attributes
	 * @var array
	 */
	private $boolean_attributes = array(
					'allowfullscreen', 'async', 'autofocus', 'autoplay', 'checked', 'compact', 'controls',
					'declare', 'default', 'defaultchecked', 'defaultmuted', 'defaultselected', 'defer',
					'disabled', 'draggable', 'enabled', 'formnovalidate', 'hidden', 'indeterminate', 'inert',
					'ismap', 'itemscope', 'loop', 'multiple', 'muted', 'nohref', 'noresize', 'noshade',
					'novalidate', 'nowrap', 'open', 'pauseonexit', 'readonly', 'required', 'reversed',
					'scoped', 'seamless', 'selected', 'sortable', 'spellcheck', 'translate', 'truespeed',
					'typemustmatch', 'visible'
				);

	/**
	 * If we should close all tags
	 * @var boolean
	 */
	private $close_all_tags = false;

	/**
	 * List of session variables that we protect from setting
	 * @var array
	 */
	private $protected_variables = array('uid', 'logged', 'level', 'username', 'fullname', 'captcha');

	/**
	 * Cache handler.
	 * @var object
	 */
	private $cache = null;

	/**
	 * Constructor
	 *
	 * @param string $file
	 * @return TemplateHandler
	 */
	public function __construct($file = '', $path = '') {
		global $template_path;

		$this->active = false;
		$this->params = array();
		$this->module = null;
		$path = empty($path) ? $template_path : $path;
		$this->file = $path.$file;
		$this->cache = Cache::getInstance();

		// if file exits then load
		if (!empty($this->file) && file_exists($this->file)) {
			$data = @file_get_contents($this->file);
			$this->engine = new XMLParser($data, $this->file);
			$this->engine->Parse();

			$this->active = true;
		}
	}

	/**
	 * Restores XML to original state
	 */
	public function restoreXML() {
		if (isset($this->engine))
			$this->engine->Parse();
	}

	/**
	 * Manually set XML
	 * @param string $data
	 */
	public function setXML($data) {
		if (isset($this->engine))
			unset($this->engine);

		$this->engine = new XMLParser($data, '');
		$this->engine->Parse();
		$this->active = true;
	}

	/**
	 * Sets local params
	 *
	 * @param array $params
	 */
	public function setLocalParams($params) {
		$this->params = $params;
	}

	/**
	 * Sets template parameters.
	 *
	 * @param array $params;
	 */
	public function setTemplateParams($params) {
		$this->template_params = $params;
	}

	/**
 	 * Allows setting template params from tag children array.
	 *
	 * @param array $children
	 */
	public function setTemplateParamsFromArray($children) {
		if (count($children) == 0)
			return;

		// collect params
		$template_params = array();

		foreach ($children as $child)
			if ($child->tagName == 'param')
				$template_params[$child->tagAttrs['name']] = $child->tagAttrs['value'];

		// set params
		$this->setTemplateParams($template_params);
	}

	/**
	 * Sets mapped module name for section content parsing
	 *
	 * @param string $module
	 */
	public function setMappedModule($module) {
		if (is_string($module)) {
			if (ModuleHandler::is_loaded($module))
				$this->module = call_user_func(array($module, 'getInstance'));

		} else {
			$this->module = $module;
		}
	}

	/**
	 * Parse loaded template
	 *
	 * @param integer $level Current level of parsing
	 * @param array $tags Leave blank, used for recursion
	 * @param boolean $parent_block If parent tag is block element
	 */
	public function parse($tags=array()) {
		global $section, $action, $language, $template_path, $system_template_path, $images_path;

		// turn on custom error hanlder
		set_error_handler(array($this, 'handleError'));

		if ((!$this->active) && empty($tags))
			return;

		// take the tag list for parsing
		$tag_array = array();

		if (!empty($tags))
			$tag_array = $tags;

		if (empty($tag_array) && $this->active)
			$tag_array = $this->engine->document->tagChildren;

		// start parsing tags
		$count = count($tag_array);
		for ($i=0; $i<$count; $i++) {
			$tag = $tag_array[$i];

			// if tag has eval set
			if (isset($tag->tagAttrs['cms:eval']) || isset($tag->tagAttrs['eval'])) {
				// get evaluation values
				if (isset($tag->tagAttrs['eval']))
					$value = $tag->tagAttrs['eval']; else
					$value = $tag->tagAttrs['cms:eval'];

				$eval_params = explode(',', $value);

				foreach ($eval_params as $param) {
					// prepare module includes for evaluation
					$settings = array();
					if (!is_null($this->module))
						$settings = $this->module->settings;

					$params = $this->params;
					$template = $this->template_params;
					$to_eval = $tag->tagAttrs[$param];

					$response = @eval('global $section, $action, $language, $language_rtl; return '.$to_eval.';');

					if ($response !== false)
						$tag->tagAttrs[$param] = $response; else
						trigger_error('Error while trying to `cms:eval` "'.$to_eval.'" in file: '.$this->file, E_USER_WARNING);
				}

				// unset param
				unset($tag->tagAttrs['cms:eval']);
			}

			if (isset($tag->tagAttrs['cms:optional'])) {
				// get evaluation values
				$optional_params = explode(',', $tag->tagAttrs['cms:optional']);

				foreach ($optional_params as $param) {
					// prepare module includes for evaluation
					$settings = array();
					if (!is_null($this->module))
						$settings = $this->module->settings;

					$params = $this->params;
					$template = $this->template_params;
					$to_eval = $tag->tagAttrs[$param];

					$value = eval('global $section, $action, $language, $language_rtl; return '.$to_eval.';');

					if ($value == false)
						unset($tag->tagAttrs[$param]); else
						$tag->tagAttrs[$param] = $value;
				}

				// unset param
				unset($tag->tagAttrs['cms:optional']);
			}

			// implement tooltip
			if (isset($tag->tagAttrs['cms:tooltip'])) {
				if (!is_null($this->module))
					$value = $this->module->getLanguageConstant($tag->tagAttrs['cms:tooltip']); else
					$value = Language::getText($tag->tagAttrs['cms:tooltip']);

				if (!empty($value))
					$tag->tagAttrs['data-tooltip'] = $value;
				unset($tag->tagAttrs['cms:tooltip']);
			}

			// implement constants
			if (isset($tag->tagAttrs['cms:constant'])) {
				$params = explode(',', $tag->tagAttrs['cms:constant']);

				if (count($params) > 0)
					foreach ($params as $param)
						if (!is_null($this->module))
							$tag->tagAttrs[$param] = $this->module->getLanguageConstant($tag->tagAttrs[$param]); else
							$tag->tagAttrs[$param] = Language::getText($tag->tagAttrs[$param]);

				unset($tag->tagAttrs['cms:constant']);
			}

			// check if specified tag shouldn't be cached
			$skip_cache = false;

			if (isset($tag->tagAttrs['cms:skip_cache'])) {
				// unset param
				unset($tag->tagAttrs['cms:skip_cache']);

				// only if current URL is being cached, we start dirty area
				if ($this->cache->isCaching()) {
					$this->cache->startDirtyArea();
					$skip_cache = true;

					// reconstruct template for cache,
					// ugly but we are not doing it a lot
					$data = $this->getDataForCache($tag);
					$this->cache->setCacheForDirtyArea($data);
				}
			}

			// check if we should flush after tag is closed
			$flush_data = false;

			if (isset($tag->tagAttrs['cms:flush'])) {
				unset($tag->tagAttrs['cms:skip_cache']);
				$flush_data = false;
			}

			// now parse the tag
			switch ($tag->tagName) {
				// handle tag used for setting session variable
				case 'cms:session':
					$name = $tag->tagAttrs['name'];

					// allow setting referral only once per seesion
					if (isset($tag->tagAttrs['once']))
						$only_once = in_array($tag->tagAttrs['once'], array(1, 'yes')); else
						$only_once = false;

					$should_set = ($only_once && !isset($_SESSION[$name])) || !$only_once;

					// store value
					if (!in_array($name, $this->protected_variables) && $should_set)
						$_SESSION[$name] = $tag->tagAttrs['value'];

					break;

				// transfer control to module
				case 'cms:module':
					if (ModuleHandler::is_loaded($tag->tagAttrs['name'])) {
						$module = call_user_func(array($tag->tagAttrs['name'], 'getInstance'));
						$module->transferControl($tag->tagAttrs, $tag->tagChildren);
					}
					break;

				// load other template
				case 'cms:template':
					$file = $tag->tagAttrs['file'];
					$path = (key_exists('path', $tag->tagAttrs)) ? $tag->tagAttrs['path'] : '';

					// create new template handler
					$new = new TemplateHandler($file, $path);

					// transfer local params to new template handler
					$new->setLocalParams($this->params);
					$new->setTemplateParamsFromArray($tag->tagChildren);

					// parse new template
					$new->parse();
					break;

				// raw text copy
				case 'cms:raw':
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

					echo $text;
					break;

				// embed svg images
				case 'cms:svg':
					$path = _BASEPATH.'/'.$images_path;
					$file = $tag->tagAttrs['file'];

					echo file_get_contents($path.$file);
					break;

				// multi language constants
				case 'cms:text':
					$constant = $tag->tagAttrs['constant'];
					$language = (key_exists('language', $tag->tagAttrs)) ? $tag->tagAttrs['language'] : $language;
					$text = "";

					// check if constant is module based
					if (key_exists('module', $tag->tagAttrs)) {
						if (ModuleHandler::is_loaded($tag->tagAttrs['module'])) {
							$module = call_user_func(array($tag->tagAttrs['module'], 'getInstance'));
							$text = $module->getLanguageConstant($constant, $language);
						}

					} else {
						// use default language handler
						$text = Language::getText($constant, $language);
					}

					echo $text;
					break;

				// support for markdown
				case 'cms:markdown':
					$char_count = isset($tag->tagAttrs['chars']) ? fix_id($tag->tagAttrs['chars']) : null;
					$end_with = isset($tag->tagAttrs['end_with']) ? fix_id($tag->tagAttrs['end_with']) : null;
					$name = isset($tag->tagAttrs['param']) ? $tag->tagAttrs['param'] : null;
					$multilanguage = isset($tag->tagAttrs['multilanguage']) ? $tag->tagAttrs['multilanguage'] == 'yes' : false;
					$clear_text = isset($tag->tagAttrs['clear_text']) ? $tag->tagAttrs['clear_text'] == 'yes' : false;

					// get content for parsing
					if (is_null($name))
						$content = $tag->tagData;
					$content = $multilanguage ? $this->params[$name][$language] : $this->params[$name];

					// convert to HTML
					$content = Markdown::parse($content);

					// strip tags if needed
					if ($clear_text)
						$content = strip_tags($content);

					// limit words if specified
					if (!is_null($char_count)) {
						if (is_null($end_with))
							$content = limit_words($content, $char_count); else
							$content = limit_words($content, $char_count, $end_with);
					}

					echo $content;
					break;

				// call section specific data
				case 'cms:section_data':
					if (!is_null($this->module)) {
						$file = $this->module->getSectionFile($section, $action, $language);

						$new = new TemplateHandler(basename($file), dirname($file).'/');
						$new->setLocalParams($this->params);
						$new->setMappedModule($this->module);
						$new->parse();
					} else {
						// log error
						trigger_error('Mapped module is not loaded! File: '.$this->file, E_USER_WARNING);
					}
					break;

				// print multilanguage data
				case 'cms:language_data':
					$name = isset($tag->tagAttrs['param']) ? $tag->tagAttrs['param'] : null;

					if (!isset($this->params[$name]) || !is_array($this->params[$name]) || is_null($name)) break;

					$template = new TemplateHandler('language_data.xml', $system_template_path);
					$template->setMappedModule($this->module);

					foreach($this->params[$name] as $lang => $data) {
						$params = array(
									'param'		=> $name,
									'language'	=> $lang,
									'data'		=> $data,
								);
						$template->restoreXML();
						$template->setLocalParams($params);
						$template->parse();
					}

					break;

				// replace tag data string with matching params
				case 'cms:replace':
					$pool = isset($tag->tagAttrs['param']) ? $this->params[$tag->tagAttrs['param']] : $this->params;

					$keys = array_keys($pool);
					$values = array_values($pool);

					foreach($keys as $i => $key)
						$keys[$i] = "%{$key}%";

					// we can't replact string with array, only matching data types
					foreach($values as $i => $value)
						if (is_array($value)) {
							unset($keys[$i]);
							unset($values[$i]);
						}

					echo str_replace($keys, $values, $tag->tagData);
					break;

				// conditional tag
				case '_if':
				case 'cms:if':
					$settings = !is_null($this->module) ? $this->module->settings : array();
					$params = $this->params;
					$template = $this->template_params;
					$condition = true;

					// check if section is specified and matches
					if (isset($tag->tagAttrs['section']))
						$condition &= $tag->tagAttrs['section'] == $section;

					// check if action is specified and matches
					if (isset($tag->tagAttrs['action']))
						$condition &= $tag->tagAttrs['action'] == $action;

					// check custom condition
					if (isset($tag->tagAttrs['condition'])) {
						$to_eval = $tag->tagAttrs['condition'];
						$eval_result = eval('global $section, $action, $language, $language_rtl; return '.$to_eval.';') == true;
						$condition &= $eval_result;
					}

					// parse children
					if ($condition)
						$this->parse($tag->tagChildren);

					break;

				// conditional tag parsed for desktop version
				case 'cms:desktop':
					if (_DESKTOP_VERSION)
						$this->parse($tag->tagChildren);

					break;

				// conditional tag parsed for mobile version
				case 'cms:mobile':
					if (_MOBILE_VERSION)
						$this->parse($tag->tagChildren);

					break;

				// conditional tag parsed for users that are logged in
				case 'cms:user':
					if ($_SESSION['logged'])
						$this->parse($tag->tagChildren);

					break;

				// conditional tag parsed for guests
				case 'cms:guest':
					if (!$_SESSION['logged'])
						$this->parse($tag->tagChildren);

					break;

				// variable
				case 'cms:var':
					$settings = array();
					if (!is_null($this->module))
						$settings = $this->module->settings;

					$params = $this->params;
					$output = '';

					if (isset($tag->tagAttrs['name'])) {
						// old method with eval
						$to_eval = $tag->tagAttrs['name'];
						$output = eval('global $section, $action, $language, $language_rtl; return '.$to_eval.';');

					} else if (isset($tag->tagAttrs['param'])) {
						$param = $tag->tagAttrs['param'];
						$multilanguage = isset($tag->tagAttrs['multilanguage']) ? $tag->tagAttrs['multilanguage'] == 'yes' : false;

						if (isset($params[$param]))
							if (!$multilanguage)
								$output = $params[$param]; else
								$output = $params[$param][$language];
					}

					echo $output;
					break;

				// support for script tag
				case 'cms:script':
					if (ModuleHandler::is_loaded('head_tag')) {
						$head_tag = head_tag::getInstance();
						$head_tag->addTag('script', $tag->tagAttrs);
					}
					break;

				// support for collection module
				case 'cms:collection':
					if (array_key_exists('include', $tag->tagAttrs) && ModuleHandler::is_loaded('collection')) {
						$scripts = fix_chars(explode(',', $tag->tagAttrs['include']));

						$collection = collection::getInstance();
						$collection->includeScript($scripts);
					}
					break;

				// support for link tag
				case 'cms:link':
					if (ModuleHandler::is_loaded('head_tag')) {
						$head_tag = head_tag::getInstance();
						$head_tag->addTag('link', $tag->tagAttrs);
					}
					break;

				// support for parameter based choice
				case 'cms:choice':
					$param_value = null;

					if (array_key_exists('param', $tag->tagAttrs)) {
						// grap param value from GET or POST parameters
						$param_name = fix_chars($tag->tagAttrs['param']);
						$param_value = isset($_REQUEST[$param_name]) ? fix_chars($_REQUEST[$param_name]) : null;

					} else if (array_key_exists('value', $tag->tagAttrs)) {
						// use param value specified
						$param_value = fix_chars($tag->tagAttrs['value']);
					}

					// parse only option
					foreach ($tag->tagChildren as $option) {
						if (!$option->tagName == 'option')
							continue;

						$option_value = isset($option->tagAttrs['value']) ? $option->tagAttrs['value'] : null;
						$option_default = isset($option->tagAttrs['default']) ? $option->tagAttrs['default'] == 1 : false;

						// values match or option is default, parse its content
						if ($option_value == $param_value || $option_default) {
							$this->parse($option->tagChildren);
							break;
						}
					}

					break;

				// force flush on common elements
				case 'head':
				case 'header':
				case 'footer':
					$flush_data = true;

				// default action for parser, draw tag
				default:
					if (array_key_exists($tag->tagName, $this->handlers)) {
						// custom tag handler is set...
						$handle = $this->handlers[$tag->tagName];
						$obj = $handle['object'];
						$function = $handle['function'];

						if (!array_key_exists($tag->tagName, $this->tag_children))
							$obj->$function($tag->tagAttrs, $tag->tagChildren); else
							$obj->$function($tag->tagAttrs, $this->tag_children[$tag->tagName]);

					} else {
						// default tag handler
						echo '<'.$tag->tagName.$this->getTagParams($tag->tagAttrs).'>';

						if (count($tag->tagChildren) > 0)
							$this->parse($tag->tagChildren);

						if (count($tag->tagData) > 0)
							echo $tag->tagData;

						// check if tag needs to be closed
						if ($this->close_all_tags)
							$close_tag = true; else
							$close_tag = !in_array($tag->tagName, $this->tags_without_end);

						// close tag if needed
						if ($close_tag)
							echo '</'.$tag->tagName.'>';
					}

					if ($flush_data)
						flush();

					break;
			}

			// end cache dirty area if initialized
			if ($skip_cache)
				$this->cache->endDirtyArea();
		}

		// restore previous error handler
		restore_error_handler();
	}

	/**
	 * Return formated parameter tags
	 *
	 * @param resource $params
	 */
	private function getTagParams($params) {
		$result = "";

		if (count($params) == 0)
			return $result;

		foreach ($params as $param=>$value) {
			$is_boolean = $param == $value && _STANDARD !== 'xml' && in_array(strtolower($param), $this->boolean_attributes);

			if (!$is_boolean)
				$result .= ' '.$param.'="'.$value.'"'; else
				$result .= ' '.$param;
		}

		return $result;
	}

	/**
	 * Reconstruct template for cache
	 *
	 * @param object $tag
	 * @return string
	 */
	private function getDataForCache($tag) {
		// open tag
		$result = '<'.$tag->tagName.$this->getTagParams($tag->tagAttrs).'>';

		// get tag children
		if (count($tag->tagChildren) > 0)
			foreach($tag->tagChildren as $child)
				$result .= $this->getDataForCache($child);

		// show tag data
		if (count($tag->tagData) > 0)
			$result .= $tag->tagData;

		// close tag
		$result .= '</'.$tag->tagName.'>';

		return $result;
	}

	/**
	 * Registers handler function for specified tag
	 *
	 * @param string $tag_name Name of a tag to be handled
	 * @param pointer $object Object with specified public method
	 * @param string $function_name Public tag handler method
	 * @example function tagHandler($level, $params, $children)
	 */
	public function registerTagHandler($tag_name, $object, $function_name) {
		$this->handlers[$tag_name] = array(
					'object' 	=> $object,
					'function'	=> $function_name
				);
	}

	/**
	 * Set tag children tags to be overriden.
	 *
	 * @param string $tag_name
	 * @param array $children
	 */
	public function setTagChildren($tag_name, &$children) {
		if (!array_key_exists($tag_name, $this->handlers))
			return;

		$this->tag_children[$tag_name] = $children;
	}

	/**
	 * Handle errors inside of template parser.
	 *
	 * @param integer $number - level of error raised
	 * @param string $message - error message
	 * @param string $file - file in which error has occurred
	 * @param integer $line - line where error has occurred
	 * @param array $context - variable map in the time of raising an error
	 * @return boolean
	 */
	private function handleError($number, $message, $file=null, $line=null, $context=null) {
		$data = array();

		$data[] = $type.':';
		$data[] = $message.' in template "';
		$data[] = $this->file.'"';

		if (!is_null($file))
			$data[] = '('.$file.' on line '.$line.')';

		$text = implode('', $data);
		error_log($text);
		return true;
	}
}

?>
