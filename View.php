<?php
class View {
    /**
     * Affiche la page principale du plugin.
     */
    public function afficher_page_principale($chauffeurs, $selected_chauffeur, $results, $total_heures_travaillees_filtre, $total_heures_chauffeur_max, $total_tickets_restaurant_par_chauffeur, $totaux) {
        $debugManager = DebugManager::getInstance();
        
        $this->afficher_formulaire_import();
        $this->afficher_bouton_generer_excel();
        $this->afficher_bouton_telecharger_excel();
        $this->afficher_boutons_filtrage($chauffeurs);
        $this->afficher_informations_chauffeur_selectionne($selected_chauffeur, $total_heures_travaillees_filtre, $total_tickets_restaurant_par_chauffeur);
        $this->afficher_informations_chauffeur_max(
            $totaux['chauffeur_max_heures'],
            $total_heures_chauffeur_max,
            $totaux['moyenne_heures_travaillees'],
            $totaux['total_tickets_restaurant'],
        );
        $this->afficher_messages_debug();
        $this->afficher_tableau_donnees($results, $selected_chauffeur);

        $debugManager->addMessage('Moyenne Heures Travaillees dans afficher_page_principale de View.php: ' . $totaux['moyenne_heures_travaillees']);
    }

    /**
     * Affiche le formulaire d'importation de fichier CSV.
     */
    public function afficher_formulaire_import() {
        ?>
        <div class="wrap">
            <form method="post" enctype="multipart/form-data">
                <input type="file" name="csv_file" accept=".csv">
                <input type="submit" name="submit" value="Importer et afficher les données">
            </form>
        </div>
        <?php
    }

    /**
     * Affiche le bouton "Générer le fichier Excel".
     */
    public function afficher_bouton_generer_excel() {
        ?>
        <div class="wrap">
            <form method="post">
                <button type="submit" name="generate_excel">Générer le fichier Excel</button>
            </form>
        </div>
        <?php
    }

    /**
     * Affiche le bouton "Télécharger le fichier Excel".
     */
    public function afficher_bouton_telecharger_excel() {
        ?>
        <div class="wrap">
            <form action="" method="post">
                <?php wp_nonce_field('download_excel', 'download_nonce'); ?>
                <button type="submit" name="download_excel" class="btn btn-download">Télécharger le fichier Excel</button>
            </form>
        </div>
        <?php
        if (isset($_POST['download_excel'])) {
            $instances = get_instances();
            $fileHandler = $instances['fileHandler'];
            $fileHandler->telecharger_excel();
        }
    }

    /**
     * Affiche les boutons de filtrage par chauffeur.
     *
     * @param array $chauffeurs Tableau des chauffeurs.
     */
    public function afficher_boutons_filtrage($chauffeurs) {
        ?>
        <div class="wrap" id="filtrage_chauffeur">
            <h2>Filtrer par guide</h2>
            <form method="post">
                <?php foreach ($chauffeurs as $chauffeur): ?>
                    <button type="submit" name="filter_chauffeur" value="<?php echo esc_attr($chauffeur->nom_chauffeur); ?>">
                        <?php echo esc_html($chauffeur->nom_chauffeur); ?>
                    </button>
                <?php endforeach; ?>
                <button type="submit" name="filter_chauffeur" value="">Tous</button>
            </form>
        </div>
        <?php
    }

    /**
     * Affiche les informations du chauffeur sélectionné.
     *
     * @param string $selected_chauffeur Nom du chauffeur sélectionné.
     * @param string $total_heures_travaillees_filtre Total des heures travaillées pour le chauffeur sélectionné.
     * @param array $total_tickets_restaurant_par_chauffeur Total des tickets restaurant par chauffeur.
     */
    public function afficher_informations_chauffeur_selectionne($selected_chauffeur, $total_heures_travaillees_filtre, $total_tickets_restaurant_par_chauffeur) {
        ?>
        <div class="wrap">
            <h2>Informations du guide Sélectionné</h2>
            <div id="chauffeur_selectionne">
                <p><strong>Nom du guide :</strong> <?php echo esc_html($selected_chauffeur ? $selected_chauffeur : 'Tous'); ?></p>
                <p><strong>Total des Heures Travaillées :</strong> <?php echo esc_html($total_heures_travaillees_filtre); ?></p>
                <p><strong>Total des Tickets Restaurant :</strong> <?php echo esc_html($selected_chauffeur ? $total_tickets_restaurant_par_chauffeur[$selected_chauffeur] : $total_tickets_restaurant_par_chauffeur['tous']); ?></p>
                <?php if ($selected_chauffeur): ?>
                    <form method="post">
                        <input type="hidden" name="chauffeur_a_supprimer" value="<?php echo esc_attr($selected_chauffeur); ?>">
                        <button type="submit" name="supprimer_chauffeur" class="btn-supprimer">Supprimer le guide sélectionné</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Affiche les informations globales sur les chauffeurs.
     *
     * @param string $chauffeur_max_heures Nom du chauffeur ayant travaillé le plus d'heures.
     * @param string $total_heures_chauffeur_max Total des heures travaillées par le chauffeur ayant travaillé le plus d'heures.
     * @param string $moyenne_heures_travaillees Moyenne des heures travaillées par tous les chauffeurs.
     * @param int $total_tickets_restaurant Total global des tickets restaurant.
     */
    public function afficher_informations_chauffeur_max($chauffeur_max_heures, $total_heures_chauffeur_max, $moyenne_heures_travaillees, $total_tickets_restaurant) {
        $debugManager = DebugManager::getInstance();

        $moyenne_heures_travaillees_formatted = $this->format_heures($moyenne_heures_travaillees);

        ?>
        <div class="wrap">
            <h2>Informations Globales sur les guides</h2>
            <div id="chauffeur_max">
                <p><strong>Guide avec le plus d'heures :</strong> <?php echo esc_html($chauffeur_max_heures); ?></p>
                <p><strong>Avec :</strong> <?php echo esc_html($total_heures_chauffeur_max); ?> heures</p>
                <p><strong>Moyenne des Heures Travaillées par tous les guides :</strong> <?php echo esc_html($moyenne_heures_travaillees_formatted); ?> heures</p>
                <p><strong>Total global des Tickets Restaurant :</strong> <?php echo esc_html($total_tickets_restaurant); ?></p>
            </div>
        </div>
        <?php
    }

    /**
     * Affiche le tableau des données des heures travaillées.
     *
     * @param array $results Tableau des résultats contenant les données des heures travaillées.
     * @param string $selected_chauffeur Nom du chauffeur sélectionné pour le filtrage.
     */
    public function afficher_tableau_donnees($results, $selected_chauffeur) {
        ?>
        <div class="wrap">
            <h2>Données des Heures</h2>
            <form method="post">
                <input type="hidden" name="selected_chauffeur" value="<?php echo esc_attr($selected_chauffeur); ?>">
                <button type="submit" name="valider_modifications">Valider les modifications</button>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nom du guide</th>
                            <th>Date Mission</th>
                            <th>Heure Début</th>
                            <th>Coupure</th>
                            <th>Heure Fin</th>
                            <th>Heures Travaillées</th>
                            <th>Commentaire</th>
                            <th>Ticket Restaurant</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $row): ?>
                            <tr>
                                    <td><?php echo esc_html($row->id); ?></td>
                                    <td><?php echo esc_html($row->nom_chauffeur); ?></td>
                                    <td><?php echo esc_html($row->date_mission); ?></td>
                                    <td><input type="text" name="heure_debut[<?php echo $row->id; ?>]" value="<?php echo esc_html(date('H:i', strtotime($row->heure_debut))); ?>"></td>
                                    <td><input type="text" name="coupure[<?php echo $row->id; ?>]" value="<?php echo esc_html(date('H:i', strtotime($row->coupure))); ?>"></td>
                                    <td><input type="text" name="heure_fin[<?php echo $row->id; ?>]" value="<?php echo esc_html(date('H:i', strtotime($row->heure_fin))); ?>"></td>
                                    <td class="<?php echo ($row->est_superieur_a_12_heures) ? 'bold-red' : ''; ?>">
                                        <?php echo esc_html(date('H:i', strtotime($row->heures_travaillees))); ?>
                                    </td>
                                    <td><input type="text" name="commentaire[<?php echo $row->id; ?>]" value="<?php echo esc_html($row->commentaire); ?>"></td>
                                    <td><input type="checkbox" name="ticket_restaurant[<?php echo $row->id; ?>]" value="1" <?php checked($row->ticket_restaurant, 1); ?>></td>
                                </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </form>
        </div>
        <?php
    }

    /**
     * Affiche les messages de débogage.
     *
     * @param array $messages Tableau des messages de débogage.
     */
    public function afficher_messages_debug() {
        $debugManager = DebugManager::getInstance();
        $messages = $debugManager->getMessages();
        if (!empty($messages)) {
            ?>
            <div class="wrap">
                <h2>Messages de Débogage</h2>
                <ul>
                    <?php foreach ($messages as $message): ?>
                        <li><?php echo esc_html($message); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php
            $debugManager->clearMessages(); // Clear messages after displaying
        }
    }

    /**
     * Formate les heures au format HH:MM.
     *
     * @param string $time Temps au format HH:MM:SS.
     * @return string Temps au format HH:MM.
     */
    private function format_heures($time) {
        list($hours, $minutes, $seconds) = explode(':', $time);
        $hours = (int) $hours;
        $minutes = (int) $minutes;
        $total_minutes = $hours * 60 + $minutes;
        $formatted_hours = intdiv($total_minutes, 60);
        $formatted_minutes = $total_minutes % 60;
        return sprintf('%02d:%02d', $formatted_hours, $formatted_minutes);
    }
}