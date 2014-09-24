<?php
 
/**********************
 * COMPARER HTML class V1.00
 **********************
 *   This class prints the result of comparison on browser.
 *   Use the ComparerClass to get a comparion result.
 *
 *   @author David Pauli
 *
 *   How does it work
 *   ****************
 *      1. load ComparerHTMLClass with compare-result
 *      2. print with ->printDocument(first) and give parameter "first" if you want to print some HTML-elements before output
 *****************/
 
class ComparerHTML {
 
	private $configuration	= array();	// save configuration
	private $elements	= array();	// save elements
	private $structure	= array();	// save structure
	private $differences	= array();	// save differences
 
	private $arrayHandler;			// handles important array functions
 
	private $env;				// space for intern environment variables
 
	// color-handling, make it colorful. Just a list of used colors
	private $colors		= array("FF530D", "E82C0C", "FF0000", "E80C7A", "FF0DFF", "7F0000", "FF4C4C", "FF0000", "7F2626", "CC0000", "B21212", "FFFC19", "FF0000", "1485CC", "0971B2", "B20000", "FF1919", "FF0000", "00B233", "00FF48", "CC4A14", "99583D", "FF0000", "40FF40", "54CC14", "BF0000", "7F0000", "FF0000", "400000", "E50000");
	private $color		= 0;	// actual point in $colors, increments it after using
 
	function __construct($result="") {
 
		if($result==="") $this->e(1);				// no parameter
		if(!is_Array($result)) $this->e(2);			// no array as parameter
		if(!isset($result['_configuration']) ||			// not the correct result-format from ComparerClass
			!isset($result['_structure']) ||
			!isset($result['_difference']) ||
			!isset($result['_elements']) ||
			!isset($result['_environment'])) $this->e(3);
 
		// save all information you get from result
		$this->configuration	= $result['_configuration'];
		$this->elements		= $result['_elements'];
		$this->structure	= $result['_structure'];
		$this->difference	= $result['_difference'];
		$this->env		= $result['_environment'];
 
		// shuffle colors
		shuffle($this->colors);
 
		// save used CSS
		$this->env['css']['media']	= "all";
		$this->env['css']['href']	= "style.css";
		// calculate color of each element
		$this->env['color'][0]		= $this->getNextColor();
		$this->env['color'][1]		= $this->getNextColor();
		$this->env['color']['ns']	= $this->getNextColor();
		$this->env['color']['dv']	= $this->getNextColor();
		$this->env['color']['dt']	= $this->getNextColor();
		$this->env['color']['default']	= $this->getNextColor();
		$this->env['color']['integer']	= $this->getNextColor();
		$this->env['color']['string']	= $this->getNextColor();
		$this->env['color']['boolean']	= $this->getNextColor();
		// information text
		$this->env['information']['ns']	= "Key is in one element not set.";
		$this->env['information']['dv']	= "Keys of the elements differs each other.";
		$this->env['information']['dt']	= "Keys of element have other type.";
 
		$this->arrayHandler	= new ArrayHandler();		// initalize handler for array operation
	}
 
	/******************
	 * printDocument()
	 ******************
	 *   prints complete document
	 *
	 *   @param
	 *      string first:	adds element to the top of <body>-section
	 */
	function printDocument($first="") {
 
		$this->printHeader();
		echo $first!=="" ? $first."\n" : "";
		$this->printBody();
		$this->printFooter();
	}
 
	/**************
	 * printBody()
	 **************
	 *   prints the body
	 */
	private function printBody() {
 
		$color = $this->env['color'];
 
		echo "<div id=\"body\">\n";
 
		echo "<table>\n";
		echo "\t<tr class=\"head\">\n";
		echo "\t\t<th class=\"structure\">Structure</th>\n";
		echo "\t\t<th class=\"icon\">ns</th>\n";
		echo "\t\t<th class=\"icon\">dv</th>\n";
		echo "\t\t<th class=\"icon\">dt</th>\n";
 
		echo "\t\t<th class=\"left element\" style=\"color: ".$color[0]['background']."\">".$this->configuration[0]['name']."</th>\n";
		echo "\t\t<th class=\"right element\" style=\"color: ".$color[1]['background']."\">".$this->configuration[1]['name']."</th>\n";
		echo "\t</tr>\n";
 
		$this->printBodyDifference($this->difference, 0, $this->elements[0], $this->elements[1]);
 
		echo "</table>\n";
		echo "</div>\n";
	}
 
	/************************
	 * printBodyDifference()
	 ************************
	 *   prints the differences
	 *
	 *   @param:
	 *      array difference:	difference
	 *	int depth:		actual depth
	 *	array element1:		element 1
	 *	array element2:		element 2
	 */
	private function printBodyDifference($difference, $depth, $element1, $element2) {
 
		foreach($difference as $key=>$value) {
 
			echo "\t<tr>\n";
 
			$foundDifference = array_key_exists("ns", $difference[$key]) || array_key_exists("dv", $difference[$key]) || array_key_exists("dt", $difference[$key]);
 
			// DRAW STRUCTURE
			if($foundDifference) {
 
				echo "\t\t<td class=\"child structure\">";
				for($i=0;$i<$depth;$i++) echo $i==0 ? "|-" : "--";
				echo " ".$key."</td>\n";
 
				$types = array("ns", "dt", "dv");	// all possible types
 
				foreach($types as &$type) {
 
					if(array_key_exists($type, $difference[$key])) echo "\t\t<td title=\"".$this->env['information'][$type]."\" class=\"icon\" style=\"background: ".$this->env['color'][$type]['background']."; color: ".$this->env['color'][$type]['text']."\">".$type."</td>\n";
					else echo "\t\t<td class=\"empty\"></td>\n";
				}
			}
			else if($key!="ns" && $key!="dt" && $key!="dv") {
 
				echo "\t\t<td class=\"parent structure\" colspan=\"4\">";
				for($i=0;$i<$depth;$i++) echo $i==0 ? "|-" : "--";
				echo " ".$key."</td>\n";
			}
 
			// DRAW CONTENT
			if($foundDifference) {
 
				echo "\t\t<td class=\"left element\">";
				if(isset($element1[$key])) echo is_Array($element1[$key]) ? "<strong><i>[ARRAY]</i></strong>" : $element1[$key]." <span style=\"color: ".$this->env['color'][$this->getVarType($element1[$key])]['text']."; background: ".$this->env['color'][$this->getVarType($element1[$key])]['background']."\" title=\"". $this->getVarType($element1[$key])."\">[ ".substr($this->getVarType($element1[$key]),0,1)." ]</span>";
				else echo "<strong><i>[not set]</i></strong>";
				echo"</td>\n";
 
				echo "\t\t<td class=\"right element\">";
				if(isset($element2[$key])) echo is_Array($element2[$key]) ? "<strong><i>[ARRAY]</i></strong>" : "<span style=\"color: ".$this->env['color'][$this->getVarType($element2[$key])]['text']."; background: ".$this->env['color'][$this->getVarType($element2[$key])]['background']."\" title=\"". $this->getVarType($element2[$key])."\">[ ".substr($this->getVarType($element2[$key]),0,1)." ]</span> ".$element2[$key];
				else echo "<strong><i>[not set]</i></strong>";
				echo"</td>\n";
			}
			else echo "\t\t<td colspan=\"2\"></td>\n";
 
			echo "\t</tr>\n";
 
			// GO DEEPER
			if(!isset($element1[$key])) $element1[$key]="";
			if(!isset($element2[$key])) $element2[$key]="";
			if(is_Array($element1[$key]) || is_Array($element2[$key])) $this->printBodyDifference($difference[$key], $depth+1, $element1[$key], $element2[$key]);
 
		}
	}
 
	/****************
	 * printFooter()
	 ****************
	 *   prints the footer of HTML
	 */
	private function printFooter() {
 
		echo "</body>\n";
		echo "</html>";
	}
 
	/****************
	 * printHeader()
	 ****************
	 *   prints the header of HTML
	 */
	private function printHeader() {
 
		echo "<!DOCTYPE html>\n";
		echo "<html>\n";
		echo "<head>\n";
		echo "<title>Compare two elements</title>\n";
		echo "<meta charset=\"UTF-8\">\n";
		$this->printCSS();
		echo "</head>\n";
		echo "<body>\n";
	}
 
	/*************
	 * printCSS()
	 *************
	 *   prints the CSS-Content
	 */
	private function printCSS() {
 
		if($this->env['css']['href']!=="") {
			echo "<link rel=\"stylesheet\" type=\"text/css\" href=\"";
			echo $this->env['css']['href'];
			echo "\" media=\"".$this->env['css']['media']."\"";
			echo ">\n";
		}
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
	 *      string type: 	type of variable (boolean | float | integer | string)
	 */
	private function getVarType($variable) {
 
		if (is_bool($variable)) return "boolean";
		if (is_float($variable)) return "float";
		if (is_int($variable)) return "integer";
		if (is_string($variable)) return "string";
		return "other";
	}
 
	/***********************
	 * calculateTextColor()
	 ***********************
	 *   calculates a text color which fits to given background color
	 *
	 *   @param
	 *      string background:	actual background color, hexadecimal format (RRGGBB)
	 *
	 *   @return
	 *      string textcolor:	text color, which fits to background, hexadecimal format (RRGGBB)
	 */
	private function calculateTextColor($background) {
 
		$background_r = hexdec(substr($background, 0, 2));
		$background_g = hexdec(substr($background, 2, 2));
		$background_b = hexdec(substr($background, 4, 2));
 
		$return = ($background_r+$background_g+$background_b) > 381 ? "000000" : "FFFFFF";
 
		return $return;
	}
 
	/*****************
	 * getNextColor()
	 *****************
	 *   returns the next available text color and background color
	 *
	 *   @return
	 *      array color:	[background] background color, [text] text color, format: #RRGGBB
	 */
	private function getNextColor() {
 
		$return['background']	= "#".$this->colors[$this->color];
		$return['text']		= "#".$this->calculateTextColor($this->colors[$this->color]);
 
		$this->color = $this->color+1==count($this->colors) ? 0 : $this->color+1;
 
		return $return;
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
 
		echo "<p><h3>Configuration:</h3><pre>";
		var_dump($this->configuration);
		echo "</pre></p><p><h3>Elements:</h3><pre>";
		var_dump($this->elements);
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
 
	/**********************
	/ ERROR HANDLING
	/ next usable index: 6
	/**********************
	 * all messages in array
	 *
	 *   title:	value of strong-title
	 *   message:	description behind title
	 */
	private $error = array (
		"title" => array (
			"Undefined error happens",
			"No parameter given",
			"Wrong parameter given",
			"Wrong parameter given",
			"Environment variable not known" ),
 
		"message" => array (
			"Repair your code",
			"Please load the class with <i>ComparerClass</i> result as parameter.",
			"You need to gave a array while construct the class.",
			"Please set the right data format from <i>ComparerClass</i>.",
			"The environment variable you want to set is not known. Check all possible environment variables with <i>printEnv()</i>." )
		);
 
	/****************
	 * e()
	 ****************
	 *   post an error. Error will stop the script
	 *
	 *   @param
	 *      id:	ID of the message
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
	 *      id:	ID of the message
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
	 *      id:	ID of the message
	 *	
	 */
	private function errorPrint($id=0) {
 
		echo "<p><strong>error ".$id."<br/><u>".$this->error['title'][$id]."</u></strong>: ".$this->error['message'][$id]."</p>";
	}
}
 
?>
