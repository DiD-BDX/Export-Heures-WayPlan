# Export-Heures-WayPlan

Importer un fichier CSV généré par WayPlan, le modifier avec le plugin et exporter le résultat dans un fichier Excel.

## Sommaire

- [Export-Heures-WayPlan](#export-heures-wayplan)
  - [Sommaire](#sommaire)
  - [Description](#description)
  - [Installation](#installation)
  - [Utilisation](#utilisation)
    - [Shortcodes](#shortcodes)
    - [Fonctionnalités](#fonctionnalités)
  - [Format du fichier CSV](#format-du-fichier-csv)
  - [Développement](#développement)
  - [Crédits](#crédits)

## Description

Le plugin **Export-Heures-WayPlan** permet d'importer des données RH depuis un fichier CSV généré par WayPlan, de les modifier et de les exporter dans un fichier Excel. Il permet également de recalculer les totaux des heures travaillées et des tickets restaurant.

## Installation

1. Téléchargez le plugin depuis le [dépôt GitHub](https://github.com/DiD-BDX/Export-Heures-WayPlan).
2. Décompressez le fichier ZIP téléchargé.
3. Téléversez le dossier `export-heures-wayplan` dans le répertoire `wp-content/plugins/` de votre installation WordPress.
4. Activez le plugin via le menu `Plugins` dans l'interface d'administration de WordPress.
5. Le plugin créera automatiquement les tables nécessaires dans la base de données lors de son activation.

## Utilisation

### Shortcodes

Le plugin fournit plusieurs shortcodes pour afficher différentes fonctionnalités sur vos pages ou articles WordPress :

- `[export_heures]` - Affiche la page principale du plugin Export Heures.
- `[generer_excel_button]` - Affiche le bouton "Générer le fichier Excel".
- `[telecharger_excel_button]` - Affiche le bouton "Télécharger le fichier Excel".
- `[formulaire_import_csv]` - Affiche le formulaire d'importation de fichier CSV.
- `[filtrage_chauffeur]` - Affiche les boutons de filtrage par chauffeur.
- `[chauffeur_selectionne]` - Affiche les informations du chauffeur sélectionné.
- `[chauffeur_max]` - Affiche les informations du chauffeur ayant travaillé le plus d'heures.
- `[tableau_donnees]` - Affiche le tableau des données des heures travaillées.

### Fonctionnalités

1. **Importer un fichier CSV** :
   - Utilisez le formulaire d'importation pour téléverser un fichier CSV contenant les données RH.
   - Le plugin convertira automatiquement le fichier en UTF-8 et insérera les données dans la base de données.

2. **Afficher les données** :
   - Utilisez le shortcode `[tableau_donnees]` pour afficher un tableau des heures travaillées.
   - Filtrez les données par chauffeur en utilisant le shortcode `[filtrage_chauffeur]`.

3. **Recalculer les totaux** :
   - Le plugin recalculera automatiquement les totaux des heures travaillées et des tickets restaurant après chaque importation ou modification des données.

4. **Générer et télécharger un fichier Excel** :
   - Utilisez le bouton "Générer le fichier Excel" pour créer un fichier Excel contenant les données modifiées.
   - Téléchargez le fichier Excel généré en utilisant le bouton "Télécharger le fichier Excel".

## Format du fichier CSV

Pour que le plugin fonctionne correctement, le fichier CSV doit être formaté comme suit :

- Le fichier doit être encodé en UTF-8.
- Les colonnes doivent être séparées par des points-virgules (`;`).
- La première ligne du fichier CSV doit contenir les en-têtes des colonnes.
- Les colonnes doivent être dans l'ordre suivant :
  1. `nom_chauffeur` : Le nom du chauffeur (ex. : "John Doe").
  2. `date_mission` : La date de la mission (ex. : "2023-10-01").
  3. `heure_debut` : L'heure de début de la mission (ex. : "08:00").
  4. `heure_fin` : L'heure de fin de la mission (ex. : "17:00").

Exemple de fichier CSV :

```csv
nom_chauffeur;date_mission;heure_debut;heure_fin
John Doe;2023-10-01;08:00;17:00
Jane Smith;2023-10-01;09:00;18:00
```
## Développement

Pour contribuer au développement de ce plugin, suivez les étapes ci-dessous :

1. Clonez le dépôt GitHub :
   ```sh
   git clone https://github.com/DiD-BDX/Export-Heures-WayPlan.git
   ```
2. Installez les dépendances via Composer :
   ```sh
   composer install
   ```
3. Faites vos modifications et soumettez une pull request.

## Crédits

- **Auteur** : Didier BD
- **GitHub** : [DiD-BDX](https://github.com/DiD-BDX)
- **Version** : 9.6