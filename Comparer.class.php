<?php
 
/*****************
 * COMPARER class V1.20
 *****************
 *   This class compares the structure and content of two elements.
 *
 *   @author David Pauli
 *
 *   How does it work
 *   ****************
 *      1. load both elements into the comparer
 *      2. generate a structure of these both elements
 *      3. compare each element with these structure
 *      4. return the differences
 *****************/
 
class Comparer {
 
	// parser-classes
	private $plistParser;
	private $yamlParser;
 
	private $arrayHandler;			// handles important functions for array operations
 
	private $elements	= array();	// space to save loaded elements
	private $names		= array();	// name of the loaded elements
	private $types		= array();	// original type of the loaded elements (plist/yaml)
 
	private $structure	= array();	// array for generated / loaded structure
	private $comparison	= array();	// array for compare result
 
	private $env 		= array();	// save settings / environment variables
 
	function __construct() {
 
		$this->plistParser	= new pListParser();
		$this->yamlParser	= new Spyc();
		$this->arrayHandler	= new ArrayHandler();
 
		$this->env['strictMode']	= false;	// while checking value, also check type (!==) if set on true
		$this->env['placeHolder']['dv']	= "dv";		// placeholder for different value entry
		$this->env['placeHolder']['dt']	= "dt";		// placeholder for different type entry
		$this->env['placeHolder']['ns']	= "ns";		// placeholder for not set entry
	}
 
	/****************
	 * loadElement()
	 ****************
	 *   loads input element into class
	 *
	 *   @param
	 *      string source:		path to file or complete content
	 *	string sourceType:	type of source-string ('file' or 'text')
	 *	string contentType:	type of content ('plist' or 'yaml')
	 *	string name:		internal used name of element
	 */
	function loadElement($source="", $sourceType="", $contentType="", $name="") {
 
		// validate input
		if($source=="" || $sourceType=="" || $contentType=="" || $name=="") $this->e(1);	// all parameters are set?
		if(is_int(array_search($name, $this->names))) $this->e(2);				// <name> already given?
 
		// handle parameter <sourceType>
		// is want to open a (uploaded) file
		if($this->isSimilar($sourceType,"file")) {
 
			if(!is_file($source)) $this->e(3);		// <source> is not a file
 
			// open file
			$resource = fopen($source,'r');
			if(!$resource) $this->e(4);			// cant open <source>
 
			// read file
			$content = fread($resource,filesize($source));
			if(!$content) $this->e(5);			// unknown problem while open <source>
 
			// close file
			fclose($resource);
		}
		else if($this->isSimilar($sourceType,"text")) $content = $source;	// if paramter is like "text"
		else $this->e(6);							// parameter <sourceType> is wrong
 
		// convert data with extern classes to xml
		// if data is in plist format
		if($this->isSimilar($contentType, "plist")) {
 
			$data = $this->plistParser->parseString($content);
			array_push($this->elements,$data);
			array_push($this->names,$name);
			array_push($this->types,"plist");
		}
		// if data is in yaml format
		else if($this->isSimilar($contentType, "yaml")) {
 
			$data = $this->yamlParser->load($content);
			array_push($this->elements,$data);
			array_push($this->names,$name);
			array_push($this->types,"yaml");
		}
		else $this->e(7);					// unknown <contentType>
	}
 
	/**********************
	 * generateStructure()
	 **********************
	 *   generates a structure of two elements
	 *
	 *   @param
	 *      string element1:	name of element 1
	 *	string element2:	name of element 2
	 */
	function generateStructure($element1="", $element2="") {
 
		if($element1=="" || $element2=="") $this->e(11);			// need 2 parameters
 
		// get ID of the element
		$id1 = array_search($element1, $this->names);
		$id2 = array_search($element2, $this->names);
 
		if(!is_Int($id1) || !is_Int($id2)) $this->e(8);	// can't find elements
 
		// merge elements recursive
		$this->structure = $this->arrayHandler->mergeRecursive($this->elements[$id1], $this->elements[$id2]);
 
		// clean (only need keys, not the values) and sort structure
		$this->structure = $this->arrayHandler->clean($this->structure);
		$this->structure = $this->arrayHandler->ksortRecursive($this->structure);
	}
 
	/************
	 * compare()
	 ************
	 *   compares two elements with structure
	 *
	 *   @param
	 *      string element1:	name of element 1
	 *	string element2:	name of element 2
	 *
	 *   @result
	 *      array compare:	result of comparison
	 */
	function compare($element1="", $element2="") {
 
		if($element1=="" || $element2=="") $this->ve(13);			// need 2 parameters
 
		// get the IDs of the elements
		$id1 = array_search($element1, $this->names);
		$id2 = array_search($element2, $this->names);
 
		if(!is_Int($id1) || !is_Int($id2)) $this->e(9);			// can't find elements
 
		if(empty($this->structure)) $this->e(14);			// structure not set yet
 
		// add standard definitions to result
		$this->comparison['_configuration'][0]['name'] = $this->names[$id1];	// save given name
		$this->comparison['_configuration'][1]['name'] = $this->names[$id2];
		$this->comparison['_configuration'][0]['type'] = $this->types[$id1];	// save given type
		$this->comparison['_configuration'][1]['type'] = $this->types[$id2];
		$this->comparison['_structure'] = $this->structure;			// save complete structure
		$this->comparison['_elements'][0] = $this->elements[0];			// save both elements
		$this->comparison['_elements'][1] = $this->elements[1];
		$this->comparison['_environment'] = $this->env;				// save environment variables
		$this->comparison['_difference'] = array();				// save difference
 
		// start recursive part of comparison, add results step by step
		$this->compareRecursive($this->elements[$id1], $this->elements[$id2]);
		return $this->comparison;
	}
 
	/*********************
	 * compareRecursive()
	 *********************
	 *   compare step by step
	 *
	 *   @param
	 *      array param1:	element 1 to compare
	 *	array param2:	element 2 to compare
	 *	array path:	path to walk to
	 */
	private function compareRecursive($param1, $param2, $path=array("")) {
 
		$structure	= $this->structure;				// save real structure
		$element  	= array($param1,$param2);			// complete array with both compare-elements
 
		// go to path, deeper and deeper
		foreach($path as $key=>$value) {
 
			if($value==="") continue;				// path-array has empty key
 
			$structure = $structure[$value];			// go deeper in structure
 
			if(isset($element[0][$value])) $element[0] = $element[0][$value];		// go deeper in element0
			else $element[0] = array();
 
			if(isset($element[1][$value])) $element[1] = $element[1][$value];		// go deeper in element1
			else $element[1] = array();
		}
 
		foreach($structure as $key=>$value) {
 
			$parameter = array();			// empty the parameter, which would set in _difference part
			$reportpath = $path;			// path which is need to report (path + key)
			array_push($reportpath, $key);		// add last element to path
 
			// if key is not set in one of both elements -> note this
			if(!isset($element[0][$key]) || !isset($element[1][$key])) {
 
				// set empty parameter
				$parameter = array("false", "false");
 
				if(!isset($element[0][$key])) $parameter[0]="true";			// element0 is not set
				if(!isset($element[1][$key])) $parameter[1]="true";			// element1 is not set
				$this->addCompareDifference($reportpath, 'ns', $parameter);		// note this difference
			}
 
			// found array -> go deeper
			if(is_Array($structure[$key])) {
 
				// if it is array: save deeper path, go deeper. After that: delete deeper part
				array_push($path, $key);
				$this->compareRecursive($param1,$param2,$path);
				array_pop($path);
			}
			// found an element
			else if(isset($element[0][$key]) && isset($element[1][$key])) {
 
				$key0 = isset($element[0][$key]) ? $element[0][$key] : "";
				$key1 = isset($element[1][$key]) ? $element[1][$key] : "";
 
				// data type is different
				if($this->getVarType($key0)!=$this->getVarType($key1)) {
 
					$parameter[0] = $this->getVarType($key0);
					$parameter[1] = $this->getVarType($key1);
					$this->addCompareDifference($reportpath, 'dt', $parameter);
				}
 
				$dataCompare = $this->env['strictMode'] ? $key0!==$key1 : $key0!=$key1;
				// data is different
				if($dataCompare) {
 
					$parameter[0] = $key0;
					$parameter[1] = $key1;
					$this->addCompareDifference($reportpath, 'dv', $parameter);
				}
			}
		}
	}
 
	/*************************
	 * addCompareDifference()
	 *************************
	 *   adds a difference in compare result
	 *
	 *   @param
	 *      array path:		where to add
	 *	string type:		type of difference
	 *		ns - key not set
	 *		dv - different value
	 *		dt - different type
	 *	array parameter:	parameter to add, send as array,
	 */
	private function addCompareDifference($path, $type, $parameter) {
 
		$difference[$this->env['placeHolder'][$type]] = $parameter;				// element to add
		$feaze = $this->arrayHandler->feaze($path, $difference);	// "open" difference with path
		$this->comparison['_difference'] = $this->arrayHandler->mergeRecursive($this->comparison['_difference'], $feaze);
	}
 
	/***************
	 * getVarType()
	 ***************
	 *   return the type of a var
	 *      reason, why not use gettype(): it is to slow
	 *
	 *   @param
	 *      mixed variable:	variable to parse
	 *
	 *   @return
	 *      string type:	type of variable (boolean | float | integer | string)
	 */
	private function getVarType($variable) {
 
		if (is_bool($variable)) return "boolean";
		if (is_float($variable)) return "float";
		if (is_int($variable)) return "integer";
		if (is_string($variable)) return "string";
		return "other";
	}
 
	/***********************
	 * convertVarToString()
	 ***********************
	 *   this function contains a variable to string
	 *      why not use type cast (string): (string) convert some things wrong (e.g. bool false into string "")
	 *
	 *   @param
	 *      variable:	variable to convert to string
	 *
	 *   @return string: 	string representation of variable
	 */
	private function convertVarToString($variable) {
 
		$type = $this->getVarType($variable);
 
		if($type == "boolean") return $variable ? "true" : "false";
		if($type == "int" || $type == "float") return (string) $variable;
		return $variable;
	}
 
	/******************
	 * flushElements()
	 ******************
	 *   flushes all saved elements
	 */
	function flushElements() {
 
		$this->elements	= array();
		$this->names	= array();
		$this->types	= array();
	}
 
	/*******************
	 * flushStructure()
	 *******************
	 *   flushes structure
	 */
	function flushStructure() {
 
		$this->structure = array();
	}
 
	/**************
	 * isSimilar()
	 **************
	 *   calculates if two strings are similar enough (>50%)
	 *
	 *   @param
	 *      string value1:	compare this value ...
	 *	string value2:	... with this value
	 *
	 *   @return
	 *      boolean 0/1:	true: values are similar enough (>50%), false: not similar enough
	 */
	private function isSimilar($value1, $value2="") {
 
		similar_text($value1, $value2, $p);
		if($p>50) return true;
		else return false;
	}
 
	/***********
	 * setEnv()
	 ***********
	 *   sets environment variables
	 *
	 *   @param
	 *      string variable:	name of the environment variable
	 *	mixed value:		new value
	 */
	function setEnv($variable="", $value="") {
 
		if($variable="") $this->e(15);					// variable-var is empty
		if(!array_key_exists($variable, $this->env)) $this->e(12);	// unknown environment variable
		$this->env[$variable] = array_merge($this->env[$variable], $value);
	}
 
	/*************
	 * printEnv()
	 *************
	 *   prints all environment variables
	 */
	function printEnv() {
 
		echo "<p><h3>Environment:</h3><pre>";
		var_dump($this->env);
		echo "</pre></p>";
	}	
 
	/******************
	 * printElements()
	 ******************
	 *   print all elements
	 */
	function printElements() {
 
		echo "<p><h3>Names:</h3><pre>";
		var_dump($this->names);
		echo "</pre></p><p><h3>Types:</h3><pre>";
		var_dump($this->types);
		echo "</pre></p><p><h3>Elements:</h3><pre>";
		var_dump($this->elements);
		echo "</pre></p>";
	}
 
	/*****************
	 * printElement()
	 *****************
	 *   print an specific element
	 *
	 *   @param
	 *      mixed element:	the ID or name of the element you want to print
	 */
	function printElement($element=0) {
 
		// parameter is an integer -> ID is set
		if(is_int($element)) {
			$max = count($this->names);
			if($element>$max-1) $this->e(9);		// element is not found
			if($element<0) $this->e(17);			// elementID is <0
			$id = $element;
		}
		// parameter is the element name (string)
		else if(is_string($element)) {
			$id = array_search($element, $this->names);
			if(!is_Int($id)) $this->e(9);			// element is not found
		}
		// wrong function call
		else $this->e(10);
 
		echo "<p><h3>".$this->names[$id]." (".$this->types[$id]."):</h3><pre>";
		var_dump($this->elements[$id]);
		echo "</pre></p>";
	}
 
	/*******************
	 * printStructure()
	 *******************
	 *   print structure
	 */
	function printStructure() {
 
		echo "<p><h3>Structure:</h3><pre>";
		var_dump($this->structure);
		echo "</pre></p>";
	}
 
	/***********************
	/ ERROR HANDLING
	/ next usable index: 18
	/***********************
	 * all messages in array
	 *
	 *   title:	value of strong-title
	 *   message:	description behind title
	 */
	private $error = array (
		"title" => array (
			"Undefined error happens",
			"Unset parameter",
			"Name already given",
			"Not a file",
			"Can't open file",
			"Unknow problem with file",
			"Unknown sourceType",
			"Unknown contentType",
			"Unknown element",
			"Unknown element",
			"Wrong function call",
			"Not enough parameter",
			"Environment variable not known",
			"Not enough parameter",
			"Structure not set",
			"Empty environment variable",
			"Unknown element",
			"Wrong element ID" ),
 
		"message" => array (
			"Repair your code",
			"You missed a parameter in <i>loadElement</i>-call. Please set <u>source</u>, <u>sourceType</u>, <u>contentType</u> and <u>name</u>.",
			"There already exists an element with this name. Change the <i>name</i> in <i>loadElement()</i>-call to something unique or flush all elements <i>flushElements()</i>.",
			"Given <u>source</u> in <i>loadElement()</i>-call is not a file.",
			"Given <u>source</u> in <i>loadElement()</i>-call cannot be opened.",
			"Cannot read <u>source</u> in <i>loadElement()</i>-call.",
			"Do not understand the <u>sourceType</u> in <i>loadElement()</i>-call. Type <u>file</u> or <u>text</u> as parameter.",
			"Do not understand the <u>contentType</u> in <i>loadElement()</i>-call. Type <u>plist</u> or <u>yaml</u> as parameter.",
			"Element is not loaded yet. Change the element name in <i>generateStructure</i> or load it with <i>loadElement</i>.",
			"Element is not loaded yet. Change the element name in <i>compare</i> or load it with <i>loadElement</i>.",
			"You need to call <i>printElement</i> with integer or string as parameter.",
			"To call <i>generateStructure</i> you need to set set 2 elements as parameter.",
			"The environment variable you want to set is not known. Check all possible environment variables with <i>printEnv()</i>.",
			"To call <i>compare</i> you need to set set 2 elements as parameter.",
			"You need a structure which is not empty. Try <i>generateStructure</i> to set a structure.",
			"You don't have set an environment varibale. Please post one in <i>setEnv</i> if you want to change it.",
			"Element is not loaded yet. Change the element name in <i>printElement</i> or load it with <i>loadElement</i>.",
			"Element IDs are not lower than 0. If you want to show an element, send ID>=0 in <i>printElement()</i>." )
		);
 
	/****************
	 * e()
	 ****************
	 *   post an error. Error will stop the script
	 *
	 *   @param
	 *      int id:	ID of the message
	 *	
	 */
	private function e($id=0) {
		$this->errorPrint($id);
		exit;
	}
 
	/****************
	 * l()
	 ****************
	 *   post a log message. Script will not stop
	 *
	 *   @param
	 *      int id:	ID of the message
	 *	
	 */
	private function l($id=0) {
		$this->errorPrint($id);
	}
 
	/****************
	 * errorPrint()
	 ****************
	 *   echo a log message.
	 *
	 *   @param
	 *      int id:	ID of the message
	 *	
	 */
	private function errorPrint($id=0) {
 
		echo "<p><strong>error ".$id."<br/><u>".$this->error['title'][$id]."</u></strong>: ".$this->error['message'][$id]."</p>";
	}
}
 
?>
