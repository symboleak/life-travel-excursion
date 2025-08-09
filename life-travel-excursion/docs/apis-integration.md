# Documentation des APIs et points d'intégration

Ce document détaille les interfaces de programmation et les points d'intégration entre les différents composants du système Life Travel.

## 1. APIs WordPress utilisées

### 1.1 APIs de types de contenu
- **Hook**: `init`
- **Fonction**: `register_post_type()`, `register_taxonomy()`
- **Utilisé par**: Core & Plugin Excursion
- **Problème**: Duplication des définitions entre les deux plugins
- **Recommandation**: Standardiser via une API commune

```php
// Exemple d'appel standardisé à implémenter
do_action('life_travel_register_content_types', $args);
```

### 1.2 APIs de média
- **Hook**: `wp_enqueue_scripts`, `admin_enqueue_scripts` 
- **Fonction**: `wp_enqueue_style()`, `wp_enqueue_script()`
- **Utilisé par**: Core, Plugin, Thème
- **Problème**: Chargement non coordonné des assets
- **Recommandation**: Centraliser la gestion via API commune

```php
// Implémentation recommandée
LifeTravel::assets()->register($handle, $args);
LifeTravel::assets()->enqueue($handles);
```

## 2. APIs WooCommerce utilisées

### 2.1 Passerelles de paiement
- **Hook**: `woocommerce_payment_gateways`
- **Utilisé par**: Plugin Excursion via `add_iwomipay_gateways()`
- **Problème**: Fortement couplé avec `life-travel-site-integration.php`
- **Recommandation**: Créer une classe dédiée aux passerelles

```php
// Implémentation recommandée
class Life_Travel_Payment_Gateways {
    public function register_gateways($gateways) {
        // Logique isolée ici
    }
}
```

### 2.2 Gestion des prix et commandes
- **Hook**: `woocommerce_order_status_changed`, `woocommerce_cart_item_price`
- **Utilisé par**: Plugin Excursion via diverse méthodes
- **Problème**: Dispersion de la logique et faible cohésion
- **Recommandation**: Regrouper dans une classe Orders

## 3. APIs personnalisées à créer

### 3.1 API de gestion des excursions
```php
/**
 * Récupère les données d'une excursion
 * 
 * @param int $excursion_id ID de l'excursion
 * @param array $args Arguments optionnels
 * @return array|WP_Error Données ou erreur
 */
function life_travel_get_excursion($excursion_id, $args = []) {
    // Abstraction unifiée indépendante du stockage
}

/**
 * Crée ou met à jour une excursion
 * 
 * @param array $excursion_data Données de l'excursion
 * @param int $excursion_id ID optionnel (0 pour création)
 * @return int|WP_Error ID de l'excursion ou erreur
 */
function life_travel_save_excursion($excursion_data, $excursion_id = 0) {
    // Abstraction unifiée
}
```

### 3.2 API de gestion de connexion réseau
```php
/**
 * Optimise le contenu selon la qualité de connexion
 * 
 * @param string $content Le contenu HTML
 * @param string $connection_type Type de connexion (slow, medium, fast)
 * @return string Contenu optimisé
 */
function life_travel_optimize_for_connection($content, $connection_type = null) {
    // Logique d'optimisation
}
```

### 3.3 API unifiée d'administration
```php
/**
 * Enregistre une page d'administration dans le tableau de bord unifié
 * 
 * @param string $id Identifiant unique
 * @param array $args Arguments de configuration
 */
function life_travel_register_admin_page($id, $args = []) {
    // Logique d'enregistrement
}

/**
 * Enregistre une option dans une page donnée
 * 
 * @param string $page_id Page parent
 * @param string $option_id Identifiant de l'option
 * @param array $args Configuration
 */
function life_travel_register_admin_option($page_id, $option_id, $args = []) {
    // Logique d'enregistrement
}
```

## 4. Points d'intégration critique à refactoriser

### 4.1 Intégration site-plugin
- **Fichier actuel**: `includes/life-travel-site-integration.php`
- **Problème**: Classe monolithique avec trop de responsabilités
- **Solution**: Décomposer en classes spécialisées

```php
// Nouvelle architecture proposée
- Life_Travel_Core // Plugin principal
  - Life_Travel_Admin // Administration unifiée
  - Life_Travel_Assets // Gestion des assets
  - Life_Travel_Network // Optimisations réseau
  - Life_Travel_Integrations // Intégrations externes

- Life_Travel_Excursion // Plugin excursions
  - Life_Travel_Bookings // Réservations
  - Life_Travel_Payments // Paiements
  - Life_Travel_Cart // Panier et récupération
```

### 4.2 Système de récupération des paniers abandonnés
- **Fichier actuel**: `includes/abandoned-cart-recovery.php`
- **Forces**: Validation robuste, gestion intermittente, sécurité
- **Amélioration**: Ajouter une interface admin intuitive

```php
// Hook pour l'interface admin
add_action('life_travel_register_admin_page', function() {
    life_travel_register_admin_page('abandoned-carts', [
        'title' => 'Paniers abandonnés',
        'capability' => 'manage_woocommerce',
        'icon' => 'shopping-cart',
        'position' => 25,
        'sections' => [
            'statistics' => [
                'title' => 'Statistiques',
                'callback' => 'life_travel_display_cart_stats'
            ],
            'recovery' => [
                'title' => 'Récupération',
                'callback' => 'life_travel_display_cart_recovery'
            ]
        ]
    ]);
});
```

## 5. Standardisation des hooks

Pour maintenir une cohérence et permettre l'extension future, tous les hooks personnalisés devraient suivre cette convention:

- Hooks d'actions: `life_travel_{composant}_{action}`
- Hooks de filtres: `life_travel_{composant}_{élément}_filter`

### 5.1 Hooks prioritaires à implémenter
- `life_travel_admin_init` - Initialisation du tableau de bord
- `life_travel_excursion_register` - Enregistrement d'excursions
- `life_travel_network_optimize` - Optimisation réseau
- `life_travel_media_register` - Enregistrement de médias
- `life_travel_payment_process` - Traitement des paiements
