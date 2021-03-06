<?php 

class eltern {
	/**
	 * 
	 * @var schueler[]
	 */
	private $schueler = array();

    /**
     * @var bool
     */
	private $schuelerInited = false;

    /**
     * @var int
     */
	private $userID = 0;

    /**
     * @var string
     */
	private $email = "";
	
	public function __construct($userID) {
	    $this->userID= $userID;
	}

	private function initSchueler() {
	    if($this->schuelerInited) return;

        $schuelers = DB::getDB()->query("SELECT * FROM eltern_email JOIN schueler ON eltern_email.elternSchuelerAsvID=schueler.schuelerAsvID WHERE elternUserID='" . $this->userID . "'");
        while($s = DB::getDB()->fetch_array($schuelers)) {
            $this->email = $s['elternEMail'];
            $this->schueler[] = new schueler($s);
        }

        $this->schuelerInited = true;
    }
	
	public function getKlassenAsArray() {
	    $this->initSchueler();
		$klassen = array();
		
		for($i = 0; $i < sizeof($this->schueler); $i++) {
			$klassen[] = $this->schueler[$i]->getKlasse();
		}
		
		return $klassen;
	}
	
	/**
	 * 
	 * @return klasse[]
	 */
	public function getKlassenObjectsAsArray() {

        $this->initSchueler();

        $klassen = array();


	    for($i = 0; $i < sizeof($this->schueler); $i++) {
	        $klassen[] = $this->schueler[$i]->getKlassenObjekt();
	    }
	    
	    return $klassen;
	}
	
	/**
	 * 
	 * @return schueler[]
	 */
	public function getMySchueler() {
        $this->initSchueler();
		return $this->schueler;
	}
	
	/**
	 * Liest die ASV IDs der Schüler des Elternteils aus.
	 * @return String[]
	 */
	public function getMySchuelerAsvIDs() {
        $this->initSchueler();
	    $ids = [];
	    for($i = 0; $i < sizeof($this->schueler); $i++) $ids[] = $this->schueler[$i]->getAsvID();
	    return $ids;
	}

    /**
     * Ermittelt alle ASVIDs der Kinder ohne zu überprüfen, ob diese existieren.
     * @return  string[]
     */
	public function getRawKinderASVIDs() {
        $this->initSchueler();
	    $asvIDs = [];

        $schuelers = DB::getDB()->query("SELECT elternSchuelerAsvID FROM eltern_email WHERE elternUserID='" . $this->userID . "'");
        while($s = DB::getDB()->fetch_array($schuelers)) {
            $asvIDs[] = $s['elternSchuelerAsvID'];
        }

        return $asvIDs;

    }

    /**
     * @param schueler $schueler
     */
	public function addSchueler($schueler) {
        DB::getDB()->query("INSERT INTO eltern_email (elternEMail, elternSchuelerAsvID, elternUserID) values('" . DB::getDB()->escapeString($this->email) . "','" . $schueler->getAsvID() . "','" . $this->userID . "') ON DUPLICATE KEY UPDATE elternSchuelerAsvID=elternSchuelerAsvID");
    }

    /**
     * @param schueler $schueler
     */
    public function removeSchueler($schueler) {

        $this->initSchueler();

        if(sizeof($this->schueler) == 1) {
            // Letzter Schüler wird entfernt
            // --> Benutzer löschen (Löscht auch Zuordnungen der Eltern)
            user::getUserByID($this->userID)->deleteUser();
        }
        else {
            DB::getDB()->query("DELETE FROM eltern_email WHERE 
            elternEMail='" . DB::getDB()->escapeString($this->email) . "'
            AND elternSchuelerAsvID='" . $schueler->getAsvID() . "'
            AND elternUserID='" . $this->userID . "'
            ");
        }


    }

    /**
     * @param string $asvID
     */
    public function removeSchuelerByASVID($asvID) {

        $this->initSchueler();

        $rawASVIds = $this->getRawKinderASVIDs();

        if(sizeof($rawASVIds) == 1) {
            // Letzter Schüler wird entfernt
            // --> Benutzer löschen (Löscht auch Zuordnungen der Eltern)
            user::getUserByID($this->userID)->deleteUser();
        }
        else {
            DB::getDB()->query("DELETE FROM eltern_email WHERE 
            elternEMail='" . DB::getDB()->escapeString($this->email) . "'
            AND elternSchuelerAsvID='" . $asvID . "'
            AND elternUserID='" . $this->userID . "'
            ");
        }


    }
}

