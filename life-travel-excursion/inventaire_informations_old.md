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
12. [Administration des excursions](#12-administration-des-excursions)
13. [Guide complet de l'administrateur](#13-guide-complet-de-ladministrateur) *(nouvelle section)*
    - [Configuration du système de fidélité](#configuration-du-système-de-fidélité)
    - [Gestion des optimisations réseau](#gestion-des-optimisations-réseau)
    - [Maintenance quotidienne](#maintenance-quotidienne)
14. [Documentation technique pour développeurs](#14-documentation-technique-pour-développeurs) *(nouvelle section)*
    - [Architecture du code](#architecture-du-code)
    - [Points d'extension](#points-dextension)
    - [Guide de développement](#guide-de-développement)
15. [Conseils d'utilisation et maintenance](#15-conseils-dutilisation-et-maintenance)
16. [Intégrations externes](#16-intégrations-externes)

---

## 1. ARCHITECTURE GLOBALE

### Structure du projet
- **Site WordPress Principal**: `C:/Users/aleko/CascadeProjects/life-travel/`
  - Plugin Core: `life-travel-core/`
  - Thème: `life-travel-theme/`
- **Plugin d'Excursions**: `C:/Users/aleko/Documents/Life-travel.org/`

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

### Configuration de WooCommerce

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
     - Format de devise: 50 000 FCFA (avec espace)
   - Aller dans Réglages > WooCommerce > Produits > Général
     - Unités de poids: kg
     - Unités de dimension: cm
   - Aller dans Réglages > WooCommerce > Comptes et confidentialité
     - Activer l'inscription des utilisateurs
     - Activer les comptes et Commander en tant qu'invité

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

### Configuration de la base de données suppplémentaires

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
```

---

## 3. CONFIGURATION DES PASSERELLES DE PAIEMENT

### IwomiPay pour MTN Mobile Money (MoMo)
- **Dossier d'installation**: `payment-gateways/iwomipay-momo-woocommerce/`
- **Plugin WordPress**: Fichier ZIP `iwomipay-momo-woocommerce.zip`
- **Paramètres de production**:
  - Nom d'utilisateur IwomiPay
  - Mot de passe IwomiPay
  - Clé d'identification (Credi Key)
  - Secret d'identification (Credi Secret)
- **Paramètres Sandbox** (similaires)

### Orange Money (OM) - Intégré au plugin
- **Intégration directe**: Dans le plugin principal
- **Icône**: `/assets/img/orange-money.png`
- **Configuration**: Via Customizer → Design → Upload d'icônes de paiement
- **Paramètres** (identiques à MTN MoMo)

### URLs API communes
- **Production**: https://api.iwomipay.com/
- **Sandbox**: https://sandbox.iwomipay.com/

---

## 3. NOTIFICATIONS ET MESSAGERIE

### Configuration Twilio
- **Identifiants requis**:
  - Account SID
  - Auth Token
  - Numéro de téléphone Twilio (avec indicatif pays)
  - Numéro WhatsApp Business
- **Dépendance**: `composer require twilio/sdk`

### Canaux de notification
- **Email**: Configuration SMTP requise
- **SMS**: Via Twilio ou méthode de secours
- **WhatsApp**: Via Twilio Business API
- **In-app**: Notifications navigateur via Push API

### Système de notification avancé
- **Administration**: Email, SMS, WhatsApp pour nouvelles réservations
- **Utilisateurs**: Préférences par canal (Email, SMS, WhatsApp)
- **Types**: Commandes, réservations, rappels, compte, sécurité
- **Éditeur de modèles**: Interface visuelle pour personnaliser sans code

---

## 4. DÉPENDANCES TECHNIQUES

### Serveur
- **PHP**: 8.0 ou supérieur
- **WordPress**: 6.0 ou supérieur
- **WooCommerce**: 8.0 ou supérieur
- **Extensions PHP**: curl, json, fileinfo, gd, intl
- **Composer**: Pour les bibliothèques externes

### Frontend
- **Node.js & npm**: Version 14+
- **Build process**: Webpack pour JS/CSS via `npm run build`
- **Assets structure**: Sources dans `/assets/`, bundlés vers `/assets/dist/`

### Tests
- **PHPUnit**: Via `vendor/bin/phpunit`
- **Composer**: Pour gestion des dépendances de test

---

## 5. EXPÉRIENCE UTILISATEUR AVANCÉE

### Système de fidélité et points
- **Fonctionnalité**: Attribution et utilisation de points de fidélité
  - **Fichiers principaux**:
    - `/includes/frontend/loyalty-excursions.php`: Gestion des points d'excursion
    - `/includes/frontend/loyalty-social.php`: Points pour partages sociaux
    - `/includes/frontend/loyalty-integration.php`: Coordination des composants
    - `/includes/admin/loyalty-admin.php`: Interface d'administration
  - **Paramétrage par excursion**:
    - Type d'attribution: Points fixes ou pourcentage du montant
    - Valeur des points: Nombre de points ou pourcentage
    - Plafond spécifique: Limite maximum par excursion
  - **Configuration globale**:
    - Plafond global: Limite maximum de points par transaction
    - Valeur d'échange: Nombre de points pour 1€ (par défaut: 100)
    - Réduction maximale: Pourcentage maximum du total (par défaut: 25%)
  - **Points par réseau social**:
    - Facebook: Configurable (par défaut: 10 points)
    - Twitter: Configurable (par défaut: 10 points)
    - WhatsApp: Configurable (par défaut: 5 points)
    - Instagram: Configurable (par défaut: 15 points)
  - **Interface utilisateur**:
    - Tableau de bord de fidélité: Dans l'espace client
    - Notifications: Alertes pour les nouveaux points gagnés
    - Utilisation: Formulaire au checkout pour appliquer des points
  - **Statistiques admin**:
    - Utilisateurs avec points
    - Distribution des points
    - Configuration par excursion

### Checkout & Panier
- **Étapes visuelles**: Processus guidé avec validation
- **Récupération des paniers**: Système robuste pour sessions interrompues
  - Interface Admin: Tableau de bord → Paniers abandonnés → Statistiques
  - Délais configurables: 1h, 3h, 12h, 24h, personnalisé
  - Emails automatiques: Modèles personnalisables avec tokens
  - SMS optionnels: Via API Twilio (configuration séparée requise)
- **Modes de stockage**: 
  - Utilisateurs connectés: Base de données WordPress
  - Anonymes: Cookies et LocalStorage
  - Synchronisation hors-ligne: Support pour les interruptions réseau
  - Reconstructions des paniers: Mécanisme pour sessions expirées
- **Sécurité**: 
  - Validations: Intégrité des données et permissions
  - Nonces: Protection CSRF systématique
  - Sanitization: Échappement et validation des entrées
  - Journalisation: Traces des événements critiques

### Comptes clients
- **Onglets personnalisés**: "Mes excursions" et "Mes points de fidélité"
- **Configuration**: Via Customizer → Comptes clients
- **Préférences**: Gestion par type et par canal de notification

### Calculateur de prix dynamique
- **Facteurs**: Type d'excursion, participants, extras, dates, remises
- **Intégrations**: Pages produit, widget de réservation, shortcodes
- **Compatibilité**: Mobile, desktop, mode partiellement hors-ligne

---

## 6. OPTIMISATIONS POUR CONNEXIONS LENTES (CONTEXTE CAMEROUNAIS)

### Interface d'administration
- **Tableau de bord**: Performances → Analyse des performances
  - **Fichier principal**: `/includes/admin/class-life-travel-admin-renderers-performance.php`
  - **Statistiques réelles**: Temps de chargement, taux de réussite du cache, stabilité
  - **Graphiques**: Visualisation par type de réseau et impact des optimisations
  - **Configuration**: Activation/désactivation des fonctionnalités avancées
  - **Seuils personnalisables**: Définition des temps de réponse par catégorie
  - **Métriques offline**: Score de capacité hors-ligne et évaluation par page

### Optimiseur Camerounais
- **Nouvelle implémentation**: Optimiseur spécifique pour réseaux instables
  - **Fichier principal**: `/includes/cameroon-assets-optimizer.php`
  - **Configuration**: Via interface admin ou détection automatique
  - **Statistiques**: Collection et analyse des performances réseau
  - **Cache local**: Optimisé pour minimiser les requêtes HTTP
  - **Support hors-ligne**: Fonctionnement même sans connexion

### Détection de vitesse avancée
- **Classifications précises**: 
  - Rapide (<300ms): Expérience complète
  - Moyenne (300-1500ms): Optimisations modulaires
  - Lente (1500-3000ms): Optimisations agressives
  - Très lente (>3000ms): Mode minimal critique
  - Hors-ligne: Fonctionnement avec données locales uniquement
- **Détection multi-niveaux**:
  - JavaScript: Network Information API et mesures de performance
  - Serveur: Analyse des temps de réponse et historique
  - Indicateurs de stabilité: Détection des coupures fréquentes
  - Géolocalisation: Adaptation aux zones géographiques connues pour leurs limitations
- **Adaptations réactives**: 
  - Chargement modulaire: Modules JS chargés à la demande
  - Scripts critiques/non-critiques: Séparation et priorisation
  - Support des attributs async/defer: Stratégie basée sur la priorité
  - Styles CSS adaptifs: Chargement intelligent des styles
- **Fichiers clés**: 
  - `/includes/cameroon-assets-optimizer.php`
  - `/assets/js/modules/core.js`
  - `/assets/js/modules/price-calculator.js`
  - `/assets/js/cameroon-optimizer.js`

### Mode hors-ligne avancé
- **Stockage structuré**: IndexedDB pour données complexes
- **API Cache**: Utilisation des API modernes pour les assets
- **File d'attente de synchronisation**: Transactions différées
- **États utilisateur**: Indicateurs visuels et feedback contextuel
- **Intégrité des données**: Validation à la synchronisation

### Optimisations médias réelles
- **Implémentation GD/WebP**: Conversion et optimisation d'images automatique
  - **Fichier principal**: `/includes/class-life-travel-assets-optimizer.php`
  - **Formats supportés**: JPEG, PNG, GIF vers WebP optimisé
  - **Niveaux de qualité**: Adaptés aux conditions réseau
  - **Miniatures adaptatives**: Génération selon le contexte
- **Lazy loading intelligent**: Stratégie basée sur la visibilité et priorité
- **Compression optimisée**: GZIP/Brotli et minification avancée
- **Images de secours SVG**: Générées dynamiquement

---

## 7. ADAPTABILITÉ MOBILE

### Support multi-appareils
- **Types**: Desktop, Tablette, Mobile
- **OS supportés**: Windows, Mac, Linux, iOS, Android
- **Orientations**: Portrait et Paysage avec styles adaptés

### Optimisations tactiles
- **Zones tactiles**: Agrandissement automatique (min 44px)
- **Espacements**: Adaptés pour minimiser les erreurs de manipulation
- **Navigation**: Menus optimisés pour écrans tactiles

### Responsive design
- **Grilles**: Flexibles et adaptatives
- **Images**: Responsives avec srcset et sizes
- **Typographie**: Fluide avec rem et unités viewport
- **Breakpoints**: Mobile (<480px), Tablette (481-768px), Desktop (>769px)

### Performances mobile
- **Batterie**: Optimisations pour réduire la consommation
- **Réseau**: Adaptation pour 2G/3G/4G
- **Data saver**: Support du mode économie de données des navigateurs

---

## 8. GESTION DES MÉDIAS

### Gestionnaire avancé de médias
- **Interface**: Administration simplifiée (Apparence → Médias Life Travel)
- **Options**: Logo, arrière-plans, qualité d'image, formats supportés
- **Fichier principal**: `/includes/media-manager.php`

### Structure des assets
- **Logos**: `/assets/img/logos/`
- **Arrière-plans**: `/assets/img/backgrounds/`
- **Galeries**: `/assets/img/gallery/`
- **Icônes**: `/assets/img/icons/` (SVG préféré)
  - `life-travel-excursion-card` (600×400)
  - `life-travel-gallery` (800×600)
  - `life-travel-thumbnail` (300×200)
- **Lazy loading**: 
  - Automatique sauf première image
  - Threshold configurable
  - Placeholder: Couleur unie ou miniature floutée
- **Compression**: 
  - Paramétrable via interface admin
  - Préréglages: Faible (90%), Moyenne (75%), Haute (60%), Extrême (40%)
  - WebP: Conversion automatique si supporté
- **Optimisations réseau**:
  - Adaptation aux connexions lentes
  - Désactivation automatique des animations et effets
  - Modes d'économie de données

---

## 9. OPTIMISATION DE LA BASE DE DONNÉES

### Interface d'administration dédiée
- **Tableau de bord**: Base de données → Optimisation MySQL
  - **Fichier principal**: `/includes/admin/class-life-travel-admin-renderers-database.php`
  - **Points d'entrée AJAX**: `/includes/admin/database-ajax.php`
  - **Fonctionnalités**: Installation d'index, gestion du cache, monitoring des requêtes
  - **Analyse des performances**: Suivi des requêtes lentes et optimisation

### Optimiseur de base de données
- **Core d'optimisation**: Système avancé pour les requêtes fréquentes
  - **Fichier principal**: `/includes/database-optimization.php`
  - **Caching adaptatif**: Durée et niveau basés sur la fréquence d'utilisation
  - **Index personnalisés**: Création et gestion pour les requêtes clés
  - **Requêtes préparées**: Protection contre les injections SQL
  - **Purge intelligente**: Stratégie basée sur les mises à jour

### Optimisations spécifiques
- **Disponibilité des excursions**: Requêtes optimisées dans `ajax/availability-ajax.php`
- **Paniers abandonnés**: Index dédiés et cache pour les récupérations
- **Calcul des prix**: Mise en cache des résultats complexes
- **Statistiques admin**: Cache transient avec durée variable selon contexte

### Surveillance et diagnostics
- **Tableau de bord**: Métriques sur les performances des requêtes
- **Debugging**: Mode diagnostique pour développeurs
- **Logs**: Enregistrement des requêtes lentes pour analyse

---

## 10. ARCHITECTURE JAVASCRIPT MODULAIRE

### Conception modulaire
- **Architecture des modules**: Séparation des responsabilités en modules autonomes
  - **Core**: `/assets/js/modules/core.js` - Fonctions de base, détection réseau, utilitaires
  - **Price Calculator**: `/assets/js/modules/price-calculator.js` - Calcul des prix d'excursion
  - **Mediateur**: `/assets/js/cameroon-optimizer.js` - Orchestration du chargement

### Chargement optimisé
- **Stratégies de chargement**: Adaptation à la qualité de la connexion
  - **Critique**: Composants essentiels chargés immédiatement
  - **Différé**: Composants secondaires chargés après la page principale
  - **Sur demande**: Composants optionnels chargés uniquement si nécessaire
  - **Fichier principal**: `/includes/cameroon-assets-optimizer.php:split_frontend_js()`

### Fonctionnalités avancées
- **Détection réseau côté client**:
  - Test de latence et bande passante
  - Surveillance continue de la qualité de connexion
  - Adaptation dynamique aux changements
- **Support hors-ligne**:
  - API Cache pour les assets critiques
  - IndexedDB pour les données structurées
  - LocalStorage pour les préférences et états
- **Synchronisation intelligente**:
  - File d'attente des actions à synchroniser
  - Stratégie de résolution des conflits
  - Indicateurs d'état utilisateur

### Points d'entrée AJAX
- **Chargement de module**: `/wp-admin/admin-ajax.php?action=life_travel_load_module`
- **Détection de connectivité**: `/wp-admin/admin-ajax.php?action=life_travel_check_connectivity`
- **Mise en cache des données**: `/wp-admin/admin-ajax.php?action=life_travel_cache_data`

---

## 11. FONCTIONNALITÉS DÉCOUVERTES ET OPTIMISÉES

### Tableau de Bord Administrateur Unifié
- **Interface principale**: Tableau de bord centralisé pour administrateurs non-techniques  
  Fichier: `/includes/admin/class-life-travel-admin.php`
- **Architecture modulaire**: Utilisation de traits PHP pour un code maintenable  
  Répertoire: `/includes/admin/`
- **Pages d'administration**:
  - **Dashboard**: Vue d'ensemble et actions rapides avec statistiques réelles  
    Fichier: `/includes/admin/class-life-travel-admin-renderers-dashboard.php`
  - **Performance**: Analyse des performances réseau et optimisations spécifiques au Cameroun  
    Fichier: `/includes/admin/class-life-travel-admin-renderers-performance.php`
  - **Base de données**: Gestion des optimisations et index MySQL  
    Fichier: `/includes/admin/class-life-travel-admin-renderers-database.php`
  - **Media**: Gestion des logos, arrière-plans et galeries  
    Fichier: `/includes/admin/class-life-travel-admin-renderers-media.php`
  - **Network Tester**: Tests réseau réels avec analyse de performance  
    Fichier: `/includes/admin/class-life-travel-admin-renderers-network-tester.php`
  - **Optimisation réseau Cameroun**: Analyse réseau et métriques spécifiques au contexte local  
    Fichier: `/includes/cameroon-assets-optimizer.php`
  - **Excursions**: Gestion centralisée des excursions et paramètres globaux  
    Fichier: `/includes/admin/class-life-travel-admin-renderers-excursions.php`
  - **Payments**: Configuration des passerelles de paiement  
    Fichier: `/includes/admin/class-life-travel-admin-renderers-payments.php`
  - **Abandoned Carts**: Gestion et récupération des paniers abandonnés  
    Fichier: `/includes/admin/class-life-travel-admin-renderers-cart.php`
- **Ressources frontend**: 
  - CSS: `/assets/css/admin.css`
  - JavaScript: `/assets/js/admin.js`
- **Sécurité**: Validation des données, nonces, vérification des permissions
- **Optimisations**: Support pour les connexions lentes, mode partiellement hors-ligne, interface adaptatée au Cameroun

### Sécurité et récupération des paniers abandonnés
- **Interface d'administration**: `/includes/admin/class-life-travel-admin-renderers-cart.php:render_cart_abandoned()`
  - Tableau de bord: Statistiques et tendances
  - Liste des paniers: Filtrables par date, montant, origine
  - Accès direct: Reconstruction et administration
  - Actions en masse: Rappels, nettoyage, archivage
- **Paramètres de récupération**:
  - Délais configurables: 1h, 3h, 12h, 24h, personnalisé
  - Nombre de tentatives: Limitable
  - Récompenses: Coupons de réduction automatiques
  - Messages: Personnalisables avec variables dynamiques
- **Optimisations pour le Cameroun**:
  - Mode hors-ligne: Récupération après reconnexion
  - File d'attente: Envoi différé des emails
  - SMS: Option via réseau local
- **Sécurité renforcée**:
  - Analyseur de sécurité: `/includes/abandoned-cart-security-analyzer.php`
  - Journalisation: `/includes/abandoned-cart-security-logger.php`
  - Rapports: `/includes/abandoned-cart-security-chart.php`
  - Validations: Contrôles avancés contre CSRF, XSS et injections SQL

### Intégration multilingue
- **TranslatePress**: Intégration avec le panier `/includes/frontend/translatepress-cart-integration.php`
- **Chaînes traduisibles**: Gestion des traductions pour l'interface utilisateur
- **Interface admin**: Modules pour gérer les traductions des excursions

### Fonctionnalités sociales
- **Partage**: Module de partage social `/includes/frontend/share.php`
- **Fidélité**: Système de points et récompenses `/includes/frontend/loyalty.php`
- **Évaluations**: Module de votes et avis `/includes/frontend/vote.php`

---

## 10. ADMINISTRATION DES EXCURSIONS

### Interface centralisée des excursions
- **Tableau de bord des excursions**: `/includes/admin/class-life-travel-admin-renderers-excursions.php:render_excursions_dashboard()`
  - Statistiques en temps réel: Ventes, réservations, disponibilité
  - Actions rapides: Création, modification, publication
  - Filtres avancés: Par type, statut, période
  - Exportation: CSV, Excel, PDF
- **Paramètres globaux**: `/includes/admin/class-life-travel-admin-renderers-excursions.php:render_excursions_settings()`
  - **Configuration globale**: Paramètres communs à tous les types
  - **Excursions de groupe**: 
    - Gestion des groupes et remises
    - Seuils de déclenchement automatique
    - Pourcentages configurables
  - **Excursions privées**: 
    - Tarification par tranches de participants
    - Tarifs minimum et maximum
    - Suppléments et extras
  - **Saisonnalité**: 
    - Prix variables selon périodes
    - Haute saison: Mois configurables
    - Basse saison: Mois configurables
    - Multiplicateurs de prix

### Paramètres spécifiques
- **Limites de participants**: Minimum et maximum par excursion
- **Délais de réservation**: Minimum avant départ
- **Disponibilité**: Jours et plages autorisés
- **Extras**: Options complémentaires configurables par excursion
  - Format: Nom|Prix|Type|Multiplicateur
  - Types: quantité, fixe
  - Multiplicateurs: participants, jours, jours_participants, fixe
- **Véhicules/Pirogues**:
  - Capacité de base: Nombre de personnes par unité
  - Seuil déclencheur: Participants supplémentaires pour ajouter un véhicule
  - Coût additionnel: Prix par véhicule supplémentaire
  - Plafonnement: Nombre maximum de véhicules disponibles
- **Points de fidélité**:
  - Type: Fixe ou pourcentage
  - Valeur: Nombre de points ou taux par montant
  - Plafond: Limite par excursion (optionnel)

### Points d'extension
- **Hooks WordPress**: 
  - **Filtres**:
    - `life_travel_excursion_price`: Modifier les prix calculés
    - `life_travel_available_slots`: Ajuster les places disponibles
    - `life_travel_booking_fields`: Modifier les champs de réservation
    - `life_travel_payment_gateways`: Ajouter/modifier passerelles
    - `life_travel_admin_menu_items`: Personnaliser menu admin
    - `lte_get_user_loyalty_points`: Récupérer le solde de points d'un utilisateur
    - `lte_points_earned_multiplier`: Modifier le multiplicateur de points gagnés
  - **Actions**:
    - `life_travel_before_booking_save`: Avant enregistrement réservation
    - `life_travel_after_booking_save`: Après enregistrement réservation
    - `life_travel_payment_complete`: Paiement réussi
    - `life_travel_abandoned_cart_detected`: Panier abandonné
    - `life_travel_admin_init`: Initialisation admin
    - `lte_points_awarded`: Après attribution de points de fidélité
    - `lte_points_redeemed`: Après utilisation de points de fidélité
- **API REST**: Endpoints pour intégrations externes
  - Préfixe: `/wp-json/life-travel/v1/`
  - Documentation Swagger: `/docs/api-reference.html`
  - Authentification: Clés API ou JWT
- **Templates surchargeables**: Dans le thème pour personnalisation visuelle
  - Chemin thème: `/life-travel-excursion/`
  - Priorité: Thème enfant > Thème parent > Plugin

---

## 13. GUIDE COMPLET DE L'ADMINISTRATEUR

Cette section est spécialement conçue pour les administrateurs non-techniques qui gèrent quotidiennement le site Life Travel.

### Tableau de bord principal

#### Accès au tableau de bord
- Se connecter à l'administration WordPress (`https://votre-site.com/wp-admin/`)
- Naviguer vers le menu latéral "Life Travel" → "Tableau de bord"

#### Vue d'ensemble des métriques
- **Réservations**: Nombre total, récentes, et en attente
- **Excursions**: Populaires, récentes, et statistiques
- **Revenus**: Journaliers, hebdomadaires, mensuels
- **Points de fidélité**: Distribution totale et récente

#### Actions rapides
- **Créer une excursion**: Bouton "Ajouter une excursion"
- **Gérer les réservations**: Lien vers la liste des réservations
- **Paramètres rapides**: Accès aux configurations principales

### Gestion des excursions

#### Création d'une nouvelle excursion
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

#### Modification d'une excursion existante
1. Aller à "Life Travel" → "Excursions" → "Toutes les excursions"
2. Cliquer sur le titre de l'excursion à modifier
3. Effectuer les changements nécessaires
4. Cliquer sur "Mettre à jour"

#### Duplication d'une excursion
1. Dans la liste des excursions, survoler le titre
2. Cliquer sur "Dupliquer"
3. La nouvelle copie s'appellera "Copie de [Titre original]"

### Configuration du système de fidélité

#### Paramètres globaux
1. Aller à "Life Travel" → "Fidélité" → "Paramètres"
2. Configurer les paramètres de base:
   - **Activation/désactivation**: Activer le système
   - **Taux de conversion**: Points → Valeur monétaire (XAF)
   - **Minimum pour utilisation**: Seuil minimal de points
   - **Maximum par commande**: Limite d'utilisation
   - **Plafond global**: Limite de points gagnés par excursion
   - **Expiration**: Durée de validité des points

#### Paramètres spécifiques par excursion
1. Éditer une excursion
2. Défiler jusqu'à la section "Système de points de fidélité"
3. Configurer les options spécifiques:
   - **Activer/désactiver** pour cette excursion
   - **Type de récompense**: Fixe ou pourcentage
   - **Valeur**: Points fixes ou pourcentage du prix
   - **Plafonnement**: Limite spécifique à ce produit

#### Points pour partages sociaux
1. Aller à "Life Travel" → "Fidélité" → "Partages sociaux"
2. Définir les points attribués pour:
   - Partage sur Facebook
   - Partage sur Twitter/X
   - Partage sur WhatsApp
   - Évaluation/avis

#### Tableau de bord des statistiques de fidélité
1. Aller à "Life Travel" → "Fidélité" → "Statistiques"
2. Analyser les données disponibles:
   - **Utilisateurs les plus fidèles**: Classement
   - **Distribution des points**: Par source
   - **Tendances d'utilisation**: Graphiques
   - **Excursions populaires**: Par points générés
3. Utiliser les outils d'export:
   - CSV pour analyse externe
   - PDF pour rapports

### Gestion des optimisations réseau

#### Configuration des optimisations Cameroun
1. Aller à "Life Travel" → "Réseau" → "Optimisations"
2. Configurer les paramètres:
   - **Détection auto**: Activer/désactiver
   - **Mode hors-ligne**: Configurer niveau
   - **File d'attente des paiements**: Niveau de priorité
   - **Compression des images**: Qualité adaptative

#### Tests de performance réseau
1. Aller à "Life Travel" → "Réseau" → "Test de performance"
2. Utiliser les outils disponibles:
   - Test de latence
   - Test de bande passante
   - Simulateur de connexion lente
3. Appliquer les recommandations automatiques

#### Statistiques réseau
1. Aller à "Life Travel" → "Réseau" → "Statistiques"
2. Analyser les métriques:
   - Temps de chargement moyen
   - Taux de synchronisation hors-ligne
   - Taux de compression des médias
   - Utilisateurs par type de connexion

### Maintenance quotidienne

#### Vérifications journalières
1. **Réservations**: Vérifier les nouvelles réservations et confirmer
2. **Paiements**: Vérifier les paiements en attente/échecs
3. **Stock d'excursions**: Vérifier disponibilité et capacités
4. **Points de fidélité**: Surveiller distribution et utilisation

#### Vérifications hebdomadaires
1. **Sauvegarde**: Confirmer les sauvegardes automatiques
2. **Performance**: Vérifier statistiques de vitesse
3. **Mise à jour**: Planifier updates si disponibles
4. **Contenu**: Rafraîchir excursions populaires

#### Vérifications mensuelles
1. **Nettoyage base de données**: Optimiser les tables
2. **Statistiques complètes**: Exporter et analyser
3. **Réindexation**: Reconstruire index AJAX et recherche
4. **Ajustements fidélité**: Revoir taux et plafonds

#### Procédures de maintenance

**Optimisation de la base de données**
1. Aller à "Life Travel" → "Base de données" → "Optimisation"
2. Cliquer sur "Analyser les tables"
3. Sélectionner les tables à optimiser ou "Toutes"
4. Cliquer sur "Optimiser"
5. Vérifier le rapport de résultats

**Purge du cache**
1. Aller à "Life Travel" → "Performance" → "Cache"
2. Sélectionner les types de cache à vider
3. Cliquer sur "Purger la sélection"
4. Vérifier confirmation de nettoyage

**Gestion des paniers abandonnés**
1. Aller à "Life Travel" → "Paniers" → "Abandonnés"
2. Filtrer par période ou valeur
3. Actions disponibles:
   - Envoyer rappel manuel
   - Offrir coupon de récupération
   - Archiver les anciens

### Résolution des problèmes courants

#### Problèmes de paiement
- **Erreur MoMo**: Vérifier configuration IwomiPay et clés API
- **Échec de transaction**: Consulter journal des transactions
- **Paiement bloqué**: Vérifier statut de la commande et débloquer

#### Problèmes de réservation
- **Capacité incorrecte**: Vérifier paramètres d'excursion
- **Prix erroné**: Vider cache de prix et recalculer
- **Conflit de dates**: Vérifier calendrier et disponibilité

#### Problèmes de points de fidélité
- **Points non attribués**: Vérifier journal et attribuer manuellement
- **Impossible d'utiliser**: Vérifier solde et seuil minimum
- **Historique incorrect**: Accéder au profil utilisateur et ajuster

#### Support et aide
- **Documentation**: Accéder à la documentation complète
- **Support technique**: Contacter le support via le formulaire dédié
- **Vidéos tutoriels**: Accéder à la bibliothèque de vidéos

---

## 14. DOCUMENTATION TECHNIQUE POUR DÉVELOPPEURS

### Architecture du code

#### Structure des répertoires

```
life-travel-excursion/
├── includes/                     # Code PHP du plugin
│   ├── admin/                    # Interfaces administrateur
│   │   ├── class-life-travel-admin.php                    # Point d'entrée admin
│   │   ├── class-life-travel-admin-renderers-*.php        # Renderers admin
│   │   ├── loyalty-*.php                                  # Admin fidélité
│   ├── ajax/                     # Points d'entrée AJAX
│   │   ├── availability-ajax.php                          # Disponibilité
│   │   ├── pricing-ajax-optimizer.php                     # Calcul de prix
│   ├── frontend/                 # Interface utilisateur
│   │   ├── loyalty-*.php                                  # Système fidélité frontend
├── assets/                       # Ressources statiques
│   ├── js/                       # Scripts JavaScript
│   │   ├── modules/              # Architecture modulaire JS
│   │   ├── offline-*.js          # Support hors-ligne
│   │   ├── admin/                # Scripts admin
│   ├── css/                      # Styles CSS
│   ├── img/                      # Images du plugin
├── templates/                    # Modèles de rendu
│   ├── emails/                   # Templates d'emails
│   ├── myaccount/                # Templates compte utilisateur
├── docs/                         # Documentation
├── tests/                        # Tests automatisés
```

#### Diagramme d'architecture

```
┌────────────────────────┐      ┌────────────────────────┐
│     FRONTEND           │      │        ADMIN            │
│                        │      │                        │
│  ┌──────────────────┐  │      │  ┌──────────────────┐  │
│  │  Templates       │  │      │  │  Admin Renderers │  │
│  └──────────────────┘  │      │  └──────────────────┘  │
│           │            │      │           │            │
│  ┌──────────────────┐  │      │  ┌──────────────────┐  │
│  │  Core Classes    │──┼──────┼──│  Admin Classes   │  │
│  └──────────────────┘  │      │  └──────────────────┘  │
│           │            │      │           │            │
└────────────────────────┘      └────────────────────────┘
            │                                │
            │                                │
            ▼                                ▼
┌────────────────────────┐      ┌────────────────────────┐
│     MODULES            │      │     INFRASTRUCTURE     │
│                        │      │                        │
│  ┌──────────────────┐  │      │  ┌──────────────────┐  │
│  │  Loyalty System  │  │      │  │  Database Layer  │  │
│  └──────────────────┘  │      │  └──────────────────┘  │
│  ┌──────────────────┐  │      │  ┌──────────────────┐  │
│  │  Offline Support │  │      │  │  WooCommerce     │  │
│  └──────────────────┘  │      │  └──────────────────┘  │
│  ┌──────────────────┐  │      │  ┌──────────────────┐  │
│  │  Price Calculator│  │      │  │  WordPress Hooks │  │
│  └──────────────────┘  │      │  └──────────────────┘  │
└────────────────────────┘      └────────────────────────┘
```

#### Flux de données principales

```
Réservation d'excursion:

Utilisateur → Selection excursion → Calculateur de prix → 
Panier WooCommerce → Checkout → Paiement IwomiPay → 
Traitement commande → Attribution points

Utilisation points fidélité:

Utilisateur → Panier → Formulaire points → Application réduction → 
Checkout → Paiement → Déduction points
```

### Points d'extension

#### Filtres WordPress

```php
// Modifier le prix calculé d'une excursion
apply_filters('life_travel_excursion_price', $price, $product_id, $participants, $extras);  
  
// Modifier les places disponibles pour une excursion
apply_filters('life_travel_available_slots', $available, $product_id, $date);  
  
// Modifier les points de fidélité attribués
apply_filters('lte_points_earned', $points, $order_id, $user_id);  
  
// Modifier le facteur de conversion points → monnaie
apply_filters('lte_points_conversion_rate', $rate, $user_id, $context);  
  
// Modifier la détection de réseau Cameroun
apply_filters('lte_network_detection', $network_info, $user_id);  
```

#### Actions WordPress

```php
// Déclenché après l'attribution de points
do_action('lte_points_awarded', $user_id, $points, $source, $order_id);  
  
// Déclenché après l'utilisation de points
do_action('lte_points_redeemed', $user_id, $points, $order_id, $amount);  
  
// Déclenché lors d'une réservation hors-ligne
do_action('lte_offline_booking_saved', $booking_data, $user_id, $sync_id);  
  
// Déclenché lors d'un changement de qualité réseau
do_action('lte_network_quality_changed', $old_quality, $new_quality, $user_id);  
```

#### Classes d'extension

```php
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
```

### Guide de développement

#### Environnement de développement

1. **Installation locale**
   ```bash
   # Cloner le repository
   git clone https://repository-url.git life-travel-excursion
   cd life-travel-excursion
   
   # Installer dépendances
   composer install
   npm install
   
   # Build des assets
   npm run dev # Pour développement (avec sourcemaps)
   ```

2. **Workflow de développement**
   - Utiliser le mode développement dans wp-config.php:
     ```php
     define('WP_DEBUG', true);
     define('WP_DEBUG_LOG', true);
     define('WP_DEBUG_DISPLAY', true);
     define('SCRIPT_DEBUG', true);
     ```
   - Activer les logs verbeux dans le plugin:
     ```php
     // Dans fichier principal du plugin
     define('LTE_DEBUG', true);
     ```

3. **Structure des commits Git**
   - Format: `[type]: description courte`
   - Types: `feat`, `fix`, `docs`, `style`, `refactor`, `test`, `chore`
   - Exemple: `feat: ajout système de points fidélité par partage`

#### Documentation du code

Respectez ces standards pour toute nouvelle contribution:

```php
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
```

#### Tests automatisés

1. **Exécution des tests**
   ```bash
   # Installer PHPUnit si nécessaire
   composer require --dev phpunit/phpunit
   
   # Exécuter les tests
   ./vendor/bin/phpunit
   ```

2. **Structure des tests**
   - Tests unitaires: `tests/unit/`
   - Tests d'intégration: `tests/integration/`
   - Tests frontaux: `tests/e2e/`

3. **Exemple de test unitaire**
   ```php
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
   ```

---

## 15. CONSEILS D'UTILISATION ET MAINTENANCE

### Optimisations recommandées
- **Cache**: 
  - Activer un plugin de cache pour les pages statiques
  - Recommandés: WP Rocket, W3 Total Cache, LiteSpeed Cache
  - Configuration spéciale: Exclure pages de paiement et checkout du cache
- **Optimisation images**: 
  - Utiliser WebP quand disponible (activé dans l'interface Media)
  - Compression adaptative selon qualité réseau
  - Redimensionnement automatique aux dimensions exactes
- **Minification**: 
  - Activer pour CSS/JS en production
  - Concaténation des fichiers
  - Différer le chargement des scripts non-critiques
- **CDN**: 
  - Configurer si disponible pour les ressources statiques
  - Recommandations pour le Cameroun: Éviter si bande passante internationale limitée
  - Alternative locale: Optimisation du serveur local
- **Paramètres réseau**:
  - Tableau de bord → Réseau et performances → Optimisations pour connexions lentes
  - Détection automatique de la qualité de connexion
  - Modes d'économie de données

### Système de fidélité
- **Vérifications**:
  - Attribution des points pour chaque excursion configurée
  - Plafonnement correct des points
  - Conversion des points en réduction
  - Affichage et fonctionnement des notifications
- **Configuration**:
  - Produits → Fidélité → Paramètres globaux
  - Par excursion: Dans la section "Système de points de fidélité"
  - Partages sociaux: Dans les paramètres de fidélité

### Sauvegardes
- **Fréquence**: Quotidienne recommandée
- **Contenu à inclure**: Base de données, uploads, plugins, thème
- **Rotation**: Conserver au moins 7 jours d'historique
- **Test de restauration**: Vérifier régulièrement

### Mise à jour
- **Environnement de test**: Toujours tester les mises à jour
- **Séquence recommandée**: WordPress, puis Plugins, puis Thème
- **Vérifications post-mise à jour**: 
  - Passerelles de paiement
  - Calculateur de prix
  - Fonctionnement hors-ligne
  - Adaptabilité mobile

### Monitoring
- **Surveiller**: Temps de réponse, erreurs 404/500, tentatives de connexion
- **Diagnostics réseau**: Vérifier performances avec connexion lente
- **Alertes**: Configurer pour échecs de paiement ou erreurs critiques

---

## 12. INTÉGRATIONS EXTERNES

### API et webhooks disponibles
- **Paiements**: Webhooks IwomiPay pour notifications de paiement
- **Disponibilité**: API REST pour vérifier places disponibles
- **Réservations**: Endpoints pour créer/modifier des réservations

### Points d'extension
- **Filtres WordPress**: Personnalisation des comportements
- **Actions WordPress**: Hooks pour fonctionnalités additionnelles
- **Templates surchargeables**: Dans le thème pour personnalisation visuelle

### Services tiers supportés
- **Twilio**: Notifications SMS et WhatsApp
- **IwomiPay**: Passerelles de paiement
- **Facebook**: OAuth pour authentification
- **Google Maps**: Carte interactive
