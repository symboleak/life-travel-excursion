<?php
/**
 * Pont d'unification de l'interface d'administration
 * 
 * Ce fichier assure la transition entre l'ancien système d'administration
 * et notre nouveau tableau de bord unifié Life Travel.
 * 
 * @package Life Travel Excursion
 * @version 2.4.0
 */

defined('ABSPATH') || exit;

/**
 * Charge le système d'administration approprié et gère la transition
 * 
 * @return bool True si un système a été chargé
 */
function life_travel_load_admin_system() {
    // Vérifier si le nouveau tableau de bord est disponible
    $admin_file = __DIR__ . '/admin/class-life-travel-admin.php';
    $use_new_admin = get_option('life_travel_use_new_admin', null);
    
    // Si l'option n'existe pas encore, définir à true pour les nouvelles installations
    if ($use_new_admin === null) {
        // Par défaut, activer le nouveau système
        $use_new_admin = true;
        update_option('life_travel_use_new_admin', $use_new_admin);
    }
    
    // Charger le système approprié
    if ($use_new_admin && file_exists($admin_file)) {
        require_once $admin_file;
        
        // Ajouter une option à la page outils pour basculer entre les systèmes
        add_action('admin_menu', 'life_travel_add_admin_tools_page');
        
        return true;
    }
    
    return false;
}

/**
 * Ajoute une page d'outils pour gérer la transition entre les systèmes
 */
function life_travel_add_admin_tools_page() {
    add_submenu_page(
        'life-travel-dashboard', // Parent slug
        __('Outils de Migration', 'life-travel-excursion'),
        __('Migration', 'life-travel-excursion'),
        'manage_options',
        'life-travel-migration',
        'life_travel_render_migration_tools'
    );
}

/**
 * Affiche l'interface des outils de migration
 */
function life_travel_render_migration_tools() {
    // Traiter les actions si nécessaire
    if (isset($_POST['life_travel_migration_action']) && check_admin_referer('life_travel_migration_nonce')) {
        $action = sanitize_text_field($_POST['life_travel_migration_action']);
        
        switch ($action) {
            case 'migrate_carts':
                $results = life_travel_migrate_abandoned_carts();
                $message = $results['success'] 
                    ? sprintf(__('%d paniers abandonnés migrés avec succès.', 'life-travel-excursion'), $results['migrated'])
                    : __('Erreur lors de la migration des paniers abandonnés.', 'life-travel-excursion');
                break;
                
            case 'toggle_admin':
                $use_new_admin = isset($_POST['use_new_admin']) ? (bool) $_POST['use_new_admin'] : true;
                update_option('life_travel_use_new_admin', $use_new_admin);
                $message = $use_new_admin 
                    ? __('Nouveau tableau de bord activé avec succès.', 'life-travel-excursion')
                    : __('Ancien système d\'administration restauré.', 'life-travel-excursion');
                break;
                
            case 'toggle_optimizer':
                $use_new_optimizer = isset($_POST['use_new_optimizer']) ? (bool) $_POST['use_new_optimizer'] : true;
                life_travel_switch_optimizer($use_new_optimizer);
                $message = $use_new_optimizer 
                    ? __('Nouvel optimisateur d\'assets activé avec succès.', 'life-travel-excursion')
                    : __('Ancien optimisateur de performances restauré.', 'life-travel-excursion');
                break;
                
            case 'toggle_cart_system':
                $use_new_cart_system = isset($_POST['use_new_cart_system']) ? (bool) $_POST['use_new_cart_system'] : true;
                life_travel_switch_cart_system($use_new_cart_system);
                $message = $use_new_cart_system 
                    ? __('Nouveau système de paniers abandonnés activé avec succès.', 'life-travel-excursion')
                    : __('Ancien système de paniers abandonnés restauré.', 'life-travel-excursion');
                break;
                
            case 'toggle_offline_system':
                $use_new_offline_system = isset($_POST['use_new_offline_system']) ? (bool) $_POST['use_new_offline_system'] : true;
                life_travel_switch_offline_system($use_new_offline_system);
                $message = $use_new_offline_system 
                    ? __('Nouveau système de messages hors ligne activé avec succès.', 'life-travel-excursion')
                    : __('Ancien système de page hors ligne restauré.', 'life-travel-excursion');
                break;
                
            case 'toggle_scripts':
                $use_optimized_scripts = isset($_POST['use_optimized_scripts']) ? (bool) $_POST['use_optimized_scripts'] : true;
                if (function_exists('life_travel_switch_to_optimized_scripts')) {
                    life_travel_switch_to_optimized_scripts($use_optimized_scripts);
                } else {
                    update_option('life_travel_use_optimized_scripts', $use_optimized_scripts);
                }
                $message = $use_optimized_scripts 
                    ? __('Scripts JavaScript optimisés activés avec succès.', 'life-travel-excursion')
                    : __('Scripts JavaScript originaux restaurés.', 'life-travel-excursion');
                break;
                
            case 'toggle_styles':
                $use_optimized_styles = isset($_POST['use_optimized_styles']) ? (bool) $_POST['use_optimized_styles'] : true;
                if (function_exists('life_travel_switch_to_optimized_styles')) {
                    life_travel_switch_to_optimized_styles($use_optimized_styles);
                } else {
                    update_option('life_travel_use_optimized_styles', $use_optimized_styles);
                }
                $message = $use_optimized_styles 
                    ? __('Styles CSS optimisés activés avec succès.', 'life-travel-excursion')
                    : __('Styles CSS originaux restaurés.', 'life-travel-excursion');
                break;
                
            case 'toggle_pwa':
                $use_optimized_pwa = isset($_POST['use_optimized_pwa']) ? (bool) $_POST['use_optimized_pwa'] : true;
                if (function_exists('life_travel_switch_to_optimized_pwa')) {
                    life_travel_switch_to_optimized_pwa($use_optimized_pwa);
                } else {
                    update_option('life_travel_use_optimized_pwa', $use_optimized_pwa);
                }
                $message = $use_optimized_pwa 
                    ? __('Système PWA optimisé activé avec succès.', 'life-travel-excursion')
                    : __('Système PWA original restauré.', 'life-travel-excursion');
                break;
                
            case 'toggle_images':
                $use_optimized_images = isset($_POST['use_optimized_images']) ? (bool) $_POST['use_optimized_images'] : true;
                if (function_exists('life_travel_switch_to_optimized_images')) {
                    life_travel_switch_to_optimized_images($use_optimized_images);
                } else {
                    update_option('life_travel_use_optimized_images', $use_optimized_images);
                }
                $message = $use_optimized_images 
                    ? __('Système d\'images optimisées activé avec succès.', 'life-travel-excursion')
                    : __('Système d\'images original restauré.', 'life-travel-excursion');
                break;
        }
        
        // Afficher un message de succès ou d'erreur
        if (isset($message)) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
        }
    }
    
    // Récupérer l'état actuel des systèmes
    $use_new_admin = get_option('life_travel_use_new_admin', true);
    $use_new_optimizer = get_option('life_travel_use_new_optimizer', true);
    $use_new_cart_system = get_option('life_travel_use_new_cart_system', true);
    $use_new_offline_system = get_option('life_travel_use_new_offline_system', true);
    $use_optimized_scripts = get_option('life_travel_use_optimized_scripts', true);
    $use_optimized_styles = get_option('life_travel_use_optimized_styles', true);
    $use_optimized_pwa = get_option('life_travel_use_optimized_pwa', true);
    $use_optimized_images = get_option('life_travel_use_optimized_images', true);
    
    // Vérifier si nous avons des paniers abandonnés non migrés
    global $wpdb;
    $old_table = $wpdb->prefix . 'life_travel_abandoned_carts';
    $new_table = $wpdb->prefix . 'life_travel_carts';
    
    $old_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$old_table'") == $old_table;
    $new_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$new_table'") == $new_table;
    
    $abandoned_carts_count = 0;
    if ($old_table_exists) {
        $abandoned_carts_count = $wpdb->get_var("SELECT COUNT(*) FROM $old_table WHERE recovered = 0");
    }
    
    // Afficher l'interface
    ?>
    <div class="wrap">
        <h1><?php _e('Outils de Migration Life Travel', 'life-travel-excursion'); ?></h1>
        
        <div class="notice notice-info">
            <p><?php _e('Cette page vous permet de gérer la transition entre l\'ancien système Life Travel et la nouvelle architecture centralisée.', 'life-travel-excursion'); ?></p>
        </div>
        
        <div class="card" style="max-width: 800px; margin-top: 20px; padding: 20px;">
            <h2><?php _e('État des systèmes', 'life-travel-excursion'); ?></h2>
            
            <table class="widefat" style="margin-top: 15px;">
                <thead>
                    <tr>
                        <th><?php _e('Composant', 'life-travel-excursion'); ?></th>
                        <th><?php _e('Système actif', 'life-travel-excursion'); ?></th>
                        <th><?php _e('Actions', 'life-travel-excursion'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong><?php _e('Interface d\'administration', 'life-travel-excursion'); ?></strong></td>
                        <td>
                            <?php if ($use_new_admin): ?>
                                <span style="color: green;"><?php _e('Nouveau tableau de bord unifié', 'life-travel-excursion'); ?></span>
                            <?php else: ?>
                                <span style="color: orange;"><?php _e('Ancien système d\'administration', 'life-travel-excursion'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="post" style="display: inline;">
                                <?php wp_nonce_field('life_travel_migration_nonce'); ?>
                                <input type="hidden" name="life_travel_migration_action" value="toggle_admin">
                                <input type="hidden" name="use_new_admin" value="<?php echo $use_new_admin ? '0' : '1'; ?>">
                                <button type="submit" class="button">
                                    <?php echo $use_new_admin ? __('Revenir à l\'ancien système', 'life-travel-excursion') : __('Utiliser le nouveau tableau de bord', 'life-travel-excursion'); ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                    
                    <tr>
                        <td><strong><?php _e('Optimisation des performances', 'life-travel-excursion'); ?></strong></td>
                        <td>
                            <?php if ($use_new_optimizer): ?>
                                <span style="color: green;"><?php _e('Nouvel optimisateur d\'assets', 'life-travel-excursion'); ?></span>
                            <?php else: ?>
                                <span style="color: orange;"><?php _e('Ancien optimisateur de performances', 'life-travel-excursion'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="post" style="display: inline;">
                                <?php wp_nonce_field('life_travel_migration_nonce'); ?>
                                <input type="hidden" name="life_travel_migration_action" value="toggle_optimizer">
                                <input type="hidden" name="use_new_optimizer" value="<?php echo $use_new_optimizer ? '0' : '1'; ?>">
                                <button type="submit" class="button">
                                    <?php echo $use_new_optimizer ? __('Revenir à l\'ancien optimisateur', 'life-travel-excursion') : __('Utiliser le nouvel optimisateur', 'life-travel-excursion'); ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                    
                    <tr>
                        <td><strong><?php _e('Gestion des paniers abandonnés', 'life-travel-excursion'); ?></strong></td>
                        <td>
                            <?php if ($use_new_cart_system): ?>
                                <span style="color: green;"><?php _e('Nouveau système de paniers abandonnés', 'life-travel-excursion'); ?></span>
                            <?php else: ?>
                                <span style="color: orange;"><?php _e('Ancien système de paniers abandonnés', 'life-travel-excursion'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="post" style="display: inline;">
                                <?php wp_nonce_field('life_travel_migration_nonce'); ?>
                                <input type="hidden" name="life_travel_migration_action" value="toggle_cart_system">
                                <input type="hidden" name="use_new_cart_system" value="<?php echo $use_new_cart_system ? '0' : '1'; ?>">
                                <button type="submit" class="button">
                                    <?php echo $use_new_cart_system ? __('Revenir à l\'ancien système', 'life-travel-excursion') : __('Utiliser le nouveau système', 'life-travel-excursion'); ?>
                                </button>
                            </form>
                            
                            <?php if ($abandoned_carts_count > 0 && $old_table_exists && $new_table_exists): ?>
                                <form method="post" style="display: inline; margin-left: 10px;">
                                    <?php wp_nonce_field('life_travel_migration_nonce'); ?>
                                    <input type="hidden" name="life_travel_migration_action" value="migrate_carts">
                                    <button type="submit" class="button button-primary">
                                        <?php printf(__('Migrer %d paniers', 'life-travel-excursion'), $abandoned_carts_count); ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    
                    <tr>
                        <td><strong><?php _e('Support mode hors ligne', 'life-travel-excursion'); ?></strong></td>
                        <td>
                            <?php if ($use_new_offline_system): ?>
                                <span style="color: green;"><?php _e('Nouveau système de messages personnalisables', 'life-travel-excursion'); ?></span>
                            <?php else: ?>
                                <span style="color: orange;"><?php _e('Ancienne page hors ligne', 'life-travel-excursion'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="post" style="display: inline;">
                                <?php wp_nonce_field('life_travel_migration_nonce'); ?>
                                <input type="hidden" name="life_travel_migration_action" value="toggle_offline_system">
                                <input type="hidden" name="use_new_offline_system" value="<?php echo $use_new_offline_system ? '0' : '1'; ?>">
                                <button type="submit" class="button">
                                    <?php echo $use_new_offline_system ? __('Revenir à l\'ancienne page', 'life-travel-excursion') : __('Utiliser le nouveau système', 'life-travel-excursion'); ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                    
                    <tr>
                        <td><strong><?php _e('Scripts JavaScript', 'life-travel-excursion'); ?></strong></td>
                        <td>
                            <?php if ($use_optimized_scripts): ?>
                                <span style="color: green;"><?php _e('Scripts optimisés et unifiés', 'life-travel-excursion'); ?></span>
                            <?php else: ?>
                                <span style="color: orange;"><?php _e('Scripts originaux séparés', 'life-travel-excursion'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="post" style="display: inline;">
                                <?php wp_nonce_field('life_travel_migration_nonce'); ?>
                                <input type="hidden" name="life_travel_migration_action" value="toggle_scripts">
                                <input type="hidden" name="use_optimized_scripts" value="<?php echo $use_optimized_scripts ? '0' : '1'; ?>">
                                <button type="submit" class="button">
                                    <?php echo $use_optimized_scripts ? __('Revenir aux scripts originaux', 'life-travel-excursion') : __('Utiliser les scripts optimisés', 'life-travel-excursion'); ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                    
                    <tr>
                        <td><strong><?php _e('Styles CSS', 'life-travel-excursion'); ?></strong></td>
                        <td>
                            <?php if ($use_optimized_styles): ?>
                                <span style="color: green;"><?php _e('Styles optimisés et unifiés', 'life-travel-excursion'); ?></span>
                            <?php else: ?>
                                <span style="color: orange;"><?php _e('Styles originaux séparés', 'life-travel-excursion'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="post" style="display: inline;">
                                <?php wp_nonce_field('life_travel_migration_nonce'); ?>
                                <input type="hidden" name="life_travel_migration_action" value="toggle_styles">
                                <input type="hidden" name="use_optimized_styles" value="<?php echo $use_optimized_styles ? '0' : '1'; ?>">
                                <button type="submit" class="button">
                                    <?php echo $use_optimized_styles ? __('Revenir aux styles originaux', 'life-travel-excursion') : __('Utiliser les styles optimisés', 'life-travel-excursion'); ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                    
                    <tr>
                        <td><strong><?php _e('Progressive Web App (PWA)', 'life-travel-excursion'); ?></strong></td>
                        <td>
                            <?php if ($use_optimized_pwa): ?>
                                <span style="color: green;"><?php _e('Système PWA optimisé', 'life-travel-excursion'); ?></span>
                            <?php else: ?>
                                <span style="color: orange;"><?php _e('Système PWA basique', 'life-travel-excursion'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="post" style="display: inline;">
                                <?php wp_nonce_field('life_travel_migration_nonce'); ?>
                                <input type="hidden" name="life_travel_migration_action" value="toggle_pwa">
                                <input type="hidden" name="use_optimized_pwa" value="<?php echo $use_optimized_pwa ? '0' : '1'; ?>">
                                <button type="submit" class="button">
                                    <?php echo $use_optimized_pwa ? __('Revenir au système PWA basique', 'life-travel-excursion') : __('Utiliser le système PWA optimisé', 'life-travel-excursion'); ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                    
                    <tr>
                        <td><strong><?php _e('Ressources graphiques', 'life-travel-excursion'); ?></strong></td>
                        <td>
                            <?php if ($use_optimized_images): ?>
                                <span style="color: green;"><?php _e('Images optimisées avec sprite SVG', 'life-travel-excursion'); ?></span>
                            <?php else: ?>
                                <span style="color: orange;"><?php _e('Images originales', 'life-travel-excursion'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="post" style="display: inline;">
                                <?php wp_nonce_field('life_travel_migration_nonce'); ?>
                                <input type="hidden" name="life_travel_migration_action" value="toggle_images">
                                <input type="hidden" name="use_optimized_images" value="<?php echo $use_optimized_images ? '0' : '1'; ?>">
                                <button type="submit" class="button">
                                    <?php echo $use_optimized_images ? __('Revenir aux images originales', 'life-travel-excursion') : __('Utiliser les images optimisées', 'life-travel-excursion'); ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <div class="card" style="max-width: 800px; margin-top: 20px; padding: 20px;">
            <h2><?php _e('Instructions de migration', 'life-travel-excursion'); ?></h2>
            
            <p><?php _e('Pour une transition en douceur vers la nouvelle architecture Life Travel, nous recommandons :', 'life-travel-excursion'); ?></p>
            
            <ol>
                <li><?php _e('Migrer d\'abord les paniers abandonnés pour préserver les données client', 'life-travel-excursion'); ?></li>
                <li><?php _e('Activer le nouveau tableau de bord et vous familiariser avec son interface', 'life-travel-excursion'); ?></li>
                <li><?php _e('Activer l\'optimisateur d\'assets pour améliorer les performances', 'life-travel-excursion'); ?></li>
                <li><?php _e('Activer les styles CSS optimisés pour améliorer la cohérence visuelle et les performances', 'life-travel-excursion'); ?></li>
                <li><?php _e('Activer les scripts JavaScript optimisés pour réduire le temps de chargement des pages', 'life-travel-excursion'); ?></li>
                <li><?php _e('Activer le système d\'images optimisées pour réduire le poids des pages et améliorer la compatibilité', 'life-travel-excursion'); ?></li>
                <li><?php _e('Activer le système PWA optimisé pour améliorer l\'expérience hors ligne et mobile', 'life-travel-excursion'); ?></li>
                <li><?php _e('Configurer les messages hors ligne personnalisés depuis le menu Réseau > Messages Hors Ligne', 'life-travel-excursion'); ?></li>
            </ol>
            
            <p><strong><?php _e('Note:', 'life-travel-excursion'); ?></strong> <?php _e('Vous pouvez à tout moment revenir à l\'ancien système en cas de problème.', 'life-travel-excursion'); ?></p>
        </div>
    </div>
    <?php
}

/**
 * Vérifie si nous utilisons la nouvelle interface d'administration
 * 
 * @return bool True si nous utilisons le nouveau système
 */
function life_travel_is_using_new_admin() {
    return get_option('life_travel_use_new_admin', false);
}

/**
 * Permet de basculer entre les deux systèmes d'administration
 * 
 * @param bool $use_new_admin True pour utiliser le nouveau système
 * @return bool Résultat de l'opération
 */
function life_travel_switch_admin_system($use_new_admin = true) {
    return update_option('life_travel_use_new_admin', (bool) $use_new_admin);
}

// Charger le système d'administration approprié
life_travel_load_admin_system();
