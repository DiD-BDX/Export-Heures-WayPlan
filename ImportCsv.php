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
        // Convertir le fichier CSV en UTF-8
        $this->convertir_csv_en_utf8($csv_file);
    
        // Vider la table avant l'importation
        $this->databaseManager->vider_table();
    
        // Vérifier l'encodage du fichier CSV
        $file_content = file_get_contents($csv_file);
        if (!mb_check_encoding($file_content, 'UTF-8')) {
            $debugManager->addMessage("Le fichier CSV n'est pas encodé en UTF-8.");
            return $debugManager->getMessages();
        }
    
        // Ouvrir le fichier CSV
        if (($handle = fopen($csv_file, 'r')) !== FALSE) {
            $row = 1;
            // Lire chaque ligne du fichier CSV
            while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
                // Ignorer la première ligne (en-têtes)
                if ($row > 1) {
                    $nom_chauffeur = preg_replace('/\s+/', ' ', trim($data[0]));
                    
                    // Vérifier si la ligne doit être ignorée
                    if (empty($nom_chauffeur) || $nom_chauffeur === "Nom chauffeur" || $nom_chauffeur === "_______________") {
                        $row++;
                        continue;
                    }
    
                    $date_mission = $data[1];
                    $heure_debut = $data[2];
                    $coupure = '00:30'; // Coupure fixe de 30 minutes
                    $heure_fin = $data[3];
    
                    // Vérifier la validité des heures de début et de fin
                    if (empty($heure_debut) || empty($heure_fin) || strtotime($heure_debut) === false || strtotime($heure_fin) === false) {
                        $row++;
                        continue;
                    }
    
                    // Calculer les heures travaillées
                    $heures_travaillees = $this->calculations->calculer_heures_travaillees($heure_debut, $heure_fin, $coupure);
    
                    // Vérifier l'encodage du nom du chauffeur
                    if (!mb_check_encoding($nom_chauffeur, 'UTF-8')) {
                        $row++;
                        continue;
                    }
    
                    // Insérer les données dans la base de données
                    try {
                        $this->databaseManager->inserer_donnees([
                            'nom_chauffeur' => $nom_chauffeur,
                            'date_mission' => $date_mission,
                            'heure_debut' => $heure_debut,
                            'coupure' => $coupure,
                            'heure_fin' => $heure_fin,
                            'heures_travaillees' => $heures_travaillees
                        ]);
                    } catch (Exception $e) {
                        $debugManager->addMessage("Erreur lors de l'insertion des données pour la ligne $row: " . $e->getMessage());
                    }
                }
                $row++;
            }
            fclose($handle);
        }
    
        return $debugManager->getMessages();
    }
}