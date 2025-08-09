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
   ```  

2. **Création de la base de données**  
   ```sql
   CREATE DATABASE life_travel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'life_travel_user'@'localhost' IDENTIFIED BY 'motdepasse_sécurisé';
   GRANT ALL PRIVILEGES ON life_travel.* TO 'life_travel_user'@'localhost';
   FLUSH PRIVILEGES;
   ```  

3. **Configuration de wp-config.php**  
   ```bash
   cp wp-config-sample.php wp-config.php
   nano wp-config.php
   ```  
   Éditer les informations de connexion et ajouter:  
   ```php
   // Clés de sécurité (générer sur https://api.wordpress.org/secret-key/1.1/salt/)
   define('AUTH_KEY', 'valeur_unique');
   // [...] Autres clés

   // Configuration spécifique Life Travel
   define('WP_MEMORY_LIMIT', '256M');
   define('WP_MAX_MEMORY_LIMIT', '512M');
   define('WP_DEBUG', false); // true uniquement en développement
   define('WP_DEBUG_LOG', true);
   define('DISALLOW_FILE_EDIT', true);
   define('WP_AUTO_UPDATE_CORE', 'minor');
   define('WP_CACHE', true);
   ```  

4. **Installation via navigateur**  
   - Accéder à l'URL du site  
   - Suivre l'installation en utilisant les informations de la base de données  
   - Créer un compte administrateur avec un mot de passe fort  

### Installation de WooCommerce

1. **Installation de WooCommerce**  
   - Dans l'admin WordPress, aller à Extensions > Ajouter  
   - Rechercher "WooCommerce" et cliquer sur "Installer"  
   - Activer l'extension après l'installation  

2. **Assistant de configuration WooCommerce**  
   - Lancer l'assistant de configuration  
   - Configurer les éléments suivants:  
     - **Emplacement de la boutique**: Cameroun  
     - **Devise**: Franc CFA (XAF)  
     - **Produits physiques/numériques**: Configurer pour services (excursions)  
     - **TVA/Taxes**: Selon la réglementation camerounaise  
     - **Paiement**: Laisser temporairement avec les options par défaut  

3. **Configuration spécifique pour excursions**  
   - Aller dans Réglages > WooCommerce > Général  
     - Devise: XAF  
     - Format de devise: 50 000 FCFA (avec espace)  
   - Aller dans Réglages > WooCommerce > Produits > Général  
     - Unités de poids: kg  
     - Unités de dimension: cm  
   - Aller dans Réglages > WooCommerce > Comptes et confidentialité  
     - Activer l'inscription des utilisateurs  
     - Activer les comptes et commander en tant qu'invité  

### Installation du plugin Life Travel Excursion

1. **Téléchargement du plugin**  
   - Télécharger le ZIP du plugin depuis le dépôt officiel  
   - Alternative: Cloner le repository Git si disponible  

2. **Installation manuelle**  
   ```bash
   # Extraire le plugin dans le répertoire des extensions
   unzip life-travel-excursion.zip -d /chemin/vers/wp-content/plugins/
   chmod -R 755 /chemin/vers/wp-content/plugins/life-travel-excursion
   ```  

3. **Activation du plugin**  
   - Dans l'admin WordPress, aller à Extensions > Extensions installées  
   - Trouver "Life Travel Excursion" et cliquer sur "Activer"  

4. **Installer les dépendances Composer**  
   ```bash
   cd /chemin/vers/wp-content/plugins/life-travel-excursion
   composer install --no-dev --optimize-autoloader
   ```  

5. **Construire les assets frontend**  
   ```bash
   cd /chemin/vers/wp-content/plugins/life-travel-excursion
   npm install
   npm run build
   ```  

### Configuration initiale

1. **Page d'accueil et structure**  
   - Créer une page d'accueil  
   - Dans Réglages > Lecture, définir une page statique comme page d'accueil  

2. **Permaliens optimisés**  
   - Dans Réglages > Permaliens, choisir "Titre de publication"  

3. **Configuration du plugin Life Travel**  
   - Accéder à Life Travel > Tableau de bord  
   - Compléter les paramètres initiaux:  
     - Informations entreprise  
     - Logo et couleurs  
     - Configuration des excursions de base  
     - Réglages de réseau (spécifique Cameroun)  

4. **Installation des plugins complémentaires recommandés**  
   - Wordfence Security (sécurité)  
   - Yoast SEO (référencement)  
   - WP Rocket ou similaire (cache)  
   - Updraft Plus (sauvegardes)  
   - TranslatePress (si site multilingue nécessaire)  

5. **Vérification post-installation**  
   - Tester la création d'une excursion  
   - Vérifier le processus de réservation de bout en bout  
   - Confirmer que toutes les optimisations réseau sont actives  
   - Tester le système de fidélité avec un compte de test  

### Configuration de la base de données supplémentaires

Le plugin nécessite certaines tables personnalisées en plus des tables WordPress standard:

```sql
-- Exécuter ces requêtes si l'activation du plugin ne les a pas créées

-- Table des statistiques de fidélité
CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}lte_points_history` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `points` int(11) NOT NULL,
  `source` varchar(50) NOT NULL,
  `product_id` bigint(20) unsigned DEFAULT NULL,
  `order_id` bigint(20) unsigned DEFAULT NULL,
  `date_created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `product_id` (`product_id`),
  KEY `order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des rédemptions de points
CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}lte_points_redemption` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `points` int(11) NOT NULL,
  `order_id` bigint(20) unsigned NOT NULL,
  `discount_amount` decimal(10,2) NOT NULL,
  `date_created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des notifications push
CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}lte_push_notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(50) NOT NULL,
  `data` text DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `date_created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `is_read` (`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

3. CONFIGURATION DES PASSERELLES DE PAIEMENT
IwomiPay pour MTN Mobile Money (MoMo)
* Dossier d'installation: payment-gateways/iwomipay-momo-woocommerce/
* Plugin WordPress: Fichier ZIP iwomipay-momo-woocommerce.zip
* Paramètres de production:
* Nom d'utilisateur IwomiPay
* Mot de passe IwomiPay
* Clé d'identification (Credi Key)
* Secret d'identification (Credi Secret)
* Paramètres Sandbox: (similaires à la production, fournis par IwomiPay)
Le plugin IwomiPay gère l'interface avec l'API Mobile Money. Veuillez saisir les identifiants exacts fournis pour l'environnement choisi (Sandbox ou Live).
Orange Money (OM) - Intégré au plugin
* Intégration directe: Dans le plugin principal
* Icône: /assets/img/orange-money.png
* Configuration: Via Customizer ? Design ? Upload d'icônes de paiement
* Paramètres: (identiques à MTN MoMo)
Orange Money est pris en charge nativement par le plugin Life Travel Excursion. Les identifiants API Orange (ou IwomiPay correspondants) doivent être renseignés dans les réglages du plugin. L'icône OM sera utilisée sur la page de paiement (à téléverser via le Customizer).
URLs API communes
* Production: https://api.iwomipay.com/
* Sandbox: https://sandbox.iwomipay.com/
Les URL de l'API IwomiPay pour Mobile Money sont spécifiées ci-dessus. Assurez-vous de passer en URL de Production une fois en phase de lancement réel, et de tester au préalable via le Sandbox.

4. NOTIFICATIONS ET MESSAGERIE
Configuration Twilio
* Identifiants requis:
* Account SID
* Auth Token
* Numéro de téléphone Twilio (avec indicatif pays)
* Numéro WhatsApp Business
* Dépendance: composer require twilio/sdk
Renseignez les identifiants Twilio dans la page de configuration du plugin. Ils serviront pour l'envoi de SMS et messages WhatsApp. (WhatsApp via Twilio est prévu, non actif en v2.5.0).
Canaux de notification
* Email: Configuration SMTP requise
* SMS: Via Twilio ou méthode de secours
* WhatsApp: Via Twilio Business API (prévu, non actif en v2.5.0)
* In-app: Notifications navigateur via Push API (non implémenté en v2.5.0)
Le système supporte plusieurs canaux de notification. Actuellement, les emails sont utilisés (pensez à configurer un SMTP pour fiabilité). Les SMS et WhatsApp pourront être activés via Twilio dès que la fonctionnalité sera finalisée. Les notifications in-app (push navigateur) sont envisagées pour une future version.
Système de notification avancé
* Administration: Email, SMS, WhatsApp pour nouvelles réservations
* Utilisateurs: Préférences par canal (Email, SMS, WhatsApp)
* Types: Commandes, réservations, rappels, compte, sécurité
* Éditeur de modèles: Interface visuelle pour personnaliser sans code
Le plugin prévoit un système complet de notifications paramétrables. Par exemple, un administrateur peut choisir de recevoir un SMS et un email pour chaque nouvelle réservation. Un utilisateur pourra préférer les confirmations par WhatsApp. Un éditeur de modèles (e-mail/SMS) est disponible pour personnaliser les messages (variables dynamiques pour nom, date d'excursion, etc.).

5. DÉPENDANCES TECHNIQUES
Serveur
* PHP: 8.0 ou supérieur
* WordPress: 6.0 ou supérieur
* WooCommerce: 8.0 ou supérieur
* Extensions PHP: curl, json, fileinfo, gd, intl
* Composer: Pour les bibliothèques externes
Frontend
* Node.js & npm: Version 14+
* Build process: Webpack pour JS/CSS via npm run build
* Assets structure: Sources dans /assets/, bundlés vers /assets/dist/
Tests
* PHPUnit: Via vendor/bin/phpunit
* Composer: Pour gestion des dépendances de test
Le projet utilise Composer pour les packages PHP (Twilio SDK, etc.), et Node/Webpack pour compiler les fichiers front (JS, SCSS). Assurez-vous d'installer ces dépendances lors de la mise en place du dev. Des tests unitaires sont configurés ; exécutez-les pour valider les composants critiques.

5. EXPÉRIENCE UTILISATEUR AVANCÉE
Système de fidélité et points
* Fonctionnalité: Attribution et utilisation de points de fidélité
* Fichiers principaux:
o /includes/frontend/loyalty-excursions.php: Gestion des points d'excursion
o /includes/frontend/loyalty-social.php: Points pour partages sociaux
o /includes/frontend/loyalty-integration.php: Coordination des composants
o /includes/admin/loyalty-admin.php: Interface d'administration
* Paramétrage par excursion:
o Type d'attribution: Points fixes ou pourcentage du montant
o Valeur des points: Nombre de points ou pourcentage
o Plafond spécifique: Limite maximum par excursion
* Configuration globale:
o Plafond global: Limite maximum de points par transaction
o Valeur d'échange: Nombre de points pour 1 (par défaut: 100)
o Réduction maximale: Pourcentage maximum du total (par défaut: 25%)
* Points par réseau social:
o Facebook: Configurable (par défaut: 10 points)
o Twitter: Configurable (par défaut: 10 points)
o WhatsApp: Configurable (par défaut: 5 points)
o Instagram: Configurable (par défaut: 15 points) (pas de suivi automatique)
* Interface utilisateur:
o Tableau de bord de fidélité: Dans l'espace client
o Notifications: Alertes pour les nouveaux points gagnés
o Utilisation: Formulaire au checkout pour appliquer des points
* Statistiques admin:
o Utilisateurs avec points
o Distribution des points
o Configuration par excursion
Le système de fidélité permet de récompenser les clients par des points convertibles en réductions. Chaque excursion peut définir son barème, et des points supplémentaires peuvent être octroyés pour certaines actions (ex: partage sur Facebook). Note: l'attribution automatique de points pour Instagram n'est pas supportée techniquement dans la version actuelle. Le solde de points est visible dans "Mon Compte" (onglet "Mes points de fidélité"), et utilisable lors du paiement via un champ dédié.
Checkout & Panier
* Étapes visuelles: Processus guidé avec validation
* Récupération des paniers: Système robuste pour sessions interrompues
* Interface Admin: Tableau de bord ? Paniers abandonnés ? Statistiques
* Délais configurables: 1h, 3h, 12h, 24h, personnalisé
* Emails automatiques: Modèles personnalisables avec tokens
* SMS optionnels: Via API Twilio (configuration séparée requise)
* Modes de stockage:
* Utilisateurs connectés: Base de données WordPress
* Anonymes: Cookies et LocalStorage
* Synchronisation hors-ligne: Support pour les interruptions réseau
* Reconstructions des paniers: Mécanisme pour sessions expirées
* Sécurité:
* Validations: Intégrité des données et permissions
* Nonces: Protection CSRF systématique
* Sanitization: Échappement et validation des entrées
* Journalisation: Traces des événements critiques
Le tunnel de commande est optimisé pour réduire les abandons. Si un utilisateur quitte en cours de réservation, le panier est sauvegardé (en cookie ou BDD selon cas). Des emails de rappel de panier abandonné peuvent être envoyés après X heures d'inactivité, avec éventuellement un code promo incitatif. L'administrateur dispose d'un écran listant les paniers abandonnés pour suivre les conversions manquées. À noter: un mode hors-ligne partiel est en place (cache navigateur) pour permettre la récupération du panier même après une perte de connexion, mais la synchronisation complète hors-ligne/en-ligne sera introduite dans une future version.
Comptes clients
* Onglets personnalisés: "Mes excursions" et "Mes points de fidélité"
* Configuration: Via Customizer ? Comptes clients
* Préférences: Gestion par type et par canal de notification
Calculateur de prix dynamique
* Facteurs : Type d'excursion, participants, extras, dates, remises
* Intégrations : Pages produit, widget de réservation, shortcodes
* Compatibilité : Mobile, desktop, mode partiellement hors-ligne
Le calculateur de prix dynamique recalcule en temps réel le tarif en fonction des choix de l'utilisateur (nombre de participants, options supplémentaires, date – par exemple, saison haute vs basse). Il est intégré à la page d'excursion (via un module JS et un shortcode), afin que le client voie le prix final avant d'ajouter au panier. Ce calculateur fonctionne aussi hors-ligne pour donner une estimation continue même sans réseau (les règles étant chargées au préalable).

7. OPTIMISATIONS POUR CONNEXIONS LENTES (CONTEXTE CAMEROUNAIS) (mode hors-ligne partiel)
Interface d'administration
* Tableau de bord : Performances ? Analyse des performances
* Fichier principal : /includes/admin/class-life-travel-admin-renderers-performance.php
* Statistiques réelles: Temps de chargement, taux de réussite du cache, stabilité
* Graphiques: Visualisation par type de réseau et impact des optimisations
* Configuration: Activation/désactivation des fonctionnalités avancées
* Seuils personnalisables: Définition des temps de réponse par catégorie
* Métriques offline: Score de capacité hors-ligne et évaluation par page
Optimiseur Camerounais
* Nouvelle implémentation: Optimiseur spécifique pour réseaux instables
* Fichier principal: /includes/cameroon-assets-optimizer.php
* Configuration: Via interface admin ou détection automatique
* Statistiques: Collection et analyse des performances réseau
* Cache local: Optimisé pour minimiser les requêtes HTTP
* Support hors-ligne: Fonctionnement même sans connexion (couche partielle en v2.5.0)
Détection de vitesse avancée
* Classifications précises:
* Rapide (<300ms): Expérience complète
* Moyenne (300-1500ms): Optimisations modulaires
* Lente (1500-3000ms): Optimisations agressives
* Très lente (>3000ms): Mode minimal critique
* Hors-ligne: Fonctionnement avec données locales uniquement
* Détection multi-niveaux:
* JavaScript: Network Information API et mesures de performance
* Serveur: Analyse des temps de réponse et historique
* Indicateurs de stabilité: Détection des coupures fréquentes
* Géolocalisation: Adaptation aux zones géographiques connues pour leurs limitations
* Adaptations réactives:
* Chargement modulaire: Modules JS chargés à la demande
* Scripts critiques/non-critiques: Séparation et priorisation
* Support des attributs async/defer: Stratégie basée sur la priorité
* Styles CSS adaptifs: Chargement intelligent des styles
* Fichiers clés:
* /includes/cameroon-assets-optimizer.php
* /assets/js/modules/core.js
* /assets/js/modules/price-calculator.js
* /assets/js/cameroon-optimizer.js
Mode hors-ligne avancé (prévu dans version future)
* Stockage structuré: IndexedDB pour données complexes
* API Cache: Utilisation des API modernes pour les assets
* File d'attente de synchronisation: Transactions différées
* États utilisateur: Indicateurs visuels et feedback contextuel
* Intégrité des données: Validation à la synchronisation
(Les fonctionnalités listées ci-dessus seront introduites progressivement pour permettre une navigation hors-ligne complète. En v2.5.0, un mode hors-ligne partiel existe  par exemple, les pages visitées restent en cache, et un message "Vous êtes hors ligne" s'affiche le cas échéant  mais la synchronisation automatique des actions hors-ligne n'est pas encore disponible.)
Optimisations médias réelles
* Implémentation GD/WebP: Conversion et optimisation d'images automatique
* Fichier principal: /includes/class-life-travel-assets-optimizer.php
* Formats supportés: JPEG, PNG, GIF vers WebP optimisé
* Niveaux de qualité: Adaptés aux conditions réseau
* Miniatures adaptatives: Génération selon le contexte
* Lazy loading intelligent: Stratégie basée sur la visibilité et priorité
* Compression optimisée: GZIP/Brotli et minification avancée
* Images de secours SVG: Générées dynamiquement
Ces optimisations garantissent que le site soit utilisable même avec une bande passante réduite. Par exemple, sur une connexion "Très lente", seules les ressources critiques sont chargées, les images sont fortement compressées, et certaines animations visuelles sont désactivées. Si l'utilisateur perd complètement la connexion, le site affiche une page hors-ligne avec les informations déjà en cache. (Le mode offline complet avec file d'attente sera implémenté plus tard, on peut pour l'instant simuler le comportement et voir l'impact dans l'interface d'administration dédiée.)

8. ADAPTABILITÉ MOBILE
Support multi-appareils
* Types: Desktop, Tablette, Mobile
* OS supportés: Windows, Mac, Linux, iOS, Android
* Orientations: Portrait et Paysage avec styles adaptés
Optimisations tactiles
* Zones tactiles: Agrandissement automatique (min 44px)
* Espacements: Adaptés pour minimiser les erreurs de manipulation
* Navigation: Menus optimisés pour écrans tactiles
Responsive design
* Grilles: Flexibles et adaptatives
* Images: Responsives avec srcset et sizes
* Typographie: Fluide avec rem et unités viewport
* Breakpoints: Mobile (<480px), Tablette (481-768px), Desktop (>769px)
Performances mobile
* Batterie: Optimisations pour réduire la consommation
* Réseau: Adaptation pour 2G/3G/4G
* Data saver: Support du mode économie de données des navigateurs
Le site Life Travel a été conçu en priorité pour mobile. L'UI s'adapte sur petits écrans : le menu devient un menu "hamburger", les éléments s'empilent verticalement, et les boutons sont assez grands pour être tapés du doigt. Des breakpoints CSS spécifiques sont définis pour améliorer l'affichage sur tablette et smartphone. De plus, le site détecte si l'utilisateur a activé le mode "Data Saver" sur Chrome/Android et peut alors réduire la qualité des images et désactiver les vidéos de fond automatiquement. Le but est d'offrir une expérience fluide sur mobile, même en usage prolongé (économie de batterie via moins d'animations, etc.).

9. GESTION DES MÉDIAS
Gestionnaire avancé de médias
* Interface: Administration simplifiée (Apparence ? Médias Life Travel)
* Options: Logo, arrière-plans, qualité d'image, formats supportés
* Fichier principal: /includes/media-manager.php
Structure des assets
* Logos: /assets/img/logos/
* Arrière-plans: /assets/img/backgrounds/
* Galeries: /assets/img/gallery/
* Icônes: /assets/img/icons/ (SVG préféré)
* life-travel-excursion-card (600×400)
* life-travel-gallery (800×600)
* life-travel-thumbnail (300×200)
* Lazy loading:
* Automatique sauf première image
* Threshold configurable
* Placeholder: Couleur unie ou miniature floutée
* Compression:
* Paramétrable via interface admin
* Préréglages: Faible (90%), Moyenne (75%), Haute (60%), Extrême (40%)
* WebP: Conversion automatique si supporté
* Optimisations réseau:
* Adaptation aux connexions lentes
* Désactivation automatique des animations et effets
* Modes d'économie de données
Le plugin inclut un gestionnaire de médias dédié où l'administrateur peut configurer les visuels du site sans passer par l'onglet Media natif pour certaines images clés. Par exemple, on peut y uploader le logo officiel de l'entreprise (utilisé sur la page d'accueil et les emails), les images de fond des sections, etc. La compression des images téléchargées est effectuée automatiquement selon le paramétrage choisi  par défaut en "Haute" pour équilibrer qualité et performance. Lorsque l'utilisateur navigue, les images en dessous de la ligne de flottaison sont chargées de manière différée (lazy-load) pour accélérer l'affichage initial. On peut ajuster le seuil du lazy-load (par ex, commencer à charger 100px avant d'être visible).

10. OPTIMISATION DE LA BASE DE DONNÉES
Interface d'administration dédiée
* Tableau de bord: Base de données ? Optimisation MySQL
* Fichier principal: /includes/admin/class-life-travel-admin-renderers-database.php
* Points d'entrée AJAX: /includes/admin/database-ajax.php
* Fonctionnalités: Installation d'index, gestion du cache, monitoring des requêtes
* Analyse des performances: Suivi des requêtes lentes et optimisation
Optimiseur de base de données
* Core d'optimisation: Système avancé pour les requêtes fréquentes
* Fichier principal: /includes/database-optimization.php
* Caching adaptatif: Durée et niveau basés sur la fréquence d'utilisation
* Index personnalisés: Création et gestion pour les requêtes clés
* Requêtes préparées: Protection contre les injections SQL
* Purge intelligente: Stratégie basée sur les mises à jour
Optimisations spécifiques
* Disponibilité des excursions: Requêtes optimisées dans ajax/availability-ajax.php
* Paniers abandonnés: Index dédiés et cache pour les récupérations
* Calcul des prix: Mise en cache des résultats complexes
* Statistiques admin: Cache transient avec durée variable selon contexte
Surveillance et diagnostics
* Tableau de bord: Métriques sur les performances des requêtes
* Debugging: Mode diagnostique pour développeurs
* Logs: Enregistrement des requêtes lentes pour analyse
Ces outils permettent de maintenir la base de données performante. Par exemple, via l'interface "Optimisation MySQL", un admin technique peut lancer une analyse des tables pour recommander la création d'index sur certaines colonnes si nécessaire. Le plugin utilise largement des requêtes préparées (paramétrées) pour éviter toute injection SQL. Un système de cache adaptatif stocke en mémoire les résultats de calculs ou de requêtes lourdes, avec expiration automatique. En mode debug développeur, on peut activer un logging des requêtes lentes : celles-ci apparaîtront dans le log de performance avec leur durée et suggestion d'optimisation (par ex, "Ajouter un index sur wp_life_travel_log.user_id").

11. ARCHITECTURE JAVASCRIPT MODULAIRE
Conception modulaire
* Architecture des modules: Séparation des responsabilités en modules autonomes
* Core: /assets/js/modules/core.js - Fonctions de base, détection réseau, utilitaires
* Price Calculator: /assets/js/modules/price-calculator.js - Calcul des prix d'excursion
* Médiateur: /assets/js/cameroon-optimizer.js - Orchestration du chargement
Chargement optimisé
* Stratégies de chargement: Adaptation à la qualité de la connexion
* Critique: Composants essentiels chargés immédiatement
* Différé: Composants secondaires chargés après la page principale
* Sur demande: Composants optionnels chargés uniquement si nécessaire
* Fichier principal: /includes/cameroon-assets-optimizer.php:split_frontend_js()
Fonctionnalités avancées
* Détection réseau côté client:
* Test de latence et bande passante
* Surveillance continue de la qualité de connexion
* Adaptation dynamique aux changements
* Support hors-ligne:
* API Cache pour les assets critiques
* IndexedDB pour les données structurées
* LocalStorage pour les préférences et états
* Synchronisation intelligente:
* File d'attente des actions à synchroniser
* Stratégie de résolution des conflits
* Indicateurs d'état utilisateur
Points d'entrée AJAX
* Chargement de module: /wp-admin/admin-ajax.php?action=life_travel_load_module
* Détection de connectivité: /wp-admin/admin-ajax.php?action=life_travel_check_connectivity
* Mise en cache des données: /wp-admin/admin-ajax.php?action=life_travel_cache_data
Le code JavaScript du plugin est organisé en modules pour faciliter la maintenance et le chargement conditionnel. Par exemple, si l'utilisateur navigue sur une page sans calculateur de prix, le module price-calculator.js ne sera pas chargé du tout, économisant de la bande passante. Le module "core.js" contient les fonctions partagées (ex: une fonction pour afficher une notification toast, ou pour effectuer une requête AJAX en gérant les erreurs réseau). La fonction split_frontend_js() du côté PHP se charge de générer le bon fichier de bundle JS en fonction du contexte (version allégée si mode très lent, etc.).

12. FONCTIONNALITÉS DÉCOUVERTES ET OPTIMISÉES
Tableau de Bord Administrateur Unifié
* Interface principale: Tableau de bord centralisé pour administrateurs non-techniques
Fichier: /includes/admin/class-life-travel-admin.php
* Architecture modulaire: Utilisation de traits PHP pour un code maintenable
Répertoire: /includes/admin/
* Pages d'administration:
* Dashboard: Vue d'ensemble et actions rapides avec statistiques réelles
Fichier: /includes/admin/class-life-travel-admin-renderers-dashboard.php
* Performance: Analyse des performances réseau et optimisations spécifiques au Cameroun
Fichier: /includes/admin/class-life-travel-admin-renderers-performance.php
* Base de données: Gestion des optimisations et index MySQL
Fichier: /includes/admin/class-life-travel-admin-renderers-database.php
* Media: Gestion des logos, arrière-plans et galeries
Fichier: /includes/admin/class-life-travel-admin-renderers-media.php
* Network Tester: Tests réseau réels avec analyse de performance
Fichier: /includes/admin/class-life-travel-admin-renderers-network-tester.php
* Optimisation réseau Cameroun: Analyse réseau et métriques spécifiques au contexte local
Fichier: /includes/cameroon-assets-optimizer.php
* Excursions: Gestion centralisée des excursions et paramètres globaux
Fichier: /includes/admin/class-life-travel-admin-renderers-excursions.php
* Payments: Configuration des passerelles de paiement
Fichier: /includes/admin/class-life-travel-admin-renderers-payments.php
* Abandoned Carts: Gestion et récupération des paniers abandonnés
Fichier: /includes/admin/class-life-travel-admin-renderers-cart.php
* Ressources frontend:
* CSS: /assets/css/admin.css
* JavaScript: /assets/js/admin.js
* Sécurité: Validation des données, nonces, vérification des permissions
* Optimisations: Support pour les connexions lentes, mode partiellement hors-ligne, interface adaptée au Cameroun
Toutes les fonctions d'administration sont désormais regroupées sous le menu "Life Travel". Le tableau de bord admin offre un aperçu global (réservations du jour, ventes, visites offline vs online, etc.) avec des graphiques simples. Un système d'actions rapides y est présent (bouton "Ajouter une excursion", lien vers "Voir les logs", etc.). Chaque page admin est implémentée dans un renderer distinct (selon le concept de WP Admin UI séparée du logic). L'interface est conçue pour être utilisable par des non-techniciens : jargon minimisé, explications sous les options si besoin. Elle reste aussi légère et réactive malgré les multiples modules, grâce au chargement conditionnel des sections (ex : les statistiques réseau ne se chargent que si l'admin ouvre l'onglet Performance).
Sécurité et récupération des paniers abandonnés
* Interface d'administration: /includes/admin/class-life-travel-admin-renderers-cart.php:render_cart_abandoned()
* Tableau de bord: Statistiques et tendances
* Liste des paniers: Filtrables par date, montant, origine
* Accès direct: Reconstruction et administration
* Actions en masse: Rappels, nettoyage, archivage
* Paramètres de récupération:
* Délais configurables: 1h, 3h, 12h, 24h, personnalisé
* Nombre de tentatives: Limitable
* Récompenses: Coupons de réduction automatiques
* Messages: Personnalisables avec variables dynamiques
* Optimisations pour le Cameroun:
* Mode hors-ligne: Récupération après reconnexion
* File d'attente: Envoi différé des emails
* SMS: Option via réseau local
* Sécurité renforcée:
* Analyseur de sécurité: /includes/abandoned-cart-security-analyzer.php
* Journalisation: /includes/abandoned-cart-security-logger.php
* Rapports: /includes/abandoned-cart-security-chart.php
* Validations: Contrôles avancés contre CSRF, XSS et injections SQL
Cette section recouvre la double problématique de récupérer les ventes perdues (paniers abandonnés) tout en sécurisant ces processus (éviter qu'un utilisateur malveillant n'exploite ces mécanismes). L'interface admin "Paniers abandonnés" permet de voir tous les paniers non convertis, d'envoyer manuellement un rappel ou d'appliquer un coupon de rattrapage. Le système peut automatiser X rappels selon la configuration. Les optimisations Cameroun sont prises en compte : par exemple, si un rappel email n'a pas pu partir car l'utilisateur était offline, il sera envoyé plus tard automatiquement (file d'attente). Côté sécurité, une attention particulière est portée aux liens de rappel envoyés (ce sont des liens uniques contenant un token pour récupérer le panier sans ressaisir tout ; ces tokens expirent et sont signés pour éviter toute injection). Toutes les entrées de ce système passent aussi par l'analyseur de sécurité intégré (contrôle de la validité des paramètres).
Intégration multilingue
* TranslatePress: Intégration avec le panier
Fichier: /includes/frontend/translatepress-cart-integration.php
* Chaînes traduisibles: Gestion des traductions pour l'interface utilisateur
* Interface admin: Modules pour gérer les traductions des excursions
Le site est conçu pour être bilingue (Français/Anglais). L'extension TranslatePress est pleinement supportée : toutes les chaînes ajoutées par le plugin Life Travel sont enveloppées dans les fonctions de traduction WordPress, et des règles spécifiques ont été ajoutées pour que TranslatePress détecte les textes dynamiques du panier ou des emails. Par exemple, les noms d'excursions peuvent être traduits via le panneau TranslatePress. L'interface admin comprend une section "Traductions Life Travel" qui liste les chaînes custom du plugin pour aider le traducteur à les retrouver facilement.
Fonctionnalités sociales
* Partage: Module de partage social /includes/frontend/share.php
* Fidélité: Système de points et récompenses /includes/frontend/loyalty.php
* Évaluations: Module de votes et avis /includes/frontend/vote.php
Les fonctionnalités sociales visent à encourager l'engagement des utilisateurs :
- Le partage social génère des boutons (Facebook, Twitter, WhatsApp) sur les pages d'excursion et la page de confirmation de réservation, permettant aux utilisateurs de facilement partager leur expérience ou l'offre avec leurs contacts. Ceci peut amener du trafic viral (et le plugin reconnaît le paramètre de référence de partage pour attribuer les points de fidélité si configuré).
- Le module de fidélité (déjà détaillé) permet de transformer les clients en ambassadeurs en les récompensant pour ces partages ou parrainages.
- Le module d'évaluations ajoute la possibilité pour un utilisateur de noter une excursion et de laisser un avis. Ces avis (une fois modérés dans l'admin si besoin) apparaissent sur la page de l'excursion, ce qui renforce la confiance des futurs clients. Un système de vote (utile/pas utile) sur les avis peut également être activé.

13. GUIDE COMPLET DE L'ADMINISTRATEUR
Cette section est spécialement conçue pour les administrateurs non-techniques qui gèrent quotidiennement le site Life Travel.
Tableau de bord principal
Accès au tableau de bord
* Se connecter à l'administration WordPress (https://votre-site.com/wp-admin/)
* Naviguer vers le menu latéral "Life Travel" → "Tableau de bord"
Vue d'ensemble des métriques
* Réservations: Nombre total, récentes, et en attente
* Excursions: Populaires, récentes, et statistiques
* Revenus: Journaliers, hebdomadaires, mensuels
* Points de fidélité: Distribution totale et récente
Actions rapides
* Créer une excursion: Bouton "Ajouter une excursion"
* Gérer les réservations: Lien vers la liste des réservations
* Paramètres rapides: Accès aux configurations principales
Gestion des excursions
Création d'une nouvelle excursion
1. Aller à "Life Travel" → "Excursions" → "Ajouter"
2. Compléter les informations de base:
   - Titre et description
   - Prix de base
   - Durée et dates disponibles
   - Capacité minimale/maximale
3. Configurer la tarification avancée:
   - Prix par personne/groupe
   - Suppléments saisonniers
   - Extras (activités, services)
4. Ajouter des médias:
   - Image principale (600×400px recommandé)
   - Galerie (800×600px recommandé)
5. Configurer les points de fidélité:
   - Type: Fixe ou pourcentage
   - Valeur: Nombre ou taux
   - Plafond: Limite éventuelle
(Pensez à publier l'excursion une fois tous les champs remplis. Elle deviendra alors visible sur le site public. Utilisez l'option "Aperçu" pour vérifier la mise en page avant publication.)
Modification d'une excursion existante
1. Aller à "Life Travel" → "Excursions" → "Toutes les excursions"
2. Cliquer sur le titre de l'excursion à modifier
3. Effectuer les changements nécessaires
4. Cliquer sur "Mettre à jour"
(Les modifications seront instantanément prises en compte sur le site public. Pour des changements massifs (ex: mise à jour des tarifs de toutes les excursions), songez à utiliser l'outil d'export/import ou à éditer directement dans la base si à l'aise, mais toujours avec prudence.)
Duplication d'une excursion
1. Dans la liste des excursions, survoler le titre
2. Cliquer sur "Dupliquer"
3. La nouvelle copie s'appellera "Copie de [Titre original]"
(La duplication crée un brouillon d'excursion identique à l'originale, sans les réservations. N'oubliez pas de vérifier les dates et stock sur la copie avant publication.)
Configuration du système de fidélité
Paramètres globaux
1. Aller à "Life Travel" → "Fidélité" → "Paramètres"
2. Configurer les paramètres de base:
   - Activation/désactivation: Activer le système
   - Taux de conversion: Points → Valeur monétaire (XAF)
   - Minimum pour utilisation: Seuil minimal de points
   - Maximum par commande: Limite d'utilisation
   - Plafond global: Limite de points gagnés par excursion
   - Expiration: Durée de validité des points
Paramètres spécifiques par excursion
1. Éditer une excursion
2. Défiler jusqu'à la section "Système de points de fidélité"
3. Configurer les options spécifiques:
   - Activer/désactiver pour cette excursion
   - Type de récompense: Fixe ou pourcentage
   - Valeur: Points fixes ou pourcentage du prix
   - Plafonnement: Limite spécifique à ce produit
Points pour partages sociaux
1. Aller à "Life Travel" → "Fidélité" → "Partages sociaux"
2. Définir les points attribués pour:
   - Partage sur Facebook
   - Partage sur Twitter/X
   - Partage sur WhatsApp
   - Évaluation/avis
(Ces valeurs déterminent le bonus de points qu'un client reçoit lorsqu'il effectue l'action correspondante. Par exemple, si "Partage sur Facebook = 10 points", alors après avoir partagé une excursion sur Facebook (via le bouton du site), l'utilisateur gagnera automatiquement 10 points de fidélité. Note: L'attribution pour avis peut nécessiter une validation d'admin pour éviter les abus.)
Tableau de bord des statistiques de fidélité
1. Aller à "Life Travel" → "Fidélité" → "Statistiques"
2. Analyser les données disponibles:
   - Utilisateurs les plus fidèles: Classement
   - Distribution des points: Par source
   - Tendances d'utilisation: Graphiques
   - Excursions populaires: Par points générés
3. Utiliser les outils d'export:
   - CSV pour analyse externe
   - PDF pour rapports
(Cette page donne un aperçu de l'efficacité du programme de fidélité. Par exemple, vous pouvez voir si beaucoup de points expirent sans être utilisés, signe qu'il faudrait peut-être abaisser le seuil minimal, etc.)
Gestion des optimisations réseau
Configuration des optimisations Cameroun
1. Aller à "Life Travel" → "Réseau" → "Optimisations"
2. Configurer les paramètres:
   - Détection auto: Activer/désactiver
   - Mode hors-ligne: Configurer niveau
   - File d'attente des paiements: Niveau de priorité
   - Compression des images: Qualité adaptative
(Par défaut, la détection auto est activée, ce qui signifie que le site ajuste automatiquement ses optimisations en fonction de la connexion de l'utilisateur. Vous pouvez forcer un "Mode hors-ligne" particulier pour tester, mais en production laissez sur auto.)
Tests de performance réseau
1. Aller à "Life Travel" → "Réseau" → "Test de performance"
2. Utiliser les outils disponibles:
   - Test de latence
   - Test de bande passante
   - Simulateur de connexion lente
3. Appliquer les recommandations automatiques
(Le simulateur de connexion lente vous permet de visualiser le site tel qu'un utilisateur en 2G le verrait. Après les tests, des recommandations s'affichent (ex: "Vos images de bannière sont encore lourdes, envisagez de les compresser davantage"). Vous pouvez appliquer certaines optimisations en un clic depuis cette interface.)
Statistiques réseau
1. Aller à "Life Travel" → "Réseau" → "Statistiques"
2. Analyser les métriques:
   - Temps de chargement moyen
   - Taux de synchronisation hors-ligne
   - Taux de compression des médias
   - Utilisateurs par type de connexion
(Cette page statistique permet de vérifier l'impact de vos optimisations. Par ex, si le "taux de synchronisation offline" est bas, ça signifie que peu d'utilisateurs reviennent en ligne pour terminer leur réservation, possiblement un problème UX. Si beaucoup d'utilisateurs ont une connexion "très lente", vous verrez ce segment en % et pourriez décider d'ajuster encore la qualité des médias pour eux.)
Maintenance quotidienne
Vérifications journalières
1. Réservations: Vérifier les nouvelles réservations et confirmer
2. Paiements: Vérifier les paiements en attente/échecs
3. Stock d'excursions: Vérifier disponibilité et capacités
4. Points de fidélité: Surveiller distribution et utilisation
Vérifications hebdomadaires
1. Sauvegarde: Confirmer les sauvegardes automatiques
2. Performance: Vérifier statistiques de vitesse
3. Mise à jour: Planifier updates si disponibles
4. Contenu: Rafraîchir excursions populaires
Vérifications mensuelles
1. Nettoyage base de données: Optimiser les tables
2. Statistiques complètes: Exporter et analyser
3. Réindexation: Reconstruire index AJAX et recherche
4. Ajustements fidélité: Revoir taux et plafonds
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
Résolution des problèmes courants
Problèmes de paiement
* Erreur MoMo: Vérifier configuration IwomiPay et clés API
* Échec de transaction: Consulter journal des transactions
* Paiement bloqué: Vérifier statut de la commande et débloquer
Problèmes de réservation
* Capacité incorrecte: Vérifier paramètres d'excursion
* Prix erroné: Vider cache de prix et recalculer
* Conflit de dates: Vérifier calendrier et disponibilité
Problèmes de points de fidélité
* Points non attribués: Vérifier journal et attribuer manuellement
* Impossible d'utiliser: Vérifier solde et seuil minimum
* Historique incorrect: Accéder au profil utilisateur et ajuster
Support et aide
* Documentation: Accéder à la documentation complète
* Support technique: Contacter le support via le formulaire dédié
* Vidéos tutoriels: Accéder à la bibliothèque de vidéos
(En cas de problème persistant, pensez à vérifier les logs (dans Life Travel → Journal des modifications, ou dans debug.log si WP_DEBUG_LOG est activé). La documentation technique peut aussi fournir des indices en cas de comportement inattendu.)

14. DOCUMENTATION TECHNIQUE POUR DÉVELOPPEURS
Architecture du code
Structure des répertoires
life-travel-excursion/
├── includes/                     # Code PHP du plugin
│   ├── admin/                    # Interfaces administrateur
│   │   ├── class-life-travel-admin.php                    # Point d'entrée admin
│   │   ├── class-life-travel-admin-renderers-*.php        # Renderers admin
│   │   └── loyalty-*.php                                  # Admin fidélité
│   ├── ajax/                     # Points d'entrée AJAX
│   │   ├── availability-ajax.php                          # Disponibilité
│   │   └── pricing-ajax-optimizer.php                     # Calcul de prix
│   ├── frontend/                 # Interface utilisateur
│   │   └── loyalty-*.php                                  # Système fidélité frontend
│   ├── assets/                       # Ressources statiques
│   │   ├── js/                       # Scripts JavaScript
│   │   │   ├── modules/              # Architecture modulaire JS
│   │   │   ├── offline-*.js          # Support hors-ligne
│   │   │   └── admin/                # Scripts admin
│   │   ├── css/                      # Styles CSS
│   │   └── img/                      # Images du plugin
│   ├── templates/                    # Modèles de rendu
│   │   ├── emails/                   # Templates d'emails
│   │   └── myaccount/                # Templates compte utilisateur
│   ├── docs/                         # Documentation
│   └── tests/                        # Tests automatisés
Diagramme d'architecture
┌──────────────────────┐      ┌──────────────────────┐
│     FRONTEND         │      │        ADMIN         │
│                      │      │                      │
│  ┌────────────────┐  │      │  ┌────────────────┐  │
│  │  Templates     │  │      │  │  Admin Renderers│  │
│  └────────────────┘  │      │  └────────────────┘  │
│           │          │      │           │          │
│  ┌────────────────┐  │      │  ┌────────────────┐  │
│  │  Core Classes  ├──┼──────┼──┤  Admin Classes │  │
│  └────────────────┘  │      │  └────────────────┘  │
│           │          │      │           │          │
└───────────┼──────────┘      └───────────┼──────────┘
            │                             │
            │                             │
            │                             │
┌───────────┼──────────┐      ┌───────────┼──────────┐
│     MODULES          │      │     INFRASTRUCTURE   │
│                      │      │                      │
│  ┌────────────────┐  │      │  ┌────────────────┐  │
│  │  Loyalty System│  │      │  │  Database Layer│  │
│  └────────────────┘  │      │  └────────────────┘  │
│  ┌────────────────┐  │      │  ┌────────────────┐  │
│  │  Offline Support│  │      │  │  WooCommerce   │  │
│  └────────────────┘  │      │  └────────────────┘  │
│  ┌────────────────┐  │      │  ┌────────────────┐  │
│  │  Price Calculator│ │      │  │  WordPress Hooks│ │
│  └────────────────┘  │      │  └────────────────┘  │
└──────────────────────┘      └──────────────────────┘
Flux de données principales
Réservation d'excursion:

Utilisateur → Sélection excursion → Calculateur de prix → 
Panier WooCommerce → Checkout → Paiement IwomiPay → 
Traitement commande → Attribution points

Utilisation points fidélité:

Utilisateur → Panier → Formulaire points → Application réduction → 
Checkout → Paiement → Déduction points
Points d'extension
Filtres WordPress
// Modifier le prix calculé d'une excursion
apply_filters('life_travel_excursion_price', $price, $product_id, $participants, $extras);  

{{ ... }}
apply_filters('life_travel_available_slots', $available, $product_id, $date);  

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
do_action('lte_network_quality_changed', $old_quality, $new_quality, $user_id);
Classes d'extension
// Exemple d'extension du calculateur de prix
class My_Custom_Price_Calculator extends Life_Travel_Price_Calculator {  
    public function calculate_price($product_id, $participants, $start_date, $end_date, $extras = []) {  
        // Prix de base calculé par la classe parente  
        $price = parent::calculate_price($product_id, $participants, $start_date, $end_date, $extras);  
        // Application de logique personnalisée  
        if (my_custom_condition()) {  
            $price *= 0.9; // Exemple: 10% de réduction  
        }  
        return $price;  
    }  
}

// Exemple d'extension pour les points de fidélité
class My_Custom_Loyalty_Manager extends Life_Travel_Loyalty_Manager {  
    public function calculate_points($order_total, $user_id, $product_id = null) {  
        // Points calculés par la classe parente  
        $points = parent::calculate_points($order_total, $user_id, $product_id);
        // Application de logique personnalisée  
        if ($this->is_birthday($user_id)) {  
            $points *= 2; // Exemple: Double points pour l'anniversaire  
        }  
        return $points;  
    }  
}
Guide de développement
Environnement de développement
1. Installation locale

  # Cloner le repository
git clone https://repository-url.git life-travel-excursion
cd life-travel-excursion

# Installer dépendances
composer install
npm install

# Build des assets
npm run dev # Pour développement (avec sourcemaps)

2. **Workflow de développement**

3. Utiliser le mode développement dans wp-config.php:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', true);
define('SCRIPT_DEBUG', true);
```

4. Activer les logs verbeux dans le plugin:

```php
// Dans fichier principal du plugin
define('LTE_DEBUG', true);
```

5. Structure des commits Git:
   - Format: [type]: description courte
   - Types: feat, fix, docs, style, refactor, test, chore
   - Exemple: feat: ajout système de points fidélité par partage
Documentation du code
Respectez ces standards pour toute nouvelle contribution:
/**
 * Calcule les points de fidélité basés sur le montant de la commande.
 *
 * Cette fonction prend en compte les paramètres globaux et spécifiques
 * du produit pour déterminer les points à attribuer.
 *
 * @since 2.3.0
 * @param float  $order_total Montant total de la commande
 * @param int    $user_id     ID de l'utilisateur
 * @param int    $product_id  ID du produit (optionnel)
 * @return int   Nombre de points à attribuer
 */
public function calculate_loyalty_points($order_total, $user_id, $product_id = null) {
    // Corps de la fonction
}
Tests automatisés
1. Exécution des tests

  # Installer PHPUnit si nécessaire
composer require --dev phpunit/phpunit

# Exécuter les tests
./vendor/bin/phpunit
2. **Structure des tests**
   - Tests unitaires: tests/unit/
   - Tests d'intégration: tests/integration/
   - Tests frontaux: tests/e2e/

3. **Exemple de test unitaire**

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
        $points = $loyalty->calculate_loyalty_points($order_total, $user_id, $product_id);

        // Assert
        $this->assertEquals(5000, $points); // 5% de 100,000 = 5,000 points
    }
}

15. CONSEILS D'UTILISATION ET MAINTENANCE
Optimisations recommandées
* Cache:
* Activer un plugin de cache pour les pages statiques
* Recommandés: WP Rocket, W3 Total Cache, LiteSpeed Cache
* Configuration spéciale: Exclure pages de paiement et checkout du cache
* Optimisation images:
* Utiliser WebP quand disponible (activé dans l'interface Media)
* Compression adaptative selon qualité réseau
* Redimensionnement automatique aux dimensions exactes
* Minification:
* Activer pour CSS/JS en production
* Concaténation des fichiers
* Différer le chargement des scripts non-critiques
* CDN:
* Configurer si disponible pour les ressources statiques
* Recommandations pour le Cameroun: Éviter si bande passante internationale limitée
* Alternative locale: Optimisation du serveur local
* Paramètres réseau:
* Tableau de bord → Réseau et performances → Optimisations pour connexions lentes
* Détection automatique de la qualité de connexion
* Modes d'économie de données
Système de fidélité
* Vérifications:
* Attribution des points pour chaque excursion configurée
* Plafonnement correct des points
* Conversion des points en réduction
* Affichage et fonctionnement des notifications
* Configuration:
* Produits → Fidélité → Paramètres globaux
* Par excursion: Dans la section "Système de points de fidélité"
* Partages sociaux: Dans les paramètres de fidélité
Sauvegardes
* Fréquence: Quotidienne recommandée
* Contenu à inclure: Base de données, uploads, plugins, thème
* Rotation: Conserver au moins 7 jours d'historique
* Test de restauration: Vérifier régulièrement
Mise à jour
* Environnement de test: Toujours tester les mises à jour
* Séquence recommandée: WordPress, puis Plugins, puis Thème
* Vérifications post-mise à jour:
* Passerelles de paiement
* Calculateur de prix
* Fonctionnement hors-ligne
* Adaptabilité mobile
Monitoring
* Surveiller: Temps de réponse, erreurs 404/500, tentatives de connexion
* Diagnostics réseau: Vérifier performances avec connexion lente
* Alertes: Configurer pour échecs de paiement ou erreurs critiques
```

[1] [2] [3] [4] [5] [6] [9] [10] [11] [12] [13] [16] [17] [18] [19] [20] [21] [22] [23] [24] [25] [26] [27] [28] [29] [30] [31] [32] [33] [34] [35] [36] [37] [39] [40] [41] [44] [45] [46] [47] [48] [49] [50] [51] [52] [53] [54] [55] [56] [57] [61] [62] [64] [66] [68] [69] [71] [72] [73] [74] [75] [85] [86] [87] [88] [94] [95] [96] [97] [125] [126] [127] [128] [129] [130] [131] [132] [133] [134] [135] [137] [138] [139] [140] [141] [142] [143] [144] [145] [152] [164] [167] [168] [170] inventaire_informations.md
file://file-EUnJ4gTKMH9eNidZwuEL7i
[7] [8] [14] [15] [38] [42] [43] [58] [59] [60] [63] [65] [67] [70] [76] [77] [78] [79] [80] [81] [82] [83] [84] [89] [90] [91] [92] [93] [98] [99] [100] [101] [102] [103] [104] [105] [106] [107] [108] [109] [110] [111] [112] [113] [114] [115] [116] [117] [118] [119] [120] [121] [122] [123] [124] [136] [146] [147] [148] [149] [150] [151] [153] [154] [155] [156] [157] [158] [159] [160] [161] [162] [163] [165] [166] [169] [171] Plan dintégration technique pas-à-pas (Claude 3.7 Sonnet Thinking  Windsurf, Mai 2025).pdf
file://file-Fj7xcGPCdTVPv2kHmWopNj
