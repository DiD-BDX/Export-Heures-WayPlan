<?php
require_once 'IDatabaseManager.php';
require_once 'DatabaseManager.php';
require_once 'View.php';
require_once 'ImportCsv.php';
require_once 'ExportExcel.php';
require_once 'FileHandler.php';
require_once 'Calculations.php';
require_once 'MainController.php';
require_once 'DebugManager.php';

class ExportHeuresPlugin {
    private $databaseManager;
    private $view;
    private $importCsv;
    private $exportExcel;
    private $fileHandler;
    private $calculations;
    private $mainController;

    public function __construct() {
        global $wpdb;
        $this->databaseManager = new DatabaseManager($wpdb, $wpdb->prefix . 'heures');
        $this->view = new View();
        $this->calculations = new Calculations();
        $this->importCsv = new ImportCsv($this->databaseManager, $this->calculations);
        $this->exportExcel = new ExportExcel($this->databaseManager);
        $this->fileHandler = new FileHandler();
        $this->mainController = new MainController($this->databaseManager, $this->view, $this->importCsv, $this->exportExcel, $this->calculations, $this->fileHandler);
    }

    /**
     * Ajouter les éléments de menu pour le plugin Export Heures.
     */
    public function export_heures_menu() {
        add_menu_page(
            'Export Heures', // Titre de la page
            'Export Heures', // Titre du menu
            'manage_options', // Capacité requise pour accéder à cette page
            'export-heures', // Slug de la page
            [$this, 'export_heures_page'] // Fonction de rappel pour afficher le contenu de la page
        );
    }

    /**
     * Fonction d'installation du plugin.
     * Crée la table nécessaire dans la base de données.
     */
    public function export_heures_install() {
        $this->databaseManager->installer();
    }

    /**
     * Afficher la page principale du plugin Export Heures.
     */
    public function export_heures_page() {
        $this->mainController->afficher_page();
    }
}