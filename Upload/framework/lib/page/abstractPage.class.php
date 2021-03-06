<?php

/**
 * Abstrakte Seite auf der alle andere Seiten aufbauen.
 * @author Christian Spitschka
 */

abstract class AbstractPage {
    

	private $title; // only if $ignoreSession == true

	public $header = "";

	protected $isAPI = false;
	protected $apiIsSessionOK = false;
	protected $sitename = "index";
	protected $messageItem = "";
	protected $taskItem = "";
	protected $loginStatus = "";
	protected $userImage = "";
	protected $eltermailPopup = "";
	protected $helpTopic = "";
	protected static $isBeta = false;

	private static $activePages = array();
	private $acl = false;
	private $request = false;
	private $extension = false;
	private $isAnyAdmin = false;
	
	static $adminGroupName = NULL;
	static $aclGroupName = NULL;


	/**
	 * 
	 * @param pageline Array
	 * @param ignoreSession Boolean
	 * @param isAdmin Boolean
	 * @param isNotenverwaltung Boolean
	 * @param request Array ( _GET Parameter)
	 * @param extension Array
	 */
	public function __construct($pageline,
															$ignoreSession = false,
															$isAdmin = false,
															$isNotenverwaltung = false,
															$request = [],
															$extension = [] ) {


		header("X-Frame-Options: deny");
		
		$this->request = $request;
		$this->extension = $extension;

		$this->sitename = addslashes(trim($request['page']));
				
		if ($this->sitename != "" && in_array($this->sitename, requesthandler::getAllowedActions()) && !self::isActive ( $this->sitename )) {
			// TODO: Sinnvolle Fehlermeldung
			die ( "Die angegebene Seite ist leider nicht aktiviert" );
		}
		
		// Load Extension JSON and set Defaults
		if ($this->extension) {
            $path = str_replace(DS.'admin','',PATH_EXTENSION);
			$this->extension['json'] = self::getExtensionJSON($path.'extension.json');
			if ( isset($this->extension['json']) ) {

				// Admin Group
				if ( $this->extension['json']['adminGroupName'] ) {
					self::setAdminGroup($this->extension['json']['adminGroupName']);
				}

				// ACL Group
				if ( $this->extension['json']['aclGroupName'] ) {
					self::setAclGroup($this->extension['json']['aclGroupName']);
				}
			}
		}
		

		// Seite ohne Session aufrufen?
		// TODO: @Spitschka es gibt kein else ???
		if (!$ignoreSession) {

			$this->title = $title;
			$this->sitename = $sitename;
			
			if (isset($_COOKIE['schuleinternsession'])) {
				DB::initSession($_COOKIE['schuleinternsession']);
				if (!DB::isLoggedIn()) {
					if (isset($_COOKIE['schuleinternsession'])) {
						setcookie("schuleinternsession", null);
					}
					$message = "<div class=\"callout callout-danger\"><p><strong>Sie waren leider zu lange inaktiv. Sie k&ouml;nnen dauerhaft angemeldet bleiben, wenn Sie den Haken bei \"Anmeldung speichern\" setzen. </strong></p></div>";
					eval ( "echo(\"" . DB::getTPL ()->get ( "login/index" ) . "\");" );
					exit;

				} else {
					DB::getSession()->update();
				}
			}
			
			
			// 2 Faktor

			$needTwoFactor = false;
			if( DB::isLoggedIn()
				&& TwoFactor::is2FAActive()
				&& TwoFactor::enforcedForUser(DB::getSession()->getUser()) ) {
        $needTwoFactor = true;
      }
			if($needTwoFactor || ($this->need2Factor() && TwoFactor::is2FAActive())) {
				$pagesWithoutTwoFactor = [
					'login',
					'logout',
					'TwoFactor'
				];	
				$currentPage = $_REQUEST['page'];
				if( !DB::getSession()->is2FactorActive()
					&& !in_array($currentPage, $pagesWithoutTwoFactor) ) {
					header("Location: index.php?page=TwoFactor&action=initSession&gotoPage=" . urlencode($currentPage));
					exit(0);			        
				}			    
			}
			

			// Wartungsmodus
			
			$infoWartungsmodus = "";
			if ( DB::getSettings()->getValue("general-wartungsmodus")
				&& $_REQUEST['page'] != "login"
				&& $_REQUEST['page'] != "logout"
				&& $_REQUEST['page'] != "impressum" ) {
				if ( !DB::isLoggedIn() || !DB::getSession()->isAdmin()) {
					eval( "echo(\"" . DB::getTPL ()->get ( "wartungsmodus/index" ) . "\");" );
					exit();
				} else {
					$infoWartungsmodus = "<div class=\"callout callout-danger\"><i class=\"fa fa-cogs\"></i> Die Seite befindet sich im Wartungsmodus! Bitte unter den <a href=\"index.php?page=administrationmodule&module=index\">Einstellungen</a> wieder deaktivieren!</div>";
				}
			}
		
			// Datenschutz
			
			if (	DB::isLoggedIn()
				&& datenschutz::needFreigabe(DB::getSession()->getUser())
				&& !datenschutz::isFreigegeben(DB::getSession()->getUser())
				&& $_REQUEST['page'] != "login"
				&& $_REQUEST['page'] != "logout"
				&& $_REQUEST['page'] != "impressum"
				&& $_REQUEST['page'] != "datenschutz" ) {
				header("Location: index.php?page=datenschutz&confirmPopUp=1");
				exit(0);
			}


			// Check Adminrights

			if( DB::isLoggedIn()
				&& ( DB::getSession()->isAdmin() || DB::getSession()->isMember($this->getAdminGroup())) ) {
				$this->isAnyAdmin = true;
			} else {
				$this->isAnyAdmin = false;
			}

			if ($this->request['admin'] && $this->isAnyAdmin == false ) {
					new errorPage('Kein Zugriff');
			}

			// Login Status

			if (DB::isLoggedIn()) {
				$displayName = DB::getSession()->getData('userFirstName')." ".DB::getSession()->getData('userLastName');
				if (DB::isLoggedIn() && DB::getSession()->isTeacher()) {
					$mainGroup = "Lehrer";
				} else if (DB::isLoggedIn() && DB::getSession()->isPupil()) {
					$mainGroup = "Sch??ler (Klasse ".DB::getSession()->getPupilObject()->getGrade().")";
				} else if (DB::isLoggedIn() && DB::getSession()->isEltern()) {
					$mainGroup = "Eltern";
				} else {
					$mainGroup = "Sonstiger Benutzer";
				}
			} else {
				$displayName = "Nicht angemeldet";
				$mainGroup = "";
			}

			
			// Header and Menu
			
			$this->prepareHeaderBar($mainGroup);
				
			$menu = new menu($isAdmin, $isNotenverwaltung);
			$menuHTML = $menu->getHTML();
			
			$sitemapline = "";
			for($i = 0; $i < sizeof ( $pageline ); $i ++) {
				$sitemapline .= '<li class="active">' . $pageline [$i] . '</li>';
			}
			
			$siteTitle = $pageline[sizeof($pageline) - 1];
			
			

			
			// Page Skin Color

			$skinColor = DB::$mySettings['skinColor'];
			if(DB::getSettings()->getValue('global-skin-default-color') != '') {
				if(DB::getSettings()->getBoolean('global-skin-force-color')) {
					$skinColor = DB::getSettings()->getValue('global-skin-default-color');
				} else if ($skinColor == '') {
					$skinColor = DB::getSettings()->getValue('global-skin-default-color');
				}
			}
			// Default Color f??r alle: Gr??n
			if($skinColor == "") $skinColor = "green";		
			

			// Laufzettel Info
			
			if ( $this->isActive("laufzettel")
				&& DB::isLoggedIn()
				&& DB::getSession()->isTeacher() ) {
				$zuBestaetigen = DB::getDB()->query_first( "SELECT COUNT(laufzettelID) AS zubestaetigen FROM laufzettel WHERE laufzettelDatum >= CURDATE() AND laufzettelID IN (SELECT laufzettelID FROM laufzettel_stunden WHERE laufzettelLehrer LIKE '" . DB::getSession()->getTeacherObject()->getKuerzel() . "' AND laufzettelZustimmung=0)" );
				
				if ($zuBestaetigen[0] > 0) {
					if ($zuBestaetigen[0] == 1) {
						$nummer = "Ein";
						$verb = "wartet";
					} else {
						$nummer = $zuBestaetigen[0];
						$verb = "warten";
					}
					$infoLaufzettel = "<a href=\"index.php?page=laufzettel&mode=myLaufzettel\" class=\"btn btn-xs btn-info\"><i class=\"fa fa-check\"></i> " . $nummer . " Laufzettel $verb auf Ihre Zustimmung</a>";
				} else
					$infoLaufzettel = "";
			} else {
				$infoLaufzettel = "";
			}
			
			// Message Info
			
			$infoMessages = "";
			$countMessage = 0;
			if( DB::isLoggedIn() && Message::userHasUnreadMessages() ) {
				$countMessage = Message::getUnreadMessageNumber(DB::getSession()->getUser(), "POSTEINGANG", 0);
				if(DB::getSettings()->getBoolean('messages-banner-new-messages')) {
					$infoMessages = "<a href=\"index.php?page=MessageInbox&folder=POSTEINGANG\" class=\"btn btn-danger btn-xs\"><i class=\"fa fa-envelope fa-spin\"></i> $countMessage ungelesene Nachricht" . (($countMessage > 1) ? "en" : "") . "</a>";
				} else {
					$infoMessages = "";
				} 
			}

			// Fremdsession
			
			if(DB::isLoggedIn()) {
				$fremdlogin = Fremdlogin::getMyFremdlogin();
				if($fremdlogin != null) {
					if($fremdlogin->getAdminUser() != null) {
						$fremdloginUser = $fremdlogin->getAdminUser()->getDisplayNameWithFunction();
					} else {
						$fremdloginUser = "n/a";
					} 
					if($fremdlogin->getAdminUser() != null) {
						$fremdloginUserID = $fremdlogin->getAdminUser()->getUserID();
					} else {
						$fremdloginUserID = "n/a";
					} 
					$fremdloginNachricht = $fremdlogin->getMessage();
					$fremdloginTime = functions::makeDateFromTimestamp($fremdlogin->getTime());
					$fremdloginID = $fremdlogin->getID();
				}
				if(DB::getSession()->isDebugSession()) {
						$debugSession = true;
				} else {
						$debugSession = false;
				}
			}
			

			// Is Admin ?
			
			if( DB::isLoggedIn()
				&& $this->hasAdmin()
				&& ( DB::getSession()->isAdmin() || DB::getSession()->isMember($this->getAdminGroup())) ) {
				$isAdmin = true;
			} else {
				$isAdmin = false;
			}

			// Render Header

			eval ( "\$this->header =  \"" . DB::getTPL ()->get ( 'header/header' ) . "\";" );

		}
	}


	/**
	 * Render Extension Template
	 * 
	 * @param page String
	 * @param scripts Array
	 * @param data Array
	 */
	public function render($arg) {

		// set default view/tmpl
		if (!$arg['tmpl'] && !$arg['tmplHTML']) {
			$arg['tmpl'] = 'default';
		}
		$path = PATH_EXTENSION.'tmpl'.DS;

		if ( $arg['tmplHTML'] || file_exists($path.$arg['tmpl'].'.tmpl.php')  ) {
			echo $this->header;

			// check if global menu
			if ( !isset($arg['submenu']) ) {
                if ($this->extension['json']->submenu) {
                    $sub = $this->extension['json']->submenu;
                } else if ($this->extension['json']['submenu']) {
                    $sub = $this->extension['json']['submenu'];
                };
				if ( isset($this->extension['json']) && isset($sub) ) {
					$arg['submenu'] = (array)$sub;
				}
			}
			// render submenu and dropdown
			if ($arg['submenu'] || $arg['dropdown']) {
				echo $this->makeSubmenu($arg['submenu'], $arg['dropdown']);
			}

			if ($arg['tmplHTML']) {
				echo $arg['tmplHTML'];
			} else {

				// Check for tmpl Overrights
				if ( $this->request['page']
				&& file_exists(PATH_TMPL_OVERRIGHTS.'extensions'.DS.$this->request['page'].DS.$arg['tmpl'].'.tmpl.php') ) {
					include_once(PATH_TMPL_OVERRIGHTS.'extensions'.DS.$this->request['page'].DS.$arg['tmpl'].'.tmpl.php');
				} else {
                    if (count($arg['vars']) >= 1) {
                        foreach($arg['vars'] as $key => $var) {
                            // TODO: better way?
                            if ($key && $var) {
                                switch (gettype($var)) {
                                    case "integer":
                                        eval("\$".$key." = ".$var."; ");
                                        break;
                                    default:
                                    case "string":
                                    eval("\$".$key." = '".$var."'; ");
                                        break;
                                    case "array":
                                        eval("\$".$key." = '".json_encode($var)."'; ");
                                        break;
                                    case "boolean":
                                        eval("\$".$key." = ".$var."; ");
                                        break;
                                }
                            }

                        }
                    }
					include_once($path.$arg['tmpl'].'.tmpl.php');
				}
			}
			
			// render Data for JavaScript
			if ($arg['data']) {
				echo $this->getScriptData($arg['data']);
			}

			// import JavaScript Files
			if ($arg['scripts']) {
				echo $this->getScript($arg['tmpl'], $arg['scripts']);
			}

		} else {
			new errorPage('Missing Template File');
			exit;
		}
	}


	/**
	 * get Extension JSON
	 * 
	 */
	public static function getExtensionJSON($path = false) {

        if (!$path) {
            if (!PATH_EXTENSION || PATH_EXTENSION == 'PATH_EXTENSION') {
                return false;
            }
            $path = PATH_EXTENSION.DS.'extension.json';
        }
		if ( file_exists($path) ) {
			$file = file_get_contents($path);
			$json = (array)json_decode($file);
			if ($json) {
				return $json;
			}
		}
		return false;
	}



	/**
	 * Load PHP Variables to JavaScript
	 * 
	 * @param data Array
	 */
	private function getScriptData($data){
		if ($data) {
			return '<script>var globals = '.json_encode($data).';</script>';
		}
		return '<script>var globals = {};</script>';
	}


	/**
	 * Get JavaScript Scripts Files
	 * 
	 * @param page String
	 * @param scripts Array
	 */
	private function getScript($view, $scripts ){

		if ( !$scripts || count($scripts) <= 0 ) {
			return false;
		}
		$html = '';
		foreach( $scripts as $script ) {
			if (file_exists($script)) {
				$file = file_get_contents($script);
				if ($file) {
					$html .= '<script>'.$file.'</script>';
				}
			}
		}
		return $html;
	}


	/**
	 * render login Status for Headerbar
	 */
	private function prepareHeaderBar($mainGroup) {
		
		if($mainGroup && DB::isLoggedIn()) {
			$displayName = DB::getSession()->getData('userFirstName')." ".DB::getSession()->getData('userLastName');
			$image = DB::getDB()->query_first("SELECT uploadID FROM image_uploads WHERE uploadUserName LIKE '" . DB::getSession()->getData("userName") . "'");
			if($image['uploadID'] > 0) {
				$this->userImage = "index.php?page=userprofileuserimage&getImage=profile";
			} else {
				$this->userImage = "cssjs/images/userimages/default.png";
			} 
			eval("\$this->loginStatus = \"" . DB::getTPL()->get("header/loginStatusLoggedIn") . "\";");
	
		} else {
			$this->displayName = "Nicht angemeldet";
			eval("\$this->loginStatus = \"" . DB::getTPL()->get("header/loginStatusNotLoggedIn") . "\";");
		}
	}


	/**
	 * Hilfsfunktion f??r die Seiten, um zu ??berpr??fen, ob der aktuelle Benutzerzugriff hat, wenn der die Gruppe $groupName braucht
	 * @param unknown $groupName Ben??tigte Gruppe
	 */
	protected function checkAccessWithGroup($groupName) {

		$hasAccess = false;
		if($groupName && DB::isLoggedIn()) {
			if(in_array($groupName, DB::getSession()->getGroupNames())) {
				$hasAccess = true;
			}
		}
		if(!$hasAccess) {
			header("Location: index.php");
		}
	}

	/**
	 * Pr??ft, ob eine Person angemeldet ist.
	 */
	protected function checkLogin() {

		if(!DB::isLoggedIn()) {
			if(in_array($this->request['page'], requesthandler::getAllowedActions())) {
				$redirectPage = $this->request['page'];
			} else {
				$redirectPage = "index";
			}
			if($_REQUEST['message'] != "") {
				$message = "<div class=\"callout\"><p><strong>" . addslashes($_REQUEST['message']) . "</strong></p></div>";
			}
			$valueusername = "";
			eval("echo(\"".DB::getTPL()->get("login/index")."\");");
			PAGE::kill(false);
		}
	}



	/**
	 * Zeigt die Seite an.
	 */
	public abstract function execute();

	/**
	 * @deprecated
	 */
	public static function notifyUserAdded($userID) {

	}

	/**
	 * @deprecated Soll in einem Cron abgearbeitet werden.
	 */
	public static function notifyUserDeleted($userID) {

	}

	/**
	 * ??berpr??ft, ob der angegebene Classname aktiviert ist.
	 * @param String $name Classname
	 * @return boolean
	 */
	public static function isActive($name) {

		if($name::siteIsAlwaysActive()){
			return true;
		}
		if(sizeof(self::$activePages) == 0) {
			// Active Pages
			$pages = DB::getDB()->query("SELECT * FROM site_activation WHERE siteIsActive=1");
			while($p = DB::getDB()->fetch_array($pages)) {
				self::$activePages[] = $p['siteName'];
			}
			// Active Extensions
			$result = DB::getDB()->query('SELECT `id`,`name` FROM `extensions` WHERE `active` = 1 ');
			while($row = DB::getDB()->fetch_array($result)) {
				self::$activePages[] = $row['name'];
			}
		}
		if(sizeof($name::onlyForSchool()) > 0) {
			if(!in_array(DB::getGlobalSettings()->schulnummer, $name::onlyForSchool())) {
				return false;
			}
		}
		return in_array($name, self::$activePages);
	}
	

	public static function getActivePages() {
	    return self::$activePages;
	}

	public static function hasSettings() {
		return false;
	}

	public static function getSettingsDescription() {
		return [];
	}

	/**
	 * Return Extension Settings from getSettingsDescription()
	 */
	public function getSettings() {
		$settings = $this->getSettingsDescription();
		if ( count($settings) > 0  ) {
			foreach($settings as $key => $item) {
				$result = DB::getDB()->query_first('SELECT `settingValue` FROM `settings` WHERE `settingsExtension` = "'.$this->extension['folder'].'"  AND `settingName` = "'.$item['name'].'" ');
				if ( isset($result['settingValue']) ) {
					$settings[$key]['value'] = $result['settingValue'];
				}
			}
		}
		return $settings;
	}

	/**
	 * Liest den Displaynamen der Seite aus.
	 */
	public abstract static function getSiteDisplayName();

	/**
	 * @deprecated
	 */
	public static function getUserGroups() {
		return [];
	}

	/**
	 * Zeigt an, ob die Seite immer aktiviert sein muss.
	 * @return boolean true: Seite kann nicht deaktiviert werden.
	 */
	public static function siteIsAlwaysActive() {
		return false;
	}
	
	/**
	 * Gibt an, ob eine Seite von anderen abh??ngig ist. Dadurch k??nnen diese nicht deaktiviert werden solange abgeleitete Seiten aktiv sind.
	 * @return String[] Seitennamen
	 */
	public static function dependsPage() {
		return [];
	}

	/**
	 * Liste der Schulnummer, f??r die diese Funktion exklusiv ist.
	 * @return String[] Liste der Schulnummern, leer wenn f??r alle
	 */
	public static function onlyForSchool() {
		return [];
	}

	/**
	 * Setzt das Modul in den Auslieferungszustand zur??ck.
	 * @return boolean Erfolgsmeldung
	 */
	public static function resetPage() {
		return true;	// Sollte eine Seite keine R??cksetzmeldung haben, dann ist das Trotzdem ein Erfolg.
	}
	
	/**
	 * ??berpr??ft, ob die Seite eine Administration hat.
	 * @return boolean
	 */
	public static function hasAdmin() {
		return false;
	}
	
	/**
	 * Icon im Men??
	 * @return string
	 */
	public static function getAdminMenuIcon() {
		return 'fa fa-cogs';
	}
	
	/**
	 * Men??gruppe in der das Adminmodul angezeigt wird.
	 * @return string
	 */
	public static function getAdminMenuGroup() {
		return 'NULL';
	}
	
	/**
	 * Icon der Men??gruppe
	 * @return string
	 */
	public static function getAdminMenuGroupIcon() {
		return 'fa fa-cogs';	// Zahnrad
	}
	
	/**
	 * ??berpr??ft, ob die Seite eine Benutzeradministration hat.
	 * @return boolean
	 */
	public static function hasUserAdmin() {
		return false;
	}
	
	/**
	 * Liest die Gruppe aus, die Zugriff auf die Administration des Moduls hat.
	 * @return String Gruppenname als String
	 */
	public static function getAdminGroup() {
		return self::$adminGroupName;
	}

	/**
	 * Setzt die Admin Gruppe als String
	 * @param String Gruppenname als String
	 */
	public static function setAdminGroup($str) {
		if ($str) {
			self::$adminGroupName = $str;
		}
	}


	/**
	 * Gibt den Gruppennamen f??r die ACL Rechte zur??ck
	 * @return String Gruppenname als String
	 */
	public static function getAclGroup() {
		if (self::$aclGroupName) {
			return self::$aclGroupName;
		}
		return get_called_class();
	}

	/**
	 * Setzt die ACL Gruppe als String
	 * @param String Gruppenname als String
	 */
	public static function setAclGroup($str) {
		if ($str) {
			self::$aclGroupName = $str;
		}
	}
	

	/**
	 * Zeigt die Administration an. (Nur Bereich innerhalb des Main Body)
	 * @param $selfURL URL zu sich selbst zur??ck (weitere Parameter k??nnen vom Script per & angeh??ngt werden.)
	 * @return HTML
	 */
	public static function displayAdministration($selfURL) {
		return "";
	}
	
	/**
	 * Zeigt die Benutzeradministration an. (Nur Bereich innerhalb von einem TabbedPane, keinen Footer etc.)
	 * @param $selfURL URL zu sich selbst zur??ck (weitere Parameter k??nnen vom Script per & angeh??ngt werden.)
	 */
	public static function displayUserAdministration($selfURL) {
		return "";
	}
	
	/**
	 * Ben??tigt das Modul eine zweiFaktor Authentifizierung.
	 * <i>Noch nicht implementiert!</i>
	 * @return boolean JaNein
	 */
	public static function need2Factor() {
		return false;
	}
	
	/**
	 * Archiviert das komplette Modul. (R??ckgabe frei, je nach Modul)
	 * <i>Noch nicht implementiert!</i>
	 * @return boolean Erfolgreich?
	 */
	public static function archiveDataForSchoolYear() {
		return false;
	}
	
	/**
	 * R??umt das Modul regelm????ig per Cron auf.
	 * @return Erfolgsmeldung
	 */
	public static function cronTidyUp() {
		return true;
	}
	
	/**
	 * 
	 * @param user $user Benutzer
	 * @return boolean Zugriff
	 */
	public static function userHasAccess($user) {
		return false;
	}
	
	
	/**
	 * Gibt an, welche Aktion beim Schuljahreswechsel durchgef??hrt wird. (Leer, wenn keine Aktion erfolgt.)
	 * @return String
	 */
	public static function getActionSchuljahreswechsel() {
		return '';
	}
	
	/**
	 * F??hrt den Schuljahreswechsel durch.
	 * @param String $sqlDateFirstSchoolDay Erster Schultag
	 */
	public static function doSchuljahreswechsel($sqlDateFirstSchoolDay) {
		
	}


    /**
     * Gibt das Submenu zur??ck
     * @return Array
     */
    public  function getSubmenu() {
        $submenuHTML = '';
        if ($this->submenu) {
            foreach($this->submenu as $item) {
                if ($item['href'] && $item['label']) {
                    $submenuHTML .= '<a href="'.$item['href'].'" alt="" title="" class="'.$item['labelClass'].'">'.$item['label'].'</a>';
                }
            }
        }
        return $submenuHTML;

    }

    /**
     * Speichert das Submenu
     * @return Array
     */
    public  function setSubmenu($submenu) {
        if($submenu) {
            $this->submenu = $submenu;
        }
    }


	/**
	 * Access Control List
	 * @return acl
	 */


	/**
	* @deprecated:  use getAclGroup
	*/
	public function aclModuleName() {
		return get_called_class();
	}

	public function acl() {
		if (DB::getSession()) {
			$userID = DB::getSession()->getUser();
		}
		$moduleClass = $this->getAclGroup();
		if ($userID && $moduleClass) {
			
			$this->acl = ACL::getAcl($userID, $moduleClass, false, $this->getAdminGroup() );
		}
	}

	public function getAclAll() {
		if (!$this->acl) {
			$this->acl();
		}
		return $this->acl;
	}

	public function getAcl() {
		if (!$this->acl) {
			$this->acl();
		}
		return [ 'rights' => $this->acl['rights'], 'owne' => $this->acl['owne'] ];
	}

	public function getAclRead() {
		return $this->acl['rights']['read'];
	}

	public function getAclWrite() {
		return $this->acl['rights']['write'];
	}

	public function getAclDelete() {
		return $this->acl['rights']['delete'];
	}

	/**
	 * Ist das Modul im Beta Test?
	 * @return Boolean
	 */
	public static function isBeta() {
		return false;
	}


	/**
	 * Generiert das Submenu (Array to HTML)
	 * 
	 * @param submenu Array
	 * @param dropdown Array
	 * @return String (HTML)
	 */
	private function makeSubmenu($submenu, $dropdown) {

		$html = '<div class="flex-row">';

		// Submenu
		$html .= '<div class="flex-3 page-submenue" style="height: 3.2rem;">';
		if (is_array($submenu) && count($submenu) >= 1) {
			foreach($submenu as $item) {
				$item = (array)$item;
				$active = '';
				if ( $item['admin'] == 'true' && $this->isAnyAdmin == false ) {
					continue;
				}
				if ($item['url'] && $item['title']) {
                    $link = 'index.php?page='.$item['url']->page;
                    $params_str = [];
                    if ($item['url']->params && count(get_object_vars($item['url']->params)) ) {
                        foreach($item['url']->params as $params_key => $params_link) {
                            $params_str[] = $params_key.'='.$params_link;
                        }
                        $params_str = join('&',$params_str);
                        $link .= '&'.$params_str;
                    }
					if (DS.$link == URL_FILE) {
						$active = 'active';
					}
					$html .= '<a href="'.$link.'"  class="margin-r-xs '.$active.'">';
					if ($item['icon']) {
						$html .= '<i class="margin-r-s '.$item['icon'].'"></i>';
					}
					$html .= $item['title'].'</a>';
				}
			
				
			}
		}
		$html .= '</div>';

		// Dropdown
		if (is_array($dropdown) && count($dropdown) >= 1) {
			$html .= '<div class="flex-1 page-dropdownMenue ">
									<button class="dropbtn"><i class="fas fa-ellipsis-v"></i></button>
									<div class="page-dropdownMenue-content">';
			foreach($dropdown as $item) {
				$html .= '<a href="'.$item['url'].'" class="margin-r-xs active">';
				if ($item['icon']) {
					$html .= '<i class="margin-r-s '.$item['icon'].'"></i>';
				}
				$html .= $item['title'].'</a>';
			}
			$html .= '</div></div>';
		}

		$html .= '</div>';
		return $html;
	}

	/**
	 * Getter Request
	 * 
	 * @return Array
	 */
	public function getRequest() {
		if ($this->request) {
			return $this->request;
		}
		return [];
	}

	/**
	 * Redirect to same Page without url parameter z.b. &task=...
	 * 
	 * @param String
	 */
	public function reloadWithoutParam($str) {

		if ($str) {
			$parsed = parse_url($_SERVER['REQUEST_URI']);
			$query = $parsed['query'];
			parse_str($query, $params);
			unset($params[$str]);
			$string = http_build_query($params);
			header('Location: index.php?'.$string);
		} else {
			exit;
		}

	}
}
