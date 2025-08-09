<?php
/**
 * Life Travel Admin Renderers Optimizer
 * 
 * Ce fichier contient le trait qui ajoute une interface d'administration
 * pour l'optimisateur d'assets, permettant de contrôler les options
 * d'optimisation et de lancer l'optimisation manuellement.
 *
 * @package Life Travel Excursion
 * @since 2.4.0
 */

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Trait Life_Travel_Admin_Renderers_Optimizer
 */
trait Life_Travel_Admin_Renderers_Optimizer {

    /**
     * Affiche l'interface d'optimisation des assets
     * 
     * @param string $page_id ID de la page
     * @param string $section_id ID de la section
     */
    public function render_optimizer_assets($page_id, $section_id) {
        // Récupérer l'instance de l'optimisateur
        $optimizer = life_travel_assets_optimizer();
        
        // Charger les statistiques
        $last_optimization = get_option('life_travel_last_optimization', 0);
        $last_optimization_date = $last_optimization ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $last_optimization) : __('Jamais', 'life-travel-excursion');
        
        // Traiter les mises à jour de configuration
        if (isset($_POST['life_travel_optimizer_config_update']) && check_admin_referer('life_travel_optimizer_config_nonce')) {
            // Récupérer et sanitiser les options
            $config = array(
                'minify_css' => isset($_POST['minify_css']) ? true : false,
                'minify_js' => isset($_POST['minify_js']) ? true : false,
                'combine_css' => isset($_POST['combine_css']) ? true : false,
                'combine_js' => isset($_POST['combine_js']) ? true : false,
                'defer_js' => isset($_POST['defer_js']) ? true : false,
                'preload_critical' => isset($_POST['preload_critical']) ? true : false,
                'image_optimization' => array(
                    'enabled' => isset($_POST['image_optimization_enabled']) ? true : false,
                    'webp_conversion' => isset($_POST['webp_conversion']) ? true : false,
                    'quality' => isset($_POST['image_quality']) ? intval($_POST['image_quality']) : 80
                ),
                'exclude_files' => array(
                    'css' => isset($_POST['exclude_css']) ? array_map('sanitize_text_field', explode(',', $_POST['exclude_css'])) : array(),
                    'js' => isset($_POST['exclude_js']) ? array_map('sanitize_text_field', explode(',', $_POST['exclude_js'])) : array()
                )
            );
            
            // Mettre à jour la configuration
            if ($optimizer->update_config($config)) {
                echo '<div class="notice notice-success is-dismissible"><p>' . __('Configuration mise à jour avec succès.', 'life-travel-excursion') . '</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>' . __('Erreur lors de la mise à jour de la configuration.', 'life-travel-excursion') . '</p></div>';
            }
        }
        
        // Récupérer la configuration actuelle
        $config = $optimizer->get_config();
        
        // Afficher l'interface
        ?>
        <div class="life-travel-admin-intro">
            <h3><?php _e('Optimisateur d\'assets', 'life-travel-excursion'); ?></h3>
            <p><?php _e('Optimisez les fichiers CSS, JavaScript et images pour améliorer les performances.', 'life-travel-excursion'); ?></p>
        </div>
        
        <div class="life-travel-admin-section">
            <div class="life-travel-admin-info-box">
                <div class="life-travel-admin-info-icon">
                    <span class="dashicons dashicons-clock"></span>
                </div>
                <div class="life-travel-admin-info-content">
                    <h4><?php _e('Informations d\'optimisation', 'life-travel-excursion'); ?></h4>
                    <ul>
                        <li><strong><?php _e('Dernière optimisation :', 'life-travel-excursion'); ?></strong> <?php echo $last_optimization_date; ?></li>
                    </ul>
                </div>
            </div>
            
            <form method="post" action="">
                <?php wp_nonce_field('life_travel_optimizer_config_nonce'); ?>
                <input type="hidden" name="life_travel_optimizer_config_update" value="1">
                
                <h4><?php _e('Options CSS', 'life-travel-excursion'); ?></h4>
                <div class="life-travel-admin-field-group">
                    <div class="life-travel-toggle-field">
                        <label class="life-travel-toggle">
                            <input type="checkbox" name="minify_css" <?php checked($config['minify_css']); ?>>
                            <span class="life-travel-toggle-slider"></span>
                        </label>
                        <span class="life-travel-toggle-label"><?php _e('Minifier les fichiers CSS', 'life-travel-excursion'); ?></span>
                        <span class="life-travel-tooltip" data-tooltip="<?php _e('Supprime les espaces, commentaires et autres caractères inutiles pour réduire la taille des fichiers.', 'life-travel-excursion'); ?>">?</span>
                    </div>
                    
                    <div class="life-travel-toggle-field">
                        <label class="life-travel-toggle">
                            <input type="checkbox" name="combine_css" <?php checked($config['combine_css']); ?>>
                            <span class="life-travel-toggle-slider"></span>
                        </label>
                        <span class="life-travel-toggle-label"><?php _e('Combiner les fichiers CSS', 'life-travel-excursion'); ?></span>
                        <span class="life-travel-tooltip" data-tooltip="<?php _e('Combine tous les fichiers CSS en un seul pour réduire le nombre de requêtes HTTP.', 'life-travel-excursion'); ?>">?</span>
                    </div>
                    
                    <div class="life-travel-admin-field">
                        <label for="exclude_css"><?php _e('Fichiers CSS à exclure :', 'life-travel-excursion'); ?></label>
                        <input type="text" name="exclude_css" id="exclude_css" value="<?php echo esc_attr(implode(',', $config['exclude_files']['css'])); ?>" class="regular-text">
                        <p class="description"><?php _e('Liste de fichiers séparés par des virgules (ex: admin.css,custom.css).', 'life-travel-excursion'); ?></p>
                    </div>
                </div>
                
                <h4><?php _e('Options JavaScript', 'life-travel-excursion'); ?></h4>
                <div class="life-travel-admin-field-group">
                    <div class="life-travel-toggle-field">
                        <label class="life-travel-toggle">
                            <input type="checkbox" name="minify_js" <?php checked($config['minify_js']); ?>>
                            <span class="life-travel-toggle-slider"></span>
                        </label>
                        <span class="life-travel-toggle-label"><?php _e('Minifier les fichiers JavaScript', 'life-travel-excursion'); ?></span>
                        <span class="life-travel-tooltip" data-tooltip="<?php _e('Supprime les espaces, commentaires et autres caractères inutiles pour réduire la taille des fichiers.', 'life-travel-excursion'); ?>">?</span>
                    </div>
                    
                    <div class="life-travel-toggle-field">
                        <label class="life-travel-toggle">
                            <input type="checkbox" name="combine_js" <?php checked($config['combine_js']); ?>>
                            <span class="life-travel-toggle-slider"></span>
                        </label>
                        <span class="life-travel-toggle-label"><?php _e('Combiner les fichiers JavaScript', 'life-travel-excursion'); ?></span>
                        <span class="life-travel-tooltip" data-tooltip="<?php _e('Combine tous les fichiers JavaScript en un seul pour réduire le nombre de requêtes HTTP.', 'life-travel-excursion'); ?>">?</span>
                    </div>
                    
                    <div class="life-travel-toggle-field">
                        <label class="life-travel-toggle">
                            <input type="checkbox" name="defer_js" <?php checked($config['defer_js']); ?>>
                            <span class="life-travel-toggle-slider"></span>
                        </label>
                        <span class="life-travel-toggle-label"><?php _e('Différer le chargement des scripts', 'life-travel-excursion'); ?></span>
                        <span class="life-travel-tooltip" data-tooltip="<?php _e('Ajoute l\'attribut defer aux balises script pour améliorer le chargement des pages.', 'life-travel-excursion'); ?>">?</span>
                    </div>
                    
                    <div class="life-travel-admin-field">
                        <label for="exclude_js"><?php _e('Fichiers JS à exclure :', 'life-travel-excursion'); ?></label>
                        <input type="text" name="exclude_js" id="exclude_js" value="<?php echo esc_attr(implode(',', $config['exclude_files']['js'])); ?>" class="regular-text">
                        <p class="description"><?php _e('Liste de fichiers séparés par des virgules (ex: admin.js,custom.js).', 'life-travel-excursion'); ?></p>
                    </div>
                </div>
                
                <h4><?php _e('Optimisation des images', 'life-travel-excursion'); ?></h4>
                <div class="life-travel-admin-field-group">
                    <div class="life-travel-toggle-field">
                        <label class="life-travel-toggle">
                            <input type="checkbox" name="image_optimization_enabled" <?php checked($config['image_optimization']['enabled']); ?> data-controls="image-optimization-settings">
                            <span class="life-travel-toggle-slider"></span>
                        </label>
                        <span class="life-travel-toggle-label"><?php _e('Activer l\'optimisation des images', 'life-travel-excursion'); ?></span>
                        <span class="life-travel-tooltip" data-tooltip="<?php _e('Compresse les images pour réduire leur taille sans perte de qualité visible.', 'life-travel-excursion'); ?>">?</span>
                    </div>
                    
                    <div id="image-optimization-settings" class="life-travel-admin-field-group <?php echo $config['image_optimization']['enabled'] ? '' : 'disabled'; ?>">
                        <div class="life-travel-toggle-field">
                            <label class="life-travel-toggle">
                                <input type="checkbox" name="webp_conversion" <?php checked($config['image_optimization']['webp_conversion']); ?>>
                                <span class="life-travel-toggle-slider"></span>
                            </label>
                            <span class="life-travel-toggle-label"><?php _e('Conversion WebP', 'life-travel-excursion'); ?></span>
                            <span class="life-travel-tooltip" data-tooltip="<?php _e('Crée des versions WebP des images pour les navigateurs qui supportent ce format plus léger.', 'life-travel-excursion'); ?>">?</span>
                        </div>
                        
                        <div class="life-travel-admin-field">
                            <label for="image_quality"><?php _e('Qualité d\'image :', 'life-travel-excursion'); ?></label>
                            <div class="life-travel-range-field">
                                <input type="range" name="image_quality" id="image_quality" min="40" max="100" value="<?php echo esc_attr($config['image_optimization']['quality']); ?>">
                                <span class="life-travel-range-value"><?php echo esc_html($config['image_optimization']['quality']); ?>%</span>
                            </div>
                            <p class="description"><?php _e('Une valeur plus basse réduit la taille des fichiers mais peut affecter la qualité.', 'life-travel-excursion'); ?></p>
                        </div>
                    </div>
                </div>
                
                <p><button type="submit" class="button button-primary"><?php _e('Enregistrer les paramètres', 'life-travel-excursion'); ?></button></p>
            </form>
        </div>
        
        <div class="life-travel-admin-section">
            <h4><?php _e('Optimisation manuelle', 'life-travel-excursion'); ?></h4>
            <p><?php _e('Lancez l\'optimisation des assets avec les paramètres configurés ci-dessus.', 'life-travel-excursion'); ?></p>
            
            <div class="life-travel-admin-tips">
                <h4><?php _e('Conseils d\'optimisation', 'life-travel-excursion'); ?></h4>
                <ul>
                    <li><?php _e('L\'optimisation des assets peut prendre quelques instants, surtout si vous avez beaucoup d\'images.', 'life-travel-excursion'); ?></li>
                    <li><?php _e('Il est recommandé d\'exécuter l\'optimisation après chaque mise à jour majeure du site.', 'life-travel-excursion'); ?></li>
                    <li><?php _e('Testez votre site après l\'optimisation pour vous assurer que tout fonctionne correctement.', 'life-travel-excursion'); ?></li>
                    <li><?php _e('Pour le contexte camerounais, une qualité d\'image de 60-70% offre un bon équilibre entre taille et apparence.', 'life-travel-excursion'); ?></li>
                </ul>
            </div>
            
            <button id="run-optimization" class="button button-primary" data-nonce="<?php echo wp_create_nonce('life_travel_optimize_assets_nonce'); ?>"><?php _e('Lancer l\'optimisation', 'life-travel-excursion'); ?></button>
            <span id="optimization-status"></span>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Mettre à jour la valeur du slider
            $('#image_quality').on('input', function() {
                $('.life-travel-range-value').text($(this).val() + '%');
            });
            
            // Gestion de l'optimisation manuelle
            $('#run-optimization').on('click', function() {
                var $button = $(this);
                var $status = $('#optimization-status');
                
                $button.prop('disabled', true).text('<?php _e('Optimisation en cours...', 'life-travel-excursion'); ?>');
                $status.html('<span class="spinner is-active" style="float: none; margin: 0 10px;"></span> <?php _e('Optimisation en cours, veuillez patienter...', 'life-travel-excursion'); ?>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'life_travel_optimize_assets',
                        nonce: $button.data('nonce')
                    },
                    success: function(response) {
                        $button.prop('disabled', false).text('<?php _e('Lancer l\'optimisation', 'life-travel-excursion'); ?>');
                        
                        if (response.success) {
                            var results = response.data.results;
                            var html = '<div class="notice notice-success inline"><p>' + response.data.message + '</p>';
                            
                            html += '<ul>';
                            html += '<li><strong><?php _e('CSS :', 'life-travel-excursion'); ?></strong> ' + results.css.processed + ' <?php _e('fichiers traités, économie de', 'life-travel-excursion'); ?> ' + Math.round(results.css.saved_bytes / 1024) + ' KB</li>';
                            html += '<li><strong><?php _e('JavaScript :', 'life-travel-excursion'); ?></strong> ' + results.js.processed + ' <?php _e('fichiers traités, économie de', 'life-travel-excursion'); ?> ' + Math.round(results.js.saved_bytes / 1024) + ' KB</li>';
                            html += '<li><strong><?php _e('Images :', 'life-travel-excursion'); ?></strong> ' + results.images.processed + ' <?php _e('images optimisées, économie de', 'life-travel-excursion'); ?> ' + Math.round(results.images.saved_bytes / 1024) + ' KB</li>';
                            html += '</ul></div>';
                            
                            $status.html(html);
                            
                            // Recharger la page après 3 secondes pour mettre à jour les statistiques
                            setTimeout(function() {
                                window.location.reload();
                            }, 3000);
                        } else {
                            $status.html('<div class="notice notice-error inline"><p>' + response.data.message + '</p></div>');
                        }
                    },
                    error: function() {
                        $button.prop('disabled', false).text('<?php _e('Lancer l\'optimisation', 'life-travel-excursion'); ?>');
                        $status.html('<div class="notice notice-error inline"><p><?php _e('Erreur lors de l\'optimisation. Veuillez réessayer.', 'life-travel-excursion'); ?></p></div>');
                    }
                });
            });
        });
        </script>
        <?php
    }
}
