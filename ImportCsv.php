<?php
class ImportCsv {
    private $databaseManager;
    private $calculations;

    /**
     * Constructeur de la classe ImportCsv.
     *
     * @param IDatabaseManager $databaseManager Instance de l'interface IDatabaseManager.
     * @param Calculations $calculations Instance de la classe Calculations.
     */
    public function __construct(IDatabaseManager $databaseManager, Calculations $calculations) {
        $this->databaseManager = $databaseManager;
        $this->calculations = $calculations;
    }

    /**
     * Convertit un fichier CSV en UTF-8.
     *
     * @param string $csv_file Chemin vers le fichier CSV à convertir.
     */
    private function convertir_csv_en_utf8($csv_file) {
        $file_content = file_get_contents($csv_file);
        $encoding = mb_detect_encoding($file_content, 'UTF-8, ISO-8859-1', true);
        if ($encoding !== 'UTF-8') {
            $file_content = mb_convert_encoding($file_content, 'UTF-8', $encoding);
            file_put_contents($csv_file, $file_content);
        }
    }

    /**
     * Importe les données d'un fichier CSV dans la base de données.
     *
     * @param string $csv_file Chemin vers le fichier CSV à importer.
     * @return array Tableau des messages de débogage.
     */
    public function importer_csv($csv_file) {
        $debugManager = DebugManager::getInstance();
        $this->convertir_csv_en_utf8($csv_file);
        $this->databaseManager->vider_table();
    
        if (!$this->verifier_encodage_csv($csv_file, $debugManager)) {
            return $debugManager->getMessages();
        }
    
        if (($handle = fopen($csv_file, 'r')) !== FALSE) {
            $this->traiter_lignes_csv($handle, $debugManager);
            fclose($handle);
        }
    
        $this->calculations->recalculer_totaux($this->databaseManager);
        return $debugManager->getMessages();
    }
    
    private function verifier_encodage_csv($csv_file, $debugManager) {
        $file_content = file_get_contents($csv_file);
        if (!mb_check_encoding($file_content, 'UTF-8')) {
            $debugManager->addMessage("Le fichier CSV n'est pas encodé en UTF-8.");
            return false;
        }
        return true;
    }
    
    private function traiter_lignes_csv($handle, $debugManager) {
        $row = 1;
        while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
            if ($row > 1) {
                $this->traiter_ligne_csv($data, $row, $debugManager);
            }
            $row++;
        }
    }
    
    private function traiter_ligne_csv($data, $row, $debugManager) {
        $nom_chauffeur = preg_replace('/\s+/', ' ', trim($data[0]));
    
        if ($this->doit_ignorer_ligne($nom_chauffeur, $data)) {
            return;
        }
    
        $date_mission = $data[1];
        $heure_debut = $data[2];
        $coupure = '00:30';
        $heure_fin = $data[3];
        $commentaire = $data[13];
    
        if (!$this->heures_valides($heure_debut, $heure_fin)) {
            return;
        }
    
        $heures_travaillees = $this->calculations->calculer_heures_travaillees($heure_debut, $heure_fin, $coupure);
    
        if (!mb_check_encoding($nom_chauffeur, 'UTF-8')) {
            return;
        }
    
        $this->inserer_donnees([
            'nom_chauffeur' => $nom_chauffeur,
            'date_mission' => $date_mission,
            'heure_debut' => $heure_debut,
            'coupure' => $coupure,
            'heure_fin' => $heure_fin,
            'heures_travaillees' => $heures_travaillees,
            'commentaire' => $commentaire
        ], $row, $debugManager);
    }
    
    private function doit_ignorer_ligne($nom_chauffeur, $data) {
        return empty($nom_chauffeur) || $nom_chauffeur === "Nom chauffeur" || $nom_chauffeur === "_______________";
    }
    
    private function heures_valides($heure_debut, $heure_fin) {
        return !empty($heure_debut) && !empty($heure_fin) && strtotime($heure_debut) !== false && strtotime($heure_fin) !== false;
    }
    
    private function inserer_donnees($data, $row, $debugManager) {
        try {
            $this->databaseManager->inserer_donnees($data);
        } catch (Exception $e) {
            $debugManager->addMessage("Erreur lors de l'insertion des données pour la ligne $row: " . $e->getMessage());
        }
    }
}