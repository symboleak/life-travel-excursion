<?php
/**
 * Renderers de gestion du réseau et des performances pour Life Travel
 *
 * Ce fichier contient les méthodes de rendu pour la gestion des optimisations réseau
 * et performances dans l'interface administrateur unifiée
 *
 * @package Life Travel Excursion
 * @version 2.3.7
 */

defined('ABSPATH') || exit;

/**
 * Méthodes de rendu pour la gestion du réseau et des performances
 */
trait Life_Travel_Admin_Renderers_Network {
    
    /**
     * Affiche l'interface de gestion des optimisations pour connexions lentes
     * 
     * @param string $page_id ID de la page
     * @param string $section_id ID de la section
     */
    public function render_network_connection($page_id, $section_id) {
        // Récupérer les paramètres actuels
        $offline_mode = get_option('life_travel_offline_mode', 'basic');
        $connection_detection = get_option('life_travel_connection_detection', 'on');
        $image_quality_slow = get_option('life_travel_image_quality_slow', 70);
        $max_asset_size = get_option('life_travel_max_asset_size', 500);
        
        // Traitement du formulaire
        if (isset($_POST['life_travel_save_network']) && check_admin_referer('life_travel_save_network')) {
            if (isset($_POST['life_travel_offline_mode'])) {
                $offline_mode = sanitize_text_field($_POST['life_travel_offline_mode']);
                update_option('life_travel_offline_mode', $offline_mode);
            }
            
            $connection_detection = isset($_POST['life_travel_connection_detection']) ? 'on' : 'off';
            update_option('life_travel_connection_detection', $connection_detection);
            
            if (isset($_POST['life_travel_image_quality_slow'])) {
                $quality = intval($_POST['life_travel_image_quality_slow']);
                $quality = max(40, min(90, $quality)); // Limiter entre 40 et 90
                update_option('life_travel_image_quality_slow', $quality);
                $image_quality_slow = $quality;
            }
            
            if (isset($_POST['life_travel_max_asset_size'])) {
                $size = intval($_POST['life_travel_max_asset_size']);
                $size = max(100, min(1000, $size)); // Limiter entre 100KB et 1000KB
                update_option('life_travel_max_asset_size', $size);
                $max_asset_size = $size;
            }
            
            echo '<div class="updated"><p>' . esc_html__('Paramètres réseau enregistrés avec succès.', 'life-travel-excursion') . '</p></div>';
        }
        
        // Vérifier la vitesse de connexion actuelle
        $connection_status = function_exists('life_travel_get_connection_status') ? life_travel_get_connection_status() : 'unknown';
        $connection_class = 'unknown';
        $connection_text = __('Inconnue', 'life-travel-excursion');
        
        if ($connection_status === 'fast') {
            $connection_class = 'good';
            $connection_text = __('Rapide', 'life-travel-excursion');
        } elseif ($connection_status === 'medium') {
            $connection_class = 'medium';
            $connection_text = __('Moyenne', 'life-travel-excursion');
        } elseif ($connection_status === 'slow') {
            $connection_class = 'warning';
            $connection_text = __('Lente', 'life-travel-excursion');
        } elseif ($connection_status === 'offline') {
            $connection_class = 'error';
            $connection_text = __('Hors ligne', 'life-travel-excursion');
        }
        
        // Afficher l'interface utilisateur
        ?>
        <div class="life-travel-admin-section">
            <div class="life-travel-admin-intro">
                <h3><?php _e('Optimisations pour connexions lentes', 'life-travel-excursion'); ?></h3>
                <p><?php _e('Configurez comment votre site s\'adapte aux connexions lentes, fréquentes au Cameroun.', 'life-travel-excursion'); ?></p>
            </div>
            
            <div class="life-travel-connection-status <?php echo esc_attr($connection_class); ?>">
                <div class="life-travel-connection-icon">
                    <span class="dashicons dashicons-admin-site"></span>
                </div>
                <div class="life-travel-connection-info">
                    <h4><?php _e('État actuel de la connexion', 'life-travel-excursion'); ?></h4>
                    <div class="life-travel-connection-value">
                        <?php echo esc_html($connection_text); ?>
                    </div>
                    <div class="life-travel-connection-test">
                        <button type="button" class="button life-travel-test-connection">
                            <?php _e('Tester à nouveau', 'life-travel-excursion'); ?>
                        </button>
                    </div>
                </div>
            </div>
            
            <form method="post" action="">
                <?php wp_nonce_field('life_travel_save_network'); ?>
                
                <div class="life-travel-admin-field-group">
                    <div class="life-travel-admin-field">
                        <label for="life_travel_offline_mode">
                            <?php _e('Mode hors-ligne', 'life-travel-excursion'); ?>
                            <span class="life-travel-tooltip" data-tooltip="<?php esc_attr_e('Détermine comment le site se comporte lors des coupures de connexion.', 'life-travel-excursion'); ?>">?</span>
                        </label>
                        
                        <div class="life-travel-select-field">
                            <select name="life_travel_offline_mode" id="life_travel_offline_mode">
                                <option value="disabled" <?php selected($offline_mode, 'disabled'); ?>><?php _e('Désactivé', 'life-travel-excursion'); ?></option>
                                <option value="basic" <?php selected($offline_mode, 'basic'); ?>><?php _e('Basique (page hors-ligne simple)', 'life-travel-excursion'); ?></option>
                                <option value="advanced" <?php selected($offline_mode, 'advanced'); ?>><?php _e('Avancé (navigation limitée hors-ligne)', 'life-travel-excursion'); ?></option>
                                <option value="full" <?php selected($offline_mode, 'full'); ?>><?php _e('Complet (expérience complète hors-ligne)', 'life-travel-excursion'); ?></option>
                            </select>
                        </div>
                        
                        <p class="description">
                            <?php _e('L\'option "Complet" nécessite plus d\'espace de stockage côté client', 'life-travel-excursion'); ?>
                        </p>
                    </div>
                    
                    <div class="life-travel-admin-field">
                        <label>
                            <?php _e('Détection automatique de connexion', 'life-travel-excursion'); ?>
                            <span class="life-travel-tooltip" data-tooltip="<?php esc_attr_e('Active la détection automatique de la qualité de connexion pour adapter le contenu.', 'life-travel-excursion'); ?>">?</span>
                        </label>
                        
                        <div class="life-travel-switch-field">
                            <label class="life-travel-switch">
                                <input type="checkbox" name="life_travel_connection_detection" <?php checked($connection_detection, 'on'); ?>>
                                <span class="life-travel-slider"></span>
                            </label>
                            <span class="life-travel-switch-label"><?php _e('Activer la détection automatique', 'life-travel-excursion'); ?></span>
                        </div>
                        
                        <p class="description">
                            <?php _e('Recommandé pour optimiser l\'expérience utilisateur', 'life-travel-excursion'); ?>
                        </p>
                    </div>
                    
                    <div class="life-travel-admin-field">
                        <label for="life_travel_image_quality_slow">
                            <?php _e('Qualité des images sur connexion lente', 'life-travel-excursion'); ?>
                            <span class="life-travel-tooltip" data-tooltip="<?php esc_attr_e('Qualité de compression des images lorsque la connexion est détectée comme lente.', 'life-travel-excursion'); ?>">?</span>
                        </label>
                        
                        <div class="life-travel-range-field">
                            <input type="range" name="life_travel_image_quality_slow" id="life_travel_image_quality_slow" min="40" max="90" value="<?php echo esc_attr($image_quality_slow); ?>">
                            <span class="life-travel-range-value"><?php echo esc_html($image_quality_slow); ?>%</span>
                        </div>
                        
                        <p class="description">
                            <?php _e('Une qualité plus basse charge plus rapidement mais avec moins de détails', 'life-travel-excursion'); ?>
                        </p>
                    </div>
                    
                    <div class="life-travel-admin-field">
                        <label for="life_travel_max_asset_size">
                            <?php _e('Taille maximale des ressources (KB)', 'life-travel-excursion'); ?>
                            <span class="life-travel-tooltip" data-tooltip="<?php esc_attr_e('Taille maximale des ressources chargées sur connexion lente (en kilobytes).', 'life-travel-excursion'); ?>">?</span>
                        </label>
                        
                        <div class="life-travel-range-field">
                            <input type="range" name="life_travel_max_asset_size" id="life_travel_max_asset_size" min="100" max="1000" step="50" value="<?php echo esc_attr($max_asset_size); ?>">
                            <span class="life-travel-range-value"><?php echo esc_html($max_asset_size); ?> KB</span>
                        </div>
                        
                        <p class="description">
                            <?php _e('Les ressources dépassant cette taille seront remplacées par des versions plus légères', 'life-travel-excursion'); ?>
                        </p>
                    </div>
                </div>
                
                <div class="life-travel-admin-submit">
                    <input type="submit" name="life_travel_save_network" class="button button-primary" value="<?php esc_attr_e('Enregistrer les modifications', 'life-travel-excursion'); ?>">
                </div>
            </form>
            
            <div class="life-travel-admin-tips">
                <h4><?php _e('Conseils pour les connexions lentes', 'life-travel-excursion'); ?></h4>
                <ul>
                    <li><?php _e('Utilisez des images plus petites et compressées pour les visuels non critiques', 'life-travel-excursion'); ?></li>
                    <li><?php _e('Activez le mode hors-ligne pour permettre la navigation en cas de coupure', 'life-travel-excursion'); ?></li>
                    <li><?php _e('Testez régulièrement votre site avec Network Throttling dans les outils de développement', 'life-travel-excursion'); ?></li>
                </ul>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Tester la connexion
            $('.life-travel-test-connection').on('click', function() {
                var $button = $(this);
                var $statusBox = $('.life-travel-connection-status');
                var $statusValue = $('.life-travel-connection-value');
                
                $button.prop('disabled', true).text('<?php _e('Test en cours...', 'life-travel-excursion'); ?>');
                
                // Simuler un test de connexion (à remplacer par un vrai test AJAX)
                setTimeout(function() {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'life_travel_test_connection',
                            nonce: '<?php echo wp_create_nonce('life_travel_test_connection'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                $statusBox.removeClass('good medium warning error unknown')
                                          .addClass(response.data.class);
                                $statusValue.text(response.data.text);
                            } else {
                                alert('<?php _e('Erreur lors du test de connexion.', 'life-travel-excursion'); ?>');
                            }
                        },
                        error: function() {
                            $statusBox.removeClass('good medium warning unknown')
                                      .addClass('error');
                            $statusValue.text('<?php _e('Hors ligne', 'life-travel-excursion'); ?>');
                        },
                        complete: function() {
                            $button.prop('disabled', false).text('<?php _e('Tester à nouveau', 'life-travel-excursion'); ?>');
                        }
                    });
                }, 1500);
            });
            
            // Mise à jour dynamique des valeurs des curseurs
            $('#life_travel_image_quality_slow').on('input', function() {
                $(this).next('.life-travel-range-value').text($(this).val() + '%');
            });
            
            $('#life_travel_max_asset_size').on('input', function() {
                $(this).next('.life-travel-range-value').text($(this).val() + ' KB');
            });
        });
        </script>
        <?php
    }
    
    /**
     * Affiche l'interface de gestion des optimisations mobiles
     * 
     * @param string $page_id ID de la page
     * @param string $section_id ID de la section
     */
    public function render_network_mobile($page_id, $section_id) {
        // Récupérer les paramètres actuels
        $mobile_optimization = get_option('life_travel_mobile_optimization', 'on');
        $mobile_preview = get_option('life_travel_mobile_preview', 'on');
        $touch_optimization = get_option('life_travel_touch_optimization', 'on');
        $mobile_fonts = get_option('life_travel_mobile_fonts', 'system');
        
        // Traitement du formulaire
        if (isset($_POST['life_travel_save_mobile']) && check_admin_referer('life_travel_save_mobile')) {
            $mobile_optimization = isset($_POST['life_travel_mobile_optimization']) ? 'on' : 'off';
            update_option('life_travel_mobile_optimization', $mobile_optimization);
            
            $mobile_preview = isset($_POST['life_travel_mobile_preview']) ? 'on' : 'off';
            update_option('life_travel_mobile_preview', $mobile_preview);
            
            $touch_optimization = isset($_POST['life_travel_touch_optimization']) ? 'on' : 'off';
            update_option('life_travel_touch_optimization', $touch_optimization);
            
            if (isset($_POST['life_travel_mobile_fonts'])) {
                $fonts = sanitize_text_field($_POST['life_travel_mobile_fonts']);
                update_option('life_travel_mobile_fonts', $fonts);
                $mobile_fonts = $fonts;
            }
            
            echo '<div class="updated"><p>' . esc_html__('Paramètres mobiles enregistrés avec succès.', 'life-travel-excursion') . '</p></div>';
        }
        
        // Afficher l'interface utilisateur
        ?>
        <div class="life-travel-admin-section">
            <div class="life-travel-admin-intro">
                <h3><?php _e('Optimisations mobiles', 'life-travel-excursion'); ?></h3>
                <p><?php _e('Configurez comment votre site s\'adapte aux appareils mobiles.', 'life-travel-excursion'); ?></p>
            </div>
            
            <div class="life-travel-device-preview">
                <div class="life-travel-device-selector">
                    <button type="button" class="button life-travel-device-button active" data-device="mobile">
                        <span class="dashicons dashicons-smartphone"></span>
                        <?php _e('Mobile', 'life-travel-excursion'); ?>
                    </button>
                    <button type="button" class="button life-travel-device-button" data-device="tablet">
                        <span class="dashicons dashicons-tablet"></span>
                        <?php _e('Tablette', 'life-travel-excursion'); ?>
                    </button>
                    <button type="button" class="button life-travel-device-button" data-device="desktop">
                        <span class="dashicons dashicons-desktop"></span>
                        <?php _e('Desktop', 'life-travel-excursion'); ?>
                    </button>
                </div>
                
                <div class="life-travel-device-frame mobile">
                    <div class="life-travel-device-screen">
                        <iframe src="<?php echo esc_url(home_url()); ?>" title="<?php esc_attr_e('Aperçu mobile', 'life-travel-excursion'); ?>"></iframe>
                    </div>
                </div>
            </div>
            
            <form method="post" action="">
                <?php wp_nonce_field('life_travel_save_mobile'); ?>
                
                <div class="life-travel-admin-field-group">
                    <div class="life-travel-admin-field">
                        <label>
                            <?php _e('Optimisations mobiles', 'life-travel-excursion'); ?>
                            <span class="life-travel-tooltip" data-tooltip="<?php esc_attr_e('Active des optimisations spécifiques pour les appareils mobiles.', 'life-travel-excursion'); ?>">?</span>
                        </label>
                        
                        <div class="life-travel-switch-field">
                            <label class="life-travel-switch">
                                <input type="checkbox" name="life_travel_mobile_optimization" <?php checked($mobile_optimization, 'on'); ?>>
                                <span class="life-travel-slider"></span>
                            </label>
                            <span class="life-travel-switch-label"><?php _e('Activer les optimisations mobiles', 'life-travel-excursion'); ?></span>
                        </div>
                        
                        <p class="description">
                            <?php _e('Inclut des adaptations de mise en page et d\'images', 'life-travel-excursion'); ?>
                        </p>
                    </div>
                    
                    <div class="life-travel-admin-field">
                        <label>
                            <?php _e('Prévisualisateur mobile', 'life-travel-excursion'); ?>
                            <span class="life-travel-tooltip" data-tooltip="<?php esc_attr_e('Active le prévisualisateur mobile dans l\'administration.', 'life-travel-excursion'); ?>">?</span>
                        </label>
                        
                        <div class="life-travel-switch-field">
                            <label class="life-travel-switch">
                                <input type="checkbox" name="life_travel_mobile_preview" <?php checked($mobile_preview, 'on'); ?>>
                                <span class="life-travel-slider"></span>
                            </label>
                            <span class="life-travel-switch-label"><?php _e('Activer le prévisualisateur', 'life-travel-excursion'); ?></span>
                        </div>
                        
                        <p class="description">
                            <?php _e('Permet de tester l\'affichage mobile directement depuis l\'admin', 'life-travel-excursion'); ?>
                        </p>
                    </div>
                    
                    <div class="life-travel-admin-field">
                        <label>
                            <?php _e('Optimisations tactiles', 'life-travel-excursion'); ?>
                            <span class="life-travel-tooltip" data-tooltip="<?php esc_attr_e('Améliore l\'expérience sur écrans tactiles avec des zones de clic plus grandes.', 'life-travel-excursion'); ?>">?</span>
                        </label>
                        
                        <div class="life-travel-switch-field">
                            <label class="life-travel-switch">
                                <input type="checkbox" name="life_travel_touch_optimization" <?php checked($touch_optimization, 'on'); ?>>
                                <span class="life-travel-slider"></span>
                            </label>
                            <span class="life-travel-switch-label"><?php _e('Activer les optimisations tactiles', 'life-travel-excursion'); ?></span>
                        </div>
                        
                        <p class="description">
                            <?php _e('Agrandit les zones cliquables pour éviter les erreurs de manipulation', 'life-travel-excursion'); ?>
                        </p>
                    </div>
                    
                    <div class="life-travel-admin-field">
                        <label for="life_travel_mobile_fonts">
                            <?php _e('Polices sur mobile', 'life-travel-excursion'); ?>
                            <span class="life-travel-tooltip" data-tooltip="<?php esc_attr_e('Détermine quelles polices utiliser sur appareils mobiles.', 'life-travel-excursion'); ?>">?</span>
                        </label>
                        
                        <div class="life-travel-select-field">
                            <select name="life_travel_mobile_fonts" id="life_travel_mobile_fonts">
                                <option value="same" <?php selected($mobile_fonts, 'same'); ?>><?php _e('Identiques au desktop', 'life-travel-excursion'); ?></option>
                                <option value="system" <?php selected($mobile_fonts, 'system'); ?>><?php _e('Polices système (recommandé)', 'life-travel-excursion'); ?></option>
                                <option value="simplified" <?php selected($mobile_fonts, 'simplified'); ?>><?php _e('Simplifiées (plus légères)', 'life-travel-excursion'); ?></option>
                            </select>
                        </div>
                        
                        <p class="description">
                            <?php _e('Les polices système sont plus rapides mais moins distinctives', 'life-travel-excursion'); ?>
                        </p>
                    </div>
                </div>
                
                <div class="life-travel-admin-submit">
                    <input type="submit" name="life_travel_save_mobile" class="button button-primary" value="<?php esc_attr_e('Enregistrer les modifications', 'life-travel-excursion'); ?>">
                </div>
            </form>
            
            <div class="life-travel-admin-tips">
                <h4><?php _e('Conseils pour les appareils mobiles', 'life-travel-excursion'); ?></h4>
                <ul>
                    <li><?php _e('Testez votre site sur différents appareils réels', 'life-travel-excursion'); ?></li>
                    <li><?php _e('Assurez-vous que les boutons et liens sont suffisamment grands (min. 44px)', 'life-travel-excursion'); ?></li>
                    <li><?php _e('Priorisez le contenu important en haut de page sur mobile', 'life-travel-excursion'); ?></li>
                </ul>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Changement d'appareil dans le prévisualisateur
            $('.life-travel-device-button').on('click', function() {
                var device = $(this).data('device');
                
                // Mettre à jour le bouton actif
                $('.life-travel-device-button').removeClass('active');
                $(this).addClass('active');
                
                // Mettre à jour la classe du cadre
                $('.life-travel-device-frame').removeClass('mobile tablet desktop')
                                             .addClass(device);
            });
        });
        </script>
        <?php
    }
}
