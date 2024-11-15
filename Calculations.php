<?php
require_once 'DebugManager.php';

class Calculations {
    private $debug_messages = [];

    /**
     * Recalcule les totaux et met à jour la base de données.
     *
     * @param IDatabaseManager $databaseManager Instance de l'interface IDatabaseManager.
     * @return array Tableau associatif contenant les totaux recalculés.
     */
    public function recalculer_totaux(IDatabaseManager $databaseManager) {
        $debugManager = DebugManager::getInstance();

        // Récupérer toutes les données
        $all_results = $databaseManager->obtenir_donnees();

        // Recalculer les heures travaillées pour chaque entrée
        foreach ($all_results as $row) {
            $heures_travaillees = $this->calculer_heures_travaillees($row->heure_debut, $row->heure_fin, $row->coupure);
            $databaseManager->update_donnees(['heures_travaillees' => $heures_travaillees], ['id' => $row->id]);
        }

        // Obtenir les résultats mis à jour
        $all_results = $databaseManager->obtenir_donnees();

        // Calculer les totaux
        $chauffeur_max_heures = $this->obtenir_chauffeur_max_heures($all_results);
        $total_heures_travaillees = $this->calculer_total_heures_travaillees($all_results);
        $nombre_chauffeurs = count($databaseManager->obtenir_noms_chauffeurs());
        $moyenne_heures_travaillees = $this->calculer_moyenne_heures_travaillees($total_heures_travaillees, $nombre_chauffeurs);
        $total_tickets_restaurant_par_chauffeur = $this->calculer_totaux_tickets_restaurant($all_results);
        $total_tickets_restaurant = $total_tickets_restaurant_par_chauffeur['tous'];

        // Debug message
        //$debugManager->addMessage('Total Heures Chauffeur Max dans recalculer totaux de Calculations.php: ' . $chauffeur_max_heures['total_heures']);
        //$debugManager->addMessage('Moyenne Heures Travaillees dans recalculer totaux de Calculations.php: ' . $moyenne_heures_travaillees);

        // Mettre à jour les totaux calculés dans la base de données
        $databaseManager->update_totaux([
            'total_heures_travaillees' => $total_heures_travaillees,
            'chauffeur_max_heures' => $chauffeur_max_heures['nom_chauffeur'],
            'moyenne_heures_travaillees' => $moyenne_heures_travaillees,
            'total_tickets_restaurant' => $total_tickets_restaurant,
        ]);

        // Mettre à jour les totaux des tickets restaurant par chauffeur
        foreach ($total_tickets_restaurant_par_chauffeur as $nom_chauffeur => $total_tickets) {
            $databaseManager->update_totaux_chauffeur($nom_chauffeur, $total_tickets);
        }

        // Retourner les totaux recalculés
        return [
            'chauffeur_max_heures' => $chauffeur_max_heures,
            'total_heures_travaillees' => $total_heures_travaillees,
            'moyenne_heures_travaillees' => $moyenne_heures_travaillees,
            'total_tickets_restaurant' => $total_tickets_restaurant,
            'total_tickets_restaurant_par_chauffeur' => $total_tickets_restaurant_par_chauffeur,
            'debug_messages' => $this->debug_messages,
        ];
    }

    /**
     * Calcule le total des heures travaillées.
     *
     * @param array $results Tableau des résultats contenant les heures travaillées.
     * @return string Total des heures travaillées au format HH:MM.
     */
    public function calculer_total_heures_travaillees($results) {
        $total_minutes = 0;
        foreach ($results as $row) {
            list($heures, $minutes) = explode(':', $row->heures_travaillees);
            $total_minutes += $heures * 60 + $minutes;
        }
        $total_heures = intdiv($total_minutes, 60);
        $total_minutes = $total_minutes % 60;
        return sprintf('%02d:%02d', $total_heures, $total_minutes);
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
        $total_heures_chauffeur_max = sprintf('%02d:%02d', $heures, $minutes);

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
        list($total_heures, $total_minutes) = explode(':', $total_heures_travaillees);
        $total_minutes += $total_heures * 60;
        $moyenne_minutes = $total_minutes / $nombre_chauffeurs;
        $heures = intdiv((int) round($moyenne_minutes), 60);
        $minutes = (int) round($moyenne_minutes) % 60;
        return sprintf('%02d:%02d', $heures, $minutes);
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
        $heure_debut_dt = new DateTime($heure_debut);
        $heure_fin_dt = new DateTime($heure_fin);
        $coupure_dt = new DateTime($coupure);

        // Vérifier si l'heure de fin est le jour suivant
        if ($heure_fin_dt < $heure_debut_dt) {
            $heure_fin_dt->modify('+1 day');
        }

        $interval = $heure_fin_dt->diff($heure_debut_dt);
        $coupure_minutes = ($coupure_dt->format('H') * 60) + $coupure_dt->format('i');
        $minutes_travaillees = ($interval->h * 60 + $interval->i) - $coupure_minutes;

        $heures = intdiv($minutes_travaillees, 60);
        $minutes = $minutes_travaillees % 60;
        return sprintf('%02d:%02d', $heures, $minutes);
    }

    /**
     * Vérifie si les heures travaillées sont supérieures à 12 heures.
     *
     * @param string $heures_travaillees Heures travaillées au format HH:MM.
     * @return bool True si les heures travaillées sont supérieures à 12 heures, sinon False.
     */
    public function est_superieur_a_12_heures($heures_travaillees) {
        list($heures, $minutes) = explode(':', $heures_travaillees);
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
}