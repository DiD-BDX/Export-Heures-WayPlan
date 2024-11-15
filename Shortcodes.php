<?php
// shortcodes.php

/**
 * Liste des shortcodes créés et comment les utiliser :
 *
 * [export_heures] - Affiche la page principale du plugin Export Heures.
 * [generer_excel_button] - Affiche le bouton "Générer le fichier Excel".
 * [telecharger_excel_button] - Affiche le bouton "Télécharger le fichier Excel".
 * [formulaire_import_csv] - Affiche le formulaire d'importation de fichier CSV.
 * [filtrage_chauffeur] - Affiche les boutons de filtrage par chauffeur.
 * [chauffeur_selectionne] - Affiche les informations du chauffeur sélectionné.
 * [chauffeur_max] - Affiche les informations du chauffeur ayant travaillé le plus d'heures.
 * [tableau_donnees] - Affiche le tableau des données des heures travaillées.
 */

/**
 * Fonction d'assistance pour instancier toutes les classes nécessaires.
 *
 * @return array
 */
function get_instances() {
    global $wpdb;
    $databaseManager = new DatabaseManager($wpdb, $wpdb->prefix . 'heures');
    $view = new View();
    $calculations = new Calculations();
    $importCsv = new ImportCsv($databaseManager, $calculations);
    $exportExcel = new ExportExcel($databaseManager);
    $fileHandler = new FileHandler();
    $mainController = new MainController($databaseManager, $view, $importCsv, $exportExcel, $calculations, $fileHandler);
    return [
        'databaseManager' => $databaseManager,
        'view' => $view,
        'calculations' => $calculations,
        'importCsv' => $importCsv,
        'exportExcel' => $exportExcel,
        'fileHandler' => $fileHandler,
        'mainController' => $mainController
    ];
}

// Ajouter un shortcode pour afficher la page principale du plugin
add_shortcode('export_heures', 'export_heures_shortcode');

/**
 * Fonction de rappel pour le shortcode [export_heures].
 */
function export_heures_shortcode() {
    ob_start();
    $instances = get_instances();
    $instances['mainController']->afficher_page();
    return ob_get_clean();
}

// Ajouter un shortcode pour le bouton "Générer le fichier Excel"
add_shortcode('generer_excel_button', 'generer_excel_button_shortcode');

/**
 * Fonction de rappel pour le shortcode [generer_excel_button].
 */
function generer_excel_button_shortcode() {
    ob_start();
    $instances = get_instances();
    $instances['view']->afficher_bouton_generer_excel();
    return ob_get_clean();
}

// Ajouter un shortcode pour le bouton "Télécharger le fichier Excel"
add_shortcode('telecharger_excel_button', 'telecharger_excel_button_shortcode');

/**
 * Fonction de rappel pour le shortcode [telecharger_excel_button].
 */
function telecharger_excel_button_shortcode() {
    ob_start();
    $instances = get_instances();
    $instances['view']->afficher_bouton_telecharger_excel();
    return ob_get_clean();
}

// Ajouter un shortcode pour afficher le formulaire d'importation de fichier CSV
add_shortcode('formulaire_import_csv', 'formulaire_import_csv_shortcode');

/**
 * Fonction de rappel pour le shortcode [formulaire_import_csv].
 */
function formulaire_import_csv_shortcode() {
    ob_start();
    $instances = get_instances();
    $instances['view']->afficher_formulaire_import();
    return ob_get_clean();
}

// Ajouter un shortcode pour afficher le bloc de filtrage par chauffeur
add_shortcode('filtrage_chauffeur', 'filtrage_chauffeur_shortcode');

/**
 * Fonction de rappel pour le shortcode [filtrage_chauffeur].
 */
function filtrage_chauffeur_shortcode($atts) {
    ob_start();
    $instances = get_instances();
    $chauffeurs = $instances['databaseManager']->obtenir_noms_chauffeurs();
    $instances['view']->afficher_boutons_filtrage($chauffeurs);
    return ob_get_clean();
}

// Ajouter un shortcode pour afficher le bloc "chauffeur_selectionné"
add_shortcode('chauffeur_selectionne', 'chauffeur_selectionne_shortcode');

/**
 * Fonction de rappel pour le shortcode [chauffeur_selectionne].
 */
function chauffeur_selectionne_shortcode($atts) {
    ob_start();
    $instances = get_instances();
    $selected_chauffeur = isset($_POST['filter_chauffeur']) ? $_POST['filter_chauffeur'] : (isset($_POST['selected_chauffeur']) ? $_POST['selected_chauffeur'] : '');
    $results = $instances['databaseManager']->obtenir_donnees($selected_chauffeur);
    $total_heures_travaillees_filtre = $instances['calculations']->calculer_total_heures_travaillees($results);
    $total_tickets_restaurant_par_chauffeur = $instances['calculations']->calculer_totaux_tickets_restaurant($results);
    $instances['view']->afficher_informations_chauffeur_selectionne($selected_chauffeur, $total_heures_travaillees_filtre, $total_tickets_restaurant_par_chauffeur);
    return ob_get_clean();
}

// Ajouter un shortcode pour afficher le bloc "chauffeur_max"
add_shortcode('chauffeur_max', 'chauffeur_max_shortcode');

/**
 * Fonction de rappel pour le shortcode [chauffeur_max].
 */
function chauffeur_max_shortcode($atts) {
    ob_start();
    $instances = get_instances();
    $totaux = $instances['databaseManager']->obtenir_totaux();
    $results_chauffeur_max = $instances['databaseManager']->obtenir_donnees($totaux['chauffeur_max_heures']);
    $total_heures_chauffeur_max = $instances['calculations']->calculer_total_heures_travaillees($results_chauffeur_max);
    $moyenne_heures_travaillees = $totaux['moyenne_heures_travaillees'];
    $total_tickets_restaurant = $totaux['total_tickets_restaurant'];
    $instances['view']->afficher_informations_chauffeur_max($totaux['chauffeur_max_heures'], $total_heures_chauffeur_max, $moyenne_heures_travaillees, $total_tickets_restaurant);
    return ob_get_clean();
}

// Ajouter un shortcode pour afficher le tableau des données des heures travaillées
add_shortcode('tableau_donnees', 'tableau_donnees_shortcode');

/**
 * Fonction de rappel pour le shortcode [tableau_donnees].
 */
function tableau_donnees_shortcode($atts) {
    ob_start();
    $instances = get_instances();
    $selected_chauffeur = isset($_POST['filter_chauffeur']) ? $_POST['filter_chauffeur'] : (isset($_POST['selected_chauffeur']) ? $_POST['selected_chauffeur'] : '');
    $results = $instances['databaseManager']->obtenir_donnees($selected_chauffeur);
    $instances['view']->afficher_tableau_donnees($results, $selected_chauffeur);
    return ob_get_clean();
}

// Ajouter une fonction pour traiter le formulaire de validation des modifications
function traiter_formulaire_modifications() {
    if (isset($_POST['valider_modifications'])) {
        $instances = get_instances();
        $instances['mainController']->traiter_formulaire_modifications();
    }
}
add_action('init', 'traiter_formulaire_modifications');

// Ajouter une fonction pour traiter le formulaire d'importation CSV
function traiter_formulaire_import_csv() {
    if (isset($_POST['submit'])) {
        $instances = get_instances();
        $instances['mainController']->traiter_formulaire_import();
    }
}
add_action('init', 'traiter_formulaire_import_csv');

// Ajouter une fonction pour traiter le formulaire de génération du fichier Excel
function traiter_formulaire_generer_excel() {
    if (isset($_POST['generate_excel'])) {
        $instances = get_instances();
        $instances['mainController']->gerer_generation_excel();
    }
}
add_action('init', 'traiter_formulaire_generer_excel');

// Ajouter une fonction pour traiter le formulaire de téléchargement du fichier Excel
function traiter_formulaire_telecharger_excel() {
    if (isset($_POST['download_excel'])) {
        $instances = get_instances();
        $instances['mainController']->gerer_telechargement_excel();
    }
}
add_action('init', 'traiter_formulaire_telecharger_excel');

// Ajouter une fonction pour traiter le formulaire de suppression du chauffeur
function traiter_formulaire_suppression_chauffeur() {
    if (isset($_POST['supprimer_chauffeur'])) {
        $instances = get_instances();
        $instances['mainController']->gerer_suppression_chauffeur();
    }
}
add_action('init', 'traiter_formulaire_suppression_chauffeur');