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
     * Importe les données d'un fichier CSV dans la base de données.
     *
     * @param string $csv_file Chemin vers le fichier CSV à importer.
     * @return array Tableau des messages de débogage.
     */
    public function importer_csv($csv_file) {
        $debugManager = DebugManager::getInstance();
        // Vider la table avant l'importation
        $this->databaseManager->vider_table();

        // Ouvrir le fichier CSV
        if (($handle = fopen($csv_file, 'r')) !== FALSE) {
            $row = 1;
            // Lire chaque ligne du fichier CSV
            while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
                // Ignorer la première ligne (en-têtes)
                if ($row > 1) {
                    $nom_chauffeur = $data[0];
                    $date_mission = $data[1];
                    $heure_debut = $data[7];
                    $coupure = '00:30'; // Coupure fixe de 30 minutes
                    $heure_fin = $data[8];

                    // Vérifier la validité des heures de début et de fin
                    if (empty($heure_debut) || empty($heure_fin) || strtotime($heure_debut) === false || strtotime($heure_fin) === false) {
                        //$debugManager->addMessage("Heure invalide pour la ligne $row: heure_debut = $heure_debut, heure_fin = $heure_fin");
                        $row++;
                        continue;
                    }

                    // Calculer les heures travaillées
                    $heures_travaillees = $this->calculations->calculer_heures_travaillees($heure_debut, $heure_fin, $coupure);

                    // Vérifier l'encodage du nom du chauffeur
                    if (!mb_check_encoding($nom_chauffeur, 'UTF-8')) {
                        //$debugManager->addMessage("Nom du chauffeur invalide pour la ligne $row: $nom_chauffeur");
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