<?php

/**
 * Code optimizer object is used to compile JavaScript and CSS. By reducting its
 * size, web page responsivness is considerably increased.
 *
 * There's no need to use this class manually as both template handler and head tag
 * module will automatically use this class if configured.
 */

require_once(_LIBPATH.'less/lib/Less/Autoloader.php');
require_once(_LIBPATH.'closure/closure.php');

use Library\Closure\Compiler as Closure;
use Library\Closure\Level as ClosureLevel;


class CodeOptimizer {
	private static $_instance;

	private $script_list = array();
	private $style_list = array();
	private $style_secondary_list = array();  // list populated with @import

	// compilers
	private $less_compiler = null;
	private $closure_compiler = null;

	const LEVEL_NONE = 0;
	const LEVEL_BASIC = 1;
	const LEVEL_ADVANCED = 2;

	/**
	 * Constructor
	 */
	protected function __construct() {
		global $scripts_path;

		$less_options = array(
				'compress'		=> false,
				'relativeUrls'	=> false,
			);
		Less_Autoloader::register();
		$this->less_compiler = new Less_Parser($less_options);

		// configure JavaScript compiler
		$this->closure_compiler = new Closure();
		$this->closure_compiler->set_secure(true);
		$this->closure_compiler->set_level(ClosureLevel::SIMPLE);

		if (file_exists($scripts_path.'externals.js'))
			$this->closure_compiler->set_externals(file_get_contents($scripts_path.'externals.js'));
	}

	/**
	 * Generate name based on list of URL's
	 *
	 * @param array $list
	 * @return string
	 */
	private function getCachedName($list) {
		$all_files = implode($list);
		return md5($all_files);
	}

	/**
	 * Check if cache file needs to be recompiled.
	 *
	 * @param string $file_name
	 * @param array $list
	 * @return boolean
	 */
	private function needsRecompile($file_name, $list) {
		$result = false;

		if (!file_exists($file_name)) {
			$result = true;

		} else {
			$cache_time = filemtime($file_name);
			foreach ($list as $file)
				if (filemtime(URL::to_file_path($file)) > $cache_time) {
					$result = true;
					break;
				}
		}

		return $result;
	}

	/**
	 * Inlude style file.
	 *
	 * @param string $file_name
	 * @param array $priority_commands
	 * @return string
	 */
	private function includeStyle($file_name, &$priority_commands) {
		global $system_module_path, $styles_path, $site_path;

		$result = array();
		$extension = pathinfo($file_name, PATHINFO_EXTENSION);
		$directory_url = dirname(dirname($file_name)).'/';

		// get absolute local path
		if (strpos($file_name, 'http://') === 0 || strpos($file_name, 'https://') === 0 || strpos($file_name, '//') === 0)
			$file_name = URL::to_file_path($file_name);

		switch ($extension) {
			case 'less':
				// compile files
				try {
					$this->less_compiler->parseFile($file_name, _BASEPATH.'/'.$styles_path);
					$data = $this->less_compiler->getCss();

				} catch (Exception $error) {
					trigger_error('Error compiling: '.$file_name.' - '.$error, E_USER_NOTICE);
				}
				break;

			case 'css':
			default:
				$module_directory = _BASEPATH.'/'.$system_module_path;
				$data = file_get_contents($file_name);

				// change path for module urls
				if (substr($file_name, 0, strlen($module_directory)) == $module_directory)
					$data = preg_replace('/url\(.*\.\.\/(.*)\)([;,])/ium', 'url('.$directory_url.'\1)\2', $data);

				break;
		}

		// remove comments
		$data = preg_replace('/\/\*.*?(?=\*\/)\*\//imus', '', $data);

		// fix relative paths
		$data = preg_replace('/url\s*\(\s*(\.\.\/){2,}/imus', 'url(../', $data);
		$data = preg_replace('/url\(..\/([^\)]+)\)/imus', 'url('._BASEURL.'/'.$site_path.'\1)', $data);

		// parse most important
		$data = str_replace("\r", '', $data);
		$data = explode("\n", $data);

		$in_comment = false;

		foreach($data as $line) {
			$line_data = trim($line);
			$command = explode(' ', $line_data);

			// skip empty lines
			if (empty($line_data))
				continue;

			// handle each command individually
			switch (strtolower($command[0])) {
				case '@import':
					if (substr($command[1], 0, 3) == 'url') {
						// add import to the top of the file
						$priority_commands []= $line_data;

					} else {
						// in place import of styles
						$data = $this->includeStyle(trim($command[1], '\'";'), $priority_commands);
						$result = array_merge($result, $data);
					}

					break;

				case '@charset':
					array_unshift($priority_commands, $line_data);
					break;

				default:
					$result []= $line_data;
			}
		}

		return $result;
	}

	/**
	 * Compile styles.
	 *
	 * @param string $file_name
	 * @param array $list
	 */
	private function recompileStyles($file_name, $list) {
		global $cache_path;

		$result = array();
		$priority_commands = array();

		// gather data
		foreach($list as $original_file) {
			$file_result = $this->includeStyle($original_file, $priority_commands);
			$result = array_merge($result, $file_result);
		}

		// insert priority commands at the top of the file
		$result = array_merge($priority_commands, $result);

		// prepare data for last optimization
		$data = implode('', $result);

		// remove units from zero values and remove - if present as there's no such thing as -0
		$data = preg_replace('/([^\d])-?(0+)(px|pt|rem|em|vw|vh|vmax|vmin|cm|mm|m\%)/imus', '\1\2', $data);

		// remove excess spaces around symbols
		// skipping + on purpose to keep calc working
		$data = preg_replace('/\s*([>~:;,\{\}])\s*/imus', '\1', $data);
		$data = preg_replace('/\s*([\(\)])\s*([^\w+-\/\*\^])/imus', '\1\2', $data);
		$data = preg_replace('/([\+])\s*([^\d])/imus', '\1\2', $data);

		// shorten color codes when possible
		$data = preg_replace('/#([\dabcdef])\1([\dabcdef])\2([\dabcdef])\3/imus', '#\1\2\3', $data);

		// remove semicolor before curly brace
		$data = preg_replace('/;\}/imus', '}', $data);

		// save compiled file
		file_put_contents($file_name, $data);

		// generate integrity hash and store it to file
		file_put_contents($file_name.'.sha384', hash_file('sha384', $file_name, true));
	}

	/**
	 * Get a single instance of this object.
	 * @return object
	 */
	public static function get_instance() {
		if (!isset(self::$_instance))
			self::$_instance = new self();

		return self::$_instance;
	}

	/**
	 * Add script to be compiled.
	 *
	 * @param string $url
	 * @return boolean
	 */
	public function addScript($url) {
		global $section;

		$result = false;
		$data = parse_url($url);

		// add script to be compiled
		if ($data['host'] == _DOMAIN) {
			$this->script_list []= $url;
			$this->closure_compiler->add_file(URL::to_file_path($url));
			$result = true;
		}

		return $result;
	}

	/**
	 * Add style to be compiled.
	 *
	 * @param string $url
	 * @return boolean
	 */
	public function addStyle($url) {
		global $module_path;

		$result = false;
		$data = parse_url($url);

		// only add styles that are site specific
		if ($data['host'] == _DOMAIN) {
			$this->style_list []= $url;
			$result = true;
		}

		return $result;
	}

	/**
	 * Return compiled scripts and styles.
	 *
	 * @return string
	 */
	public function printData() {
		global $cache_path, $include_styles;

		// compile styles if needed
		$style_cache = $cache_path.$this->getCachedName($this->style_list).'.css';
		if ($this->needsRecompile($style_cache, $this->style_list))
			$this->recompileStyles($style_cache, $this->style_list);

		// compile scripts
		$script_cache = $cache_path.$this->getCachedName($this->script_list).'.js';
		if ($this->needsRecompile($script_cache, $this->script_list)) {
			$this->closure_compiler->compile_and_save($script_cache);

			// store integrity hash
			file_put_contents($script_cache.'.sha384', hash_file('sha384', $script_cache, true));

			// report script errors
			$error_list = $this->closure_compiler->get_errors();
			if (count($error_list) > 0) {
				$input_list = $this->closure_compiler->get_input_list();

				foreach ($error_list as $error) {
					$file = $input_list[$error->file];
					$message = "JavaScript compile error in {$file} on line {$error->lineno}: {$error->error}";
					trigger_error($message, E_USER_NOTICE);
				}
			}
		}

		// include styles in page or as outside resource
		$integrity = '';
		if (file_exists($style_cache.'.sha384'))
			$integrity = ' integrity="sha384-'.base64_encode(file_get_contents($style_cache.'.sha384')).'"';

		if (!$include_styles)
			print '<link type="text/css" rel="stylesheet" href="'._BASEURL.'/'.$style_cache.'"'.$integrity.'>'; else
			print '<style type="text/css">'.file_get_contents($style_cache).'</style>';

		// show javascript tags
		$integrity = '';
		if (file_exists($script_cache.'.sha384'))
			$integrity = ' integrity="sha384-'.base64_encode(file_get_contents($script_cache.'.sha384')).'"';

		print '<script type="text/javascript" async src="'._BASEURL.'/'.$script_cache.'"'.$integrity.'></script>';
	}
}

?>
