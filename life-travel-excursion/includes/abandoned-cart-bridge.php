<?php
/**
 * Pont de migration pour la gestion des paniers abandonnés
 * 
 * Ce fichier sert de liaison entre l'ancien système de gestion des paniers abandonnés
 * et notre nouvelle implémentation optimisée et centralisée.
 * 
 * @package Life Travel Excursion
 * @version 2.4.0
 */

defined('ABSPATH') || exit;

/**
 * Vérifie quel système de gestion de paniers abandonnés utiliser
 * et charge le composant approprié.
 * 
 * @return bool True si un système a été chargé
 */
function life_travel_load_abandoned_cart_system() {
    // Vérifier si la nouvelle interface est disponible
    $admin_cart_file = __DIR__ . '/admin/class-life-travel-admin-renderers-cart.php';
    $use_new_cart_system = get_option('life_travel_use_new_cart_system', null);
    
    // Si l'option n'existe pas encore, définir sur true pour les nouvelles installations
    // et false pour les installations existantes qui ont déjà des paniers abandonnés
    if ($use_new_cart_system === null) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'life_travel_abandoned_carts';
        
        // Vérifier si la table existe et contient des données
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
        $has_data = false;
        
        if ($table_exists) {
            $has_data = $wpdb->get_var("SELECT COUNT(*) FROM $table_name") > 0;
        }
        
        // Si la table existe et contient des données, utiliser l'ancien système par défaut
        // pour éviter la perte de données
        $use_new_cart_system = !($table_exists && $has_data);
        update_option('life_travel_use_new_cart_system', $use_new_cart_system);
    }
    
    // Charger le système approprié
    if ($use_new_cart_system && file_exists($admin_cart_file)) {
        // Si l'ancien système était déjà chargé, assurer la compatibilité
        if (class_exists('Life_Travel_Abandoned_Cart')) {
            return true; // Les deux systèmes sont déjà chargés
        }
        
        // Créer une classe de compatibilité pour l'ancien système
        if (!class_exists('Life_Travel_Abandoned_Cart')) {
            class Life_Travel_Abandoned_Cart {
                /**
                 * Constructeur de compatibilité
                 */
                public function __construct() {
                    // Ajouter un message de dépréciation si WP_DEBUG est activé
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('Life Travel: Utilisation de la classe de compatibilité pour les paniers abandonnés. Pensez à migrer vos données.');
                    }
                    
                    // Enregistrer un hook pour rediriger vers le nouveau dashboard
                    add_action('admin_init', array($this, 'redirect_to_new_dashboard'));
                }
                
                /**
                 * Redirige les anciennes URL vers le nouveau tableau de bord
                 */
                public function redirect_to_new_dashboard() {
                    if (isset($_GET['page']) && $_GET['page'] === 'life-travel-abandoned-carts') {
                        wp_redirect(admin_url('admin.php?page=life-travel-cart&section=abandoned'));
                        exit;
                    }
                }
                
                /**
                 * Crée la table pour les paniers abandonnés (méthode de compatibilité)
                 */
                public static function create_table() {
                    // Cette méthode est appelée lors de l'activation du plugin
                    // Nous devons donc assurer la création de la table pour le nouveau système
                    
                    // Vérifier si la classe du nouveau système est disponible
                    if (file_exists(__DIR__ . '/class-life-travel-cart-manager.php')) {
                        require_once __DIR__ . '/class-life-travel-cart-manager.php';
                        
                        if (class_exists('Life_Travel_Cart_Manager')) {
                            Life_Travel_Cart_Manager::create_tables();
                            return;
                        }
                    }
                    
                    // Sinon, créer la table avec l'ancienne structure
                    global $wpdb;
                    
                    $table_name = $wpdb->prefix . 'life_travel_abandoned_carts';
                    $charset_collate = $wpdb->get_charset_collate();
                    
                    $sql = "CREATE TABLE $table_name (
                        id bigint(20) NOT NULL AUTO_INCREMENT,
                        user_id bigint(20) DEFAULT 0,
                        email varchar(100) NOT NULL,
                        cart_contents longtext NOT NULL,
                        cart_total decimal(10,2) NOT NULL,
                        currency varchar(10) NOT NULL,
                        created_at datetime NOT NULL,
                        last_updated datetime NOT NULL,
                        recovered tinyint(1) NOT NULL DEFAULT 0,
                        reminder_sent tinyint(1) NOT NULL DEFAULT 0,
                        offline_recovery tinyint(1) NOT NULL DEFAULT 0,
                        PRIMARY KEY  (id),
                        KEY email (email),
                        KEY recovered (recovered)
                    ) $charset_collate;";
                    
                    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
                    dbDelta($sql);
                }
            }
            
            // Initialiser pour la compatibilité
            new Life_Travel_Abandoned_Cart();
        }
        
        return true;
    } else if (file_exists(__DIR__ . '/abandoned-cart-recovery.php')) {
        // Charger l'ancien système
        require_once __DIR__ . '/abandoned-cart-recovery.php';
        return true;
    }
    
    return false;
}

/**
 * Fonction pour migrer les données de l'ancien système vers le nouveau
 * Cette fonction devrait être appelée via une action d'administration
 * 
 * @return array Résultats de la migration (succès, nombre d'éléments migrés, erreurs)
 */
function life_travel_migrate_abandoned_carts() {
    global $wpdb;
    $old_table = $wpdb->prefix . 'life_travel_abandoned_carts';
    $new_table = $wpdb->prefix . 'life_travel_carts';
    
    $results = array(
        'success' => false,
        'migrated' => 0,
        'errors' => array()
    );
    
    // Vérifier que les deux tables existent
    if ($wpdb->get_var("SHOW TABLES LIKE '$old_table'") != $old_table) {
        $results['errors'][] = 'La table source n\'existe pas.';
        return $results;
    }
    
    if ($wpdb->get_var("SHOW TABLES LIKE '$new_table'") != $new_table) {
        // Créer la nouvelle table si elle n'existe pas
        if (file_exists(__DIR__ . '/class-life-travel-cart-manager.php')) {
            require_once __DIR__ . '/class-life-travel-cart-manager.php';
            
            if (class_exists('Life_Travel_Cart_Manager')) {
                Life_Travel_Cart_Manager::create_tables();
            } else {
                $results['errors'][] = 'Impossible de créer la nouvelle table.';
                return $results;
            }
        } else {
            $results['errors'][] = 'Le fichier du nouveau système n\'existe pas.';
            return $results;
        }
    }
    
    // Récupérer tous les paniers abandonnés
    $carts = $wpdb->get_results("SELECT * FROM $old_table WHERE recovered = 0");
    
    if (empty($carts)) {
        $results['success'] = true;
        $results['migrated'] = 0;
        return $results;
    }
    
    $migrated = 0;
    
    // Migrer chaque panier
    foreach ($carts as $cart) {
        $cart_data = array(
            'user_id' => $cart->user_id,
            'email' => $cart->email,
            'cart_contents' => $cart->cart_contents,
            'cart_total' => $cart->cart_total,
            'currency' => $cart->currency,
            'created_at' => $cart->created_at,
            'updated_at' => $cart->last_updated,
            'status' => $cart->recovered ? 'recovered' : 'abandoned',
            'reminder_count' => $cart->reminder_sent,
            'is_offline' => $cart->offline_recovery,
            'metadata' => json_encode(array(
                'migrated_from' => 'old_system',
                'original_id' => $cart->id
            ))
        );
        
        $inserted = $wpdb->insert(
            $new_table,
            $cart_data,
            array(
                '%d', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%d', '%d', '%s'
            )
        );
        
        if ($inserted) {
            $migrated++;
        } else {
            $results['errors'][] = 'Erreur lors de la migration du panier ID ' . $cart->id;
        }
    }
    
    $results['success'] = true;
    $results['migrated'] = $migrated;
    
    // Mettre à jour l'option pour utiliser le nouveau système
    if ($migrated > 0) {
        update_option('life_travel_use_new_cart_system', true);
    }
    
    return $results;
}

/**
 * Fonction utilitaire pour vérifier si nous utilisons le nouveau système de paniers abandonnés
 * 
 * @return bool True si nous utilisons le nouveau système
 */
function life_travel_is_using_new_cart_system() {
    return get_option('life_travel_use_new_cart_system', false);
}

/**
 * Permet de basculer entre les deux systèmes de gestion des paniers abandonnés
 * 
 * @param bool $use_new_system True pour utiliser le nouveau système
 * @return bool Résultat de l'opération
 */
function life_travel_switch_cart_system($use_new_system = true) {
    return update_option('life_travel_use_new_cart_system', (bool) $use_new_system);
}

// Charger le système approprié
life_travel_load_abandoned_cart_system();
