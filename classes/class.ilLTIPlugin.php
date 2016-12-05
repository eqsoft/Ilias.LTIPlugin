<?php

include_once("./Services/UIComponent/classes/class.ilUserInterfaceHookPlugin.php");
 
/**
 * Example user interface plugin
 *
 * @author Stefan Schneider <schneider@hrz.uni-marburg.de>
 * @version $Id$
 *
 */
class ilLTIPlugin extends ilUserInterfaceHookPlugin
{
	const NOT_A_LTI_REQUEST = 0;
	const LTI_REQUEST = 1;
	
	function getPluginName() {
		return "LTI";
	}
}

?>
