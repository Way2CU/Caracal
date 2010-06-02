<?php

/**
 * SEARCH MODULE
 * 
 * @author MeanEYE
 * @copyright RCF Group,2008.
 */
 
class search extends Module {

	/**
	 * Constructor
	 *
	 * @return journal
	 */
	function search() {
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
			case 'get_results':
				$this->getResults($level, $params);
				break;
		}

	}

	/**
	 * Get search results
	 *
	 * @param integer $level
	 * @param array $params
	 */
	function getResults($level, $params) {
		global $ModuleHandler, $db;
		
		// prepare text variables
		$text = fix_chars($_REQUEST['query']);
		$results = array();
		
		// go through each module and call getSearchResults
		foreach($ModuleHandler->modules as $name => $array) {
			$module = $array['object'];
			
			// get the result if modules suports it 
			if (method_exists($module, 'getSearchResults'))
				$results = array_merge($results, $module->getSearchResults($text));
		}
		$results = $this->sortArray($results);

		if (count($results) > 0) {
			$template_file = (isset($global_params['template'])) ? $global_params['template'] : "item_result.xml";
			$template = new TemplateHandler($template_file, $this->path.'templates/'); 
			$template->setMappedModule($this->name);
			
			for ($i=0; $i<count($results); $i++) {
				$result = $results[$i];
				$result['number'] = $i+1;
				/*
					$result = array('score','link','title','description');
				*/
				$template->restoreXML();
				$template->setLocalParams($result);
				$template->parse($level);
			}
		} else echo $this->language->getText('message_no_results');
	}
	
	/**
	 * Sorts results by score
	 *
	 * @param array $array
	 * @param string $gid
	 * @param boolean $ascending
	 * @return array
	 */
	function sortArray($array, $gid="score", $ascending=false) {
	        $temp_array = array();
	        
	        while (count($array) > 0) {
	            $index = 0;
	            $lowest_id = 0;
	            
	            foreach ($array as $item) {
	                if (isset($item[$gid]) && 
	                	$array[$lowest_id][$gid] && 
	                	$item[$gid]<$array[$lowest_id][$gid]) $lowest_id = $index;
	                $index++;
	            }
	            
	            $temp_array[] = $array[$lowest_id];
	            $array = array_merge( array_slice($array, 0, $lowest_id), array_slice($array, $lowest_id+1) );
	        }
	        
		if (!$ascending) $temp_array = array_reverse($temp_array);
	
		return $temp_array;
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
/*
		if ($ModuleHandler->moduleExists('head_tag')) {
			$head_tag = $ModuleHandler->getObjectFromName('head_tag');
			$head_tag->addTag('link', array('href'=>url_GetFromFilePath($this->path.'include/search.css'), 'rel'=>'stylesheet', 'type'=>'text/css'));
			$head_tag->addTag('script', array('src'=>url_GetFromFilePath($this->path.'include/_blank.js'), 'type'=>'text/javascript'));
		}
*/
	}	
}
