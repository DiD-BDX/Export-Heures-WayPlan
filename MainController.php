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

        $this->gerer_import_csv();
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

        // Debug message
        //$debugManager->addMessage('nom du Chauffeur Max dans afficher_page de MainController.php: ' . $totaux['chauffeur_max_heures']);
        //$debugManager->addMessage('Total Heures Chauffeur Max dans afficher_page de MainController.php: ' . $total_heures_chauffeur_max);

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
     * Gère l'importation du fichier CSV.
     */
    private function gerer_import_csv() {
        if (isset($_POST['submit'])) {
            if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == UPLOAD_ERR_OK) {
                $csv_file = $_FILES['csv_file']['tmp_name'];
                $totaux = $this->calculations->recalculer_totaux($this->databaseManager);
            } else {
                $this->debug_messages[] = "Veuillez sélectionner un fichier CSV à importer.";
            }
        }
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
                $ticket_restaurant = isset($_POST['ticket_restaurant'][$id]) ? 1 : 0;
                $data = [
                    'heure_debut' => $heure_debut,
                    'coupure' => $coupure,
                    'heure_fin' => $heure_fin,
                    'ticket_restaurant' => $ticket_restaurant
                ];
                $where = ['id' => intval($id)];
                $this->databaseManager->update_donnees($data, $where);
            }
        }
    }

    /**
     * Recalcule les totaux et met à jour les propriétés de la classe.
     */
    private function recalculer_totaux() {
        $totaux = $this->calculations->recalculer_totaux($this->databaseManager);
        $this->chauffeur_max_heures = $totaux['chauffeur_max_heures'];
        $this->total_heures_travaillees = $totaux['total_heures_travaillees'];
        $this->moyenne_heures_travaillees = $totaux['moyenne_heures_travaillees'];
        $this->total_tickets_restaurant_par_chauffeur = $totaux['total_tickets_restaurant_par_chauffeur'];
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
                $this->recalculer_totaux();
            } else {
                $this->debug_messages[] = "Veuillez sélectionner un fichier CSV à importer.";
            }
        }
    }

    /**
     * Traite la suppression d'un guide.
     */
    public function gerer_suppression_chauffeur() {
        if (isset($_POST['supprimer_chauffeur']) && isset($_POST['chauffeur_a_supprimer'])) {
            $chauffeur_a_supprimer = sanitize_text_field($_POST['chauffeur_a_supprimer']);
            $this->databaseManager->supprimer_donnees_par_chauffeur($chauffeur_a_supprimer);
            $this->recalculer_totaux(); // Recalculer les totaux après la suppression
        }
    }
}