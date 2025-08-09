# Inventaire des fonctionnalités Life Travel

## 1. Fonctionnalités du plugin Core (site principal)

### 1.1 Types de contenu personnalisés
- **Localisation actuelle** : `life-travel-core/includes/class-cpt.php`
- **Fonctionnalité** : Définition du type `excursion_custom`
- **Dépendances** : WordPress Core, ACF, WooCommerce
- **Utilisé par** : Interface admin, templates frontend, blocs Gutenberg
- **Devrait être dans** : Plugin Excursion (déplacer)

### 1.2 Taxonomies
- **Localisation actuelle** : `life-travel-core/includes/class-taxonomies.php`
- **Fonctionnalité** : Catégorisation des excursions, lieux et difficultés
- **Dépendances** : WordPress Core, Custom Post Types
- **Utilisé par** : Interface admin, recherche, filtres
- **Devrait être dans** : Plugin Excursion (déplacer)

### 1.3 Blocs Gutenberg
- **Localisation actuelle** : `life-travel-core/blocks/`
- **Fonctionnalité** : Blocs d'édition pour le site
- **Sous-composants** :
  - `calendar` - Affichage des disponibilités (→ déplacer vers plugin Excursion)
  - `hero-banner` - Bannières génériques (→ garder dans Core)
  - `month-slider` - Sélection de dates (→ déplacer vers plugin Excursion)
  - `vote-module` - Évaluations (→ déplacer vers plugin Excursion)
- **Dépendances** : WordPress Core, React
- **Devrait être dans** : Répartir selon spécificité

### 1.4 Optimisations
- **Localisation actuelle** : `life-travel-core/includes/class-optimizations.php`
- **Fonctionnalité** : Optimisations générales du site
- **Dépendances** : WordPress Core
- **Utilisé par** : Tout le site
- **Devrait être dans** : Plugin Core (conserver)

### 1.5 Intégration WooCommerce
- **Localisation actuelle** : `life-travel-core/includes/class-woocommerce.php`
- **Fonctionnalité** : Personnalisations générales de WooCommerce
- **Dépendances** : WooCommerce
- **Utilisé par** : Ecommerce général
- **Devrait être dans** : Plugin Core (conserver)

## 2. Fonctionnalités du plugin Excursion

### 2.1 Gestion des excursions
- **Localisation actuelle** : Dispersé dans `life-travel-excursion.php` et sous-dossiers
- **Fonctionnalité** : Gestion complète des excursions
- **Dépendances** : WordPress Core, WooCommerce
- **Utilisé par** : Administrateurs, clients
- **Devrait être dans** : Plugin Excursion (centraliser)

### 2.2 Intégration au site
- **Localisation actuelle** : `includes/life-travel-site-integration.php`
- **Fonctionnalité** : Pont entre le plugin et le thème
- **Dépendances** : WordPress, thème principal
- **Utilisé par** : Tout le système
- **Action** : Restructurer pour séparer les préoccupations

### 2.3 Média manager
- **Localisation actuelle** : `includes/media-manager.php`
- **Fonctionnalité** : Gestion des médias, optimisation d'images
- **Dépendances** : WordPress Media Library
- **Utilisé par** : Interface admin, shortcodes
- **Devrait être dans** : Plugin Core (déplacer)

### 2.4 Optimisation réseau
- **Localisation actuelle** : `includes/network-optimization.php`
- **Fonctionnalité** : Optimisations pour connexions lentes (Cameroun)
- **Dépendances** : WordPress, navigateurs clients
- **Utilisé par** : Frontend
- **Devrait être dans** : Plugin Core (déplacer)

### 2.5 Récupération des paniers abandonnés
- **Localisation actuelle** : `includes/abandoned-cart-recovery.php`
- **Fonctionnalité** : Sauvegarde et récupération de paniers d'achat
- **Dépendances** : WooCommerce, base de données
- **Particularités** : 
  - Validation et assainissement robustes des données
  - Gestion des connexions intermittentes
  - Contrôles de sécurité CSRF/XSS avancés
  - Journalisation sécurisée
  - Support de multiples formats de données
  - Validation spécifique aux excursions
  - Gestion intelligente des paniers existants/nouveaux
- **Utilisé par** : Système de commande, marketing
- **Devrait être dans** : Plugin Excursion (optimiser UI)

### 2.6 Passerelles de paiement
- **Localisation actuelle** : `payment-gateways/` et références dans `life-travel-site-integration.php`
- **Fonctionnalité** : Intégration IwomiPay et autres systèmes
- **Dépendances** : WooCommerce, APIs externes
- **Utilisé par** : Processus de commande
- **Devrait être dans** : Module spécifique aux paiements (isoler)

### 2.7 Éléments visuels et shortcodes
- **Localisation actuelle** : `includes/shortcodes.php` et divers fichiers CSS/JS
- **Fonctionnalité** : Shortcodes pour médias, galeries, etc.
- **Dépendances** : WordPress, thème
- **Utilisé par** : Contenu des pages, articles
- **Devrait être dans** : Diviser entre Core (génériques) et Excursion (spécifiques)

### 2.8 Adaptabilité mobile
- **Localisation actuelle** : Dispersé dans CSS et JS, notamment `assets/js/connection-manager.js`
- **Fonctionnalité** : Support responsive, détection d'orientation
- **Dépendances** : Navigateurs clients
- **Utilisé par** : Frontend mobile
- **Devrait être dans** : Thème principal avec hooks pour extensions

## 3. Points d'intégration critiques

### 3.1 Cycle de vie des excursions
- **Description** : Création → Publication → Réservation → Paiement → Exécution
- **Composants impliqués** : CPT, WooCommerce, Passerelles de paiement
- **Problèmes actuels** : Double définition des excursions, synchronisation manuelle

### 3.2 Gestion des médias
- **Description** : Upload → Optimisation → Utilisation dans contenus
- **Composants impliqués** : Media Manager, Shortcodes, Editeur
- **Problèmes actuels** : Responsabilités dispersées, duplications

### 3.3 Passerelles de paiement
- **Description** : Configuration → Intégration → Sécurisation → Traitement
- **Composants impliqués** : WooCommerce, IwomiPay, Orange Money
- **Problèmes actuels** : Trop intégré dans `life-travel-site-integration.php`

### 3.4 Gestion des paniers abandonnés
- **Description** : Stockage → Récupération → Notification → Conversion
- **Composants impliqués** : Abandoned Cart Recovery, WooCommerce, Base de données
- **Problèmes actuels** : Bonne implémentation technique mais isolée de l'UI admin

### 3.5 Support multilingue
- **Description** : Traduction → Détection → Affichage
- **Composants impliqués** : TranslatePress, chaînes dynamiques
- **Problèmes actuels** : Dispersion des points d'intégration, complexité
