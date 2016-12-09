<?php

/* Copyright (c) 1998-2010 ILIAS open source, Extended GPL, see docs/LICENSE */

include_once("./Services/UIComponent/classes/class.ilUIHookPluginGUI.php");
include_once("class.ilLTIPlugin.php");

/**
 * User interface hook class for LTI
 * 
 * @author Stefan Schneider <schneider@hrz.uni-marburg.de>
 * @version $Id$
 * @ingroup ServicesUIComponent
 */
class ilLTIUIHookGUI extends ilUIHookPluginGUI {
	
	/**
	 * If no lti_user_image exists in SESSION object then display default user image
	 */ 
	const DEFAULT_USER_IMAGE = './templates/default/images/no_photo_xxsmall.jpg';
	
	/**
	 * 
	 */ 
	const MSG_ERROR = "0";
	const MSG_INFO = "1";
	const MSG_QUESTION = "2";
	const MSG_SUCCESS = "3";
	
	/**
	 * 
	 */ 
	private static $_ltiMode = false;
	
	/**
	 * 
	 */ 
	private static $_context_type = '';
	
	/**
	 * 
	 */ 
	private static $_context_id = '';
	
	/**
	 * 
	 */ 
	private static $_context_url = '';
	
	/**
	 * $_SESSION['lti_context_id'] is the main switch for ltiMode!
	 * We can discuss if this is a good thing
	 * 
	 * @return bool
	 */
	function getLtiMode() {
		global $DIC;
		//$DIC->logger()->root()->write("aut_mode: ".$DIC->user()->auth_mode);
		if (isset($_SESSION['lti_context_id'])) {
			self::$_context_id = $_SESSION['lti_context_id'];
			$ctxType = $this->getSessionValue('lti_context_type');
			self::$_context_type = ($ctxType == '') ? 'crs' : $ctxType;
			self::$_context_url = 'goto.php?target='.self::$_context_type.'_'.self::$_context_id;
			return true;
		}
		else {
			return false;
		}
	}
	
	/**
	 * Don't modify anything in a anonymous or root (id 6) ilias session
	 * 
	 * @return bool
	 */ 
	function getLtiSkip() {
		global $DIC;
		if (!is_object($DIC->user())) {
			return true;
		}
		$usr_id = $DIC->user()->getId();
		
		if (!$usr_id || $usr_id == ANONYMOUS_USER_ID || $usr_id == 6) {
			return true;
		}
		return false;
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
		global $DIC;			
		if (!self::$_ltiMode) {
			return array("mode" => ilUIHookPluginGUI::KEEP, "html" => "");
		}
		if ($a_part == "template_load" && $a_par["tpl_id"] == "tpl.main.html") {
			$DIC->logger()->root()->write("catch main");
			$pl = $this->getPluginObject();
			$tplLtiCss = $pl->getTemplate('tpl.lti_css.html');
			$ltiCss = file_get_contents($pl->getStyleSheetLocation('lti.css'));
			//$this->log->write($ltiCss);
			$tplLtiCss->setVariable('LTI_CSS', $ltiCss);
			
			if (isset($_SESSION['lti_launch_css_url'])) {
				try {
					$customCss = file_get_contents($_SESSION['lti_launch_css_url']);
					$tplLtiCss->setVariable('CUSTOM_CSS', $customCss);
				}
				catch (Exception $e) {
					$this->showError($e); // ToDo
				}
 			}
 			 
			$html = $tplLtiCss->get();
			return array("mode" => ilUIHookPluginGUI::APPEND, "html" => $html);
			
			//return array("mode" => ilUIHookPluginGUI::KEEP, "html" => "");
		}
		 
		// hook into tpl.main_menu.html (content is hidden via lti.css) and append a new lti menu
		if ($a_part == "template_load" && $a_par["tpl_id"] == "Services/MainMenu/tpl.main_menu.html") {
			$DIC->logger()->root()->write("catch main menu");
			$this->checkMessages();
			$pl = $this->getPluginObject();
			$tpl = $DIC->ui()->mainTemplate();
			$tpl->addCss($pl->getStyleSheetLocation('lti.css'));
			$tplLtiMenu = $pl->getTemplate('tpl.lti_menu.html');
			$userImage = (isset($_SESSION['lti_user_image'])) ? $_SESSION['lti_user_image'] : self::DEFAULT_USER_IMAGE;
			$tplLtiMenu->setVariable('SRC_USER_IMAGE', $userImage);
			$tplLtiMenu->setVariable('TXT_USER_FULLNAME',$_SESSION['lti_lis_person_name_full']);
			$tplLtiMenu->setVariable('TXT_EXIT_LTI',$pl->txt("exit")); // ToDo: Language Vars 
			$html = $tplLtiMenu->get();
			return array("mode" => ilUIHookPluginGUI::APPEND, "html" => $html);
		}
		
		if ($a_comp == "Services/Locator" && $a_part == "main_locator") {	
			$locator = $this->processLocator($a_par['locator_gui']);
			//return array("mode" => ilUIHookPluginGUI::KEEP, "html" => "");		
		}
		
		if ($a_comp == "Services/PersonalDesktop") { // ToDo	
			$this->redirectToContext(self::MSG_INFO,'forbidden');	
			//return array("mode" => ilUIHookPluginGUI::REPLACE, "html" => "");
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
		global $DIC;
		if ($a_comp == "Services/Init" && $a_part == "init_style") {
			// fake session: remove!
			/*
			if (isset($_GET['target']) && $_GET['target'] == 'crs_71') {
				$params = explode('_',$_GET['target']);
				if (count($params) == 2) {
					$this->fakeLtiSession($params[1],$params[0]);
				}	
			}
			*/
			//ToDo: look at auth_mode=lti not only if lti SESSION value is set
			
			if (!$this->getLtiMode() || $this->getLtiSkip()) {
				//$DIC->logger()->root()->write("no lti mode");
				self::$_ltiMode = false;
			}
			else {
				//$DIC->logger()->root()->write("lti mode");
				self::$_ltiMode = true;
			}
			if (self::$_ltiMode) {
				$this->checkCmd();
				$this->checkRefId($_GET['ref_id']);
			}
		}
	}
	
	/**
	 * Set Locator entries LTI object as root
	 *
	 * @param ilLocatorGUI object $locatorGUI
	 *
	 */
	function processLocator($locatorGUI) {
		global $DIC;
		$pl = $this->getPluginObject();
		$srcEntries = $locatorGUI->getItems();
		$locatorGUI->clearItems();
		
		$foundEntry = false;
		foreach($srcEntries as $srcEntry) {
			if ($srcEntry['ref_id'] == $_SESSION['lti_context_id']) {
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
		/*
		// something really strange happens here????
		$DIC->logger()->root()->write("found entry final:" . $foundEntry);
		if (!$foundEntry) { //does not work on Weblink Object....no idea
			
		}
		*/ 
	}
	
	/**
	 * exit LTI session and if defined redirecting to returnUrl
	 */
	function exitLti() {
		global $DIC;
		if ($this->getSessionValue('lti_launch_presentation_return_url') == '') {
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
			header('Location: ' . $_SESSION['lti_launch_presentation_return_url']);
			exit;
		}		
	}
	
	/**
	 * 
	 */ 
	function checkMessages() {
		global $DIC;
		$pl = $this->getPluginObject();
		$msg = $_GET["lti_msg"];
		$msg_type = $_GET["lti_msg_type"];
		switch ($msg_type) {
			case  self::MSG_ERROR:
				ilUtil::sendFailure($pl->txt($msg));
				break;
			case  self::MSG_INFO:
				ilUtil::sendInfo($pl->txt($msg));
				break;
			case  self::MSG_QUESTION:
				ilUtil::sendQuestion($pl->txt($msg));
				break;
			case  self::MSG_SUCCESS:
				ilUtil::sendSuccess($pl->txt($msg));
				break;
		}
	}
	
	/**
	 * 
	 */
	function checkCmd() {
		global $DIC;
		$cmd = $_GET["lti_cmd"];
		switch ($cmd) {
			case 'exit' :
				$this->exitLti();
				break;
		}
	} 
	
	/**
	 *
	 */
	function checkRefId($ref_id) {
		global $DIC;
		
		$pl = $this->getPluginObject();
		if (!$ref_id) {
			return;
		}
		if ($_SESSION['lti_context_id'] == $ref_id) {
			return;
		}
		$childOfContext = $DIC['tree']->isGrandChild($_SESSION['lti_context_id'],$ref_id);
		if (!$childOfContext) {
			$this->redirectToContext(self::MSG_ERROR,'forbidden');
			exit;
		}
	}   
	
	/**
	 * logout ILIAS and destroys Session and ilClientId cookie
	 */
	function logout() {
		global $DIC;
		ilSession::setClosingContext(ilSession::SESSION_CLOSE_USER);		
		$DIC['ilAuthSession']->logout();
		// reset cookie
		$client_id = $_COOKIE["ilClientId"];
		ilUtil::setCookie("ilClientId","");
	}
	
	/**
	 * 
	 */ 
	function gotoHook() {
		if (!self::$_ltiMode) {
			return;
		}
		if (isset($_GET['target']) && $_GET['target'] == $_SESSION['lti_context_type'] ."_". $_SESSION['lti_context_id']) {
			return;
		}
		$params = explode('_',$_GET['target']);
		if (count($params) == 2) {
			$this->checkRefId($params[1]);
		}
	}
	
	/**
	 * 
	 */ 
	function redirectToContext($_msg_type=self::MSG_INFO, $_msg='') {
		$msg = ($_msg != '') ? '&lti_msg='.$_msg.'&lti_msg_type='.$_msg_type : '';
		ilUtil::redirect(self::$_context_url.$msg);
	}
	
	/**
	 * fake LTI Session
	 */ 
	function fakeLtiSession($ref_id,$type) {
		global $DIC;
		$DIC->logger()->root()->write("fakeLtiSession");
		$_SESSION['lti_context_id'] = $ref_id;
		$_SESSION['lti_context_type'] = $type;
		$_SESSION['lti_launch_css_url'];
		$_SESSION['lti_launch_presentation_return_url'] = 'http://ipxe.org';
		$_SESSION['lti_lis_person_name_given'] = "Fritz";
		$_SESSION['lti_lis_person_name_family'] = "Fratze";
		$_SESSION['lti_lis_person_name_full'] = "Fritz Fratze";
	}
	
	/**
	 * get session value != ''
	 * 
	 * @param $sess_key string 
	 * @return string
	 */ 
	function getSessionValue($sess_key) {
		if (isset($_SESSION[$sess_key]) && $_SESSION[$sess_key] != '') {
			return $_SESSION[$sess_key];
		}
		else {
			return '';
		}
	}
}
?>
