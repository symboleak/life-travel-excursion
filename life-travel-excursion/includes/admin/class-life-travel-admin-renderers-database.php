<?php
/**
 * Renderers pour la gestion des optimisations de base de données
 *
 * Ce trait fournit les méthodes de rendu pour la section d'optimisation
 * de base de données dans l'administration Life Travel.
 *
 * @package Life Travel Excursion
 * @version 3.0.0
 */

defined('ABSPATH') || exit;

/**
 * Trait pour le rendu des interfaces d'optimisation de base de données
 */
trait Life_Travel_Admin_Renderers_Database {
    /**
     * Page d'optimisation de base de données
     */
    public function register_database_admin_page() {
        $this->register_admin_page('database', [
            'title' => __('Optimisation de base de données', 'life-travel-excursion'),
            'menu_title' => __('Optimisation DB', 'life-travel-excursion'),
            'parent' => 'life-travel',  // Sous-menu du tableau de bord principal
            'capability' => 'manage_options',
            'sections' => [
                'indexes' => [
                    'title' => __('Optimisation des index', 'life-travel-excursion'),
                    'callback' => [$this, 'render_db_indexes']
                ],
                'caching' => [
                    'title' => __('Optimisation du cache', 'life-travel-excursion'),
                    'callback' => [$this, 'render_db_caching']
                ],
                'monitoring' => [
                    'title' => __('Surveillance des performances', 'life-travel-excursion'),
                    'callback' => [$this, 'render_db_monitoring']
                ]
            ]
        ]);
    }
    
    /**
     * Rend la section d'optimisation des index
     */
    public function render_db_indexes() {
        ?>
        <div class="life-travel-admin-section">
            <h3><?php _e('Optimisation des index de base de données', 'life-travel-excursion'); ?></h3>
            
            <p>
                <?php _e('Cette section vous permet d\'optimiser les index de base de données pour améliorer les performances des requêtes, particulièrement importantes dans le contexte camerounais avec des réseaux instables.', 'life-travel-excursion'); ?>
            </p>
            
            <div class="life-travel-card">
                <?php
                // Vérifier si l'installateur d'index est disponible
                if (class_exists('Life_Travel_Index_Installer')) {
                    // Afficher une iframe intégrée avec la page d'installateur d'index
                    $optimizer_url = admin_url('admin.php?page=life-travel-db-optimization');
                    ?>
                    <p><strong><?php _e('Pour gérer les index, accédez à l\'outil d\'optimisation complet :', 'life-travel-excursion'); ?></strong></p>
                    <a href="<?php echo esc_url($optimizer_url); ?>" class="button button-primary"><?php _e('Ouvrir l\'optimiseur d\'index', 'life-travel-excursion'); ?></a>
                    <?php
                } else {
                    // Si l'installateur n'est pas disponible
                    ?>
                    <div class="notice notice-warning">
                        <p><?php _e('L\'outil d\'optimisation des index n\'est pas disponible. Vérifiez que le fichier index-installer.php est correctement installé.', 'life-travel-excursion'); ?></p>
                    </div>
                    <?php
                }
                ?>
            </div>
            
            <div class="life-travel-card">
                <h4><?php _e('Pourquoi optimiser les index ?', 'life-travel-excursion'); ?></h4>
                <p><?php _e('Les index de base de données accélèrent considérablement les requêtes suivantes :', 'life-travel-excursion'); ?></p>
                <ul>
                    <li><?php _e('Recherche de disponibilité des excursions', 'life-travel-excursion'); ?></li>
                    <li><?php _e('Affichage des réservations par date', 'life-travel-excursion'); ?></li>
                    <li><?php _e('Calcul de capacité restante', 'life-travel-excursion'); ?></li>
                    <li><?php _e('Génération de rapports', 'life-travel-excursion'); ?></li>
                </ul>
                <p><?php _e('Ces optimisations sont particulièrement utiles dans les environnements à connectivité limitée comme au Cameroun, où la performance et la réactivité sont essentielles.', 'life-travel-excursion'); ?></p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Rend la section d'optimisation du cache
     */
    public function render_db_caching() {
        ?>
        <div class="life-travel-admin-section">
            <h3><?php _e('Optimisation du cache de requêtes', 'life-travel-excursion'); ?></h3>
            
            <p>
                <?php _e('La mise en cache des requêtes de base de données réduit considérablement la charge serveur et améliore les temps de réponse, particulièrement pour les connexions instables.', 'life-travel-excursion'); ?>
            </p>
            
            <div class="life-travel-card">
                <h4><?php _e('Configuration du cache', 'life-travel-excursion'); ?></h4>
                
                <?php
                // Obtenir les options actuelles de mise en cache
                $cache_enabled = get_option('life_travel_db_cache_enabled', 'yes');
                $cache_ttl = get_option('life_travel_db_cache_ttl', 3600);
                $adaptive_cache = get_option('life_travel_adaptive_cache', 'yes');
                ?>
                
                <form id="cache-settings-form">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Activer le cache', 'life-travel-excursion'); ?></th>
                            <td>
                                <select name="life_travel_db_cache_enabled" id="life_travel_db_cache_enabled">
                                    <option value="yes" <?php selected($cache_enabled, 'yes'); ?>><?php _e('Oui', 'life-travel-excursion'); ?></option>
                                    <option value="no" <?php selected($cache_enabled, 'no'); ?>><?php _e('Non', 'life-travel-excursion'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Durée du cache (secondes)', 'life-travel-excursion'); ?></th>
                            <td>
                                <input type="number" name="life_travel_db_cache_ttl" id="life_travel_db_cache_ttl" value="<?php echo esc_attr($cache_ttl); ?>" min="60" max="86400" />
                                <p class="description"><?php _e('Durée de conservation du cache en secondes. Recommandation : 3600 (1 heure)', 'life-travel-excursion'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Cache adaptatif', 'life-travel-excursion'); ?></th>
                            <td>
                                <select name="life_travel_adaptive_cache" id="life_travel_adaptive_cache">
                                    <option value="yes" <?php selected($adaptive_cache, 'yes'); ?>><?php _e('Oui', 'life-travel-excursion'); ?></option>
                                    <option value="no" <?php selected($adaptive_cache, 'no'); ?>><?php _e('Non', 'life-travel-excursion'); ?></option>
                                </select>
                                <p class="description"><?php _e('Ajuste automatiquement la durée du cache en fonction de la qualité de la connexion (recommandé pour le Cameroun)', 'life-travel-excursion'); ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <p>
                        <button type="button" id="save-cache-settings" class="button button-primary"><?php _e('Enregistrer les paramètres', 'life-travel-excursion'); ?></button>
                        <span id="cache-settings-spinner" class="spinner"></span>
                        <span id="cache-settings-message"></span>
                    </p>
                </form>
                
                <script type="text/javascript">
                jQuery(document).ready(function($) {
                    $('#save-cache-settings').on('click', function() {
                        var $button = $(this);
                        var $spinner = $('#cache-settings-spinner');
                        var $message = $('#cache-settings-message');
                        
                        $button.prop('disabled', true);
                        $spinner.addClass('is-active');
                        $message.html('');
                        
                        var data = {
                            action: 'life_travel_admin_save_option',
                            nonce: '<?php echo wp_create_nonce('life_travel_admin_nonce'); ?>',
                            option_name: 'life_travel_db_cache_settings',
                            option_value: {
                                enabled: $('#life_travel_db_cache_enabled').val(),
                                ttl: $('#life_travel_db_cache_ttl').val(),
                                adaptive: $('#life_travel_adaptive_cache').val()
                            }
                        };
                        
                        $.post(ajaxurl, data, function(response) {
                            $spinner.removeClass('is-active');
                            $button.prop('disabled', false);
                            
                            if (response.success) {
                                $message.html('<span style="color:green;">' + response.data.message + '</span>');
                                
                                // Purger le cache
                                $.post(ajaxurl, {
                                    action: 'life_travel_purge_query_cache',
                                    nonce: '<?php echo wp_create_nonce('life_travel_admin_nonce'); ?>'
                                });
                            } else {
                                $message.html('<span style="color:red;">' + response.data.message + '</span>');
                            }
                        }).fail(function() {
                            $spinner.removeClass('is-active');
                            $button.prop('disabled', false);
                            $message.html('<span style="color:red;"><?php _e('Erreur de communication avec le serveur.', 'life-travel-excursion'); ?></span>');
                        });
                    });
                });
                </script>
            </div>
            
            <div class="life-travel-card">
                <h4><?php _e('Actions sur le cache', 'life-travel-excursion'); ?></h4>
                
                <p>
                    <button type="button" id="purge-query-cache" class="button"><?php _e('Purger tout le cache de requêtes', 'life-travel-excursion'); ?></button>
                    <span id="purge-cache-spinner" class="spinner"></span>
                    <span id="purge-cache-message"></span>
                </p>
                
                <script type="text/javascript">
                jQuery(document).ready(function($) {
                    $('#purge-query-cache').on('click', function() {
                        var $button = $(this);
                        var $spinner = $('#purge-cache-spinner');
                        var $message = $('#purge-cache-message');
                        
                        $button.prop('disabled', true);
                        $spinner.addClass('is-active');
                        $message.html('');
                        
                        $.post(ajaxurl, {
                            action: 'life_travel_purge_query_cache',
                            nonce: '<?php echo wp_create_nonce('life_travel_admin_nonce'); ?>'
                        }, function(response) {
                            $spinner.removeClass('is-active');
                            $button.prop('disabled', false);
                            
                            if (response.success) {
                                $message.html('<span style="color:green;">' + response.data.message + '</span>');
                            } else {
                                $message.html('<span style="color:red;">' + response.data.message + '</span>');
                            }
                        }).fail(function() {
                            $spinner.removeClass('is-active');
                            $button.prop('disabled', false);
                            $message.html('<span style="color:red;"><?php _e('Erreur de communication avec le serveur.', 'life-travel-excursion'); ?></span>');
                        });
                    });
                });
                </script>
            </div>
        </div>
        <?php
    }
    
    /**
     * Rend la section de surveillance des performances
     */
    public function render_db_monitoring() {
        ?>
        <div class="life-travel-admin-section">
            <h3><?php _e('Surveillance des performances des requêtes', 'life-travel-excursion'); ?></h3>
            
            <p>
                <?php _e('Cette section vous permet de surveiller les performances des requêtes de base de données et d\'identifier les goulets d\'étranglement potentiels.', 'life-travel-excursion'); ?>
            </p>
            
            <div class="life-travel-card">
                <h4><?php _e('Requêtes lentes', 'life-travel-excursion'); ?></h4>
                
                <?php
                // Vérifier si le suivi des requêtes lentes est activé
                if (function_exists('life_travel_db_optimizer') && method_exists(life_travel_db_optimizer(), 'get_slow_queries')) {
                    $slow_queries = life_travel_db_optimizer()->get_slow_queries();
                    
                    if (!empty($slow_queries)) {
                        ?>
                        <table class="widefat" style="margin-top:10px;">
                            <thead>
                                <tr>
                                    <th><?php _e('Requête', 'life-travel-excursion'); ?></th>
                                    <th><?php _e('Temps (s)', 'life-travel-excursion'); ?></th>
                                    <th><?php _e('Occurrence', 'life-travel-excursion'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($slow_queries as $query): ?>
                                    <tr>
                                        <td><code><?php echo esc_html($query['query']); ?></code></td>
                                        <td><?php echo number_format($query['time'], 4); ?></td>
                                        <td><?php echo esc_html($query['count']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php
                    } else {
                        echo '<p>' . __('Aucune requête lente n\'a été détectée.', 'life-travel-excursion') . '</p>';
                    }
                } else {
                    ?>
                    <div class="notice notice-warning">
                        <p><?php _e('Le suivi des requêtes lentes n\'est pas disponible. Vérifiez que l\'optimiseur de base de données est correctement installé et activé.', 'life-travel-excursion'); ?></p>
                    </div>
                    <?php
                }
                ?>
                
                <p>
                    <button type="button" id="start-query-monitoring" class="button"><?php _e('Activer le suivi des requêtes (24h)', 'life-travel-excursion'); ?></button>
                    <button type="button" id="stop-query-monitoring" class="button"><?php _e('Désactiver le suivi', 'life-travel-excursion'); ?></button>
                    <span id="monitoring-spinner" class="spinner"></span>
                    <span id="monitoring-message"></span>
                </p>
                
                <script type="text/javascript">
                jQuery(document).ready(function($) {
                    $('#start-query-monitoring, #stop-query-monitoring').on('click', function() {
                        var $button = $(this);
                        var $spinner = $('#monitoring-spinner');
                        var $message = $('#monitoring-message');
                        var action = $button.attr('id') === 'start-query-monitoring' ? 'start' : 'stop';
                        
                        $button.prop('disabled', true);
                        $spinner.addClass('is-active');
                        $message.html('');
                        
                        $.post(ajaxurl, {
                            action: 'life_travel_toggle_query_monitoring',
                            nonce: '<?php echo wp_create_nonce('life_travel_admin_nonce'); ?>',
                            monitoring: action
                        }, function(response) {
                            $spinner.removeClass('is-active');
                            $button.prop('disabled', false);
                            
                            if (response.success) {
                                $message.html('<span style="color:green;">' + response.data.message + '</span>');
                            } else {
                                $message.html('<span style="color:red;">' + response.data.message + '</span>');
                            }
                        }).fail(function() {
                            $spinner.removeClass('is-active');
                            $button.prop('disabled', false);
                            $message.html('<span style="color:red;"><?php _e('Erreur de communication avec le serveur.', 'life-travel-excursion'); ?></span>');
                        });
                    });
                });
                </script>
            </div>
            
            <div class="life-travel-card">
                <h4><?php _e('Conseils pour le Cameroun', 'life-travel-excursion'); ?></h4>
                
                <div class="life-travel-checklist">
                    <ul>
                        <li><?php _e('Limitez le nombre de requêtes sur les pages principales', 'life-travel-excursion'); ?></li>
                        <li><?php _e('Utilisez les caches optimisés pour les connexions lentes', 'life-travel-excursion'); ?></li>
                        <li><?php _e('Activez les index pour accélérer les requêtes de recherche de disponibilité', 'life-travel-excursion'); ?></li>
                        <li><?php _e('Évitez les requêtes complexes avec de nombreux JOINs', 'life-travel-excursion'); ?></li>
                        <li><?php _e('Préchargez les données des excursions populaires', 'life-travel-excursion'); ?></li>
                    </ul>
                </div>
            </div>
        </div>
        <?php
    }
}
