<?php
/**
 * Administration du système de fidélité
 *
 * Gère les paramètres globaux du système de points
 * et les statistiques d'utilisation.
 *
 * @package Life_Travel
 * @subpackage Admin
 * @since 2.5.0
 */

// Empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe d'administration du système de fidélité
 */
class Life_Travel_Loyalty_Admin {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Ajouter la page d'options
        add_action('admin_menu', array($this, 'add_loyalty_menu'));
        
        // Enregistrer les paramètres
        add_action('admin_init', array($this, 'register_loyalty_settings'));
    }
    
    /**
     * Ajoute la page dans le menu admin
     */
    public function add_loyalty_menu() {
        add_submenu_page(
            'edit.php?post_type=product',
            __('Système de Fidélité', 'life-travel-excursion'),
            __('Fidélité', 'life-travel-excursion'),
            'manage_options',
            'lte-loyalty-settings',
            array($this, 'display_loyalty_settings_page')
        );
    }
    
    /**
     * Enregistre les paramètres du système de fidélité
     */
    public function register_loyalty_settings() {
        // Enregistrement de la section principale
        add_settings_section(
            'lte_loyalty_section',
            __('Paramètres du système de points de fidélité', 'life-travel-excursion'),
            array($this, 'loyalty_section_callback'),
            'lte-loyalty-settings'
        );
        
        // Valeur des points
        register_setting('lte-loyalty-settings', 'lte_points_value');
        add_settings_field(
            'lte_points_value',
            __('Nombre de points pour 1€', 'life-travel-excursion'),
            array($this, 'points_value_callback'),
            'lte-loyalty-settings',
            'lte_loyalty_section'
        );
        
        // Plafond global de points
        register_setting('lte-loyalty-settings', 'lte_max_loyalty_points');
        add_settings_field(
            'lte_max_loyalty_points',
            __('Plafond global de points', 'life-travel-excursion'),
            array($this, 'max_loyalty_points_callback'),
            'lte-loyalty-settings',
            'lte_loyalty_section'
        );
        
        // Pourcentage max de réduction
        register_setting('lte-loyalty-settings', 'lte_max_points_discount_percent');
        add_settings_field(
            'lte_max_points_discount_percent',
            __('Réduction maximale (%)', 'life-travel-excursion'),
            array($this, 'max_discount_percent_callback'),
            'lte-loyalty-settings',
            'lte_loyalty_section'
        );
        
        // Section des réseaux sociaux
        add_settings_section(
            'lte_loyalty_social_section',
            __('Points pour partages sociaux', 'life-travel-excursion'),
            array($this, 'loyalty_social_section_callback'),
            'lte-loyalty-settings'
        );
        
        // Points par partage social
        register_setting('lte-loyalty-settings', 'lte_points_facebook');
        add_settings_field(
            'lte_points_facebook',
            __('Points pour partage Facebook', 'life-travel-excursion'),
            array($this, 'points_facebook_callback'),
            'lte-loyalty-settings',
            'lte_loyalty_social_section'
        );
        
        register_setting('lte-loyalty-settings', 'lte_points_twitter');
        add_settings_field(
            'lte_points_twitter',
            __('Points pour partage Twitter', 'life-travel-excursion'),
            array($this, 'points_twitter_callback'),
            'lte-loyalty-settings',
            'lte_loyalty_social_section'
        );
        
        register_setting('lte-loyalty-settings', 'lte_points_whatsapp');
        add_settings_field(
            'lte_points_whatsapp',
            __('Points pour partage WhatsApp', 'life-travel-excursion'),
            array($this, 'points_whatsapp_callback'),
            'lte-loyalty-settings',
            'lte_loyalty_social_section'
        );
        
        register_setting('lte-loyalty-settings', 'lte_points_instagram');
        add_settings_field(
            'lte_points_instagram',
            __('Points pour partage Instagram', 'life-travel-excursion'),
            array($this, 'points_instagram_callback'),
            'lte-loyalty-settings',
            'lte_loyalty_social_section'
        );
        
        // Section des notifications
        add_settings_section(
            'lte_loyalty_notifications_section',
            __('Paramètres des notifications', 'life-travel-excursion'),
            array($this, 'loyalty_notifications_section_callback'),
            'lte-loyalty-settings'
        );
        
        // Activer les notifications flottantes
        register_setting('lte-loyalty-settings', 'lte_enable_floating_notifications');
        add_settings_field(
            'lte_enable_floating_notifications',
            __('Notifications flottantes', 'life-travel-excursion'),
            array($this, 'enable_floating_notifications_callback'),
            'lte-loyalty-settings',
            'lte_loyalty_notifications_section'
        );
        
        // Durée d'affichage des notifications
        register_setting('lte-loyalty-settings', 'lte_notification_display_time');
        add_settings_field(
            'lte_notification_display_time',
            __('Durée d\'affichage (secondes)', 'life-travel-excursion'),
            array($this, 'notification_display_time_callback'),
            'lte-loyalty-settings',
            'lte_loyalty_notifications_section'
        );
    }
    
    /**
     * Callback pour la section de fidélité
     */
    public function loyalty_section_callback() {
        echo '<p>' . __('Configurez les paramètres globaux du système de points de fidélité.', 'life-travel-excursion') . '</p>';
    }
    
    /**
     * Callback pour la section des réseaux sociaux
     */
    public function loyalty_social_section_callback() {
        echo '<p>' . __('Définissez le nombre de points attribués pour chaque type de partage sur les réseaux sociaux.', 'life-travel-excursion') . '</p>';
    }
    
    /**
     * Callback pour la section des notifications
     */
    public function loyalty_notifications_section_callback() {
        echo '<p>' . __('Configurez les notifications de points de fidélité pour les clients.', 'life-travel-excursion') . '</p>';
    }
    
    /**
     * Callback pour le champ valeur des points
     */
    public function points_value_callback() {
        $value = get_option('lte_points_value', 100);
        echo '<input type="number" min="1" step="1" id="lte_points_value" name="lte_points_value" value="' . esc_attr($value) . '" />';
        echo '<p class="description">' . __('Nombre de points équivalent à 1€ (par défaut: 100 points = 1€)', 'life-travel-excursion') . '</p>';
    }
    
    /**
     * Callback pour le champ plafond de points
     */
    public function max_loyalty_points_callback() {
        $value = get_option('lte_max_loyalty_points', 1000);
        echo '<input type="number" min="0" step="1" id="lte_max_loyalty_points" name="lte_max_loyalty_points" value="' . esc_attr($value) . '" />';
        echo '<p class="description">' . __('Nombre maximum de points pouvant être gagnés en une seule fois (0 = pas de limite)', 'life-travel-excursion') . '</p>';
    }
    
    /**
     * Callback pour le champ pourcentage max de réduction
     */
    public function max_discount_percent_callback() {
        $value = get_option('lte_max_points_discount_percent', 25);
        echo '<input type="number" min="1" max="100" step="1" id="lte_max_points_discount_percent" name="lte_max_points_discount_percent" value="' . esc_attr($value) . '" />';
        echo '<p class="description">' . __('Pourcentage maximum de réduction applicable avec les points (par défaut: 25%)', 'life-travel-excursion') . '</p>';
    }
    
    /**
     * Callback pour le champ points Facebook
     */
    public function points_facebook_callback() {
        $value = get_option('lte_points_facebook', 10);
        echo '<input type="number" min="0" step="1" id="lte_points_facebook" name="lte_points_facebook" value="' . esc_attr($value) . '" />';
        echo '<p class="description">' . __('Points attribués pour un partage sur Facebook', 'life-travel-excursion') . '</p>';
    }
    
    /**
     * Callback pour le champ points Twitter
     */
    public function points_twitter_callback() {
        $value = get_option('lte_points_twitter', 10);
        echo '<input type="number" min="0" step="1" id="lte_points_twitter" name="lte_points_twitter" value="' . esc_attr($value) . '" />';
        echo '<p class="description">' . __('Points attribués pour un partage sur Twitter', 'life-travel-excursion') . '</p>';
    }
    
    /**
     * Callback pour le champ points WhatsApp
     */
    public function points_whatsapp_callback() {
        $value = get_option('lte_points_whatsapp', 5);
        echo '<input type="number" min="0" step="1" id="lte_points_whatsapp" name="lte_points_whatsapp" value="' . esc_attr($value) . '" />';
        echo '<p class="description">' . __('Points attribués pour un partage sur WhatsApp', 'life-travel-excursion') . '</p>';
    }
    
    /**
     * Callback pour le champ points Instagram
     */
    public function points_instagram_callback() {
        $value = get_option('lte_points_instagram', 15);
        echo '<input type="number" min="0" step="1" id="lte_points_instagram" name="lte_points_instagram" value="' . esc_attr($value) . '" />';
        echo '<p class="description">' . __('Points attribués pour un partage sur Instagram', 'life-travel-excursion') . '</p>';
    }
    
    /**
     * Callback pour les notifications flottantes
     */
    public function enable_floating_notifications_callback() {
        $value = get_option('lte_enable_floating_notifications', 1);
        echo '<input type="checkbox" id="lte_enable_floating_notifications" name="lte_enable_floating_notifications" value="1" ' . checked(1, $value, false) . ' />';
        echo '<p class="description">' . __('Afficher une notification flottante lorsque les clients gagnent de nouveaux points', 'life-travel-excursion') . '</p>';
    }
    
    /**
     * Callback pour la durée d'affichage des notifications
     */
    public function notification_display_time_callback() {
        $value = get_option('lte_notification_display_time', 10);
        echo '<input type="number" min="1" max="60" step="1" id="lte_notification_display_time" name="lte_notification_display_time" value="' . esc_attr($value) . '" />';
        echo '<p class="description">' . __('Durée d\'affichage des notifications flottantes en secondes (par défaut: 10s)', 'life-travel-excursion') . '</p>';
    }
    
    /**
     * Affiche la page de paramètres
     */
    public function display_loyalty_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <form action="options.php" method="post">
                <?php
                settings_fields('lte-loyalty-settings');
                do_settings_sections('lte-loyalty-settings');
                submit_button(__('Enregistrer les paramètres', 'life-travel-excursion'));
                ?>
            </form>
            
            <hr>
            
            <h2><?php _e('Statistiques du système de points', 'life-travel-excursion'); ?></h2>
            <?php $this->display_loyalty_stats(); ?>
        </div>
        <?php
    }
    
    /**
     * Affiche les statistiques du système de points
     */
    private function display_loyalty_stats() {
        global $wpdb;
        
        // Statistiques basiques
        $total_users_with_points = $wpdb->get_var("
            SELECT COUNT(DISTINCT user_id) 
            FROM {$wpdb->usermeta} 
            WHERE meta_key = '_lte_loyalty_points' 
            AND meta_value > 0
        ");
        
        $total_points_awarded = $wpdb->get_var("
            SELECT SUM(meta_value) 
            FROM {$wpdb->usermeta} 
            WHERE meta_key = '_lte_loyalty_points'
        ");
        
        $top_users = $wpdb->get_results("
            SELECT user_id, meta_value as points
            FROM {$wpdb->usermeta}
            WHERE meta_key = '_lte_loyalty_points'
            AND meta_value > 0
            ORDER BY meta_value+0 DESC
            LIMIT 5
        ");
        
        ?>
        <div class="lte-loyalty-stats">
            <div class="lte-stats-grid">
                <div class="lte-stat-box">
                    <h3><?php _e('Utilisateurs avec points', 'life-travel-excursion'); ?></h3>
                    <p class="lte-stat-number"><?php echo esc_html($total_users_with_points); ?></p>
                </div>
                
                <div class="lte-stat-box">
                    <h3><?php _e('Total des points attribués', 'life-travel-excursion'); ?></h3>
                    <p class="lte-stat-number"><?php echo esc_html($total_points_awarded); ?></p>
                </div>
            </div>
            
            <?php if (!empty($top_users)) : ?>
            <div class="lte-stat-box lte-stat-box-wide">
                <h3><?php _e('Top 5 des utilisateurs', 'life-travel-excursion'); ?></h3>
                <table class="widefat fixed">
                    <thead>
                        <tr>
                            <th><?php _e('Utilisateur', 'life-travel-excursion'); ?></th>
                            <th><?php _e('Points', 'life-travel-excursion'); ?></th>
                            <th><?php _e('Actions', 'life-travel-excursion'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_users as $user_data) : 
                            $user = get_userdata($user_data->user_id);
                            if (!$user) continue;
                        ?>
                            <tr>
                                <td>
                                    <?php echo esc_html($user->display_name); ?>
                                    <br>
                                    <small><?php echo esc_html($user->user_email); ?></small>
                                </td>
                                <td><?php echo esc_html($user_data->points); ?></td>
                                <td>
                                    <a href="<?php echo esc_url(admin_url('user-edit.php?user_id=' . $user_data->user_id)); ?>" class="button button-small">
                                        <?php _e('Éditer', 'life-travel-excursion'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            
            <div class="lte-stat-box lte-stat-box-wide">
                <h3><?php _e('Distribution des points par excursion', 'life-travel-excursion'); ?></h3>
                <?php
                $points_by_excursion = $wpdb->get_results("
                    SELECT p.ID, p.post_title, 
                           pm1.meta_value as points_type,
                           pm2.meta_value as points_value
                    FROM {$wpdb->posts} p
                    LEFT JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_loyalty_points_type'
                    LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_loyalty_points_value'
                    WHERE p.post_type = 'product'
                    AND p.post_status = 'publish'
                    AND EXISTS (
                        SELECT 1 FROM {$wpdb->term_relationships} tr
                        JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                        JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
                        WHERE tr.object_id = p.ID
                        AND t.slug = 'excursion'
                    )
                    AND (pm1.meta_value IS NOT NULL OR pm2.meta_value IS NOT NULL)
                    ORDER BY p.post_title
                    LIMIT 20
                ");
                
                if (!empty($points_by_excursion)) : ?>
                    <table class="widefat fixed">
                        <thead>
                            <tr>
                                <th><?php _e('Excursion', 'life-travel-excursion'); ?></th>
                                <th><?php _e('Type', 'life-travel-excursion'); ?></th>
                                <th><?php _e('Valeur', 'life-travel-excursion'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($points_by_excursion as $excursion) : ?>
                                <tr>
                                    <td>
                                        <a href="<?php echo esc_url(admin_url('post.php?post=' . $excursion->ID . '&action=edit')); ?>">
                                            <?php echo esc_html($excursion->post_title); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <?php 
                                        if ($excursion->points_type === 'fixed') {
                                            _e('Fixe', 'life-travel-excursion');
                                        } elseif ($excursion->points_type === 'percentage') {
                                            _e('Pourcentage', 'life-travel-excursion');
                                        } else {
                                            _e('Non défini', 'life-travel-excursion');
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo esc_html($excursion->points_value); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p><?php _e('Aucune excursion n\'a encore de configuration de points spécifique.', 'life-travel-excursion'); ?></p>
                <?php endif; ?>
            </div>
        </div>
        
        <style>
            .lte-stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 20px;
                margin-bottom: 20px;
            }
            .lte-stat-box {
                background: #fff;
                border: 1px solid #ccd0d4;
                box-shadow: 0 1px 1px rgba(0,0,0,0.04);
                padding: 15px;
                margin-bottom: 20px;
            }
            .lte-stat-box-wide {
                grid-column: 1 / -1;
            }
            .lte-stat-number {
                font-size: 24px;
                font-weight: 600;
                color: #2271b1;
                margin: 10px 0;
            }
        </style>
        <?php
    }
}

// Initialiser la classe
add_action('plugins_loaded', function() {
    new Life_Travel_Loyalty_Admin();
});
