<?php
require_once 'IDatabaseManager.php';
require_once 'DatabaseManager.php';
require_once 'View.php';
require_once 'ImportCsv.php';
require_once 'ExportExcel.php';
require_once 'FileHandler.php';
require_once 'Calculations.php';
require_once 'DebugManager.php';

class MainController {
    private $databaseManager;
    private $view;
    private $importCsv;
    private $exportExcel;
    private $fileHandler;
    private $calculations;
    private $debug_messages = [];

    /**
     * Constructeur de la classe MainController.
     *
     * @param IDatabaseManager $databaseManager Instance de la classe DatabaseManager.
     * @param View $view Instance de la classe View.
     * @param ImportCsv $importCsv Instance de la classe ImportCsv.
     * @param ExportExcel $exportExcel Instance de la classe ExportExcel.
     * @param Calculations $calculations Instance de la classe Calculations.
     * @param FileHandler $fileHandler Instance de la classe FileHandler.
     */
    public function __construct(IDatabaseManager $databaseManager, $view, $importCsv, $exportExcel, $calculations, $fileHandler) {
        $this->databaseManager = $databaseManager;
        $this->view = $view;
        $this->importCsv = $importCsv;
        $this->exportExcel = $exportExcel;
        $this->calculations = $calculations;
        $this->fileHandler = $fileHandler;
    }

    /**
     * Affiche la page principale du plugin.
     */
    public function afficher_page() {
        $debugManager = DebugManager::getInstance();

        $this->traiter_formulaire_import();
        $this->gerer_generation_excel();
        $this->gerer_telechargement_excel();
        $this->gerer_validation_modifications();

        $chauffeurs = $this->databaseManager->obtenir_noms_chauffeurs();
        $selected_chauffeur = $this->obtenir_chauffeur_selectionne();
        $results = $this->databaseManager->obtenir_donnees($selected_chauffeur);

        $total_heures_travaillees_filtre = $this->calculations->calculer_total_heures_travaillees($results);
        foreach ($results as $row) {
            $row->est_superieur_a_12_heures = $this->calculations->est_superieur_a_12_heures($row->heures_travaillees);
        }

        $total_tickets_restaurant_par_chauffeur = $this->calculations->calculer_totaux_tickets_restaurant($results);
        $totaux = $this->databaseManager->obtenir_totaux();
        $results_chauffeur_max = $this->databaseManager->obtenir_donnees($totaux['chauffeur_max_heures']);
        $total_heures_chauffeur_max = $this->calculations->calculer_total_heures_travaillees($results_chauffeur_max);

        $this->view->afficher_page_principale(
            $chauffeurs,
            $selected_chauffeur,
            $results,
            $total_heures_travaillees_filtre,
            $total_heures_chauffeur_max,
            $total_tickets_restaurant_par_chauffeur,
            $totaux
        );
    }

    /**
     * Gère la génération du fichier Excel.
     */
    public function gerer_generation_excel() {
        if (isset($_POST['generate_excel'])) {
            $total_heures_travaillees = $this->calculations->calculer_total_heures_travaillees($this->databaseManager->obtenir_donnees());
            $spreadsheet = $this->exportExcel->exporter_vers_excel($total_heures_travaillees);
            $this->fileHandler->enregistrer_fichier_excel($spreadsheet);
        }
    }

    /**
     * Gère le téléchargement du fichier Excel.
     */
    public function gerer_telechargement_excel() {
        if (isset($_POST['download_excel'])) {
            $this->fileHandler->telecharger_excel();
        }
    }

    /**
     * Gère la validation des modifications.
     */
    private function gerer_validation_modifications() {
        if (isset($_POST['valider_modifications'])) {
            $this->valider_modifications();
            $this->recalculer_totaux();
        }
    }

    /**
     * Récupère le nom du chauffeur sélectionné pour le filtrage.
     *
     * @return string Nom du chauffeur sélectionné.
     */
    private function obtenir_chauffeur_selectionne() {
        return isset($_POST['filter_chauffeur']) ? $_POST['filter_chauffeur'] : (isset($_POST['selected_chauffeur']) ? $_POST['selected_chauffeur'] : '');
    }

    /**
     * Valide les modifications apportées aux données.
     */
    private function valider_modifications() {
        if (isset($_POST['heure_debut'])) {
            foreach ($_POST['heure_debut'] as $id => $heure_debut) {
                $heure_debut = sanitize_text_field($heure_debut);
                $coupure = sanitize_text_field($_POST['coupure'][$id]);
                $heure_fin = sanitize_text_field($_POST['heure_fin'][$id]);
    
                // Transformer les valeurs en format HH:MM
                $heure_debut = $this->transformer_en_format_hhmm($heure_debut);
                $coupure = $this->transformer_en_format_hhmm($coupure);
                $heure_fin = $this->transformer_en_format_hhmm($heure_fin);
    
                // Vérifier si les valeurs sont au format HH:MM
                if (!$this->est_format_heure_valide($heure_debut) || !$this->est_format_heure_valide($coupure) || !$this->est_format_heure_valide($heure_fin)) {
                    // Ajouter un message de débogage ou gérer l'erreur
                    $debugManager = DebugManager::getInstance();
                    $debugManager->addMessage("Format d'heure invalide pour l'ID $id");
                    continue; // Passer à l'itération suivante
                }
    
                // Recalculer les heures travaillées
                $heures_travaillees = $this->calculations->calculer_heures_travaillees($heure_debut, $heure_fin, $coupure);
    
                $ticket_restaurant = isset($_POST['ticket_restaurant'][$id]) ? 1 : 0;
                $commentaire = sanitize_text_field($_POST['commentaire'][$id]);

                $data = [
                    'heure_debut' => $heure_debut,
                    'coupure' => $coupure,
                    'heure_fin' => $heure_fin,
                    'heures_travaillees' => $heures_travaillees,
                    'commentaire' => $commentaire,
                    'ticket_restaurant' => $ticket_restaurant
                ];
                $where = ['id' => intval($id)];
                $this->databaseManager->update_donnees($data, $where);
            }
        }
    }
    
    /**
     * Vérifie si une chaîne est au format HH:MM.
     *
     * @param string $heure La chaîne à vérifier.
     * @return bool True si la chaîne est au format HH:MM, False sinon.
     */
    private function est_format_heure_valide($heure) {
        return preg_match('/^\d{2}:\d{2}$/', $heure) === 1;
    }
    
    /**
     * Transforme une chaîne de temps en format HH:MM.
     *
     * @param string $time La chaîne de temps à transformer.
     * @return string La chaîne de temps transformée.
     */
    private function transformer_en_format_hhmm($time) {
        if (empty($time)) {
            return '00:00';
        }
        if (is_numeric($time)) {
            return sprintf('%02d:00', intval($time));
        }
        return $time;
    }

    /**
     * Traite le formulaire de validation des modifications.
     */
    public function traiter_formulaire_modifications() {
        if (isset($_POST['valider_modifications'])) {
            $this->valider_modifications();
            $this->recalculer_totaux();
        }
    }

    /**
     * Affiche les informations du chauffeur sélectionné.
     *
     * @param string $selected_chauffeur Nom du chauffeur sélectionné.
     */
    public function afficher_informations_chauffeur_selectionne($selected_chauffeur) {
        $results = $this->databaseManager->obtenir_donnees($selected_chauffeur);
        $total_heures_travaillees_filtre = $this->calculations->calculer_total_heures_travaillees($results);
        $total_tickets_restaurant_par_chauffeur = $this->calculations->calculer_total_tickets_restaurant_par_chauffeur($results);
        $this->view->afficher_informations_chauffeur_selectionne($selected_chauffeur, $total_heures_travaillees_filtre, $total_tickets_restaurant_par_chauffeur);
    }

    /**
     * Traite le formulaire d'importation CSV.
     */
    public function traiter_formulaire_import() {
        if (isset($_POST['submit'])) {
            if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == UPLOAD_ERR_OK) {
                $csv_file = $_FILES['csv_file']['tmp_name'];
                $this->debug_messages = $this->importCsv->importer_csv($csv_file);
            } else {
                $this->debug_messages[] = "Veuillez sélectionner un fichier CSV à importer.";
            }
        }
    }

    /**
     * Recalcule les totaux et met à jour les propriétés de la classe.
     */
    private function recalculer_totaux() {
        $debugManager = DebugManager::getInstance();

        // Supprimer les lignes vides
        //$this->databaseManager->supprimer_lignes_vides();

        $totaux = $this->calculations->recalculer_totaux($this->databaseManager);

        $this->chauffeur_max_heures = $totaux['chauffeur_max_heures'];
        $this->total_heures_travaillees = $totaux['total_heures_travaillees'];
        $this->moyenne_heures_travaillees = $totaux['moyenne_heures_travaillees'];
        $this->total_tickets_restaurant_par_chauffeur = $totaux['total_tickets_restaurant_par_chauffeur'];

        // Mettre à jour les totaux dans la base de données
        $this->databaseManager->update_totaux($totaux);
    }

    /**
     * Traite la suppression d'un chauffeur.
     */
    public function gerer_suppression_chauffeur() {
        $debugManager = DebugManager::getInstance();
        
        if (isset($_POST['supprimer_chauffeur']) && isset($_POST['chauffeur_a_supprimer'])) {
            $chauffeur_a_supprimer = sanitize_text_field($_POST['chauffeur_a_supprimer']);
            
            // Supprimer les données du chauffeur
            $result = $this->databaseManager->supprimer_donnees_par_chauffeur($chauffeur_a_supprimer);
            
            if ($result) {
                // Recalculer les totaux après la suppression
                $this->recalculer_totaux();
            } else {
                $debugManager->addMessage("gerer_suppression_chauffeur : Échec de la suppression des données du chauffeur.");
            }
        } else {
            // Ajouter un message de débogage si les paramètres POST ne sont pas définis
            $debugManager->addMessage("gerer_suppression_chauffeur : Paramètres POST manquants pour la suppression du chauffeur.");
        }
    }
}