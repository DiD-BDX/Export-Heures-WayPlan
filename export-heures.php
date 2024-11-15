<?php
/*
Plugin Name: Export Heures
Description: Un plugin pour importer et exporter des heures depuis des fichiers Excel, et recalculer les totaux.
Version: 8.0
Author: Didier BD
*/

// Charger automatiquement les dépendances via Composer
require 'vendor/autoload.php';

// Inclure les fichiers nécessaires
require_once plugin_dir_path(__FILE__) . 'IDatabaseManager.php';
require_once plugin_dir_path(__FILE__) . 'DatabaseManager.php';
require_once plugin_dir_path(__FILE__) . 'View.php';
require_once plugin_dir_path(__FILE__) . 'ImportCsv.php';
require_once plugin_dir_path(__FILE__) . 'ExportExcel.php';
require_once plugin_dir_path(__FILE__) . 'FileHandler.php';
require_once plugin_dir_path(__FILE__) . 'Calculations.php';
require_once plugin_dir_path(__FILE__) . 'MainController.php';
require_once plugin_dir_path(__FILE__) . 'ExportHeuresPlugin.php';
require_once plugin_dir_path(__FILE__) . 'Shortcodes.php';
require_once plugin_dir_path(__FILE__) . 'DebugManager.php';

// Créer une instance de la classe ExportHeuresPlugin
$exportHeuresPlugin = new ExportHeuresPlugin();

// Ajouter les éléments de menu dans l'interface d'administration de WordPress
add_action('admin_menu', [$exportHeuresPlugin, 'export_heures_menu']);

// Enregistrer la fonction d'installation du plugin
register_activation_hook(__FILE__, [$exportHeuresPlugin, 'export_heures_install']);