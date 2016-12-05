<?php

include_once("./Services/Component/classes/class.ilPluginConfigGUI.php");
include_once("class.ilLTIPlugin.php");
/**
 * Example configuration user interface class
 *
 * @author Stefan Schneider <schneider@hrz.uni-marburg.de>
 * @version $Id$
 *
 */
class ilLTIConfigGUI extends ilPluginConfigGUI {
	/**
	* Handles all commmands, default is "configure"
	*/
	function performCommand($cmd) {

		switch ($cmd)
		{
			case "configure":
			case "save":
				$this->$cmd();
				break;

		}
	}

	/**
	 * Configure screen
	 */
	function configure() {
		global $tpl;
		$form = $this->initConfigurationForm();
		//$tpl->setContent($form->getHTML());
	}
	
	//
	// From here on, this is just an example implementation using
	// a standard form (without saving anything)
	//
	
	/**
	 * Init configuration form.
	 *
	 * @return object form object
	 */
	public function initConfigurationForm() {
		global $lng, $ilCtrl, $ilDB, $rbacreview;
		$pl = $this->getPluginObject();
		return $form;
	}
	
	/**
	 * Save form input (currently does not save anything to db)
	 *
	 */
	public function save() {
		global $tpl, $lng, $ilCtrl, $ilDB;
		$pl = $this->getPluginObject();
		$form = $this->initConfigurationForm();
	}
}
?>
