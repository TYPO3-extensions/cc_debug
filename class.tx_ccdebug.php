<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2003 René Fritz (r.fritz@colorcube.de)
*  All rights reserved
*
*  This script is part of the Typo3 project. The Typo3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/
/**
 *
 * This Script adapts the errorReporter from Dan Allen to TYPO3
 *
 *
 * usage
 *
 * require_once(t3lib_extMgm::extPath('cc_debug').'class.tx_ccdebug.php');
 *
 *
 *
 * $error->debug($array, 'array', __LINE__, __FILE__);
 * $error->debug($string, 'string', __LINE__, __FILE__);
 *
 * OR
 *
 * debug($array, 'array', __LINE__, __FILE__);
 * debug($string, 'string', __LINE__, __FILE__);
 * debug($string, 'string', __LINE__);
 * debug($string, 'string');
 * debug($string);
 *
 *
 *
 *
 * @author	René Fritz <r.fritz@colorcube.de>
 * @author	Dan Allen, http://mojavelinux.com/
 * @author	Luite van Zelst <luite@aegee.org>
 */



// constants

define('E_USER_ALL',	E_USER_NOTICE | E_USER_WARNING | E_USER_ERROR);
define('E_NOTICE_ALL',	E_NOTICE | E_USER_NOTICE);
define('E_WARNING_ALL',	E_WARNING | E_USER_WARNING | E_CORE_WARNING | E_COMPILE_WARNING);
define('E_ERROR_ALL',	E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR);
define('E_ALL_NOT_NOTICE',	E_ALL & ~E_NOTICE_ALL);
define('E_DEBUG',		0x10000000);
define('E_VERY_ALL',	E_ERROR_ALL | E_WARNING_ALL | E_NOTICE_ALL | E_DEBUG);

define('SYSTEM_LOG',	0);
define('TCP_LOG',		2);
define('MAIL_LOG',		1);
define('FILE_LOG',		3);




// helper functions

class tx_ccdebug_div {

	/***
	* Run a loop on an iterator and a manipulator and return the number of
	* items processed.
	* @param $iterator the <code>Iterator</code> to run the loop on
	* @param $manipulator the <code>LoopManipulator</code> to use
	* @returns int
	* @static
	***/
	static function runLoop(&$iterator, &$manipulator)
	{
		$index = 0;
		$iterator->reset();
		if ($iterator->isValid())	{
			$manipulator->prepare();
		}
		for ( ; $iterator->isValid(); $iterator->next())	{
			$current =& $iterator->getCurrent();
			if ($index)	{
				$manipulator->between($index);
			}
			$manipulator->current($current, $index++);
		}
		if ($index)	{
			$manipulator->finish($index);
		}
		return $index;
	}

	function strrpos2($string, $needle, $offset = 0){
		$addLen = strlen($needle);
		$endPos = $offset - $addLen;

		while (1)
		{
			if (($newPos = strpos($string, $needle, $endPos + $addLen)) === false) {
				break;
			}
			$endPos = $newPos;
		}
		return ($endPos >= 0) ? $endPos : false;
	}

	function addPhpTags($source){
		$startTag  = '<'.'?php';
		$endTag = '?'.'>';

		$firstStartPos  = ($pos = strpos($source, $startTag)) !== false ? $pos : -1;
		$firstEndPos = ($pos = strpos($source, $endTag)) !== false ? $pos : -1;

		// no tags found then it must be solid php since html can't throw a php error
		if ($firstStartPos < 0 && $firstEndPos < 0)
		{
			return $startTag . "\n" . $source . "\n" . $endTag;
		}

		// found an end tag first, so we are missing a start tag
		if ($firstEndPos >= 0 && ($firstStartPos < 0 || $firstStartPos > $firstEndPos))
		{
			$source = $startTag . "\n" . $source;
		}

		$sourceLength = strlen($source);
		$lastStartPos  = ($pos = tx_ccdebug_div::strrpos2($source, $startTag)) !== false ? $pos : $sourceLength + 1;
		$lastEndPos  = ($pos = tx_ccdebug_div::strrpos2($source, $endTag)) !== false ? $pos : $sourceLength + 1;

		if ($lastEndPos < $lastStartPos || ($lastEndPos > $lastStartPos && $lastEndPos > $sourceLength))
		{
			$source .= $endTag;
		}

		return $source;
	}

	function removePhpTags ($source) {
		return preg_replace(':(&lt;\?php(<br />)*|\?&gt;):', '', $source);
	}


	/**
	* DebugVar for PHP / Typo3 Development.
	*
	* @author	Luite van Zelst <luite@aegee.org>
	* @link	http://www.xinix.dnsalias.net/fileadmin/t3dev/debugvar.php.txt
	*
	* @access	public
	* @version	1.0
	*
	* @param	mixed	$var	The variable you want to debug. It may be one of these: object, array, boolean, int, float, string
	* @param	string	$name	Name of the variable you are debugging. Usefull to distinguish different debugvar() calls.
	* @param	int		$level	The number of recursive levels to debug. With nested arrays/objects it's the safest thing
	* @internal 				Don't use the recursive param yourself - you'll end up with incomplete tables!
	* @return	string			Returns ready debug output in html-format. Uses nested tables, unfortunately.
	*/
	function &debugvar($var, $name = '', $level = 3, $recursive = false) {
		$style[0] = 'font-size:10px;font-family:verdana,arial;border-collapse:collapse;background:#E7EEEE;';
		$style[1] = 'border-width:1px;border-style:dotted; border-color:#A0AEB0;border-right-style:dotted;';
		$style[2] = 'border-width:1px;border-style:dotted; border-color:#A0AEB0;border-right-style:dotted;border-left-style:dotted;';
		$style[3] = 'border-width:1px;border-style:dotted; border-color:#A0AEB0;border-left-style:dotted;';
		if (@is_null($var)) {
			$type = 'Mixed';
			$var = 'NULL';
			$style[3] .= 'color:red;font-style:italic;';
		} else if(@is_array($var)) {
			$type = 'Array';
			$len = '&nbsp;('. sizeof($var) .')';
			if($level > -1) {
				$multiple = true;
				while(list($key, $val) = each($var)) {
					$line .= tx_ccdebug_div::debugvar($val, $key, $level - 1, true);
				}
				$var = sprintf("<table style=\"%s\">\n%s\n</table >\n",
					$style[0],
					$line
				);
			} else {
				$var = 'Array not debugged. Set higher "level" if you want to debug this.';
				$style[3] .= 'color:red;font-style:italic;';
			}
			$style[1] .= 'color:grey;font-face:bold;';
			$style[2] .= 'color:grey;font-face:bold;';
			$style[3].= 'padding:0px;';
		} else if(@is_object($var)) {
			$type = @get_class($var);// . '&nbsp;(extends&nbsp;' . @get_parent_class($var) . ')&nbsp;';
			$style[1] .= 'color:purple;';
			$style[3] .= 'color:purple;';
			if($level > -1) {
				$multiple = true;
				$vars = (array) @get_object_vars($var);
				while(list($key, $val) = each($vars)) {
					$line .= tx_ccdebug_div::debugvar($val, $key, $level -1, true);
				}
				$methods = (array) @get_class_methods($var);
				while(list($key, $val) = each($methods)) {
					$line .= sprintf("<tr ><td style=\"%s\">Method</td ><td colspan=\"2\" style=\"%s\">%s</td ></tr >",
						$style[1],
						$style[3],
						$val . '&nbsp;(&nbsp;)'
					);
				}
				$var = sprintf("<table style=\"%s\">\n%s\n</table >\n",
					$style[0],
					$line
				);
				$len = '&nbsp;('. sizeof($vars) . '&nbsp;+&nbsp;' . sizeof($methods) .')';
			} else {
				$var = 'Object not debugged. Set higher "level" if you want to debug this.';
				$style[3] .= 'color:red;font-style:italic;';
			}
			$style[3].= 'padding:0px;';
		} else if(@is_bool($var)) {
			$type = 'Boolean';
			$style[1] .= 'color:#906;';
			$style[2] .= 'color:#906;';
			if(!$var) $style[3] .= 'color:red;';
			if($var == 0) $var = 'FALSE';
		} else if(@is_float($var)) {
			$type = 'Float';
			$style[1] .= 'color:#066;';
			$style[2] .= 'color:#066;';
		} else if(@is_int($var)) {
			$type = 'Integer';
			$style[1] .= 'color:green;';
			$style[2] .= 'color:green;';
		} else if(@is_string($var)) {
			$type = 'String';
			$style[1] .= 'color:darkblue;';
			$style[2] .= 'color:darkblue;';
			$var = nl2br(@htmlspecialchars($var));
			$len = '&nbsp;('.strlen($var).')';
			if($var == '') $var = '&nbsp;';
		} else {
			$type = 'Unknown!';
			$style[1] .= 'color:red;';
			$style[2] .= 'color:red;';
			$var = @htmlspecialchars($var);
		}
		if(! $recursive) {
			if($name == '') {
				$name = '(no name given)';
				$style[2] .= 'font-style:italic;';
			}
			$style[2] .= 'color:red;';

			if($multiple) {
				$html = "<table cellpadding=1 style=\"%s\">\n<tr >\n<td width=\"0\" style=\"%s\">%s</td ></tr ><tr >\n<td style=\"%s\">%s</td>\n</tr >\n<tr >\n <td colspan=\"2\" style=\"%s\">%s</td>\n</tr >\n</table >\n";
			} else {
				$html = "<table cellpadding=1 style=\"%s\">\n<tr >\n<td style=\"%s\">%s</td>\n<td style=\"%s\">%s</td ><td style=\"%s\">%s</td >\n</tr >\n</table>\n";
			}
			return sprintf($html, $style[0],
				$style[1], $type . $len,
				$style[2], $name, 
				$style[3], $var
			);
		} else {
			return 	sprintf("<tr >\n<td style=\"%s\">\n%s\n</td >\n<td style=\"%s\">%s</td >\n<td style=\"%s\">\n%s\n</td ></tr >",
						$style[1],
						$type . $len,
						$style[2],
						$name,
						$style[3],
						$var
					);
		}
	}

}











class tx_ccdebug_ErrorList {

	var $elementData;

	// __constructor()
	function tx_ccdebug_ErrorList(&$reporter, $variableName = 'error', $setErrorHandler=false)	{

		// :NOTE: dallen 2003/01/31 it might be a good idea to keep this on
		// if the console is used since some cases don't stop E_ERROR
#		ini_set('display_errors', false);

	    $this->elementData = array();
		$this->reporter =& $reporter;

		// trick to fix broken set_error_handler() function in php
		$GLOBALS[$variableName] =& $this;
		trapError($variableName);
		if ($setErrorHandler) {
			set_error_handler('trapError');
		}
		
		register_shutdown_function(array(&$this, '__destructor'));
	}
	

	// __destructor()
	function __destructor()	{
		error_reporting(E_ALL ^ E_NOTICE);
		tx_ccdebug_div::runLoop(new tx_ccdebug_ErrorIterator($this), $this->reporter);
	}

	function debugEnd()	{
#		$this->__destructor();
	}


#	function add(&$error)	{
	function add($error)	{

		// rearrange for eval'd code or create function errors
		$error['line'] = intval($error['line']);
		if (preg_match(';^(.*?)\((\d+)\) : (.*?)$;', $error['file'], $matches))	{
			$error['message'] .= $error['line'] ? ' on line ' . $error['line'] : '';
			$error['message'] .= ' in ' . $matches[3];
			$error['file'] = $matches[1];
			$error['line'] = $matches[2];
		}
		if ($error['line']) {
			$error['context'] = $this->_getContext($error['file'], $error['line']);
		}

		$this->elementData[] = $error;
	}

	function &get($index)	{
		return $this->elementData[$index];
	}

	function &set($index, &$o)	{
		$item =& $this->elementData[$index];
		$this->elementData[$index] =& $o;
		return $item;
	}

	function size()	{
		return count($this->elementData);
	}

	function clear()	{
		$this->elementData = array();
	}

	function &remove($index)	{
		$item =& $this->elementData[$index];
		unset($this->elementData[$index]);
		$this->elementData = array_values($this->elementData);
		return $item;
	}

	function indexOf(&$o)	{
		$index = array_search($o, $this->elementData, true);
		if (is_int($index))		{
			return $index;
		}
		return -1;
	}

	

    function debug($variable, $name='*variable*', $line='*line*', $file='*file*', $recursiveDepth=3, $debugLevel=E_DEBUG)	{
		$line = intval($line);;
		$error = array(
			'level'		=> intval($debugLevel),
			'message'	=> 'user variable debug',
			'file'		=> $file,
			'line'		=> $line,
			'variables' => array($name => $variable),
			'signature'	=> mt_rand(),
			'depth'	=> $recursiveDepth,
		);
		$this->add($error);
	}



	function _getContext($file, $line)	{
		if ($line==0 OR !$this->reporter->contextLines OR !@is_readable($file)) {
		    return array(
		    	'start'		=> 0,
		    	'end'		=> 0,
		    	'source'	=> '',
		    	'variables'	=> array(),
		    );
        }

		$sourceLines = file($file);
		$offset = max($line - 1 - $this->reporter->contextLines, 0);
		$numLines = 2 * $this->reporter->contextLines + 1;
		$sourceLines = array_slice($sourceLines, $offset, $numLines);
		$numLines = count($sourceLines);
		// add line numbers
		foreach ($sourceLines as $index => $line)	{
			$sourceLines[$index] = ($offset + $index + 1)  . ': ' . $line;
		}

		$source = tx_ccdebug_div::addPhpTags(join('', $sourceLines));
		preg_match_all(';\$([[:alnum:]]+);', $source, $matches);
		$variables = array_values(array_unique($matches[1]));
		return array(
			'start'		=> $offset + 1,
			'end'		=> $offset + $numLines,
			'source'	=> $source,
			'variables'	=> $variables,
		);
	}

}






class tx_ccdebug_ErrorIterator {

	var $errorList;
	var $index;

	
	// __constructor()
	function tx_ccdebug_ErrorIterator(&$errorList)	{
		$this->errorList =& $errorList;
		$this->reset();
	}

	function reset()	{
		$this->index = 0;
	}

	function next()	{
		$this->index++;
	}

	function isValid()	{
		return ($this->index < $this->errorList->size());
	}

	function &getCurrent()	{
		return $this->errorList->get($this->index);
	}
}







class tx_ccdebug_ErrorReporter {

	var $errorList;
	var $reports;
	var $contextLines;
	var $contextLevel;
	var $strictContext;
	var $dateFormat;
	var $classExcludeList;
    var $excludeObjects;

	
	// __constructor
	function tx_ccdebug_ErrorReporter()	{
		$this->errorList = array();
		$this->reports = array(
			'mail'		=> array('level' => 0, 'data' => null),
			'file'		=> array('level' => 0, 'data' => null),
			'console'	=> array('level' => 0, 'data' => null),
			'stdout'	=> array('level' => 0, 'data' => null),
			'system'	=> array('level' => 0, 'data' => null),
			'redirect'  => array('level' => 0, 'data' => null),
			'browser'   => array('level' => 0, 'data' => null),
		);

		$this->contextLines = 3;
		$this->contextLevel = (E_ERROR_ALL | E_WARNING_ALL);
		$this->strictContext = true;
		$this->classExcludeList = array();
		$this->excludeObjects = true;
	}

	function setDateFormat($format)	{
		$this->dateFormat = $format;
	}


	function setContextLines($lines)	{
		$this->contextLines = intval($lines);
	}


	function setContextLevel($level)	{
		$this->contextLevel = $level;
	}

	function setStrictContext($boolean)	{
		$this->strictContext = $boolean ? true : false;
	}


	function setExcludeObjects()    {
		if (gettype(func_get_arg(0)) == 'boolean') {
			$this->excludeObjects = func_get_arg(0);
		} else {
			$list = func_get_args();
			$this->classExcludeList = array_map('strtolower', $list);
		}
	}

	function addReporter($reporter, $level, $data = null) {
		$this->reports[$reporter] = array(
			'level'	=> $level,
			'data'	=> $data
		); 
	}


#	function getMessage(&$error)	{
	function getMessage(&$error)	{
		$message = '<div style="display: none;">' . date($this->dateFormat) . ' </div>';

		if ($error['level'] & E_ERROR_ALL)	{
			$message .= '<span class="errorLevel">[error]</span>';
		} else if ($error['level'] & E_WARNING_ALL)	{
			$message .= '<span class="errorLevel">[warning]</span>';
		} else if ($error['level'] & E_NOTICE_ALL)	{
			$message .= '<span class="errorLevel">[notice]</span>';
		} else if ($error['level'] & E_DEBUG)	{
			$message .= '<span class="errorLevel">[debug]</span>';
		} else if ($error['level'] & E_LOG)	{
			$message .= '<span class="errorLevel">[log]</span>';
		} else {
			$message .= '<span class="errorLevel">[unknown]</span>';
		}

		$message .= ' in ' . $error['file'];
		$message .= $error['line'] ? ' on line <span style="text-decoration: underline; padding-bottom: 1px; border-bottom: 1px solid black;">' . $error['line'] . '</span>' : '';
		$message .= ' <div class="errorMessage">' . $error['message'] . '</div>' . "\n";
		return $message;
	}

	function prepare()	{
	}

#	function current(&$error, $index)	{
	function current($error, $index)	{
		$message = $this->getMessage($error);

		// syslog
		if ($this->reports['system']['level'] & $error['level'])	{
			@error_log(strip_tags($message), SYSTEM_LOG);
		}

		// file
		if ($this->reports['file']['level'] & $error['level'])	{
			@error_log(strip_tags($message), FILE_LOG, $this->reports['file']['data']);
		}

		// email
		if ($this->reports['mail']['level'] & $error['level'])	{
			@error_log(strip_tags($message), MAIL_LOG, $this->reports['mail']['data']);
		}
		
		// redirect
		if ($this->reports['redirect']['level'] & $error['level'])	{
			echo '<script type="text/javascript">window.location.href = \'' . $this->reports['redirect']['data'] . '\';</script>';
			exit;
		}

		// stdout
		if ($this->reports['stdout']['level'] & $error['level'])	{
			echo strip_tags($message);
		}

		// browser
		if ($this->reports['browser']['level'] & $error['level'])	{
			echo $message;
		}

		// console
		if ($this->reports['console']['level'] & $error['level'])	{
			// :NOTE: dallen 2003/02/01 perhaps we can add an addition data option for error
			// level which includes source and variable context
			// :BUG: dallen 2003/02/03 this should be a class specification
			$output = $message;
			if ($error['context']['source'] AND $error['level'] & $this->contextLevel) {
				$output .= '<div style="margin: 5px 5px 0 5px; font-family: sans-serif; font-size: 10px;">==> Source report from ' . $error['file'] . ' around line ' . $error['line'] . ' (' . $error['context']['start'] . '-' . $error['context']['end'] . ')</div><div style="margin: 0 5px 5px 5px; background-color: #EEEEEE; border: 1px dotted #B0B0B0;">' . str_replace('  ', '&nbsp; ', str_replace('&nbsp;', ' ', tx_ccdebug_div::removePhpTags(highlight_string($error['context']['source'], true)))) . '</div>';
			}
			$variables = $this->_exportVariables($error['variables'], $error['context']['variables'], $error['depth']);
			if ($variables !== false)	{

				// :BUG: dallen 2003/02/03 this should be a class specification
				$output .= '<div style="margin: 5px 5px 0 5px; font-family: sans-serif; font-size: 10px;">==> Variable scope report</div><div style="margin: 0px 5px 5px 5px;">' . $variables . '</div>';
			}
			
			$this->errorList[$index + 1] = strtr($output, array("\t" => '\\t', "\n" => '\\n', "\r" => '\\r', '\\' => '&#092;', "'" => '&#39;'));
		}
	}

	function between()	{
	}

	function finish()	{
		$errors =& $this->errorList;
		include 'error.tpl.php';
	}

	function _exportVariables(&$variables, $contextVariables, $depth)	{
		$variableString = '';
		foreach ($variables as $name => $contents)		{
			// if we are using strict context and this variable is not in the context, skip it
			if ($this->strictContext && !in_array($name, $contextVariables))			{
				continue;
			}

			// if this is an object and the class is in the exclude list, skip it
			if (is_object($contents) && in_array(get_class($contents), $this->classExcludeList))			{
				continue;
			}

			$variableString .= tx_ccdebug_div::debugvar($contents, $name, $depth?$depth:3);
		}

		if (empty($variableString))		{
			return false;
		} else {
			return "\n" . $variableString;
		}
	}

	
}




function trapError() {
	static $variable, $signatures = array();

	if (!isset($prependString) || !isset($appendString))	{
		$prependString = ini_get('error_prepend_string');
		$appendString = ini_get('error_append_string');
	}

	// error event has been caught
	if (func_num_args() == 5)	{

		// return on silenced error (using @)
		if (error_reporting() == 0)	{
			return;
		}

		$args = func_get_args();

		// return on not fitting errors depending on the global error level
		if (error_reporting() & !$args[0])	{
			return;
		}
		// I don't like 'Undefined ...' messages even if error level includes them
		if (preg_match('/^Undefined index:/', $args[1]) OR preg_match('/^Undefined offset:/', $args[1]) OR preg_match('/^Undefined variable:/', $args[1])) {
			return;
		}

		// weed out duplicate errors (coming from same line and file)
		$signature = md5($args[1] . ':' . $args[2] . ':' . $args[3]);
		if (isset($signatures[$signature]))	{
			return;
		} else {
			$signatures[$signature] = true;
		}

		// cut out the fat from the variable context (we get back a lot of junk)
#		$variables =& $args[4];
		$variables = $args[4];
		$variablesFiltered = array();
        $excludeObjects = $GLOBALS[$variable]->reporter->excludeObjects;
		foreach (array_keys($variables) as $variableName)	{
			// these are server variables most likely
			if ($variableName == strtoupper($variableName))	{
				continue;
			} elseif ($variableName{0} == '_')	{
				continue;
			} elseif ($variableName == 'argv' || $variableName == 'argc')	{
				continue;
			} elseif ($excludeObjects && gettype($variables[$variableName]) == 'object')          {
                continue;

			// don't allow instance of errorstack to come through
			} elseif (is_a($variables[$variableName], 'tx_ccdebug_ErrorList') ||
					is_a($variables[$variableName], 'tx_ccdebug_ErrorReporter'))	{
				continue;
			}
			
			// :WARNING: dallen 2003/01/31 This could lead to a memory leak,
			// maybe only copy up to a certain size
			// make a copy to preserver the state at time of error
			$variablesFiltered[$variableName] = $variables[$variableName];
		}

		$error = array(
			'level'		=> $args[0],
			'message'	=> $prependString . $args[1] . $appendString,
			'file'		=> $args[2],
			'line'		=> $args[3],
			'variables'	=> $variablesFiltered,
			'signature'	=> $signature,
		);

		$GLOBALS[$variable]->add($error);
	} elseif (func_num_args() == 1)	{
			// if only one arg is passed it's the name of the reporter object
		$variable = func_get_arg(0);
	} else {
		return $variable;
	}
}

	// is_a(): PHP 4 >= 4.2.0
if (!function_exists('is_a')) {
	function is_a($object, $className) {
		return ((strtolower($className) == get_class($object))
			or (is_subclass_of($object, $className)));
	}
}


// Debug function
// replace the debug() function in t3lib/config_default.php with this one
/*
function debug(&$variable, $name='*variable*', $line='*line*', $file='*file*', $recursiveDepth=3, $debugLevel=E_DEBUG){
		// If you wish to use the debug()-function, and it does not output something, please edit the IP mask in TYPO3_CONF_VARS
	if (!t3lib_div::cmpIP(t3lib_div::getIndpEnv("REMOTE_ADDR"), $GLOBALS["TYPO3_CONF_VARS"]["SYS"]["devIPmask"]))	return;

	if(@is_callable(array($GLOBALS['error'],'debug'))) {
		$GLOBALS['error']->debug($variable, $name, $line, $file, $recursiveDepth, $debugLevel);
	} else {
		$name = ($name == '*variable*') ? 0 : $name;
		t3lib_div::debug($variable, $name);
	}
}
*/

#function debugMsg ($variable, $name = '*message*', $line = '*line*', $file = '*file*', $level = E_DEBUG)	{
#	debug ($variable, $name, $line, $file, $level);
#}

function tx_ccdebugInit() {
	$GLOBALS['errorReporter'] =& new tx_ccdebug_ErrorReporter();
	$GLOBALS['errorReporter']->setDateFormat('[Y-m-d H:i:s]');
	$GLOBALS['errorReporter']->setStrictContext(false);
	$GLOBALS['errorReporter']->setContextLevel(E_ALL_NOT_NOTICE & !E_DEBUG);
	$GLOBALS['errorReporter']->setExcludeObjects(true);
	$GLOBALS['errorReporter']->addReporter('console', E_ALL_NOT_NOTICE | E_DEBUG);

	$GLOBALS['errorList'] =& new tx_ccdebug_ErrorList($GLOBALS['errorReporter'], 'error', false); 
	// set 'true' means set_error_handler('trapError') will be used. But that causes strange effects in PHP
}
/*
function debugBegin() {
	if(@is_callable(array($GLOBALS['error'],'debugBegin'))) {
		$GLOBALS['error']->debugBegin();
	}
}

function debugEnd() {
	if(@is_callable(array($GLOBALS['error'],'debugEnd'))) {
		$GLOBALS['error']->debugEnd();
	}
}
*/

tx_ccdebugInit();



?>
