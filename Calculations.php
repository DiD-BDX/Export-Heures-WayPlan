<?php
require_once 'DebugManager.php';

class Calculations {
    private $debug_messages = [];
    const TIME_FORMAT = '%02d:%02d';

    /**
     * Recalcule les totaux et met à jour la base de données.
     *
     * @param IDatabaseManager $databaseManager Instance de l'interface IDatabaseManager.
     * @return array Tableau associatif contenant les totaux recalculés.
     */
    public function recalculer_totaux(IDatabaseManager $databaseManager) {
        $debugManager = DebugManager::getInstance();

            // Récupérer toutes les données
            $results = $databaseManager->obtenir_donnees();

            // Calculer le total des heures travaillées
            $total_heures_travaillees = $this->calculer_total_heures_travaillees($results);
    
            // Obtenir le chauffeur ayant travaillé le plus d'heures
            $chauffeur_max_heures = $this->obtenir_chauffeur_max_heures($results);
            $results_chauffeur_max = $databaseManager->obtenir_donnees($chauffeur_max_heures['nom_chauffeur']);
            $total_heures_chauffeur_max = $this->calculer_total_heures_travaillees($results_chauffeur_max);
    
            // Calculer la moyenne des heures travaillées par chauffeur
            $nombre_chauffeurs = $this->calculer_nombre_chauffeurs($databaseManager);
            $moyenne_heures_travaillees = $this->calculer_moyenne_heures_travaillees($total_heures_travaillees, $nombre_chauffeurs);

            // Calculer les totaux des tickets restaurant
            $totaux_tickets_restaurant = $this->calculer_totaux_tickets_restaurant($results);
            $total_tickets_restaurant = $totaux_tickets_restaurant['tous'];
            $total_tickets_restaurant_par_chauffeur = $totaux_tickets_restaurant;
            unset($total_tickets_restaurant_par_chauffeur['tous']);
            $total_tickets_restaurant_chauffeur_max = $total_tickets_restaurant_par_chauffeur[$chauffeur_max_heures['nom_chauffeur']];
    
            // Mettre à jour les totaux dans la base de données
            $totaux = [
                'total_heures_travaillees' => $total_heures_travaillees,
                'chauffeur_max_heures' => $chauffeur_max_heures['nom_chauffeur'],
                'total_heures_chauffeur_max' => $total_heures_chauffeur_max,
                'moyenne_heures_travaillees' => $moyenne_heures_travaillees,
                'total_tickets_restaurant' => $total_tickets_restaurant,
                'total_tickets_restaurant_par_chauffeur' => $total_tickets_restaurant_par_chauffeur,
                'total_tickets_restaurant_chauffeur_max' => $total_tickets_restaurant_chauffeur_max
            ];
            
            $databaseManager->update_totaux($totaux);

            return $totaux;
    }

    /**
     * Calcule le total des heures travaillées.
     *
     * @param array $results Tableau des résultats contenant les heures travaillées.
     * @return string Total des heures travaillées au format HH:MM.
     */
    public function calculer_total_heures_travaillees($results) {
        $debugManager = DebugManager::getInstance();
    
        $total_minutes = 0;
        foreach ($results as $row) {
            list($heures, $minutes) = explode(':', $row->heures_travaillees);
            $heures = (int) $heures; // Convertir en entier
            $minutes = (int) $minutes; // Convertir en entier
            $total_minutes += $heures * 60 + $minutes;
        }
        $total_heures = intdiv($total_minutes, 60);
        $total_minutes = $total_minutes % 60;

        return sprintf(self::TIME_FORMAT, $total_heures, $total_minutes);
    }

    /**
     * Obtient le chauffeur ayant travaillé le plus d'heures.
     *
     * @param array $results Tableau des résultats contenant les heures travaillées.
     * @return array Tableau associatif contenant le nom du chauffeur et le total des heures travaillées.
     */
    public function obtenir_chauffeur_max_heures($results) {
        $debugManager = DebugManager::getInstance();

        $chauffeurs_heures = [];
        foreach ($results as $row) {
            if (!isset($chauffeurs_heures[$row->nom_chauffeur])) {
                $chauffeurs_heures[$row->nom_chauffeur] = 0;
            }
            list($heures, $minutes) = explode(':', $row->heures_travaillees);
            $heures = (int) $heures; // Convertir en entier
            $minutes = (int) $minutes; // Convertir en entier
            $chauffeurs_heures[$row->nom_chauffeur] += $heures * 60 + $minutes;
        }

        $max_heures = 0;
        $chauffeur_max = '';
        foreach ($chauffeurs_heures as $chauffeur => $minutes) {
            if ($minutes > $max_heures) {
                $max_heures = $minutes;
                $chauffeur_max = $chauffeur;
            }
        }
        $total_heures_chauffeur_max = sprintf(self::TIME_FORMAT, $heures, $minutes);

        return ['nom_chauffeur' => $chauffeur_max];
    }

    /**
     * Calcule la moyenne des heures travaillées par chauffeur.
     *
     * @param string $total_heures_travaillees Total des heures travaillées au format HH:MM.
     * @param int $nombre_chauffeurs Nombre total de chauffeurs.
     * @return string Moyenne des heures travaillées au format HH:MM.
     */
    public function calculer_moyenne_heures_travaillees($total_heures_travaillees, $nombre_chauffeurs) {
        $debugManager = DebugManager::getInstance();

        if ($nombre_chauffeurs == 0) {
            throw new InvalidArgumentException("Le nombre de chauffeurs ne peut pas être zéro.");
        }
    
        list($total_heures, $total_minutes) = explode(':', $total_heures_travaillees);
        $total_heures = (int) $total_heures; // Convertir en entier
        $total_minutes = (int) $total_minutes; // Convertir en entier
        $total_minutes += $total_heures * 60;
        $moyenne_minutes = $total_minutes / $nombre_chauffeurs;
        $heures = intdiv((int) round($moyenne_minutes), 60);
        $minutes = (int) round($moyenne_minutes) % 60;

        return sprintf(self::TIME_FORMAT, $heures, $minutes);
    }

    /**
     * Calcule les heures travaillées en tenant compte des coupures.
     *
     * @param string $heure_debut Heure de début au format HH:MM.
     * @param string $heure_fin Heure de fin au format HH:MM.
     * @param string $coupure Durée de la coupure au format HH:MM.
     * @return string Heures travaillées au format HH:MM.
     */
    public function calculer_heures_travaillees($heure_debut, $heure_fin, $coupure) {
        $debugManager = DebugManager::getInstance();

        // Convertir les heures et minutes en entiers
        list($debut_heures, $debut_minutes) = explode(':', $heure_debut);
        list($fin_heures, $fin_minutes) = explode(':', $heure_fin);
        list($coupure_heures, $coupure_minutes) = explode(':', $coupure);
    
        $debut_heures = (int) $debut_heures;
        $debut_minutes = (int) $debut_minutes;
        $fin_heures = (int) $fin_heures;
        $fin_minutes = (int) $fin_minutes;
        $coupure_heures = (int) $coupure_heures;
        $coupure_minutes = (int) $coupure_minutes;
    
        // Calculer les minutes totales de début, fin et coupure
        $debut_total_minutes = $debut_heures * 60 + $debut_minutes;
        $fin_total_minutes = $fin_heures * 60 + $fin_minutes;
        $coupure_total_minutes = $coupure_heures * 60 + $coupure_minutes;
    
        // Vérifier si l'heure de fin est le jour suivant
        if ($fin_total_minutes < $debut_total_minutes) {
            $fin_total_minutes += 24 * 60; // Ajouter 24 heures en minutes
        }
    
        // Calculer les minutes travaillées en soustrayant les minutes de coupure
        $minutes_travaillees = $fin_total_minutes - $debut_total_minutes - $coupure_total_minutes;
    
        // Convertir les minutes travaillées en heures et minutes
        $heures = intdiv($minutes_travaillees, 60);
        $minutes = $minutes_travaillees % 60;

        return sprintf(self::TIME_FORMAT, $heures, $minutes);
    }

    /**
     * Vérifie si les heures travaillées sont supérieures à 12 heures.
     *
     * @param string $heures_travaillees Heures travaillées au format HH:MM.
     * @return bool True si les heures travaillées sont supérieures à 12 heures, sinon False.
     */
    public function est_superieur_a_12_heures($heures_travaillees) {
        list($heures, $minutes) = explode(':', $heures_travaillees);
        $heures = (int) $heures; // Convertir en entier
        $minutes = (int) $minutes; // Convertir en entier
        $total_minutes = $heures * 60 + $minutes;
        return $total_minutes > 720; // 12 heures * 60 minutes
    }

    /**
     * Calcule le total des tickets restaurant global et par chauffeur.
     *
     * @param array $results Tableau des résultats contenant les tickets restaurant.
     * @return array Tableau associatif contenant le total global et les totaux par chauffeur.
     */
    public function calculer_totaux_tickets_restaurant($results) {
        $totaux_par_chauffeur = [];
        $total_global = 0;
        foreach ($results as $row) {
            if (!isset($totaux_par_chauffeur[$row->nom_chauffeur])) {
                $totaux_par_chauffeur[$row->nom_chauffeur] = 0;
            }
            $totaux_par_chauffeur[$row->nom_chauffeur] += $row->ticket_restaurant;
            $total_global += $row->ticket_restaurant;
        }
        $totaux_par_chauffeur['tous'] = $total_global;
        return $totaux_par_chauffeur;
    }

    /**
     * Calcule le nombre de chauffeurs uniques.
     *
     * @param IDatabaseManager $databaseManager Instance de l'interface IDatabaseManager.
     * @return int Nombre de chauffeurs uniques.
     */
    public function calculer_nombre_chauffeurs(IDatabaseManager $databaseManager) {
        $noms_chauffeurs = $databaseManager->obtenir_noms_chauffeurs();
        $noms_chauffeurs_uniques = array_unique(array_map(function($chauffeur) {
            return $chauffeur->nom_chauffeur;
        }, $noms_chauffeurs));
        return count($noms_chauffeurs_uniques);
    }
}
