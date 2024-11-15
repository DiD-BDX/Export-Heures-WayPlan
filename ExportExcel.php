<?php
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ExportExcel {
    private $databaseManager;

    /**
     * Constructeur de la classe ExportExcel.
     *
     * @param IDatabaseManager $databaseManager Instance de l'interface IDatabaseManager.
     */
    public function __construct(IDatabaseManager $databaseManager) {
        $this->databaseManager = $databaseManager;
    }

    /**
     * Exporte les données vers un fichier Excel.
     *
     * @param string $total_heures_travaillees Total des heures travaillées.
     * @return \PhpOffice\PhpSpreadsheet\Spreadsheet
     */
    public function exporter_vers_excel($total_heures_travaillees) {
        $spreadsheet = new Spreadsheet();

        // Créer le résumé des informations dans le premier onglet
        $this->ajouter_resume($spreadsheet, $total_heures_travaillees);

        // Créer un onglet par chauffeur
        $chauffeurs = $this->databaseManager->obtenir_noms_chauffeurs();
        foreach ($chauffeurs as $index => $chauffeur) {
            $sheet = new Worksheet($spreadsheet, $chauffeur->nom_chauffeur);
            $spreadsheet->addSheet($sheet, $index + 1); // Ajouter l'onglet en commençant au deuxième onglet
            $this->ajouter_entetes($sheet);
            $this->ajouter_donnees_par_chauffeur($sheet, $chauffeur->nom_chauffeur);
        }

        // Définir la première feuille comme feuille active
        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    /**
     * Ajoute le résumé des informations dans le premier onglet.
     *
     * @param \PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet Feuille de calcul.
     * @param string $total_heures_travaillees Total des heures travaillées.
     */
    private function ajouter_resume($spreadsheet, $total_heures_travaillees) {
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Résumé');
    
        $totaux = $this->databaseManager->obtenir_totaux();
        $nombre_chauffeurs = count($this->databaseManager->obtenir_noms_chauffeurs());
    
        // Styles
        $headerStyle = [
            'font' => [
                'bold' => true,
                'size' => 22,
                'color' => ['argb' => 'FFFFFFFF'],
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF4CAF50'],
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
        ];
    
        $subHeaderStyle = [
            'font' => [
                'bold' => true,
                'size' => 20,
                'color' => ['argb' => 'FF000000'],
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
        ];
    
        $cellStyle = [
            'font' => [
                'size' => 18,
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['argb' => 'FF000000'],
                ],
            ],
        ];
    
        // Set column widths
        $sheet->getColumnDimension('A')->setWidth(90);
        $sheet->getColumnDimension('B')->setWidth(50);
    
        // New Headers
        $sheet->setCellValue('A1', 'Généré par le Plugin Export-Heures');
        $sheet->mergeCells('A1:B1');
        $sheet->getStyle('A1:B1')->applyFromArray($headerStyle);
    
        $sheet->setCellValue('A2', 'By Didier BD');
        $sheet->mergeCells('A2:B2');
        $sheet->getStyle('A2:B2')->applyFromArray($subHeaderStyle);
    
        // Existing Headers
        $sheet->setCellValue('A3', 'Résumé des Informations');
        $sheet->mergeCells('A3:B3');
        $sheet->getStyle('A3:B3')->applyFromArray($headerStyle);
    
        // Data
        $data = [
            ['Nombre total de guides', $nombre_chauffeurs],
            ['Nombre total d\'heures travaillées', $total_heures_travaillees],
            ['Moyenne des heures travaillées', $totaux['moyenne_heures_travaillees']],
            ['Guide ayant fait le plus d\'heures', $totaux['chauffeur_max_heures']],
            ['Nombre d\'heures du guide ayant fait le plus d\'heures', $this->calculer_total_heures_travaillees($this->databaseManager->obtenir_donnees($totaux['chauffeur_max_heures']))],
            ['Nombre total de tickets restaurant', $totaux['total_tickets_restaurant']],
        ];
    
        $row = 4;
        foreach ($data as $item) {
            $sheet->setCellValue('A' . $row, $item[0]);
            $sheet->setCellValue('B' . $row, $item[1]);
            $sheet->getStyle('A' . $row)->applyFromArray($cellStyle);
            $sheet->getStyle('B' . $row)->applyFromArray($cellStyle);
            $row++;
        }
    
        // Apply styles to headers
        $sheet->getStyle('A4:A' . ($row - 1))->applyFromArray($headerStyle);
    }

    /**
     * Ajoute les en-têtes au fichier Excel.
     *
     * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet Feuille de calcul.
     */
    private function ajouter_entetes($sheet) {
        $headerStyle = [
            'font' => [
                'bold' => true,
                'size' => 18,
                'color' => ['argb' => 'FFFFFFFF'],
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF4CAF50'],
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
        ];
    
        $headers = [
            'ID',
            'Nom Chauffeur',
            'Date Mission',
            'Heure de Début',
            'Coupure',
            'Heure de Fin',
            'Heures Travaillées',
            'Ticket Restaurant'
        ];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $sheet->getStyle($col . '1')->applyFromArray($headerStyle);
            $sheet->getColumnDimension($col)->setAutoSize(true);
            $col++;
        }
    }

    /**
     * Ajoute les données au fichier Excel pour un chauffeur spécifique.
     *
     * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet Feuille de calcul.
     * @param string $nom_chauffeur Nom du chauffeur.
     */
    private function ajouter_donnees_par_chauffeur($sheet, $nom_chauffeur) {
        $results = $this->databaseManager->obtenir_donnees($nom_chauffeur);
        $row = 2;
        $cellStyle = [
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
            'font' => [
                'size' => 18,
            ],
        ];
    
        $total_heures_travaillees = 0;
        $total_tickets_restaurant = 0;
    
        foreach ($results as $result) {
            $sheet->setCellValue('A' . $row, $result->id);
            $sheet->setCellValue('B' . $row, $result->nom_chauffeur);
            $sheet->setCellValue('C' . $row, $result->date_mission);
            $sheet->setCellValue('D' . $row, substr($result->heure_debut, 0, 5)); // hh:mm
            $sheet->setCellValue('E' . $row, substr($result->coupure, 0, 5)); // hh:mm
            $sheet->setCellValue('F' . $row, substr($result->heure_fin, 0, 5)); // hh:mm
            $sheet->setCellValue('G' . $row, substr($result->heures_travaillees, 0, 5)); // hh:mm
            $sheet->setCellValue('H' . $row, $result->ticket_restaurant);
    
            // Calculer les totaux
            $total_heures_travaillees += $this->convertir_heures_en_minutes(substr($result->heures_travaillees, 0, 5));
            $total_tickets_restaurant += $result->ticket_restaurant;
    
            foreach (range('A', 'H') as $col) {
                $sheet->getStyle($col . $row)->applyFromArray($cellStyle);
            }
            $row++;
        }
    
        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    
        // Ajouter la ligne de total
        $headerStyle = [
            'font' => [
                'bold' => true,
                'size' => 18,
                'color' => ['argb' => 'FFFFFFFF'],
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF4CAF50'],
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
        ];
    
        $sheet->setCellValue('F' . $row, 'Total');
        $sheet->setCellValue('G' . $row, $this->convertir_minutes_en_heures($total_heures_travaillees));
        $sheet->setCellValue('H' . $row, $total_tickets_restaurant);
        foreach (range('F', 'H') as $col) {
            $sheet->getStyle($col . $row)->applyFromArray($headerStyle);
        }
    }
    
    private function convertir_heures_en_minutes($heures) {
        list($h, $m) = explode(':', $heures);
        return $h * 60 + $m;
    }
    
    private function convertir_minutes_en_heures($minutes) {
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        return sprintf('%02d:%02d', $h, $m);
    }

    /**
     * Calcule le total des heures travaillées.
     *
     * @param array $results Tableau des résultats contenant les heures travaillées.
     * @return string Total des heures travaillées au format HH:MM.
     */
    private function calculer_total_heures_travaillees($results) {
        $total_minutes = 0;
        foreach ($results as $row) {
            list($heures, $minutes) = explode(':', $row->heures_travaillees);
            $total_minutes += $heures * 60 + $minutes;
        }
        $total_heures = intdiv($total_minutes, 60);
        $total_minutes = $total_minutes % 60;
        return sprintf('%02d:%02d', $total_heures, $total_minutes);
    }
}