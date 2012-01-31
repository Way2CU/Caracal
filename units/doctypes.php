<?php

/**
 * This file contains doctype definitions used in templates and preliminary
 * configuration of template handlers.
 */

define('_HTML_401', '<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">');
define('_HTML_5', '<!DOCTYPE html>');
define('_XML_1', '<?xml version="1.0" encoding="UTF-8"?>');

switch (_STANDARD) {
	case 'html5':
		define(_DOCTYPE, _HTML_5);
		break;

	case 'xml':
		define(_DOCTYPE, _XML_1);
		break;

	case 'html401':
	default:
		define(_DOCTYPE, _HTML_401);
		break;
}


?>
