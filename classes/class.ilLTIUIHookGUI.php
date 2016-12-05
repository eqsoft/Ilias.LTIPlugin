<?php

/* Copyright (c) 1998-2010 ILIAS open source, Extended GPL, see docs/LICENSE */

include_once("./Services/UIComponent/classes/class.ilUIHookPluginGUI.php");
include_once("./Services/Init/classes/class.ilStartUpGUI.php");
include_once("class.ilLTIPlugin.php");

/**
 * User interface hook class
 * 
 * @author Stefan Schneider <schneider@hrz.uni-marburg.de>
 * @version $Id$
 * @ingroup ServicesUIComponent
 */
class ilLTIUIHookGUI extends ilUIHookPluginGUI {
	
	private static $_modifyGUI = 0;
	private $regLocator = '';
	
	// lti params
	private $contextId = '';
	private $contextType = '';
	//private $cssUrl = 'https://www.simple.org:9443/ilias_lti/lti_custom.css';
	private $cssUrl = '';
	//private $returnUrl = 'http://ipxe.org';
	private $returnUrl = '';
	private $usrFirstname = '';
	private $usrLastname = '';
	private $usrFullname = '';
	private $usrImage = './templates/default/images/no_photo_xxsmall.jpg';
	private $launchLocale = 'en-US';
	
	function __construct() {
		// get lti session flag and fetch lti config params		
		if ($_SESSION['lti_context_id']) {
			$this->contextId = $_SESSION['lti_context_id'];
			$this->contextType = $_SESSION['lti_context_type'];
			$this->cssUrl = $_SESSION['lti_launch_css_url'];
			$this->returnUrl = $_SESSION['lti_launch_presentation_return_url'];
			$this->usrFirstname = $_SESSION['lti_lis_person_name_given'];
			$this->usrLastname = $_SESSION['lti_lis_person_name_family'];
			$this->usrFullname = $_SESSION['lti_lis_person_name_full'];
			// 
			$this->launchLocale = $_SESSION['lti_launch_presentation_locale'];
			// print("hallole".$this->contextId);
			// return true;
		}
		if ($_SESSION['lti_user_image']) $this->usrImage = $_SESSION['lti_user_image'];
		
	}
	function detectLti() { 
		global $ilUser;
		$cmd = $_GET["lti_cmd"];
		switch ($cmd) {
			case 'exit' :
				$this->exitLti();
				break;
		}
		if ($this->contextId != "") {
			return true;
		} else {
			return false;
		}
	}
	
	//https://www.simple.org:9443/ilias_lti/ilias.php?ref_id=71&cmd=exit&cmdClass=illtiuihookgui&cmdNode=y6
	/**
	 * Modify HTML output of GUI elements. Modifications modes are:
	 * - ilUIHookPluginGUI::KEEP (No modification)
	 * - ilUIHookPluginGUI::REPLACE (Replace default HTML with your HTML)
	 * - ilUIHookPluginGUI::APPEND (Append your HTML to the default HTML)
	 * - ilUIHookPluginGUI::PREPEND (Prepend your HTML to the default HTML)
	 *
	 * @param string $a_comp component
	 * @param string $a_part string that identifies the part of the UI that is handled
	 * @param string $a_par array of parameters (depend on $a_comp and $a_part)
	 *
	 * @return array array with entries "mode" => modification mode, "html" => your html
	 */
	function getHTML($a_comp, $a_part, $a_par = array()) {				
		global $ilUser, $rbacreview, $tpl, $ilLog, $ilCtrl;
		
		if (!self::$_modifyGUI ) {
			return array("mode" => ilUIHookPluginGUI::KEEP, "html" => "");
		}
		
		if ($a_part == "template_load" && $a_par["tpl_id"] == "tpl.main.html") {
			$pl = $this->getPluginObject();
			$tplLtiCss = $pl->getTemplate('tpl.lti_css.html');
			$ltiCss = file_get_contents($pl->getStyleSheetLocation('lti.css'));
			$tplLtiCss->setVariable('LTI_CSS', $ltiCss);
			if ($this->cssUrl) {
				try {
					$customCss = file_get_contents($this->cssUrl);
					$tplLtiCss->setVariable('CUSTOM_CSS', $customCss);
				}
				catch (Exception $e) {
					$this->showError($e);
				}
 			} 
			$html = $tplLtiCss->get();
			//return array("mode" => ilUIHookPluginGUI::PREPEND, "html" => "<style type=\"text/css\">body { visibility:hidden }</style>");
			return array("mode" => ilUIHookPluginGUI::APPEND, "html" => $html);
		}
		 
		// hook into tpl.main_menu.html (content is hidden via lti.css) and append a new lti menu
		if ($a_part == "template_load" && $a_par["tpl_id"] == "Services/MainMenu/tpl.main_menu.html") {
			$pl = $this->getPluginObject();
			$tpl->addCss($pl->getStyleSheetLocation('lti.css'));
			$tplLtiMenu = $pl->getTemplate('tpl.lti_menu.html');
			$tplLtiMenu->setVariable('SRC_USER_IMAGE',$this->usrImage);
			$tplLtiMenu->setVariable('TXT_USER_FULLNAME',$this->usrFullname);
			$tplLtiMenu->setVariable('TXT_EXIT_LTI',$pl->txt("exit")); // ToDo: Language Vars 
			$html = $tplLtiMenu->get();
			return array("mode" => ilUIHookPluginGUI::APPEND, "html" => $html);
		}
		
		if ($a_comp == "Services/Locator" && $a_part == "main_locator") {	
			$locator = $this->processLocator($a_par['locator_gui']);		
		}
		
		if ($a_comp == "Services/PersonalDesktop" && $a_part == "left_column") {			
			return array("mode" => ilUIHookPluginGUI::REPLACE, "html" => "");
		}
		return array("mode" => ilUIHookPluginGUI::KEEP, "html" => "");
	}
	
	/**
	 * Modify GUI objects, before they generate ouput
	 * 
	 * @param string $a_comp component
	 * @param string $a_part string that identifies the part of the UI that is handled
	 * @param string $a_par array of parameters (depend on $a_comp and $a_part)
	 */
	function modifyGUI($a_comp, $a_part, $a_par = array()) {
		global $ilUser, $rbacreview, $ilAuth, $ilLog, $tpl;
		if ($a_comp == "Services/Init" && $a_part == "init_style") {			
			//$styleDefinition = $a_par["styleDefinition"];
			$pl = $this->getPluginObject();
			$usr_id = $ilUser->getId();
			// don't modify anything in a public or admin ilias session
			if (!$usr_id || $usr_id == ANONYMOUS_USER_ID || $usr_id == 6) {
				//$this->setUserGUI($styleDefinition);
				return;
			}
			self::$_modifyGUI = $this->detectLti();
		}
	}
	
	/**
	 * Set Locator entries LTI Object as root
	 *
	 * @param ilLocatorGUI Object $locatorGUI
	 *
	 */
	function processLocator($locatorGUI) {
		$pl = $this->getPluginObject();
		$srcEntries = $locatorGUI->getItems();
		$locatorGUI->clearItems();
		
		$foundEntry = false;
		foreach($srcEntries as $srcEntry) {
			if ($srcEntry['ref_id'] == $this->contextId) {//$_SESSION['lti_context_id']) {
				$foundEntry = true;
			}
			if ($foundEntry) {
				//print_r($srcEntry);
				$locatorGUI->addItem(
					$srcEntry['title'],
					$srcEntry['link'],
					$srcEntry['frame'],
					$srcEntry['ref_id'],
					$srcEntry['type']
				);
			}
			else {
				continue;
			}
		}
		if (!$foundEntry) { //Ups! where ARE you??
			print($pl->txt('forbidden'));
		}
	}
	
	function exitLti() {
		if ($this->returnUrl == '') {
			//$tpl->addBlockfile('CONTENT', 'content', $pl->getDirectory() . "/templates/tpl.lti_exit.html");
			$pl = $this->getPluginObject();
			$tplExit = $pl->getTemplate('tpl.lti_exit.html');
			$tplExit->setVariable('STYLE_EXITED',$pl->getStyleSheetLocation('lti.css'));
			$tplExit->setVariable('TXT_EXITED_TITLE',$pl->txt('exited_title'));
			$tplExit->setVariable('TXT_EXITED',$pl->txt('exited'));
			$html = $tplExit->get();
			$this->logout();
			print $html;
			exit;
		}
		else {
			$this->logout();
			header('Location: ' . $this->returnUrl);
			exit;
		}		
	}
	
	function logout() {
		ilSession::setClosingContext(ilSession::SESSION_CLOSE_USER);		
		$GLOBALS['DIC']['ilAuthSession']->logout();
		// reset cookie
		$client_id = $_COOKIE["ilClientId"];
		ilUtil::setCookie("ilClientId","");
	}
	
	function showError($e) { // ToDo
		$this->logout();
		print $e;
		exit;
	}
}
?>
