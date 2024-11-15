<?php
interface IDatabaseManager {
    public function vider_table();
    public function inserer_donnees($data);
    public function obtenir_totaux();
    public function update_totaux($totaux);
    public function installer();
    public function obtenir_donnees($nom_chauffeur = '');
    public function obtenir_noms_chauffeurs();
    public function update_donnees($data, $where);
    public function update_totaux_chauffeur($nom_chauffeur, $total_tickets);
}