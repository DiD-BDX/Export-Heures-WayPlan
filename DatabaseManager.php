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
        $result = $this->wpdb->get_row("SELECT total_heures_travaillees, chauffeur_max_heures, moyenne_heures_travaillees, total_tickets_restaurant FROM $this->table_name LIMIT 1", ARRAY_A);
        
        if ($this->wpdb->last_error) {
            wp_die('Erreur lors de la récupération des totaux : ' . $this->wpdb->last_error);
        }
        return $result;
        return $debugManager->getMessages();
    }

    /**
     * Met à jour les totaux dans la table.
     *
     * @param array $totaux Tableau associatif contenant les totaux à mettre à jour.
     */
    public function update_totaux($totaux) {
        $debugManager = DebugManager::getInstance();

        $this->wpdb->update(
            $this->table_name,
            $totaux,
            array('id' => 1), // Mettre à jour la première ligne, vous pouvez ajuster selon vos besoins
            array(
                '%s', // total_heures_travaillees
                '%s', // chauffeur_max_heures
                '%s', // moyenne_heures_travaillees
                '%d' // total_tickets_restaurant
            ),
            array('%d')
        );

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
            heure_debut time NOT NULL,
            coupure time NOT NULL,
            heure_fin time NOT NULL,
            heures_travaillees time NOT NULL,
            total_heures_travaillees time NOT NULL,
            chauffeur_max_heures varchar(255) NOT NULL,
            moyenne_heures_travaillees time NOT NULL,
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
    public function update_donnees($data, $where) {
        $data_formats = array_map(function($value) {
            return is_int($value) ? '%d' : '%s';
        }, array_values($data));
    
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

    public function supprimer_donnees_par_chauffeur($nom_chauffeur) {
        $this->wpdb->delete($this->table_name, ['nom_chauffeur' => $nom_chauffeur], ['%s']);
        if ($this->wpdb->last_error) {
            wp_die('Erreur lors de la suppression des données : ' . $this->wpdb->last_error);
        }
    }
}