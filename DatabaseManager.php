<?php
require_once 'IDatabaseManager.php';
require_once 'DebugManager.php';

class DatabaseManager implements IDatabaseManager {
    private $wpdb;
    private $table_name;

    /**
     * Constructeur de la classe DatabaseManager.
     *
     * @param object $wpdb Instance de l'objet wpdb de WordPress.
     * @param string $table_name Nom de la table à gérer.
     */
    public function __construct($wpdb, $table_name) {
        $this->wpdb = $wpdb;
        $this->table_name = $table_name;
    }

    /**
     * Vide la table en supprimant toutes les données.
     */
    public function vider_table() {
        $this->wpdb->query("TRUNCATE TABLE $this->table_name");
        if ($this->wpdb->last_error) {
            wp_die('Erreur lors de la suppression des données de la table : ' . $this->wpdb->last_error);
        }
    }

    /**
     * Insère des données dans la table.
     *
     * @param array $data Tableau associatif contenant les données à insérer.
     */
    public function inserer_donnees($data) {
        $this->wpdb->insert(
            $this->table_name,
            $data,
            array(
                '%s', // nom_chauffeur
                '%s', // date_mission
                '%s', // heure_debut
                '%s', // coupure
                '%s', // heure_fin
                '%s', // heures_travaillees
                '%s', // total_heures_travaillees
                '%s', // chauffeur_max_heures
                '%s', // moyenne_heures_travaillees
                '%d'  // ticket_restaurant
            )
        );
        if ($this->wpdb->last_error) {
            wp_die('Erreur lors de l\'insertion des données : ' . $this->wpdb->last_error);
        }
    }

    /**
     * Récupère les totaux calculés depuis la table.
     *
     * @return array Tableau associatif contenant les totaux.
     */
    public function obtenir_totaux() {
        $debugManager = DebugManager::getInstance();

        $result = $this->wpdb->get_row("SELECT total_heures_travaillees, chauffeur_max_heures, total_heures_chauffeur_max, moyenne_heures_travaillees, total_tickets_restaurant FROM $this->table_name LIMIT 1", ARRAY_A);
        
        if ($this->wpdb->last_error) {
            wp_die('Erreur lors de la récupération des totaux : ' . $this->wpdb->last_error);
        }

        return $result;
    }

    /**
     * Met à jour les totaux dans la table.
     *
     * @param array $totaux Tableau associatif contenant les totaux à mettre à jour.
     */
    public function update_totaux($totaux) {
        $debugManager = DebugManager::getInstance();
   
        $data = [
            'total_heures_travaillees' => $totaux['total_heures_travaillees'],
            'chauffeur_max_heures' => $totaux['chauffeur_max_heures'],
            'total_heures_chauffeur_max' => $totaux['total_heures_chauffeur_max'],
            'moyenne_heures_travaillees' => $totaux['moyenne_heures_travaillees'],
            'total_tickets_restaurant' => $totaux['total_tickets_restaurant'],
            'total_tickets_restaurant_par_chauffeur' => json_encode($totaux['total_tickets_restaurant_par_chauffeur'], JSON_UNESCAPED_UNICODE),
            'total_tickets_restaurant_chauffeur_max' => $totaux['total_tickets_restaurant_chauffeur_max']
        ];

        // Utiliser la fonction update_donnees pour mettre à jour les totaux dans toutes les lignes
        $this->update_donnees($data, []);
    
        if ($this->wpdb->last_error) {
            wp_die('Erreur lors de la mise à jour des totaux : ' . $this->wpdb->last_error);
        }
    }

    /**
     * Crée ou met à jour la structure de la table dans la base de données.
     */
    public function installer() {
        $charset_collate = $this->wpdb->get_charset_collate();

        $sql = "CREATE TABLE $this->table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            nom_chauffeur varchar(255) NOT NULL,
            date_mission varchar(10) NOT NULL,
            heure_debut varchar(10) NOT NULL,
            coupure varchar(10) NOT NULL,
            heure_fin varchar(10) NOT NULL,
            heures_travaillees varchar(10) NOT NULL,
            total_heures_travaillees varchar(10) NOT NULL,
            chauffeur_max_heures varchar(255) NOT NULL,
            moyenne_heures_travaillees varchar(10) NOT NULL,
            ticket_restaurant boolean NOT NULL DEFAULT 1,
            total_tickets_restaurant int NOT NULL DEFAULT 0,
            total_tickets_restaurant_par_chauffeur int NOT NULL DEFAULT 0,
            PRIMARY KEY (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Récupère les données de la table.
     *
     * @param string $nom_chauffeur (Optionnel) Nom du chauffeur pour filtrer les résultats.
     * @return array Tableau d'objets contenant les données.
     */
    public function obtenir_donnees($nom_chauffeur = '') {
        if ($nom_chauffeur) {
            return $this->wpdb->get_results($this->wpdb->prepare("SELECT * FROM $this->table_name WHERE nom_chauffeur = %s", $nom_chauffeur));
        } else {
            return $this->wpdb->get_results("SELECT * FROM $this->table_name");
        }
    }

    /**
     * Récupère les noms distincts des chauffeurs depuis la table.
     *
     * @return array Tableau d'objets contenant les noms des chauffeurs.
     */
    public function obtenir_noms_chauffeurs() {
        return $this->wpdb->get_results("SELECT DISTINCT nom_chauffeur FROM $this->table_name");
    }

    /**
     * Met à jour les données dans la table.
     *
     * @param array $data Tableau associatif contenant les nouvelles valeurs des données.
     * @param array $where Tableau associatif contenant les conditions de mise à jour.
     * @throws Exception Si une erreur survient lors de la mise à jour.
     */
    public function update_donnees($data, $where = []) {
        $data_formats = array_map(function($value) {
            return is_int($value) ? '%d' : '%s';
        }, array_values($data));
    
        if (!empty($where)) {
            $where_formats = array_map(function($value) {
                return is_int($value) ? '%d' : '%s';
            }, array_values($where));
    
            $this->wpdb->update(
                $this->table_name,
                $data,
                $where,
                $data_formats,
                $where_formats
            );
        } else {
            // Construire la clause SET pour la requête SQL
            $set_clause = [];
            foreach ($data as $column => $value) {
                $set_clause[] = "$column = " . $this->wpdb->prepare('%s', $value);
            }
            $set_clause = implode(', ', $set_clause);
    
            // Construire et exécuter la requête SQL pour mettre à jour toutes les lignes
            $sql = "UPDATE $this->table_name SET $set_clause";
            $this->wpdb->query($sql);
        }
    
        if ($this->wpdb->last_error) {
            throw new Exception('Erreur lors de la mise à jour des données : ' . $this->wpdb->last_error);
        }
    }

    /**
     * Met à jour les totaux des tickets restaurant par chauffeur.
     *
     * @param string $nom_chauffeur Nom du chauffeur.
     * @param int $total_tickets Total des tickets restaurant.
     */
    public function update_totaux_chauffeur($nom_chauffeur, $total_tickets) {
        $this->update_donnees(
            ['total_tickets_restaurant_par_chauffeur' => $total_tickets],
            ['nom_chauffeur' => $nom_chauffeur]
        );
    }

    /**
     * Supprime les données d'un chauffeur par son nom.
     *
     * @param string $nom_chauffeur Nom du chauffeur.
     * @return bool True si la suppression a réussi, False sinon.
     */
    public function supprimer_donnees_par_chauffeur($nom_chauffeur) {
        $debugManager = DebugManager::getInstance();
        // Normaliser l'encodage du nom du chauffeur
        $nom_chauffeur = mb_convert_encoding($nom_chauffeur, 'UTF-8', mb_detect_encoding($nom_chauffeur));
    
        // Normaliser les caractères du nom du chauffeur
        if (class_exists('Normalizer')) {
            $nom_chauffeur = Normalizer::normalize($nom_chauffeur, Normalizer::FORM_C);
        }
    
        // Vérifier si le chauffeur existe dans la base de données
        $chauffeur_existe = $this->wpdb->get_var($this->wpdb->prepare("SELECT COUNT(*) FROM $this->table_name WHERE nom_chauffeur = %s", $nom_chauffeur));
        if ($chauffeur_existe == 0) {
            return false;
        }
    
        // Obtenir tous les IDs du chauffeur par son nom
        $chauffeur_ids = $this->wpdb->get_col($this->wpdb->prepare("SELECT id FROM $this->table_name WHERE nom_chauffeur = %s", $nom_chauffeur));
        if (empty($chauffeur_ids)) {
            return false;
        }
    
        // Vérifier si la ligne avec id = 1 doit être supprimée
        if (in_array(1, $chauffeur_ids)) {
            // Trouver la prochaine ligne disponible qui ne sera pas supprimée
            $next_id = $this->wpdb->get_var("SELECT id FROM $this->table_name WHERE id NOT IN (" . implode(',', $chauffeur_ids) . ") ORDER BY id ASC LIMIT 1");
            if ($next_id) {
                // Copier les totaux de la ligne 1 vers la prochaine ligne disponible
                $totaux_ligne_1 = $this->wpdb->get_row("SELECT total_heures_travaillees, chauffeur_max_heures, total_heures_chauffeur_max, moyenne_heures_travaillees, total_tickets_restaurant, total_tickets_restaurant_par_chauffeur, total_tickets_restaurant_chauffeur_max FROM $this->table_name WHERE id = 1", ARRAY_A);
                $this->wpdb->update($this->table_name, $totaux_ligne_1, ['id' => $next_id], ['%s', '%s', '%s', '%s', '%d', '%s', '%d'], ['%d']);
                if ($this->wpdb->last_error) {
                    wp_die('Erreur lors de la copie des totaux de la ligne 1 vers la ligne ' . $next_id . ' : ' . $this->wpdb->last_error);
                }
            } else {
                wp_die('Aucune ligne disponible pour copier les totaux.');
            }
        }
    
        // Tenter de supprimer toutes les données du chauffeur par ID
        foreach ($chauffeur_ids as $chauffeur_id) {
            $result = $this->wpdb->delete($this->table_name, ['id' => $chauffeur_id], ['%d']);
            
            if ($this->wpdb->last_error) {
                wp_die('Erreur lors de la suppression des données : ' . $this->wpdb->last_error);
            }
    
            if ($result === false) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Récupère l'ID d'un chauffeur par son nom.
     *
     * @param string $nom_chauffeur Nom du chauffeur.
     * @return int ID du chauffeur.
     */
    public function obtenir_id_par_nom($nom_chauffeur) {
        $nom_chauffeur = trim($nom_chauffeur);
        $nom_chauffeur = mb_convert_encoding($nom_chauffeur, 'UTF-8', mb_detect_encoding($nom_chauffeur));
        if (class_exists('Normalizer')) {
            $nom_chauffeur = Normalizer::normalize($nom_chauffeur, Normalizer::FORM_C);
        }
        return $this->wpdb->get_var($this->wpdb->prepare("SELECT id FROM $this->table_name WHERE nom_chauffeur = %s", $nom_chauffeur));
    }

    /**
     * Supprime les lignes dont le champ "nom_chauffeur" est vide.
     */
    public function supprimer_lignes_vides() {
        $this->wpdb->query("DELETE FROM $this->table_name WHERE nom_chauffeur = ''");
        if ($this->wpdb->last_error) {
            wp_die('Erreur lors de la suppression des lignes vides : ' . $this->wpdb->last_error);
        }
    }
}