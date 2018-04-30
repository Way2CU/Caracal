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
use Core\CSP\Parser as CSP;
use Core\Cache\Type as CacheType;
use Core\Cache\Manager as Cache;
use Core\Testing\Handler as TestingHandler;


class TemplateHandler {
	/**
	 * Used for debugging.
	 * @var string
	 */
	public $file;

	/**
	 * XML parser
	 * @var resource
	 */
	public $engine;

	/**
	 * If XML parser is active and ready.
	 * @var boolean
	 */
	public $active;

	/**
	 * Transfer params available from within template.
	 * @var array
	 */
	private $params;

	/**
	 * Transfer params when invoking template load from another template.
	 * @var array
	 */
	private $template_params;

	/**
	 * Handling module name.
	 * @var object
	 */
	public $module;

	/**
	 * Custom tag handlers.
	 * @var array
	 */
	private $handlers = array();

	/**
	 * Tag children overrides.
	 * @var array
	 */
	private $tag_children = array();

	/**
	 * List of tags that shouldn't be closed.
	 * @var array
	 */
	private $tags_without_end = array('br', 'wbr', 'hr', 'img', 'base', 'input', 'link', 'meta');

	/**
	 * Summary list of HTML boolean attributes.
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
	 * Whether template is top level.
	 * @var boolean
	 */
	private $is_top_level = false;

	/**
	 * Type of template document.
	 * @var string
	 */
	private $document_type = 'html5';

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
	 * Storage for last code evaled to help with debugging.
	 * @var string
	 */
	private $last_eval = null;

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
		$this->cache = Cache::get_instance();

		// if file exits then load
		if (!empty($this->file) && file_exists($this->file)) {
			$data = @file_get_contents($this->file);
			$this->engine = new XMLParser($data, $this->file);
			$this->engine->Parse();

			$this->active = true;
		}
	}

	/**
	 * Set top level template indicator flag.
	 *
	 * @param boolean $top_level
	 */
	public function set_top_level($top_level=true) {
		$this->is_top_level = $top_level;
	}

	/**
	 * Restores XML to original state
	 */
	public function restore_xml() {
		if (isset($this->engine))
			$this->engine->Parse();
	}

	/**
	 * Manually set XML
	 * @param string $data
	 */
	public function set_xml($data) {
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
	public function set_local_params($params) {
		$this->params = $params;
	}

	/**
	 * Sets template parameters.
	 *
	 * @param array $params;
	 */
	public function set_template_params($params) {
		$this->template_params = $params;
	}

	/**
 	 * Allows setting template params from tag children array.
	 *
	 * @param array $children
	 */
	public function set_template_params_from_array($children) {
		if (count($children) == 0)
			return;

		// collect params
		$template_params = array();

		foreach ($children as $child)
			if ($child->tagName == 'param')
				$template_params[$child->tagAttrs['name']] = $child->tagAttrs['value'];

		// set params
		$this->set_template_params($template_params);
	}

	/**
	 * Sets mapped module name for section content parsing
	 *
	 * @param string $module
	 */
	public function set_mapped_module($module) {
		if (is_string($module)) {
			if (ModuleHandler::is_loaded($module))
				$this->module = call_user_func(array($module, 'get_instance'));

		} else {
			$this->module = $module;
		}
	}

	/**
	 * Parse loaded template
	 *
	 * @param array $tags Leave blank, used for recursion
	 */
	public function parse(&$tags=null) {
		global $section, $action, $language, $template_path, $system_template_path,
			$images_path, $cache_method, $document_types;

		if ((!$this->active) && is_null($tags))
			return;

		// turn on custom error hanlder
		set_error_handler(array($this, 'handle_error'));

		// take the tag list for parsing
		$tag_array = array();

		if (!is_null($tags)) {
			// assign tags from recursion
			$tag_array = $tags;

		} else if ($this->is_top_level) {
			// allow for special attributes in top-level tag
			$document = $this->engine->document->tagAttrs;

			// print document type
			if (isset($document['type']) && array_key_exists($document['type'], $document_types))
				$this->document_type = $document['type'];

			// set headers and show document code
			$document_type = $document_types[$this->document_type];
			$this->set_headers($document_type['mime']);
			echo $document_type['code'];
		}

		if (empty($tag_array) && $this->active)
			$tag_array = $this->engine->document->tagChildren;

		// start parsing tags
		$count = count($tag_array);
		for ($i=0; $i<$count; $i++) {
			$tag = $tag_array[$i];

			// if tag has eval set
			if (isset($tag->tagAttrs['cms:eval'])) {
				$value = $tag->tagAttrs['cms:eval'];
				$eval_params = explode(',', $value);

				foreach ($eval_params as $param) {
					$to_eval = $tag->tagAttrs[$param];
					$response = $this->get_evaluated_value($to_eval);

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
					$to_eval = $tag->tagAttrs[$param];
					$value = $this->get_evaluated_value($to_eval);

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
					$value = $this->module->get_language_constant($tag->tagAttrs['cms:tooltip']); else
					$value = Language::get_text($tag->tagAttrs['cms:tooltip']);

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
							$tag->tagAttrs[$param] = $this->module->get_language_constant($tag->tagAttrs[$param]); else
							$tag->tagAttrs[$param] = Language::get_text($tag->tagAttrs[$param]);

				unset($tag->tagAttrs['cms:constant']);
			}

			// check if specified tag shouldn't be cached
			$skip_cache = false;

			if (isset($tag->tagAttrs['cms:skip_cache'])) {
				// unset param
				unset($tag->tagAttrs['cms:skip_cache']);

				// only if current URL is being cached, we start dirty area
				if ($this->cache->is_caching()) {
					$this->cache->start_dirty_area();
					$skip_cache = true;

					// reconstruct template for cache,
					// ugly but we are not doing it a lot
					$data = $this->get_data_for_cache($tag);
					$this->cache->set_dirty_area_template($data);
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
					$module_name = $tag->tagAttrs['name'];

					// make sure module is loaded
					if (!ModuleHandler::is_loaded($module_name)) {
						trigger_error('Calling for unknown module "'.$module_name.'".', E_USER_NOTICE);
						break;
					}

					// prepare tag children
					$children = $tag->tagChildren;

					foreach ($tag->tagChildren as $child) {
						if ($child->tagName != 'cms:transfer')
							continue;

						// collect information
						if (isset($child->tagAttrs['name'])) {
							$param_name = $child->tagAttrs['name'];
							$param_value = isset($this->params[$param_name]) ? $this->params[$param_name] : null;

						} else if (isset($child->tagAttrs['template'])) {
							$param_name = $child->tagAttrs['template'];
							$param_value = isset($this->template_params[$param_name]) ? $this->template_params[$param_name] : null;
						}

						// get new tag name
						$tag_name = 'param';
						if (isset($child->tagAttrs['tag']))
							$tag_name = $child->tagAttrs['tag'];

						// prepare attributes
						$target_name = isset($child->tagAttrs['target']) ? $child->tagAttrs['target'] : $param_name;
						$tag_attributes = array(
								'name'  => $target_name,
								'value' => $param_value
							);

						// create new tag
						$children[] = new XMLTag($tag_name, $tag_attributes);
					}

					// transfer control to specified module
					$module = call_user_func(array($module_name, 'get_instance'));
					$module->transfer_control($tag->tagAttrs, $children);
					break;

				// load other template
				case 'cms:template':
					$file = $tag->tagAttrs['file'];
					$path = (key_exists('path', $tag->tagAttrs)) ? $tag->tagAttrs['path'] : '';

					// create new template handler
					$new = new TemplateHandler($file, $path);

					// transfer local params to new template handler
					$new->set_local_params($this->params);
					$new->set_template_params_from_array($tag->tagChildren);

					// parse new template
					$new->parse();
					break;

				// raw text copy
				case 'cms:raw':
					if (key_exists('file', $tag->tagAttrs)) {
						// show content of the file
						$file = $tag->tagAttrs['file'];
						$path = (key_exists('path', $tag->tagAttrs)) ? $tag->tagAttrs['path'] : $template_path;
						$text= file_get_contents($path.$file);

					} elseif (key_exists('text', $tag->tagAttrs)) {
						// show raw text
						$text = $tag->tagAttrs['text'];

					} else {
						// show content of tag
						$text = $tag->tagData;
					}

					echo $text;
					break;

				// embed svg images
				case 'cms:svg':
					$path = _BASEPATH.'/'.$images_path;
					$file = $tag->tagAttrs['file'];
					$symbol = isset($tag->tagAttrs['symbol']) ? $tag->tagAttrs['symbol'] : null;

					if (is_null($symbol)) {
						if ($file[0] != '/')
							$file = $path.$file;

						if (file_exists($file))
							echo file_get_contents($file);

					} else {
						$params = array(
								'url'      => _BASEURL.'/'.$images_path.$file,
								'symbol'   => $symbol,
								'fallback' => $cache_method != CacheType::NONE || !_BROWSER_OK
							);

						if (isset($tag->tagAttrs['class']))
							$params['class'] = $tag->tagAttrs['class'];

						$template = new TemplateHandler('svg_symbol.xml', $system_template_path);
						$template->set_mapped_module($this->module);

						$template->restore_xml();
						$template->set_local_params($params);
						$template->parse();
					}

					break;

				// multi language constants
				case 'cms:text':
					$constant = $tag->tagAttrs['constant'];
					$language = (key_exists('language', $tag->tagAttrs)) ? $tag->tagAttrs['language'] : $language;
					$text = "";

					// check if constant is module based
					if (key_exists('module', $tag->tagAttrs)) {
						if (ModuleHandler::is_loaded($tag->tagAttrs['module'])) {
							$module = call_user_func(array($tag->tagAttrs['module'], 'get_instance'));
							$text = $module->get_language_constant($constant, $language);
						}

					} else {
						// use default language handler
						$text = Language::get_text($constant, $language);
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

					// limit words if specified
					if (!is_null($char_count)) {
						if (is_null($end_with))
							$content = limit_words($content, $char_count); else
							$content = limit_words($content, $char_count, $end_with);
					}

					// convert to HTML
					$content = Markdown::parse($content);

					// strip tags if needed
					if ($clear_text)
						$content = strip_tags($content);

					echo $content;
					break;

				// print multilanguage data
				case 'cms:language_data':
					$name = isset($tag->tagAttrs['param']) ? $tag->tagAttrs['param'] : null;

					if (!isset($this->params[$name]) || !is_array($this->params[$name]) || is_null($name)) break;

					$template = new TemplateHandler('language_data.xml', $system_template_path);
					$template->set_mapped_module($this->module);

					foreach($this->params[$name] as $lang => $data) {
						$params = array(
									'param'		=> $name,
									'language'	=> $lang,
									'data'		=> $data,
								);
						$template->restore_xml();
						$template->set_local_params($params);
						$template->parse();
					}

					break;

				// replace tag data string with matching params
				case 'cms:replace':
					if (isset($tag->tagAttrs['param'])) {
						$keys = explode(',', fix_chars($tag->tagAttrs['param']));
						$pool = array();

						foreach ($keys as $key)
							$pool[$key] = $this->params[$key];

					} else {
					   	$this->params;
					}

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
				case 'cms:if':
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
						$eval_result = $this->get_evaluated_value($to_eval) == true;
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
					$params = $this->params;
					$template = $this->template_params;
					$output = '';

					if (isset($tag->tagAttrs['name'])) {
						// old method with eval
						$to_eval = $tag->tagAttrs['name'];
						$output = $this->get_evaluated_value($to_eval);

					} else if (isset($tag->tagAttrs['param'])) {
						// object parameter
						$param = $tag->tagAttrs['param'];
						$multilanguage = isset($tag->tagAttrs['multilanguage']) ? $tag->tagAttrs['multilanguage'] == 'yes' : false;

						if (isset($params[$param]))
							if (!$multilanguage)
								$output = $params[$param]; else
								$output = $params[$param][$language];

					} else if (isset($tag->tagAttrs['template'])) {
						// template parameter
						$param = $tag->tagAttrs['template'];
						$output = $this->template_params[$param];
					}

					echo $output;
					break;

				// support for script tag
				case 'cms:script':
					if (isset($tag->tagAttrs['local'])) {
						// include local module script
						$script = fix_chars($tag->tagAttrs['local']);
						$path = URL::from_file_path($this->module->path.'include/'.$script);
						echo '<script type="text/javascript" src="'.$path.'"/>';

					} else if (ModuleHandler::is_loaded('head_tag')) {
						// treat script as generic page script and pass it on to head tag
						$head_tag = head_tag::get_instance();
						$head_tag->add_tag('script', $tag->tagAttrs);
					}
					break;

				// support for collection module
				case 'cms:collection':
					if (array_key_exists('include', $tag->tagAttrs) && ModuleHandler::is_loaded('collection')) {
						$scripts = fix_chars(explode(',', $tag->tagAttrs['include']));

						$collection = collection::get_instance();
						$collection->includeScript($scripts);
					}
					break;

				// support for link tag
				case 'cms:link':
					if (ModuleHandler::is_loaded('head_tag')) {
						$head_tag = head_tag::get_instance();
						$head_tag->add_tag('link', $tag->tagAttrs);
					}
					break;

				// automated testing support
				case 'cms:test':
					// don't allow content of this tag to be cached
					if ($this->cache->is_caching()) {
						$this->cache->start_dirty_area();
						$skip_cache = true;

						// reconstruct template for cache,
						// ugly but we are not doing it a lot
						$data = $this->get_data_for_cache($tag);
						$this->cache->set_dirty_area_template($data);
					}

					// select and show version
					$handler = TestingHandler::get_instance();
					$handler->show_version($this, $tag->tagAttrs, $tag->tagChildren);
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
				case 'body':
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
						echo '<'.$tag->tagName.$this->get_tag_params($tag->tagAttrs).'>';

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
				$this->cache->end_dirty_area();
		}

		// restore previous error handler
		restore_error_handler();
	}

	/**
	 * Return formated parameter tags
	 *
	 * @param resource $params
	 */
	private function get_tag_params($params) {
		$result = "";

		if (count($params) == 0)
			return $result;

		foreach ($params as $param=>$value) {
			$is_boolean =
				$param == $value &&
				$this->document_type !== 'xml' &&
				in_array(strtolower($param), $this->boolean_attributes);

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
	private function get_data_for_cache($tag) {
		// open tag
		$result = '<'.$tag->tagName.$this->get_tag_params($tag->tagAttrs).'>';

		// get tag children
		if (count($tag->tagChildren) > 0)
			foreach($tag->tagChildren as $child)
				$result .= $this->get_data_for_cache($child);

		// show tag data
		if (count($tag->tagData) > 0)
			$result .= $tag->tagData;

		// close tag
		$result .= '</'.$tag->tagName.'>';

		return $result;
	}

	/**
	 * Evaluate specified string as PHP code and return value.
	 *
	 * @param string $code
	 * @return mixed
	 */
	private function get_evaluated_value($code) {
		// variables to be used in evaluation
		$params = $this->params;
		$template = $this->template_params;
		$settings = !is_null($this->module) ? $this->module->settings : array();
		$document_type = $this->document_type;

		// construct function call
		$function = '
			$call = function($params, $template, $settings) {
				global $section, $language, $language_rtl;
				return '.$code.';
			}; return $call($params, $template, $settings);';

		// store code to help with debugging
		$this->last_eval = $code;

		return eval($function);
	}

	/**
	 * Set response headers for page templates.
	 *
	 * @param string $document_type
	 */
	private function set_headers($document_type) {
		global $language, $referrer_policy, $frame_options;

		header('X-Powered-By: Caracal/'._VERSION);
		header('Content-Language: '.$language);
		header('Content-Type: '.$document_type.'; charset=UTF-8');

		if ($_SERVER['SERVER_PROTOCOL'] == 'HTTP/1.1') {
			// let the browser/crawler know we have different desktop/mobile styles
			header('Vary: User-Agent');

			// prevent drive-by downloads and site being treated as different type
			header('X-Content-Type-Options: nosniff');

			// prevent site loading from different origins
			header('X-Frame-Options: '.$frame_options);

			// enforce cross-site scripting protection
			header('X-Xss-Protection: 1; mode=block');

			// define content security policy
			header('Content-Security-Policy: '.CSP::get_policy());

			// set referrer policy
			header('Referrer-Policy: '.$referrer_policy);
		}
	}

	/**
	 * Registers handler function for specified tag
	 *
	 * @param string $tag_name Name of a tag to be handled
	 * @param pointer $object Object with specified public method
	 * @param string $function_name Public tag handler method
	 * @example function tagHandler($level, $params, $children)
	 */
	public function register_tag_handler($tag_name, $object, $function_name) {
		$this->handlers[$tag_name] = array(
					'object' 	=> $object,
					'function'	=> $function_name
				);
	}

	/**
	 * Set tag children tags to be overridden.
	 *
	 * @param string $tag_name
	 * @param array $children
	 */
	public function set_tag_children($tag_name, &$children) {
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
	public function handle_error($number, $message, $file=null, $line=null, $context=null) {
		$data = array();

		switch ($number) {
			case E_USER_ERROR:
				$data[] = 'Error: ';
				break;

			case E_WARNING:
			case E_USER_WARNING:
				$data[] = 'Warning: ';
				break;

			case E_NOTICE:
			case E_USER_NOTICE:
				$data[] = 'Notice: ';
				break;
		}

		// include template name
		$data[] = $message.' in template "';
		$data[] = $this->file.'"';

		// log query string as it might help
		$data[] = ' with query string "'.$_SERVER['QUERY_STRING'].'"';

		// if errors is in file include it
		if (!is_null($file))
			$data[] = ' ['.$file.' on line '.$line.']';

		// show last evaluated code
		$data[] = ' last eval -> '.$this->last_eval;

		$text = implode('', $data);
		error_log($text);
		return true;
	}
}

?>
