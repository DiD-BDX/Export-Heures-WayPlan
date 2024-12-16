<?php
require_once 'DebugManager.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class FileHandler {
    private $debug_messages = [];
    private $file_name;
    private $file_path; // Ajout de la propriété pour stocker le chemin du fichier

    /**
     * Enregistre le fichier Excel dans un répertoire sécurisé.
     *
     * @param \PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet Instance de Spreadsheet.
     */
    public function enregistrer_fichier_excel(Spreadsheet $spreadsheet) {
        // Définir le répertoire sécurisé
        $directory = ABSPATH . '../secure_exports';
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        // Générer le nom du fichier avec la date actuelle
        $this->file_name = 'Export_Heures_Guides_' . date('Y-m-d') . '.xlsx';
        $this->file_path = $directory . '/' . $this->file_name; // Stocker le chemin du fichier

        // Supprimer le fichier existant s'il existe
        if (file_exists($this->file_path)) {
            unlink($this->file_path);
        }

        // Enregistrer le fichier Excel dans le répertoire sécurisé
        $writer = new Xlsx($spreadsheet);
        $writer->save($this->file_path);

        // Stocker le chemin du fichier dans une option WordPress
        update_option('export_excel_file_path', $this->file_path);

        // Ajouter un message de débogage pour confirmer l'enregistrement
        $debugManager = DebugManager::getInstance();
        //$debugManager->addMessage('-------- 1------- Fichier Excel enregistré à : ' . $this->file_path . '<br>');
    }

    /**
     * Télécharge le fichier Excel généré.
     */
    public function telecharger_excel() {
        $debugManager = DebugManager::getInstance();
        
        if (!isset($_POST['download_nonce']) || !wp_verify_nonce($_POST['download_nonce'], 'download_excel')) {
            die('Accès non autorisé.');
        }

        // Récupérer le chemin du fichier depuis l'option WordPress
        $this->file_path = get_option('export_excel_file_path');
        $this->file_name = basename($this->file_path);

        // Vérifier si le chemin du fichier est défini
        if (empty($this->file_path)) {
            $debugManager->addMessage('Erreur : Le chemin du fichier n\'est pas défini.<br>');
            die('Erreur : Le chemin du fichier n\'est pas défini.');
        }

        // Utiliser le chemin du fichier stocké
        if (file_exists($this->file_path)) {
            $debugManager->addMessage('------- 2------  Fichier trouvé à : ' . $this->file_path . '<br>');
            header('Content-Description: File Transfer');
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $this->file_name . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($this->file_path));
            flush();
            readfile($this->file_path);
            exit;
        } else {
            $debugManager->addMessage('Erreur : Le fichier n\'a pas été trouvé à : ' . $this->file_path . '. Veuillez générer le fichier Excel d\'abord.<br>');
        }
    }
}