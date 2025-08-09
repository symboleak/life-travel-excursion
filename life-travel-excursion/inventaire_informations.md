# Inventaire Complet des Fonctionnalités Life Travel

## Date de création: 29/04/2025  
## Date de mise à jour: 01/05/2025  
## Version actuelle: 2.5.0

## Description générale

Ce document fournit un inventaire exhaustif des fonctionnalités, configurations et dépendances du système Life Travel (site et plugin d'excursions). Chaque fonctionnalité est documentée avec précision pour refléter son état d'implémentation réel. Il sert à la fois de documentation technique et de guide d'utilisation pour les administrateurs, développeurs et utilisateurs du système.

## Table des matières

1. [Architecture globale](#1-architecture-globale)  
2. [Guide d'installation complète](#2-guide-dinstallation-complète) *(nouvelle section)*  
   - [Installation de WordPress](#installation-de-wordpress)  
   - [Configuration de WooCommerce](#configuration-de-woocommerce)  
   - [Installation du plugin Life Travel Excursion](#installation-du-plugin-life-travel-excursion)  
   - [Configuration initiale](#configuration-initiale)  
3. [Configuration des passerelles de paiement](#3-configuration-des-passerelles-de-paiement)  
   - [IwomiPay pour MTN Mobile Money (MoMo)](#iwomipay-pour-mtn-mobile-money-momo)  
   - [Orange Money (OM)](#orange-money-om---intégré-au-plugin)  
4. [Notifications et messagerie](#4-notifications-et-messagerie)  
5. [Dépendances techniques](#5-dépendances-techniques)  
6. [Expérience utilisateur avancée](#6-expérience-utilisateur-avancée)  
   - [Système de fidélité et points](#système-de-fidélité-et-points)  
   - [Checkout & Panier](#checkout--panier)  
7. [Optimisations pour connexions lentes](#7-optimisations-pour-connexions-lentes-contexte-camerounais)  
8. [Adaptabilité mobile](#8-adaptabilité-mobile)  
9. [Gestion des médias](#9-gestion-des-médias)  
10. [Optimisation de la base de données](#10-optimisation-de-la-base-de-données)  
11. [Architecture JavaScript modulaire](#11-architecture-javascript-modulaire)  
12. [Fonctionnalités découvertes et optimisées](#12-fonctionnalités-découvertes-et-optimisées) *(nouvelle section)*  
13. [Administration des excursions](#13-administration-des-excursions)  
14. [Guide complet de l'administrateur](#14-guide-complet-de-ladministrateur) *(nouvelle section)*  
    - [Configuration du système de fidélité](#configuration-du-système-de-fidélité)  
    - [Gestion des optimisations réseau](#gestion-des-optimisations-réseau)  
    - [Maintenance quotidienne](#maintenance-quotidienne)  
15. [Documentation technique pour développeurs](#15-documentation-technique-pour-développeurs) *(nouvelle section)*  
    - [Architecture du code](#architecture-du-code)  
    - [Points d'extension](#points-dextension)  
    - [Guide de développement](#guide-de-développement)  
16. [Conseils d'utilisation et maintenance](#16-conseils-dutilisation-et-maintenance)  
17. [Intégrations externes](#17-intégrations-externes)  

---

## 1. ARCHITECTURE GLOBALE

### Structure du projet
- **Site WordPress Principal**: `C:\Users\symbo\Documents\Projets\SiteVoyage/`  
  - Plugin Core: `lifetravel_core/`  
  - Thème: `lifetravel_theme/`  
- **Plugin d'Excursions**: `C:\Users\symbo\Documents\Projets\SiteVoyage\lifetravel_plugin/`  

### Principales divisions fonctionnelles
1. **Core du site**: Gestion des types de contenu, blocs Gutenberg génériques  
2. **Thème**: Présentation, styling, templates et adaptations responsives  
3. **Plugin d'Excursions**: Fonctionnalités spécifiques aux excursions, e-commerce et paiements  

### Responsabilités actuelles (à optimiser)
- **Duplication de fonctionnalités**: Custom Post Type dans Core + Produit WooCommerce dans Plugin  
- **Dispersion des médias**: Gestion répartie entre le thème et le plugin  
- **Optimisations réseau**: Dans le plugin mais devrait être transversale  
- **Interface administrateur**: Fragmentée à travers plusieurs écrans  

---

## 2. GUIDE D'INSTALLATION COMPLÈTE

### Prérequis serveur

- **PHP**: 8.0 ou supérieur  
  - **Extensions requises**: curl, json, fileinfo, gd, intl, mbstring, xml  
  - **Configurations php.ini**:  
    ```ini
    memory_limit = 256M
    max_execution_time = 300
    upload_max_filesize = 64M
    post_max_size = 64M
    ```  
- **MySQL/MariaDB**: 5.7+ / 10.3+  
  - **Jeu de caractères**: UTF-8 (utf8mb4_unicode_ci)  
  - **Privilèges utilisateur**: CREATE, ALTER, SELECT, INSERT, UPDATE, DELETE  
  - **Base dédiée recommandée**: Isoler de toute autre application  
- **Serveur web**: Apache 2.4+ ou Nginx 1.18+  
  - **Modules Apache**: mod_rewrite, mod_headers, mod_expires  
  - **Configuration Nginx**: voir exemple ci-dessous  
  - **HTTPS recommandé**: Certificat SSL valide pour les paiements  

### Installation de WordPress

1. **Téléchargement de WordPress**  
   ```bash
   # Télécharger la dernière version de WordPress
   wget https://wordpress.org/latest.zip
   unzip latest.zip
   mv wordpress/* /chemin/vers/domaine/
Tableau de bord des statistiques de fidélité
Aller à "Life Travel" → "Fidélité" → "Statistiques"
Analyser les données disponibles:
Utilisateurs les plus fidèles: Classement
Distribution des points: Par source
Tendances d'utilisation: Graphiques
Excursions populaires: Par points générés
Utiliser les outils d'export:
CSV pour analyse externe
PDF pour rapports
(Cette page donne un aperçu de l'efficacité du programme de fidélité. Par exemple, vous pouvez voir si
beaucoup de points expirent sans être utilisés, signe qu'il faudrait peut-être abaisser le seuil minimal, etc.)
Gestion des optimisations réseau
Configuration des optimisations Cameroun
Aller à "Life Travel" → "Réseau" → "Optimisations"
Configurer les paramètres:
Détection auto: Activer/désactiver
Mode hors-ligne: Configurer niveau
File d'attente des paiements: Niveau de priorité
Compression des images: Qualité adaptative
(Par défaut, la détection auto est activée, ce qui signifie que le site ajuste automatiquement ses optimisations
en fonction de la connexion de l'utilisateur. Vous pouvez forcer un "Mode hors-ligne" particulier pour tester,
mais en production laissez sur auto.)
Tests de performance réseau
Aller à "Life Travel" → "Réseau" → "Test de performance"
Utiliser les outils disponibles:
Test de latence
Test de bande passante
Simulateur de connexion lente
Appliquer les recommandations automatiques
(Le simulateur de connexion lente vous permet de visualiser le site tel qu'un utilisateur en 2G le verrait. Après
les tests, des recommandations s'affichent (ex: "Vos images de bannière sont encore lourdes, envisagez de les
compresser davantage"). Vous pouvez appliquer certaines optimisations en un clic depuis cette interface.)
Statistiques réseau
Aller à "Life Travel" → "Réseau" → "Statistiques"
Analyser les métriques:
Temps de chargement moyen
Taux de synchronisation hors-ligne
Taux de compression des médias
Utilisateurs par type de connexion
1.
2.
3.
4.
5.
6.
7.
8.
9.
1.
2.
3.
4.
5.
6.
1.
2.
3.
4.
5.
6.
1.
2.
3.
4.
5.
6.
47
(Cette page statistique permet de vérifier l'impact de vos optimisations. Par ex, si le "taux de synchronisation
offline" est bas, ça signifie que peu d'utilisateurs reviennent en ligne pour terminer leur réservation,
possiblement un problème UX. Si beaucoup d'utilisateurs ont une connexion "très lente", vous verrez ce
segment en % et pourriez décider d'ajuster encore la qualité des médias pour eux.)
Maintenance quotidienne
Vérifications journalières
Réservations: Vérifier les nouvelles réservations et confirmer
Paiements: Vérifier les paiements en attente/échecs
Stock d'excursions: Vérifier disponibilité et capacités
Points de fidélité: Surveiller distribution et utilisation
Vérifications hebdomadaires
Sauvegarde: Confirmer les sauvegardes automatiques
Performance: Vérifier statistiques de vitesse
Mise à jour: Planifier updates si disponibles
Contenu: Rafraîchir excursions populaires
Vérifications mensuelles
Nettoyage base de données: Optimiser les tables
Statistiques complètes: Exporter et analyser
Réindexation: Reconstruire index AJAX et recherche
Ajustements fidélité: Revoir taux et plafonds
Procédures de maintenance
Optimisation de la base de données
1. Aller à "Life Travel" → "Base de données" → "Optimisation"
2. Cliquer sur "Analyser les tables"
3. Sélectionner les tables à optimiser ou "Toutes"
4. Cliquer sur "Optimiser"
5. Vérifier le rapport de résultats
Purge du cache
1. Aller à "Life Travel" → "Performance" → "Cache"
2. Sélectionner les types de cache à vider
3. Cliquer sur "Purger la sélection"
4. Vérifier confirmation de nettoyage
Gestion des paniers abandonnés
1. Aller à "Life Travel" → "Paniers" → "Abandonnés"
2. Filtrer par période ou valeur
3. Actions disponibles:
- Envoyer rappel manuel
- Offrir coupon de récupération
- Archiver les anciens
1.
2.
3.
4.
1.
2.
3.
4.
1.
2.
3.
4.
48
Résolution des problèmes courants
Problèmes de paiement
Erreur MoMo: Vérifier configuration IwomiPay et clés API
Échec de transaction: Consulter journal des transactions
Paiement bloqué: Vérifier statut de la commande et débloquer
Problèmes de réservation
Capacité incorrecte: Vérifier paramètres d'excursion
Prix erroné: Vider cache de prix et recalculer
Conflit de dates: Vérifier calendrier et disponibilité
Problèmes de points de fidélité
Points non attribués: Vérifier journal et attribuer manuellement
Impossible d'utiliser: Vérifier solde et seuil minimum
Historique incorrect: Accéder au profil utilisateur et ajuster
Support et aide
Documentation: Accéder à la documentation complète
Support technique: Contacter le support via le formulaire dédié
Vidéos tutoriels: Accéder à la bibliothèque de vidéos
(En cas de problème persistant, pensez à vérifier les logs (dans Life Travel → Journal des modifications, ou
dans debug.log si WP_DEBUG_LOG est activé). La documentation technique peut aussi fournir des indices en
cas de comportement inattendu.)
14. DOCUMENTATION TECHNIQUE POUR DÉVELOPPEURS
Architecture du code
Structure des répertoires
life-travel-excursion/
├── includes/ # Code PHP du plugin
│ ├── admin/ # Interfaces administrateur
│ │ ├── class-life-travel-admin.php # Point d'entrée
admin
│ │ ├── class-life-travel-admin-renderers-*.php # Renderers admin
│ │ ├── loyalty-*.php # Admin fidélité
│ ├── ajax/ # Points d'entrée AJAX
│ │ ├── availability-ajax.php # Disponibilité
│ │ ├── pricing-ajax-optimizer.php # Calcul de prix
│ ├── frontend/ # Interface utilisateur
│ │ ├── loyalty-*.php # Système fidélité
frontend
│ ├── assets/ # Ressources statiques
•
•
•
•
•
•
•
•
•
•
•
•
49
│ │ ├── js/ # Scripts JavaScript
│ │ │ ├── modules/ # Architecture modulaire JS
│ │ │ ├── offline-*.js # Support hors-ligne
│ │ │ ├── admin/ # Scripts admin
│ ├── css/ # Styles CSS
│ ├── img/ # Images du plugin
│ ├── templates/ # Modèles de rendu
│ │ ├── emails/ # Templates d'emails
│ │ ├── myaccount/ # Templates compte utilisateur
│ ├── docs/ # Documentation
│ ├── tests/ # Tests automatisés
Diagramme d'architecture
┌────────────────────────┐ ┌────────────────────────┐
│ FRONTEND │ │ ADMIN │
│ │ │ │
│ ┌──────────────────┐ │ │ ┌──────────────────┐ │
│ │ Templates │ │ │ │ Admin Renderers │ │
│ └──────────────────┘ │ │ └──────────────────┘ │
│ │ │ │ │ │
│ ┌──────────────────┐ │ │ ┌──────────────────┐ │
│ │ Core Classes │──┼──────┼──│ Admin Classes │ │
│ └──────────────────┘ │ │ └──────────────────┘ │
│ │ │ │ │ │
└────────────────────────┘ └────────────────────────┘
│ │
│ │
▼ ▼
┌────────────────────────┐ ┌────────────────────────┐
│ MODULES │ │ INFRASTRUCTURE │
│ │ │ │
│ ┌──────────────────┐ │ │ ┌──────────────────┐ │
│ │ Loyalty System │ │ │ │ Database Layer │ │
│ └──────────────────┘ │ │ └──────────────────┘ │
│ ┌──────────────────┐ │ │ ┌──────────────────┐ │
│ │ Offline Support │ │ │ │ WooCommerce │ │
│ └──────────────────┘ │ │ └──────────────────┘ │
│ ┌──────────────────┐ │ │ ┌──────────────────┐ │
│ │ Price Calculator│ │ │ │ WordPress Hooks │ │
│ └──────────────────┘ │ │ └──────────────────┘ │
└────────────────────────┘ └────────────────────────┘
Flux de données principales
Réservation d'excursion:
Utilisateur → Sélection excursion → Calculateur de prix →
50
Panier WooCommerce → Checkout → Paiement IwomiPay →
Traitement commande → Attribution points
Utilisation points fidélité:
Utilisateur → Panier → Formulaire points → Application réduction →
Checkout → Paiement → Déduction points
Points d'extension
Filtres WordPress
// Modifier le prix calculé d'une excursion
apply_filters('life_travel_excursion_price', $price, $product_id,
$participants, $extras);
// Modifier les places disponibles pour une excursion
apply_filters('life_travel_available_slots', $available, $product_id,
$date);
// Modifier les points de fidélité attribués
apply_filters('lte_points_earned', $points, $order_id, $user_id);
// Modifier le facteur de conversion points → monnaie
apply_filters('lte_points_conversion_rate', $rate, $user_id, $context);
// Modifier la détection de réseau Cameroun
apply_filters('lte_network_detection', $network_info, $user_id);
Actions WordPress
// Déclenché après l'attribution de points
do_action('lte_points_awarded', $user_id, $points, $source, $order_id);
// Déclenché après l'utilisation de points
do_action('lte_points_redeemed', $user_id, $points, $order_id, $amount);
// Déclenché lors d'une réservation hors-ligne
do_action('lte_offline_booking_saved', $booking_data, $user_id, $sync_id);
// Déclenché lors d'un changement de qualité réseau
do_action('lte_network_quality_changed', $old_quality, $new_quality,
$user_id);
51
Classes d'extension
// Exemple d'extension du calculateur de prix
class My_Custom_Price_Calculator extends Life_Travel_Price_Calculator {
public function calculate_price($product_id, $participants, $start_date,
$end_date, $extras = []) {
// Prix de base calculé par la classe parente
$price = parent::calculate_price($product_id, $participants,
$start_date, $end_date, $extras);
// Application de logique personnalisée
if (my_custom_condition()) {
$price *= 0.9; // Exemple: 10% de réduction
}
return $price;
}
}
// Exemple d'extension pour les points de fidélité
class My_Custom_Loyalty_Manager extends Life_Travel_Loyalty_Manager {
public function calculate_points($order_total, $user_id, $product_id =
null) {
// Points calculés par la classe parente
$points = parent::calculate_points($order_total, $user_id,
$product_id);
// Application de logique personnalisée
if ($this->is_birthday($user_id)) {
$points *= 2; // Exemple: Double points pour l'anniversaire
}
return $points;
}
}
Guide de développement
Environnement de développement
Installation locale
# Cloner le repository
git clone https://repository-url.git life-travel-excursion
cd life-travel-excursion
# Installer dépendances
composer install
npm install
# Build des assets
npm run dev # Pour développement (avec sourcemaps)
1.
52
Workflow de développement
Utiliser le mode développement dans wp-config.php:
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', true);
define('SCRIPT_DEBUG', true);
Activer les logs verbeux dans le plugin:
// Dans fichier principal du plugin
define('LTE_DEBUG', true);
Structure des commits Git:
Format: [type]: description courte
Types: feat , fix , docs , style , refactor , test , chore
Exemple: feat: ajout système de points fidélité par partage
Documentation du code
Respectez ces standards pour toute nouvelle contribution:
/**
* Calcule les points de fidélité basés sur le montant de la commande.
*
* Cette fonction prend en compte les paramètres globaux et spécifiques
* du produit pour déterminer les points à attribuer.
*
* @since 2.3.0
* @param float $order_total Montant total de la commande
* @param int $user_id ID de l'utilisateur
* @param int $product_id ID du produit (optionnel)
* @return int Nombre de points à attribuer
*/
public function calculate_loyalty_points($order_total, $user_id, $product_id
= null) {
// Corps de la fonction
}
Tests automatisés
Exécution des tests
# Installer PHPUnit si nécessaire
composer require --dev phpunit/phpunit
2.
3.
4.
5.
◦
◦
◦
1.
53
# Exécuter les tests
./vendor/bin/phpunit
Structure des tests
Tests unitaires: tests/unit/
Tests d'intégration: tests/integration/
Tests frontaux: tests/e2e/
Exemple de test unitaire
class Test_Loyalty_Points extends WP_UnitTestCase {
public function test_points_calculation() {
// Arrange
$order_total = 100000; // 100,000 XAF
$user_id = 1;
$product_id = 123;
// Simuler un produit avec 5% de points
update_post_meta($product_id, '_lte_loyalty_type', 'percent');
update_post_meta($product_id, '_lte_loyalty_value', '5');
$loyalty = new Life_Travel_Loyalty_Manager();
// Act
$points = $loyalty->calculate_loyalty_points($order_total,
$user_id, $product_id);
// Assert
$this->assertEquals(5000, $points); // 5% de 100,000 = 5,000
points
}
}
15. CONSEILS D'UTILISATION ET MAINTENANCE
Optimisations recommandées
Cache:
Activer un plugin de cache pour les pages statiques
Recommandés: WP Rocket, W3 Total Cache, LiteSpeed Cache
Configuration spéciale: Exclure pages de paiement et checkout du cache
Optimisation images:
Utiliser WebP quand disponible (activé dans l'interface Media)
Compression adaptative selon qualité réseau
Redimensionnement automatique aux dimensions exactes
2.
3.
4.
5.
6.
•
•
•
•
•
•
•
•
54
Minification:
Activer pour CSS/JS en production
Concaténation des fichiers
Différer le chargement des scripts non-critiques
CDN:
Configurer si disponible pour les ressources statiques
Recommandations pour le Cameroun: Éviter si bande passante internationale limitée
Alternative locale: Optimisation du serveur local
Paramètres réseau:
Tableau de bord → Réseau et performances → Optimisations pour connexions lentes
Détection automatique de la qualité de connexion
Modes d'économie de données
Système de fidélité
Vérifications:
Attribution des points pour chaque excursion configurée
Plafonnement correct des points
Conversion des points en réduction
Affichage et fonctionnement des notifications
Configuration:
Produits → Fidélité → Paramètres globaux
Par excursion: Dans la section "Système de points de fidélité"
Partages sociaux: Dans les paramètres de fidélité
Sauvegardes
Fréquence: Quotidienne recommandée
Contenu à inclure: Base de données, uploads, plugins, thème
Rotation: Conserver au moins 7 jours d'historique
Test de restauration: Vérifier régulièrement
Mise à jour
Environnement de test: Toujours tester les mises à jour
Séquence recommandée: WordPress, puis Plugins, puis Thème
Vérifications post-mise à jour:
Passerelles de paiement
Calculateur de prix
Fonctionnement hors-ligne
Adaptabilité mobile
Monitoring
Surveiller: Temps de réponse, erreurs 404/500, tentatives de connexion
Diagnostics réseau: Vérifier performances avec connexion lente
Alertes: Configurer pour échecs de paiement ou erreurs critiques
```
•
•
•
•
•
•
•
•
•
•
•
•
•
•
•
•
•
•
•
•
•
•
•
•
•
•
•
•
•
•
•
•
•
•
•
55
inventaire_informations.md
file://file-EUnJ4gTKMH9eNidZwuEL7i
Plan d’intégration
technique pas-à-pas (Claude 3.7 Sonnet Thinking – Windsurf, Mai 2025).pdf
file://file-Fj7xcGPCdTVPv2kHmWopNj
1 2 3 4 5 6 9 10 11 12 13 16 17 18 19 20 21 22 23 24 25 26 27 28 29 30 31 32 33
34 35 36 37 39 40 41 44 45 46 47 48 49 50 51 52 53 54 55 56 57 61 62 64 66 68 69 71 72
73 74 75 85 86 87 88 94 95 96 97 125 126 127 128 129 130 131 132 133 134 135 137 138 139 140 141
142 143 144 145 152 164 167 168 170
7 8 14 15 38 42 43 58 59 60 63 65 67 70 76 77 78 79 80 81 82 83 84 89 90 91 92 93 98
99 100 101 102 103 104 105 106 107 108 109 110 111 112 113 114 115 116 117 118 119 120 121 122 123 124 136
146 147 148 149 150 151 153 154 155 156 157 158 159 160 161 162 163 165 166 169 171
56
